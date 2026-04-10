<?php
/**
 * dashboard.php — SeatCard 总览看板 V0.23
 * 黑金主题 · 四级状态 · 批量迁移 · 图标操作
 *
 * 状态体系：
 *   active   → 正常访问、可编辑
 *   archived → 只读查看
 *   hidden   → 无法访问，占位保留
 *   deleted  → 彻底清除（不在 auth.json 中保留）
 */
// ── 看板密码：优先读 data/sc_config.json 的 dash_pass 字段，文件不存在则用下方默认值 ──
$_sc_pw = file_exists(__DIR__.'/data/sc_config.json')
    ? (json_decode(file_get_contents(__DIR__.'/data/sc_config.json'),true)??[])
    : [];
define('DASH_PASS', $_sc_pw['dash_pass'] ?? 'superSC2026');
unset($_sc_pw);
define('DASH_VER',  'V0.23');
define('AUTH_FILE', __DIR__.'/data/auth.json');
define('CFG_FILE',  __DIR__.'/data/sc_config.json');
define('DATA_DIR',  __DIR__.'/data/');
define('SC_ALPHA_D','346789ACDEFGHJKLMNPQRSTUVWXY'); // 28字符

// ── 工具 ─────────────────────────────────────────────────────────────────────
function dbReadAuth()  { if(!file_exists(AUTH_FILE))return []; return json_decode(file_get_contents(AUTH_FILE),true)??[]; }
function dbWriteAuth($l){ file_put_contents(AUTH_FILE,json_encode($l,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),LOCK_EX); }
function dbReadCfg()   {
    $def=['yearStart'=>2026,'yearEnd'=>2030];
    if(!file_exists(CFG_FILE))return $def;
    return array_merge($def,json_decode(file_get_contents(CFG_FILE),true)??[]);
}
function dbWriteCfg($c){ file_put_contents(CFG_FILE,json_encode($c,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE),LOCK_EX); }

function dbChecksum($yymmddx){
    $r=crc32($yymmddx); $h=($r<0)?$r+4294967296:$r;
    return SC_ALPHA_D[intdiv($h,28)%28].SC_ALPHA_D[$h%28];
}

function dbNextLetter($list,$date){
    $used=[];
    foreach($list as $e){
        $code=$e['code']??''; if(strpos($code,$date)!==0)continue;
        $len=strlen($code);
        if($len===9)  $used[]=$code[6];
        elseif($len===10) $used[]=$code[6].$code[7];
    }
    for($c=ord('A');$c<=ord('Z');$c++){$l=chr($c);if(!in_array($l,$used,true))return $l;}
    for($p=ord('a');$p<=ord('z');$p++){for($c=ord('A');$c<=ord('Z');$c++){$l=chr($p).chr($c);if(!in_array($l,$used,true))return $l;}}
    return null;
}

function relativeTime($ts){
    if(!$ts)return'';
    $d=time()-$ts;
    if($d<60)return'刚刚';
    if($d<3600)return floor($d/60).'分钟前';
    if($d<86400)return floor($d/3600).'小时前';
    if($d<86400*7)return floor($d/86400).'天前';
    if($d<86400*30)return floor($d/86400/7).'周前';
    return date('n月j日',$ts);
}
function dbStats($code){
    $dir=DATA_DIR.$code.'/';
    if(!is_dir($dir))return['tables'=>0,'guests'=>0,'hasData'=>false,'name'=>'','ver'=>'','editTs'=>0,'latestFile'=>''];
    $files=array_merge(glob($dir.'wedding-seating-backup-*.json')??[],glob($dir.'wedding-seating-slot-*.json')??[]);
    if(!$files)return['tables'=>0,'guests'=>0,'hasData'=>false,'name'=>'','ver'=>'','editTs'=>0,'latestFile'=>''];
    usort($files,fn($a,$b)=>filemtime($b)-filemtime($a));
    $d=json_decode(file_get_contents($files[0]),true);
    if(!$d)return['tables'=>0,'guests'=>0,'hasData'=>false,'name'=>'','ver'=>'','editTs'=>0,'latestFile'=>''];
    $editTs=filemtime($files[0]);
    $savedAt=$d['savedAt']??'';
    if($savedAt){$t=strtotime($savedAt);if($t>0)$editTs=$t;}
    return['tables'=>count($d['tables']??[]),'guests'=>count($d['guests']??[]),
           'hasData'=>true,'name'=>($d['projectName']??''),'ver'=>($d['version']??''),
           'editTs'=>$editTs,'latestFile'=>$files[0]];
}

function dbWeeks($year,$month){
    $weeks=[];$last=mktime(0,0,0,$month+1,0,$year);
    $wStart=mktime(0,0,0,$month,1,$year)-(date('N',mktime(0,0,0,$month,1,$year))-1)*86400;
    while($wStart<=$last){
        $days=[];
        for($d=0;$d<7;$d++){$ts=$wStart+$d*86400;$days[]=['ts'=>$ts,'date'=>date('Y-m-d',$ts),'dom'=>(int)date('j',$ts),'in'=>(int)date('n',$ts)===$month];}
        $weeks[]=$days;$wStart+=604800;
    }
    return $weeks;
}

function dbRecentActivity($list){
    $act=['days'=>[],'months'=>[]];
    foreach($list as $e){
        $ts=strtotime($e['createdAt']??'');if(!$ts)continue;
        $dkey=date('Y-m-d',$ts);$mkey=date('Y-m',$ts);
        $act['days'][$dkey]=($act['days'][$dkey]??0)+1;
        $act['months'][$mkey]=($act['months'][$mkey]??0)+1;
    }
    krsort($act['days']);krsort($act['months']);
    return $act;
}

// ── Session ───────────────────────────────────────────────────────────────────
session_start();

// ── API ───────────────────────────────────────────────────────────────────────
if(($_GET['api']??'')==='1' && $_SERVER['REQUEST_METHOD']==='POST'){
    header('Content-Type: application/json; charset=UTF-8');
    if(!($_SESSION['sc_dash']??false)){echo json_encode(['error'=>'unauthorized']);exit;}
    $act=trim($_POST['action']??'');

    // 单/批量状态变更（archive / restore / hide / unhide）
    if(in_array($act,['archive','restore','hide','unhide','batchArchive','batchHide'])){
        $codes=$_POST['codes']??[$_POST['code']??''];
        if(is_string($codes))$codes=[$codes];
        $list=dbReadAuth();$ok=0;
        foreach($list as &$e){
            $c=$e['code']??'';if(!in_array($c,$codes,true))continue;
            if($act==='archive'||$act==='batchArchive') $e['status']='archived';
            elseif($act==='hide'||$act==='batchHide')   $e['status']='hidden';
            elseif($act==='restore')                    $e['status']='active';
            elseif($act==='unhide')                     $e['status']='active';
            $ok++;
        }
        unset($e);
        if($ok){dbWriteAuth($list);echo json_encode(['ok'=>true,'count'=>$ok]);}
        else echo json_encode(['error'=>'not found']);
        exit;
    }

    // 彻底删除（从 auth.json 移除 + 清除文件）
    if(in_array($act,['delete','batchDelete'])){
        $codes=$_POST['codes']??[$_POST['code']??''];
        if(is_string($codes))$codes=[$codes];
        $list=dbReadAuth();$ok=0;
        $newList=[];
        foreach($list as $e){
            $c=$e['code']??'';
            if(in_array($c,$codes,true)){
                $dir=DATA_DIR.$c.'/';
                if(is_dir($dir)){foreach(glob($dir.'*.json')??[]as$f)unlink($f);@rmdir($dir);}
                $ok++;
            } else {
                $newList[]=$e;
            }
        }
        if($ok){dbWriteAuth($newList);echo json_encode(['ok'=>true,'count'=>$ok]);}
        else echo json_encode(['error'=>'not found']);
        exit;
    }

    // 批量迁移日期
    if($act==='batchMigrate'){
        $codes=$_POST['codes']??[];if(is_string($codes))$codes=[$codes];
        $newDate=preg_replace('/[^0-9]/','',trim($_POST['newDate']??''));
        if(strlen($newDate)!==6){echo json_encode(['error'=>'日期格式错误（需6位YYMMDD）']);exit;}
        $list=dbReadAuth();
        $results=[];$errors=[];
        foreach($codes as $oldCode){
            // find entry
            $entryIdx=null;
            foreach($list as $i=>$e){if(($e['code']??'')===$oldCode){$entryIdx=$i;break;}}
            if($entryIdx===null){$errors[]="$oldCode 不存在";continue;}
            // next available letter for newDate (considering already-migrated in this batch)
            $letter=dbNextLetter($list,$newDate);
            if($letter===null){$errors[]="$oldCode 目标日期已满";continue;}
            $cc=dbChecksum($newDate.$letter);
            $newCode=$newDate.$letter.$cc;
            // rename data dir if exists
            $oldDir=DATA_DIR.$oldCode.'/';
            $newDir=DATA_DIR.$newCode.'/';
            if(is_dir($oldDir)){if(!rename($oldDir,$newDir)){$errors[]="$oldCode 目录迁移失败";continue;}}
            // update auth.json entry
            $list[$entryIdx]['code']=$newCode;
            $list[$entryIdx]['date']=$newDate;
            $results[]=[$oldCode,$newCode];
        }
        dbWriteAuth(array_values($list));
        echo json_encode(['ok'=>true,'count'=>count($results),'results'=>$results,'errors'=>$errors]);
        exit;
    }

    // 列出空数据文件夹
    if($act==='listEmpty'){
        $list=dbReadAuth();$empty=[];
        foreach($list as $e){
            $c=$e['code']??'';$dir=DATA_DIR.$c.'/';
            if(is_dir($dir)&&count(glob($dir.'*.json')??[])==0)
                $empty[]=['code'=>$c,'note'=>$e['note']??''];
        }
        echo json_encode(['ok'=>true,'list'=>$empty]);exit;
    }

    // 清理空文件夹（可指定 codes[]）
    if($act==='cleanEmpty'){
        $codes=$_POST['codes']??null;
        if(is_string($codes))$codes=[$codes];
        $list=dbReadAuth();$cleaned=[];
        foreach($list as $e){
            $c=$e['code']??'';
            if($codes!==null&&!in_array($c,$codes,true))continue;
            $dir=DATA_DIR.$c.'/';
            if(is_dir($dir)&&count(glob($dir.'*.json')??[])==0){rmdir($dir);$cleaned[]=$c;}
        }
        echo json_encode(['ok'=>true,'cleaned'=>$cleaned,'count'=>count($cleaned)]);exit;
    }

    // 复制场次（创建副本）
    if($act==='duplicate'){
        $srcCode=trim($_POST['code']??'');
        $list=dbReadAuth();
        $entry=null;foreach($list as $e){if(($e['code']??'')===$srcCode){$entry=$e;break;}}
        if(!$entry){echo json_encode(['error'=>'未找到场次']);exit;}
        $datePart=substr($srcCode,0,6);
        $dk='20'.substr($datePart,0,2).'-'.substr($datePart,2,2).'-'.substr($datePart,4,2);
        $newLetter=dbNextLetter($list,$dk);
        if(!$newLetter){echo json_encode(['error'=>'该日期已无可用场次位']);exit;}
        $newBase=$datePart.$newLetter;
        $newCode=$newBase.dbChecksum($newBase);
        // copy latest JSON to new dir
        $srcFile=DATA_DIR.$srcCode.'/';
        $files=array_merge(glob($srcFile.'wedding-seating-backup-*.json')??[],glob($srcFile.'wedding-seating-slot-*.json')??[]);
        if($files){
            usort($files,fn($a,$b)=>filemtime($b)-filemtime($a));
            $newDir=DATA_DIR.$newCode.'/';if(!is_dir($newDir))mkdir($newDir,0755,true);
            $basename=basename($files[0]);
            copy($files[0],$newDir.$basename);
        }
        // register in auth.json
        $list[]=['code'=>$newCode,'status'=>'active','note'=>($entry['note']??'').'（副本）','createdAt'=>date('c')];
        dbWriteAuth($list);
        echo json_encode(['ok'=>true,'newCode'=>$newCode]);exit;
    }

    // 配置保存
    if($act==='saveCfg'){
        $cfg=dbReadCfg();
        $cfg['yearStart']=max(2026,min(2099,intval($_POST['yearStart']??2026)));
        $cfg['yearEnd']  =max($cfg['yearStart'],min(2099,intval($_POST['yearEnd']??2030)));
        dbWriteCfg($cfg);echo json_encode(['ok'=>true]);exit;
    }

    // 重命名场次备注
    if($act==='renameNote'){
        $code=trim($_POST['code']??'');
        $note=mb_substr(trim($_POST['note']??''),0,20);
        $list=dbReadAuth();$ok=false;
        foreach($list as &$e){if(($e['code']??'')===$code){$e['note']=$note;$ok=true;break;}}
        unset($e);
        if($ok){dbWriteAuth($list);echo json_encode(['ok'=>true,'note'=>$note]);}
        else echo json_encode(['error'=>'not found']);
        exit;
    }

    // 新建场次
    if($act==='create'){
        $dateRaw=preg_replace('/[^0-9]/','',trim($_POST['date']??''));
        if(strlen($dateRaw)!==6){echo json_encode(['error'=>'日期格式错误（需6位YYMMDD）']);exit;}
        $note=mb_substr(trim($_POST['note']??''),0,20);
        $list=dbReadAuth();
        $letter=dbNextLetter($list,$dateRaw);
        if($letter===null){echo json_encode(['error'=>'该日期已无可用场次位（A-Z 已满）']);exit;}
        $base=$dateRaw.$letter;
        $newCode=$base.dbChecksum($base);
        $list[]=['code'=>$newCode,'status'=>'active','note'=>$note,'createdAt'=>date('c')];
        dbWriteAuth($list);
        echo json_encode(['ok'=>true,'code'=>$newCode]);exit;
    }

    echo json_encode(['error'=>'unknown action']);exit;
}

// ── 登录/退出 ────────────────────────────────────────────────────────────────
if($_SERVER['REQUEST_METHOD']==='POST'){
    if(($_POST['action']??'')==='logout'){$_SESSION['sc_dash']=false;session_destroy();header('Location: dashboard.php');exit;}
    if(!($_SESSION['sc_dash']??false)&&($_POST['pass']??'')===DASH_PASS)$_SESSION['sc_dash']=true;
}

if(!($_SESSION['sc_dash']??false)):
?><!DOCTYPE html>
<html lang="zh-CN"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>SeatCard 看板</title>
<link href="https://fonts.loli.net/css2?family=Noto+Sans+SC:wght@400;500;600&display=swap" rel="stylesheet">
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Noto Sans SC',sans-serif;background:#0F0F0C;display:flex;align-items:center;justify-content:center;min-height:100vh}
.lc{background:#1A1A16;border:1px solid #3A3020;border-radius:14px;padding:40px 36px;width:320px;box-shadow:0 8px 32px rgba(0,0,0,.5);text-align:center}
h2{font-size:1.05rem;color:#C8A84A;margin-bottom:4px;font-weight:700;letter-spacing:.05em}
.sub{font-size:.78rem;color:#4A4838;margin-bottom:22px}
input[type=password]{width:100%;padding:9px 12px;background:#111110;border:1.5px solid #2A2A22;border-radius:7px;font-size:.92rem;outline:none;font-family:inherit;color:#C0BCA8;transition:border .15s}
input[type=password]:focus{border-color:#C8A84A}
button{margin-top:12px;width:100%;padding:10px;background:#C8A84A;color:#0F0F0C;border:none;border-radius:7px;font-size:.88rem;cursor:pointer;font-family:inherit;font-weight:700;transition:background .15s}
button:hover{background:#A88A38}
</style></head>
<body>
<div class="lc">
  <h2>◈ SEATCARD 看板</h2>
  <p class="sub">请输入超级密码</p>
  <form method="POST">
    <input type="password" name="pass" placeholder="超级密码" autofocus>
    <button type="submit">进入</button>
  </form>
</div>
</body></html>
<?php exit; endif;

// ── 数据准备 ──────────────────────────────────────────────────────────────────
$cfg      = dbReadCfg();
$authList = dbReadAuth();
$sessMap  = []; // 'Y-m-d' => [sessions]

// 仅显示非 deleted 场次（deleted 已从 auth.json 删除，不存在）
foreach($authList as $e){
    $code=$e['code']??''; if(strlen($code)<9)continue;
    $st=$e['status']??'active';
    $dk='20'.substr($code,0,2).'-'.substr($code,2,2).'-'.substr($code,4,2);
    $st2=dbStats($code);
    $sessMap[$dk][]=['code'=>$code,'status'=>$st,
        'note'=>$e['note']?:$st2['name'],'tables'=>$st2['tables'],
        'guests'=>$st2['guests'],'hasData'=>$st2['hasData'],'ver'=>$st2['ver'],
        'editTs'=>$st2['editTs'],'latestFile'=>$st2['latestFile']];
}

// 年度统计
$yearStats=[];
for($y=$cfg['yearStart'];$y<=$cfg['yearEnd'];$y++){
    $yy=sprintf('%02d',$y-2000);
    $ys=array_filter($authList,fn($e)=>strpos($e['code']??'',$yy)===0);
    $yearStats[$y]=['total'=>count($ys),
        'active' =>count(array_filter($ys,fn($e)=>($e['status']??'active')==='active')),
        'arch'   =>count(array_filter($ys,fn($e)=>($e['status']??'')==='archived')),
        'hidden' =>count(array_filter($ys,fn($e)=>($e['status']??'')==='hidden'))];
}

$activity    = dbRecentActivity($authList);
$recentDays  = array_slice($activity['days'],0,7,true);
$recentMonths= array_slice($activity['months'],0,3,true);

$scheme=isset($_SERVER['HTTPS'])&&$_SERVER['HTTPS']==='on'?'https':'http';
$selfDir=rtrim(str_replace('\\','/',dirname($_SERVER['PHP_SELF'])),'/');
$base=$scheme.'://'.$_SERVER['HTTP_HOST'].$selfDir.'/index.php';
$monthN=['','一月','二月','三月','四月','五月','六月','七月','八月','九月','十月','十一月','十二月'];
$dowCN=['一','二','三','四','五','六','日'];

$totalAll=count($authList);
$totalAct=count(array_filter($authList,fn($e)=>($e['status']??'active')==='active'));
$contribRaw=[];
for($i=90;$i>=0;$i--){$dk=date('Y-m-d',strtotime("-{$i} days"));$contribRaw[$dk]=$activity['days'][$dk]??0;}
?>
<!DOCTYPE html>
<html lang="zh-CN"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>SeatCard 看板 <?=DASH_VER?></title>
<link href="https://fonts.loli.net/css2?family=Noto+Sans+SC:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
:root{
  --bg:#0F0F0C;--bg2:#181814;--bg3:#222218;
  --gold:#D4AA3C;--gold2:#3A2E10;
  --text:#E4E0D4;--dim:#A8A498;
  --border:#303028;--red:#DD5555;
}
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Noto Sans SC',sans-serif;background:var(--bg);color:var(--text);min-height:100vh;display:flex;flex-direction:column}

/* ── Header ── */
header{background:#0A0A08;border-bottom:1px solid #2A2418;height:46px;display:flex;align-items:center;gap:12px;padding:0 16px;flex-shrink:0;position:sticky;top:0;z-index:200}
.hdr-title{font-size:.85rem;font-weight:700;letter-spacing:.12em;color:var(--gold);flex-shrink:0;text-shadow:0 0 18px rgba(212,170,60,.4)}
.hdr-ver{font-size:.6rem;color:#5A5030;align-self:flex-end;padding-bottom:3px;margin-left:-6px}
.hdr-stat{font-size:.7rem;color:#9A9888;white-space:nowrap}
.hdr-stat b{color:#CEC8A8}
.hdr-r{margin-left:auto;display:flex;align-items:center;gap:6px}
.hdr-btn{background:#181610;border:1px solid #3A3220;color:#A09070;font-size:.75rem;cursor:pointer;font-family:inherit;padding:4px 10px;border-radius:4px;text-decoration:none;transition:all .15s;display:inline-flex;align-items:center;gap:5px;height:26px;line-height:1}
.hdr-btn:hover{color:#E8C860;border-color:#C8A84A;background:#221E0C}
.hdr-btn.act{background:var(--gold2);color:var(--gold);border-color:#4A3C18}
.hdr-btn.sel-on{background:var(--gold);color:#0F0F0C;border-color:var(--gold)}

/* ── Layout ── */
.app{display:flex;flex:1;overflow:hidden;height:calc(100vh - 46px)}
.main{flex:1;overflow-y:auto;padding:12px 14px}
/* sidebar width/border now defined below in sidebar section */

/* ── Filter bar ── */
.fbar{display:flex;align-items:center;gap:6px;padding:6px 9px;background:var(--bg2);border:1px solid var(--border);border-radius:8px;margin-bottom:10px;flex-wrap:wrap}
.fbar select,.fbar button,.fbar label{font-family:inherit;font-size:.73rem;cursor:pointer}
.fbar select{padding:3px 6px;border:1px solid var(--border);border-radius:4px;background:var(--bg3);color:#888070;outline:none;color-scheme:dark}
.fbtn{padding:3px 10px;border:1px solid var(--border);border-radius:4px;background:var(--bg3);color:#787060;transition:all .15s;display:inline-flex;align-items:center;gap:4px}
.fbtn:hover{border-color:#6A6458;color:var(--text)}
.fbtn.sel-on{background:var(--gold);color:#0F0F0C;border-color:var(--gold);font-weight:700}
.fbtn.danger:hover{border-color:var(--red);color:var(--red)}
.fbar-info{font-size:.7rem;color:#4A4840;flex:1}
.fbar-r{display:flex;align-items:center;gap:5px;margin-left:auto}

/* ── Batch bar ── */
.batch-bar{display:none;position:sticky;bottom:0;background:#0A0A08;color:#A8A498;padding:7px 14px;font-size:.76rem;z-index:100;align-items:center;gap:8px;border-top:2px solid #2A2418}
.batch-bar.show{display:flex}
.bbtn{padding:3px 10px;border:1px solid #3A3828;border-radius:4px;background:transparent;color:#8A8878;cursor:pointer;font-family:inherit;font-size:.73rem;transition:all .15s;display:inline-flex;align-items:center;gap:4px}
.bbtn:hover{border-color:#6A6858;color:var(--text)}
.bbtn-arch{border-color:#4A2C04;color:#D09020}
.bbtn-arch:hover{background:#4A2C04;color:#E8A830;border-color:#6A3E08}
.bbtn-hide{border-color:#2E2018;color:#887050}
.bbtn-hide:hover{background:#2E2018;color:#B09070;border-color:#3A2820}
.bbtn-del{border-color:#401010;color:#C83030}
.bbtn-del:hover{background:#401010;color:#E04040;border-color:#601818}
.bbtn.gold{border-color:var(--gold2);color:var(--gold)}
.bbtn.gold:hover{background:var(--gold2)}
/* ── Card keyboard focus — 白黄光标 ── */
.sess-card.card-focus{outline:2px solid rgba(255,246,180,.88);outline-offset:2px;box-shadow:0 0 8px rgba(255,240,160,.18)}
/* ── Clean modal list ── */
.clean-list{max-height:220px;overflow-y:auto;border:1px solid #2A2418;border-radius:5px;background:#111110;margin:8px 0}
.clean-list-hdr{padding:5px 10px;border-bottom:1px solid #2A2418;font-size:.7rem;color:#6A6858;display:flex;justify-content:space-between;align-items:center}
.clean-list-item{padding:4px 10px;font-size:.73rem;color:#A8A090;border-bottom:1px solid #1A1810;display:flex;align-items:center;gap:7px}
.clean-list-item:last-child{border-bottom:none}
.clean-list-item label{cursor:pointer;display:flex;align-items:center;gap:6px;font-family:monospace}

/* ── Year section ── */
.year-sec{margin-bottom:12px;border:1px solid var(--border);border-radius:10px;overflow:visible;position:relative}
.year-hdr{display:flex;align-items:center;gap:10px;padding:8px 13px;background:#1A1A16;cursor:pointer;user-select:none;transition:background .12s;border-radius:10px}
.year-hdr.open{border-radius:10px 10px 0 0}
.year-hdr:hover{background:var(--bg3)}
.year-hdr .ytitle{font-size:.9rem;font-weight:600;color:var(--text)}
.year-hdr .ystats{font-size:.7rem;color:#7A7868;margin-left:4px}
.year-hdr .ystats b{color:#A09878}
.year-hdr .ytoggle{margin-left:auto;color:#6A6858;font-size:.75rem;transition:transform .2s}
.year-hdr.open .ytoggle{transform:rotate(90deg)}
.year-body{display:none;background:var(--bg2);padding:9px 11px;border-radius:0 0 10px 10px}
.year-body.open{display:block}

/* ── Month group ── */
.month-grp{margin-bottom:9px}
.month-hdr{font-size:.76rem;font-weight:600;color:var(--gold);padding:3px 8px;margin-bottom:5px;display:flex;align-items:center;gap:6px;border-left:3px solid var(--gold)}
.month-hdr span{font-size:.65rem;color:#807868;font-weight:400}

/* ── Day row ── */
.day-row{display:flex;gap:0;margin-bottom:6px;align-items:flex-start}
.day-lbl{width:50px;flex-shrink:0;padding-top:5px;text-align:right;padding-right:9px}
.day-lbl .dm{font-size:.78rem;font-weight:600;color:#C0B898}
.day-lbl .dw{font-size:.62rem;color:#807868;display:block;margin-top:1px}
.cards-grid{display:flex;flex-wrap:wrap;gap:5px;flex:1}

/* ── Session Card ── */
.sess-card{
  background-color:#1E1C12;
  background-image:repeating-linear-gradient(-45deg,transparent 0px,transparent 5px,rgba(255,220,80,.11) 5px,rgba(255,220,80,.11) 7px);
  border:1px solid #2C2818;
  border-radius:8px;padding:7px 32px 5px 20px;cursor:pointer;
  transition:box-shadow .12s,border-color .12s;position:relative;width:165px;flex-shrink:0;overflow:visible}
.sess-card:hover{box-shadow:0 3px 18px rgba(212,170,60,.25)}
/* 未使用(无数据) — 灰暗条纹 */
.sess-card.active.card-empty{
  background-color:#161614;
  background-image:repeating-linear-gradient(-45deg,transparent 0,transparent 5px,rgba(140,135,120,.07) 5px,rgba(140,135,120,.07) 7px)}
/* 有效·超5人 — 更亮条纹 */
.sess-card.active.card-busy{
  background-image:repeating-linear-gradient(-45deg,transparent 0,transparent 5px,rgba(255,220,80,.19) 5px,rgba(255,220,80,.19) 7px)}
/* 归档 — 略红底色 */
.sess-card.archived{background-color:#1C0E0E;background-image:repeating-linear-gradient(-45deg,transparent 0,transparent 5px,rgba(180,60,60,.09) 5px,rgba(180,60,60,.09) 7px);opacity:.88}
/* 隐藏 — 略绿底色 */
.sess-card.hidden{background-color:#0D130E;background-image:repeating-linear-gradient(-45deg,transparent 0,transparent 5px,rgba(60,130,70,.09) 5px,rgba(60,130,70,.09) 7px);opacity:.7}
/* card-sel 合并到上方 stripe 块；sel-mode 全卡灰框 + 选中橙黄 */
.sel-mode .sess-card{box-shadow:0 0 0 1px rgba(100,98,88,.32);}
.sel-mode .sess-card.card-sel{box-shadow:0 0 0 2px rgba(218,162,28,.92),0 0 10px rgba(218,162,28,.12);border-color:#9A7020;background-color:#1C1A0A;animation:none}
/* ── Left status strip (replaces sbadge) ── */
.card-slabel{position:absolute;left:0;top:0;bottom:0;width:14px;border-radius:8px 0 0 8px;
  display:flex;align-items:center;justify-content:center}
.card-slabel-txt{writing-mode:vertical-lr;text-orientation:upright;
  font-size:7px;font-weight:800;letter-spacing:.08em;color:rgba(255,255,255,.75);
  font-family:monospace;pointer-events:none;text-transform:uppercase}
.sess-card.active   .card-slabel{background:linear-gradient(180deg,#7A5C10 0%,#4A3808 100%)}
/* 归档侧边条 — 红色调 */
.sess-card.archived .card-slabel{background:linear-gradient(180deg,#6A1A1A 0%,#3C0E0E 100%)}
/* 隐藏侧边条 — 绿色调 */
.sess-card.hidden   .card-slabel{background:linear-gradient(180deg,#1A4A22 0%,#0E2C12 100%)}
/* ── Card name editable ── */
.card-name{font-size:.8rem;color:#D4C090;font-weight:500;
  white-space:nowrap;overflow:hidden;text-overflow:ellipsis;margin-bottom:2px;cursor:text;min-height:1.1em}
.card-name:hover{color:#ECD8A8}
.card-no-name{font-style:italic;color:#3A3428;font-size:.7rem}
.card-name-input{background:transparent;border:none;border-bottom:1px solid var(--gold);
  color:#D4C090;font-size:.8rem;font-weight:500;font-family:inherit;outline:none;width:100%;padding:0}

/* ── Card content ── */
.card-top{display:flex;align-items:center;gap:3px;margin-bottom:3px}
.cb{display:none;width:12px;height:12px;cursor:pointer;flex-shrink:0;accent-color:#5A8ACA}
/* 多选模式不显示勾选框，改用描边表示选中状态 */
.code-m{font-family:monospace;font-size:1.1rem;font-weight:700;color:#E4E0CC;letter-spacing:.5px;flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;cursor:pointer;transition:color .1s}
.code-f{font-family:monospace;font-size:1.1rem;font-weight:700;color:#E4E0CC;letter-spacing:.5px;flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;cursor:pointer;transition:color .1s}
.code-m:hover,.code-f:hover{color:#F8F4E8}
.code-f .ck{color:#E8903A;font-weight:900}
.dots{color:#605848}

/* .card-name defined above in card state section */
.card-meta{font-size:.68rem;color:#A8A090;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;margin-bottom:1px;display:flex;align-items:center;gap:5px}
.card-meta .mok{color:#C0A870;font-weight:500}
.card-meta .rel-t{margin-left:auto;color:#906850;font-style:italic}
.card-unused{font-size:.65rem;color:#7A6848;font-style:italic}

/* ── Enter button (small square, hover-only) ── */
.card-enter{position:absolute;right:4px;top:50%;transform:translateY(-50%);
  width:22px;height:22px;border-radius:6px;
  display:flex;align-items:center;justify-content:center;
  text-decoration:none;font-size:13px;font-weight:800;
  opacity:0;pointer-events:none;
  transition:opacity .15s,background .12s,box-shadow .12s}
.sess-card:hover .card-enter,.sess-card.card-on .card-enter{opacity:1;pointer-events:auto}
.card-enter.act{background:#2C1E06;color:#C89020;border:1px solid #4A3408}
.card-enter.act:hover{background:#3C2A0A;color:#E8B030;box-shadow:0 0 10px rgba(200,140,20,.5)}
.card-enter.arch{background:#1E1808;color:#7A5820;border:1px solid #302808}
.card-enter.arch:hover{background:#2A2210;color:#9A7030}

/* ── Card actions: float above siblings below the card ── */
.card-acts{
  position:absolute;left:0;right:0;top:calc(100% + 3px);z-index:20;
  display:flex;align-items:center;gap:2px;
  background:#201C0E;border:1px solid #3A3020;border-radius:0 0 8px 8px;
  padding:5px 3px 5px 16px;
  opacity:0;pointer-events:none;
  transform:translateY(-5px);
  transition:opacity .15s,transform .18s ease}
.sess-card.card-on .card-acts{opacity:1;pointer-events:auto;transform:translateY(0)}
.sess-card.card-on{z-index:10}

/* ── Animated flowing stripes (card-on click + card-sel multi) ── */
/* 横向移动: 20px≈2×7√2, 视觉上近无缝水平滚动 */
@keyframes stripe-scroll{
  0%  {background-position:0 0}
  100%{background-position:20px 0}
}
/* card-on: 单选激活 — 斜条横向流动 */
.sess-card.card-on{
  background-image:repeating-linear-gradient(-45deg,transparent 0,transparent 5px,rgba(212,160,30,.22) 5px,rgba(212,160,30,.22) 7px);
  animation:stripe-scroll 3s linear infinite;
  box-shadow:0 0 0 2px rgba(212,170,60,.55);border-color:#5A4A20}
/* card-sel: 多选已选中 — 橙黄描边，无条纹动画，与单选区分 */
.sess-card.card-sel{
  background-color:#1C1A0A;
  border-color:#9A7020;
  box-shadow:0 0 0 2px rgba(218,162,28,.92),0 0 10px rgba(218,162,28,.12);
  animation:none
}
.ca-l{display:flex;gap:2px;align-items:center}
.ca-r{display:flex;gap:2px;align-items:center}
.ca-sep{width:1px;height:14px;background:#322E28;margin:0 3px;flex-shrink:0}

/* ── Compact action buttons (letter + SVG icon) ── */
.cab{height:17px;border-radius:4px;border:none;padding:0 2px;
  display:inline-flex;align-items:center;justify-content:center;
  cursor:pointer;transition:filter .13s,transform .09s;flex-shrink:0;
  text-decoration:none;line-height:1;user-select:none;overflow:hidden}
.cab:hover{filter:brightness(1.55);transform:scale(1.12)}
.cab:active{transform:scale(.88)}
.cab[disabled]{opacity:.3;pointer-events:none}
.cab svg{display:block;pointer-events:none}
.cab-a{background:#6A3E08;color:#E8A030}   /* Archive  – amber       */
.cab-h{background:#3A2820;color:#A88060}   /* Hide     – gray-red    */
.cab-v{background:#403808;color:#C8A020}   /* Valid    – mid-yellow  */
.cab-d{background:#501010;color:#E04040}   /* Delete   – deep red    */
.cab-e{background:#4A2C08;color:#D07828}   /* Edit lnk – orange      */
.cab-g{background:#3C1C28;color:#C06870}   /* Guest lnk– purple-red  */

/* ── Sidebar (left side) ── */
.sidebar{width:220px;flex-shrink:0;border-right:1px solid #2A2418;background:#141410;overflow-y:auto;transition:width .2s}
.sidebar.collapsed{width:28px;overflow:hidden}
.sb-toggle-strip{display:flex;align-items:center;justify-content:center;height:100%;cursor:pointer;writing-mode:vertical-rl;transform:rotate(180deg);font-size:.62rem;color:#3A3828;letter-spacing:.1em;gap:4px;padding:12px 0;user-select:none}
.sb-toggle-strip:hover{color:#6A6858}
.sidebar.collapsed .sb-content{display:none}
.sidebar:not(.collapsed) .sb-toggle-strip{display:none}
.sb-content{padding:10px 9px}
.sb-title{font-size:.76rem;font-weight:600;color:#8A8470;margin-bottom:10px;display:flex;align-items:center;justify-content:space-between}
.sb-close{cursor:pointer;color:#4A4838;font-size:.68rem;background:none;border:none;font-family:inherit}
.sb-close:hover{color:#8A8468}
.sb-sec{margin-bottom:11px}
.sb-sec-hdr{font-size:.6rem;font-weight:700;color:#3A3828;text-transform:uppercase;letter-spacing:.1em;margin-bottom:6px}
.sb-row{display:flex;justify-content:space-between;align-items:center;font-size:.7rem;color:#5A5848;padding:2px 0}
.sb-row b{color:#8A8470}
.sb-empty{font-size:.7rem;color:#3A3828;font-style:italic}
.sb-legend span{display:flex;align-items:center;gap:5px;font-size:.68rem;color:#5A5848;padding:2px 0}
/* ── Contribution graph ── */
.contrib-wrap{display:flex;gap:2px}
.contrib-col{display:flex;flex-direction:column;gap:2px}
.contrib-cell{width:9px;height:9px;border-radius:2px;flex-shrink:0}
.contrib-0{background:#1A1A16}
.contrib-1{background:#1A3A22}
.contrib-2{background:#2A6030}
.contrib-3{background:#48A858}
.contrib-f{background:#111110}
.contrib-today{outline:1px solid var(--gold);outline-offset:1px}
.contrib-legend{display:flex;align-items:center;gap:3px;margin-top:5px;font-size:.62rem;color:#3A3828}
.contrib-legend .contrib-cell{flex-shrink:0}
/* ── Sidebar shortcuts ── */
.sb-shortcuts{display:flex;flex-direction:column;gap:1px}
.sc-grp{font-size:.6rem;color:#3A3428;margin-top:5px;margin-bottom:2px;font-style:italic}
.sc-row{display:flex;align-items:center;gap:5px;font-size:.68rem;color:#5A5848;padding:1px 0}
.sc-row span{flex:1;color:#4A4840}
kbd.sk{display:inline-block;background:#1E1C14;border:1px solid #3A3828;border-radius:3px;padding:0 4px;font-size:.6rem;font-family:monospace;color:#7A7060;min-width:18px;text-align:center;line-height:16px}

/* ── Year Range Modal ── */
.yr-modal-box{background:#1A1A16;border:1px solid #3A3020;border-radius:12px;padding:24px 26px;width:340px;font-size:.83rem;color:var(--text)}
.yr-note{background:#1E1C14;border:1px solid #3A3020;border-radius:6px;padding:10px 12px;font-size:.72rem;color:#7A7458;line-height:1.7;margin-bottom:16px}
.yr-note b{color:var(--gold)}
.yr-row{display:flex;align-items:center;gap:8px;margin-bottom:12px}
.yr-label{font-size:.72rem;color:#6A6858;width:60px;flex-shrink:0}
.yr-input{width:72px;padding:5px 8px;border:1.5px solid #2A2418;border-radius:5px;background:#111110;color:#C8C4B0;font-size:.88rem;font-family:monospace;outline:none;text-align:center;color-scheme:dark}
.yr-input:focus{border-color:var(--gold)}
.yr-sep{color:#3A3828}

/* ── Migrate Modal ── */
.modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.80);z-index:500;align-items:center;justify-content:center}
.modal-overlay.show{display:flex}
.modal-box{background:#1A1A16;border:1px solid #3A3020;border-radius:12px;padding:24px 26px;width:290px;font-size:.83rem;color:var(--text)}
.modal-title{font-size:.9rem;font-weight:700;color:var(--gold);margin-bottom:8px}
.modal-desc{color:#5A5848;font-size:.75rem;margin-bottom:13px;line-height:1.6}
.modal-label{font-size:.72rem;color:#6A6858;display:block;margin-bottom:4px}
.modal-input{width:100%;padding:7px 10px;background:#111110;border:1.5px solid #2A2418;border-radius:6px;color:#C8C4B0;font-size:.88rem;outline:none;font-family:monospace;letter-spacing:.1em;transition:border .15s}
.modal-input:focus{border-color:var(--gold)}
.modal-btns{margin-top:14px;display:flex;gap:8px;justify-content:flex-end}
.mbtn-cancel{padding:5px 14px;background:transparent;border:1px solid var(--border);border-radius:5px;color:#6A6858;cursor:pointer;font-family:inherit;font-size:.78rem;transition:all .15s}
.mbtn-cancel:hover{border-color:#6A6858;color:var(--text)}
.mbtn-ok{padding:5px 16px;background:var(--gold);border:none;border-radius:5px;color:#0F0F0C;font-weight:700;cursor:pointer;font-family:inherit;font-size:.78rem;transition:background .15s}
.mbtn-ok:hover{background:#A88A38}
.modal-result{margin-top:10px;font-size:.7rem;color:#6A6858;display:none;line-height:1.6}
</style>
</head>
<body>

<header>
  <span class="hdr-title">SEATCARD</span>
  <span class="hdr-ver"><?=DASH_VER?></span>
  <span class="hdr-stat">共 <b><?=$totalAll?></b> 场 &nbsp;·&nbsp; 有效 <b><?=$totalAct?></b></span>
  <div class="hdr-r">
    <a href="admin.php" class="hdr-btn" title="管理后台">
      <svg width="13" height="13" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><circle cx="8" cy="8" r="2.3"/><path d="M8 1v1.8M8 13.2V15M1 8h1.8M13.2 8H15M3.05 3.05l1.27 1.27M11.68 11.68l1.27 1.27M12.95 3.05l-1.27 1.27M4.32 11.68l-1.27 1.27"/></svg>
      管理
    </a>
    <a href="index.php" class="hdr-btn" title="进入 SeatCard 主页">
      <svg width="13" height="13" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M2 8h12M10 4l4 4-4 4"/></svg>
      进入
    </a>
    <button class="hdr-btn" onclick="openYearModal()" title="设置允许使用的年份范围">
      <svg width="13" height="13" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="2" width="14" height="13" rx="1.5"/><line x1="1" y1="6" x2="15" y2="6"/><line x1="5" y1="1" x2="5" y2="3.5"/><line x1="11" y1="1" x2="11" y2="3.5"/></svg>
      年份
    </button>
    <button class="hdr-btn" id="sbToggleBtn" onclick="toggleSidebar()" title="近期动态面板">
      <svg width="13" height="13" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="1,11 4,6 7,9 10,4 15,8"/></svg>
      动态
    </button>
    <form method="POST" style="display:inline">
      <input type="hidden" name="action" value="logout">
      <button type="submit" class="hdr-btn" title="锁定并退出">
        <svg width="12" height="12" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="7" width="12" height="8" rx="1.5"/><path d="M5 7V5a3 3 0 0 1 6 0v2"/></svg>
        退出
      </button>
    </form>
  </div>
</header>

<div class="app">

<!-- Sidebar (left, default open) -->
<div class="sidebar" id="sidebar">
  <div class="sb-toggle-strip" onclick="toggleSidebar()">动态</div>
  <div class="sb-content">
    <div class="sb-title">
      <svg width="12" height="12" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" style="flex-shrink:0"><polyline points="1,11 4,6 7,9 10,4 15,8"/></svg>
      近期动态
      <button class="sb-close" onclick="toggleSidebar()" title="收起">✕</button>
    </div>

    <!-- GitHub-style contribution heatmap -->
    <div class="sb-sec">
      <div class="sb-sec-hdr">13 周场次热图</div>
      <div id="contribGraph"></div>
      <div class="contrib-legend">
        <span>少</span>
        <div class="contrib-cell contrib-0"></div>
        <div class="contrib-cell contrib-1"></div>
        <div class="contrib-cell contrib-2"></div>
        <div class="contrib-cell contrib-3"></div>
        <span>多</span>
      </div>
    </div>

    <!-- Monthly stats -->
    <div class="sb-sec">
      <div class="sb-sec-hdr">近 3 月</div>
      <?php if(!$recentMonths): ?><div class="sb-empty">暂无</div>
      <?php else: foreach($recentMonths as $mk=>$cnt): ?>
        <div class="sb-row"><span><?=$mk?></span><b><?=$cnt?> 场</b></div>
      <?php endforeach;endif;?>
    </div>

    <!-- Status legend -->
    <div class="sb-sec">
      <div class="sb-sec-hdr">状态色标</div>
      <div class="sb-legend">
        <span><b style="color:#D4A820;font-size:.9rem">▌</b> VALID — 正常可编辑</span>
        <span><b style="color:#8A2020;font-size:.9rem">▌</b> ARCH — 归档只读</span>
        <span><b style="color:#2A6830;font-size:.9rem">▌</b> HIDE — 隐藏占位</span>
      </div>
    </div>

    <!-- Keyboard shortcuts -->
    <div class="sb-sec">
      <div class="sb-sec-hdr">快捷键</div>
      <div class="sb-shortcuts">
        <div class="sc-grp">激活卡片后</div>
        <div class="sc-row"><kbd class="sk">A</kbd><span>归档</span></div>
        <div class="sc-row"><kbd class="sk">H</kbd><span>隐藏</span></div>
        <div class="sc-row"><kbd class="sk">V</kbd><span>恢复</span></div>
        <div class="sc-row"><kbd class="sk">D</kbd><span>删除</span></div>
        <div class="sc-row"><kbd class="sk">E</kbd><span>编辑链接</span></div>
        <div class="sc-row"><kbd class="sk">G</kbd><span>宾客链接</span></div>
        <div class="sc-row"><kbd class="sk">R</kbd><span>重命名</span></div>
        <div class="sc-row"><kbd class="sk">↵</kbd><span>进入</span></div>
        <div class="sc-grp">全局</div>
        <div class="sc-row"><kbd class="sk">N</kbd><span>新增场次</span></div>
        <div class="sc-row"><kbd class="sk">M</kbd><span>多选模式</span></div>
        <div class="sc-row"><kbd class="sk">1–6</kbd><span>筛选</span></div>
        <div class="sc-row"><kbd class="sk">← →</kbd><span>逐卡导航</span></div>
        <div class="sc-row"><kbd class="sk">↑ ↓</kbd><span>跨日跳转</span></div>
        <div class="sc-row"><kbd class="sk">PgUp/Dn</kbd><span>跨月跳转</span></div>
        <div class="sc-row"><kbd class="sk">␣</kbd><span>选中/激活</span></div>
        <div class="sc-grp">多选模式</div>
        <div class="sc-row"><kbd class="sk">A</kbd><span>批量归档</span></div>
        <div class="sc-row"><kbd class="sk">H</kbd><span>批量隐藏</span></div>
        <div class="sc-row"><kbd class="sk">D</kbd><span>批量删除</span></div>
        <div class="sc-row"><kbd class="sk">R</kbd><span>批量重命名</span></div>
        <div class="sc-row"><kbd class="sk">T</kbd><span>迁移日期</span></div>
        <div class="sc-row"><kbd class="sk">C</kbd><span>批量复制</span></div>
        <div class="sc-row"><kbd class="sk">Esc</kbd><span>取消</span></div>
      </div>
    </div>

  </div>
</div>

<div class="main">

<!-- Filter bar -->
<div class="fbar">
  <button class="fbtn" id="newBtn" onclick="showNew()" title="新增场次 [N]">
    <svg width="12" height="12" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><line x1="8" y1="2" x2="8" y2="14"/><line x1="2" y1="8" x2="14" y2="8"/></svg>
    新增 <kbd style="font-size:.6rem;opacity:.55;margin-left:1px">N</kbd>
  </button>
  <button class="fbtn" id="selModeBtn" onclick="toggleSelectMode()" title="多选模式 [M]">
    <svg width="12" height="12" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="1" width="6" height="6" rx="1"/><rect x="1" y="9" width="6" height="6" rx="1"/><rect x="9" y="1" width="6" height="6" rx="1"/><rect x="9" y="9" width="6" height="6" rx="1"/></svg>
    多选 <kbd style="font-size:.6rem;opacity:.55;margin-left:1px">M</kbd>
  </button>
  <select id="filterSel" onchange="applyFilter()" title="筛选场次 [1-6]">
    <option value="all">全部场次</option>
    <option value="active">仅有效</option>
    <option value="unused">有效·未使用</option>
    <option value="few">人数 &lt; 5</option>
    <option value="archived">已归档</option>
    <option value="hidden">已隐藏</option>
  </select>
  <span class="fbar-info" id="fbarInfo"></span>
  <div class="fbar-r">
    <button class="fbtn" id="eyeAllBtn" onclick="toggleAllCodes()" title="隐藏/显示 校验位">
      <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
      校验位
    </button>
    <button class="fbtn danger" id="cleanBtn" onclick="showCleanModal()" title="列出并删除空数据文件夹">
      <svg width="12" height="12" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><line x1="2" y1="4" x2="14" y2="4"/><path d="M5 4V3a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v1"/><path d="M3 4l.7 10a1 1 0 0 0 1 .95h6.6a1 1 0 0 0 1-.95L13 4"/><line x1="6.5" y1="7" x2="6.5" y2="12"/><line x1="9.5" y1="7" x2="9.5" y2="12"/></svg>
      删除空场次
    </button>
  </div>
</div>

<!-- Year sections -->
<?php
$curYear=date('Y');
for($y=$cfg['yearStart'];$y<=$cfg['yearEnd'];$y++){
    $yst=$yearStats[$y];
    $isOpen=($y==$curYear);
    echo "<div class=\"year-sec\" id=\"ysec-{$y}\" data-year=\"{$y}\">";
    echo "<div class=\"year-hdr".($isOpen?' open':'')."\" onclick=\"toggleYear({$y})\">";
    echo "<span class=\"ytitle\">{$y}</span>";
    if($yst['total']>0){
        echo "<span class=\"ystats\"><b>{$yst['total']}</b> 场";
        if($yst['active'])  echo " &nbsp;有效<b>{$yst['active']}</b>";
        if($yst['arch'])    echo " &nbsp;归档<b>{$yst['arch']}</b>";
        if($yst['hidden'])  echo " &nbsp;隐藏<b>{$yst['hidden']}</b>";
        echo "</span>";
    } else {
        echo "<span class=\"ystats\" style=\"color:#2A2820\">无场次</span>";
    }
    echo "<span class=\"ytoggle\">›</span></div>";
    echo "<div class=\"year-body".($isOpen?' open':'')."\" id=\"ybody-{$y}\">";

    $yHasAny=false;
    for($m=1;$m<=12;$m++){
        $mHas=false;
        for($d=1;$d<=31;$d++){
            $dk=sprintf('%d-%02d-%02d',$y,$m,$d);
            if(!empty($sessMap[$dk])){$mHas=true;$yHasAny=true;break;}
        }
        if(!$mHas) continue;

        $mTotal=0;
        for($d=1;$d<=31;$d++) $mTotal+=count($sessMap[sprintf('%d-%02d-%02d',$y,$m,$d)]??[]);
        echo "<div class=\"month-grp\">";
        echo "<div class=\"month-hdr\">".htmlspecialchars($monthN[$m])."<span>{$mTotal} 场</span></div>";

        foreach(dbWeeks($y,$m) as $week){
            $wHas=false;
            foreach($week as $day){if($day['in']&&!empty($sessMap[$day['date']])){$wHas=true;break;}}
            if(!$wHas) continue;


            // ── Letter + icon SVG buttons (26×14 viewBox, currentColor) ──
            static $SV_ARCH ='<svg width="26" height="14" viewBox="0 0 26 14" fill="none" xmlns="http://www.w3.org/2000/svg"><text x="1" y="11" font-family="monospace" font-size="11" font-weight="800" fill="currentColor">A</text><rect x="13" y="3.5" width="11" height="9" rx="1" stroke="currentColor" stroke-width="1.3"/><line x1="13" y1="7" x2="24" y2="7" stroke="currentColor" stroke-width="1.3"/><line x1="16" y1="10.5" x2="21" y2="10.5" stroke="currentColor" stroke-width="1.2" stroke-linecap="round"/></svg>';
            static $SV_HIDE ='<svg width="26" height="14" viewBox="0 0 26 14" fill="none" xmlns="http://www.w3.org/2000/svg"><text x="1" y="11" font-family="monospace" font-size="11" font-weight="800" fill="currentColor">H</text><path d="M13 7c0 0 2.5-3 6-3s6 3 6 3-2.5 3-6 3-6-3-6-3z" stroke="currentColor" stroke-width="1.2"/><circle cx="19" cy="7" r="1.5" fill="currentColor"/><line x1="13.5" y1="3.5" x2="24.5" y2="10.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>';
            static $SV_VALID='<svg width="26" height="14" viewBox="0 0 26 14" fill="none" xmlns="http://www.w3.org/2000/svg"><text x="1" y="11" font-family="monospace" font-size="11" font-weight="800" fill="currentColor">V</text><polyline points="13,9 17,13 25,3" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>';
            static $SV_DEL  ='<svg width="26" height="14" viewBox="0 0 26 14" fill="none" xmlns="http://www.w3.org/2000/svg"><text x="1" y="11" font-family="monospace" font-size="11" font-weight="800" fill="currentColor">D</text><line x1="13.5" y1="4" x2="24.5" y2="4" stroke="currentColor" stroke-width="1.3" stroke-linecap="round"/><path d="M15.5,4 l0,1 h6 l0,-1" stroke="currentColor" stroke-width="1.2" stroke-linecap="round"/><path d="M14.5,5.5 l.7,8 h9.6 l.7,-8z" stroke="currentColor" stroke-width="1.2" stroke-linejoin="round"/><line x1="18" y1="7.5" x2="18" y2="12" stroke="currentColor" stroke-width="1.1" stroke-linecap="round"/><line x1="21" y1="7.5" x2="21" y2="12" stroke="currentColor" stroke-width="1.1" stroke-linecap="round"/></svg>';
            static $SV_EDIT ='<svg width="26" height="14" viewBox="0 0 26 14" fill="none" xmlns="http://www.w3.org/2000/svg"><text x="1" y="11" font-family="monospace" font-size="11" font-weight="800" fill="currentColor">E</text><path d="M20.5 10a2.4 2.4 0 0 0 3.4 0l1.6-1.6a2.4 2.4 0 0 0-3.4-3.4l-.8.8" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/><path d="M24 5a2.4 2.4 0 0 0-3.4 0l-1.6 1.6a2.4 2.4 0 0 0 3.4 3.4l.8-.8" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg>';
            static $SV_GUEST='<svg width="26" height="14" viewBox="0 0 26 14" fill="none" xmlns="http://www.w3.org/2000/svg"><text x="1" y="11" font-family="monospace" font-size="11" font-weight="800" fill="currentColor">G</text><circle cx="19.5" cy="5.5" r="2.5" stroke="currentColor" stroke-width="1.3"/><path d="M14 14a5.5 5 0 0 1 11 0" stroke="currentColor" stroke-width="1.3" stroke-linecap="round"/></svg>';

            foreach($week as $di=>$day){
                if(!$day['in']||empty($sessMap[$day['date']])) continue;
                $dow=$dowCN[$di];
                echo "<div class=\"day-row\">";
                echo "<div class=\"day-lbl\"><span class=\"dm\">{$m}/{$day['dom']}</span><span class=\"dw\">周{$dow}</span></div>";
                echo "<div class=\"cards-grid\">";
                foreach($sessMap[$day['date']] as $s){
                    $code=$s['code'];$st=$s['status'];
                    $prefix=substr($code,0,strlen($code)-2);
                    $viewPfx=strlen($code)===10?substr($code,0,8):substr($code,0,7);
                    $editUrl=$base.'?Auth='.urlencode($code);
                    $viewUrl=$base.'?Auth='.urlencode($viewPfx);
                    $dim=!$s['hasData']||$s['tables']<1||$s['guests']<5;
                    $sLabel=['active'=>'VALID','archived'=>'ARCH','hidden'=>'HIDE'][$st]??$st;
                    $codeEsc=htmlspecialchars($code,ENT_QUOTES);
                    $editEsc=htmlspecialchars(addslashes($editUrl));
                    $viewEsc=htmlspecialchars(addslashes($viewUrl));

                    $codeOpacity=($st!=='active')?' style="opacity:.38"':'';
                    $relTime=relativeTime($s['editTs']);
                    $cardExtra='';
                    if($st==='active'){if(!$s['hasData'])$cardExtra=' card-empty';elseif($s['guests']>=5)$cardExtra=' card-busy';}
                    echo "<div class=\"sess-card {$st}{$cardExtra}\" data-code=\"{$codeEsc}\" data-status=\"".htmlspecialchars($st,ENT_QUOTES)."\" data-guests=\"{$s['guests']}\" data-hasdata=\"".($s['hasData']?'1':'0')."\" onclick=\"cardClick(this,event)\">";
                    // Left status strip (replaces sbadge, always visible)
                    echo "<div class=\"card-slabel\"><span class=\"card-slabel-txt\">{$sLabel}</span></div>";
                    // Top: code + checkbox (no sbadge here anymore)
                    echo "<div class=\"card-top\">";
                    echo "<input type=\"checkbox\" class=\"cb\" onclick=\"cardSel(this,event)\">";
                    $ck=substr($code,-2);
                    $codeJs=htmlspecialchars(addslashes($code));
                    echo "<span class=\"code-m\" onclick=\"cpCode('{$codeJs}',this,event)\" title=\"点击复制编号\"{$codeOpacity}>".htmlspecialchars($prefix)."<span class=\"dots\">••</span></span>";
                    echo "<span class=\"code-f\" style=\"display:none\" onclick=\"cpCode('{$codeJs}',this,event)\" title=\"点击复制编号\"{$codeOpacity}>".htmlspecialchars($prefix)."<span class=\"ck\">{$ck}</span></span>";
                    echo "</div>";
                    // Name row (always rendered, double-click to edit)
                    $nameDisplay=$s['note']?htmlspecialchars(mb_substr($s['note'],0,15)):'<span class="card-no-name">双击添加名称</span>';
                    echo "<div class=\"card-name\" data-code=\"{$codeEsc}\" ondblclick=\"startEditName(this,event)\" title=\"双击编辑 / 选中后按 N\">{$nameDisplay}</div>";
                    // Meta row: guests · tables | relative time
                    if($s['hasData']){
                        echo "<div class=\"card-meta\"><span class=\"mok\">🪑{$s['tables']}·{$s['guests']}人</span>";
                        if($relTime) echo "<span class=\"rel-t\">{$relTime}</span>";
                        echo "</div>";
                    } else {
                        echo "<div class=\"card-meta\"><span class=\"card-unused\">未使用</span></div>";
                    }
                    // Actions: LEFT=status  RIGHT=utility  |  enter strip always on right edge
                    echo "<div class=\"card-acts\">";
                    echo "<div class=\"ca-l\">";
                    if($st==='active'){
                        echo "<button class=\"cab cab-a\" onclick=\"doStatus('{$codeEsc}','archive',this)\" title=\"归档 [A]\">{$SV_ARCH}</button>";
                        echo "<button class=\"cab cab-h\" onclick=\"doStatus('{$codeEsc}','hide',this)\" title=\"隐藏 [H]\">{$SV_HIDE}</button>";
                    }elseif($st==='archived'||$st==='hidden'){
                        echo "<button class=\"cab cab-v\" onclick=\"doStatus('{$codeEsc}',".($st==='hidden'?"'unhide'":"'restore'").",this)\" title=\"恢复 [V]\">{$SV_VALID}</button>";
                        echo "<button class=\"cab cab-d\" onclick=\"doStatus('{$codeEsc}','delete',this)\" title=\"删除 [D]\">{$SV_DEL}</button>";
                    }
                    echo "</div>";
                    echo "<span class=\"ca-sep\"></span>";
                    echo "<div class=\"ca-r\">";
                    if($st!=='hidden'){
                        echo "<button class=\"cab cab-e\" onclick=\"cpTxt('{$editEsc}',this,'E')\" title=\"复制编辑链接 [E]\">{$SV_EDIT}</button>";
                        echo "<button class=\"cab cab-g\" onclick=\"cpTxt('{$viewEsc}',this,'G')\" title=\"复制宾客链接 [G]\">{$SV_GUEST}</button>";
                    }
                    echo "</div>";
                    echo "</div>"; // card-acts
                    // Enter strip: absolute right edge, always visible
                    if($st!=='hidden'){
                        $sCls=$st==='active'?'act':'arch';
                        echo "<a href=\"{$editUrl}\" target=\"_blank\" class=\"card-enter {$sCls}\" title=\"进入 [{$codeEsc}]\">→</a>";
                    }
                    echo "</div>"; // sess-card
                }
                echo "</div></div>"; // cards-grid, day-row
            }
        }
        echo "</div>"; // month-grp
    }
    if(!$yHasAny) echo "<div style=\"font-size:.75rem;color:#2A2820;padding:9px 4px\">本年度暂无场次记录</div>";
    echo "</div></div>\n"; // year-body, year-sec
}
?>

<!-- Year Range Modal -->
<div class="modal-overlay" id="yearModal">
  <div class="yr-modal-box" onclick="event.stopPropagation()">
    <div class="modal-title">⚙ 年份范围设置</div>
    <div class="yr-note">
      <b>作用：</b>限制系统允许的婚宴年份区间。<br>
      · 超出范围的年份，Admin 后台将<b>拒绝生成</b>新场次授权码。<br>
      · 看板仅显示范围内的年份分组（空年份折叠显示）。<br>
      · 当前设置实时写入 <b>data/sc_config.json</b>，所有组件共享。
    </div>
    <div class="yr-row">
      <span class="yr-label">起始年份</span>
      <input class="yr-input" type="number" id="cfgYS" value="<?=$cfg['yearStart']?>" min="2020" max="2099">
      <span class="yr-sep">—</span>
      <input class="yr-input" type="number" id="cfgYE" value="<?=$cfg['yearEnd']?>" min="2020" max="2099">
      <span class="yr-label" style="width:auto;color:#5A5848">截止年份</span>
    </div>
    <div class="modal-btns">
      <button class="mbtn-cancel" onclick="closeYearModal()">取消</button>
      <button class="mbtn-ok" onclick="saveCfg()">保存并刷新</button>
    </div>
    <div class="modal-result" id="yrResult"></div>
  </div>
</div>

<!-- New Session Modal -->
<div class="modal-overlay" id="newModal">
  <div class="modal-box" style="width:310px" onclick="event.stopPropagation()">
    <div class="modal-title">
      <svg width="14" height="14" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" style="vertical-align:middle;margin-right:5px"><line x1="8" y1="2" x2="8" y2="14"/><line x1="2" y1="8" x2="14" y2="8"/></svg>
      新增场次
    </div>
    <div class="modal-desc">创建新的婚宴场次授权码，自动分配场次字母</div>
    <label class="modal-label">日期（YYMMDD，如 260501）</label>
    <input type="text" id="newDate" class="modal-input" maxlength="6" placeholder="260430" inputmode="numeric">
    <label class="modal-label" style="margin-top:10px;display:block">备注名称（选填）</label>
    <input type="text" id="newNote" class="modal-input" maxlength="20" placeholder="婚宴名称…">
    <div id="newResult" class="modal-result"></div>
    <div class="modal-btns">
      <button class="mbtn-cancel" onclick="closeNew()">取消</button>
      <button class="mbtn-ok" id="newOkBtn" onclick="doCreate()">创建</button>
    </div>
  </div>
</div>

</div><!-- /main -->
</div><!-- /app -->

<!-- Batch bar -->
<div class="batch-bar" id="batchBar">
  <span id="batchInfo">已选 0 场</span>
  <button class="bbtn bbtn-arch" onclick="batchAction('batchArchive')" title="归档选中 [A]">
    <svg width="13" height="13" viewBox="0 0 14 14" fill="none"><rect x="1" y="4" width="12" height="9" rx="1" stroke="currentColor" stroke-width="1.3"/><line x1="1" y1="7" x2="13" y2="7" stroke="currentColor" stroke-width="1.3"/><line x1="4" y1="10.5" x2="10" y2="10.5" stroke="currentColor" stroke-width="1.2" stroke-linecap="round"/><line x1="4.5" y1="1.5" x2="4.5" y2="4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><line x1="9.5" y1="1.5" x2="9.5" y2="4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
    归档 <kbd style="font-size:.6rem;opacity:.55;margin-left:1px">A</kbd>
  </button>
  <button class="bbtn bbtn-hide" onclick="batchAction('batchHide')" title="隐藏选中 [H]">
    <svg width="13" height="13" viewBox="0 0 14 14" fill="none"><path d="M1 7c0 0 2.5-3.5 6-3.5s6 3.5 6 3.5-2.5 3.5-6 3.5-6-3.5-6-3.5z" stroke="currentColor" stroke-width="1.2"/><circle cx="7" cy="7" r="1.5" fill="currentColor"/><line x1="1.5" y1="2.5" x2="12.5" y2="11.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
    隐藏 <kbd style="font-size:.6rem;opacity:.55;margin-left:1px">H</kbd>
  </button>
  <button class="bbtn bbtn-del" onclick="batchAction('batchDelete')" title="删除选中 [D]">
    <svg width="13" height="13" viewBox="0 0 14 14" fill="none"><line x1="2" y1="3.5" x2="12" y2="3.5" stroke="currentColor" stroke-width="1.3" stroke-linecap="round"/><path d="M4.5,3.5 l0,1 h5 l0,-1" stroke="currentColor" stroke-width="1.2" stroke-linecap="round"/><path d="M3,5 l.7,8 h6.6 l.7,-8z" stroke="currentColor" stroke-width="1.2" stroke-linejoin="round"/><line x1="5.5" y1="7" x2="5.5" y2="11" stroke="currentColor" stroke-width="1.1" stroke-linecap="round"/><line x1="8.5" y1="7" x2="8.5" y2="11" stroke="currentColor" stroke-width="1.1" stroke-linecap="round"/></svg>
    删除 <kbd style="font-size:.6rem;opacity:.55;margin-left:1px">D</kbd>
  </button>
  <button class="bbtn gold" onclick="showMigrate()" title="迁移日期 [T]">
    <svg width="13" height="13" viewBox="0 0 14 14" fill="none"><rect x="1" y="2" width="12" height="11" rx="1.5" stroke="currentColor" stroke-width="1.3"/><line x1="1" y1="6" x2="13" y2="6" stroke="currentColor" stroke-width="1.3"/><line x1="4.5" y1="1" x2="4.5" y2="3.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><line x1="9.5" y1="1" x2="9.5" y2="3.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
    迁移日期 <kbd style="font-size:.6rem;opacity:.55;margin-left:1px">T</kbd>
  </button>
  <button class="bbtn gold" id="batchCopyBtn" onclick="showBatchDuplicate()" title="为所选场次各创建一个副本 [C]">
    <svg width="13" height="13" viewBox="0 0 14 14" fill="none"><rect x="1" y="3" width="8" height="9" rx="1" stroke="currentColor" stroke-width="1.3"/><path d="M4 3V2a1 1 0 0 1 1-1h7a1 1 0 0 1 1 1v9a1 1 0 0 1-1 1h-1" stroke="currentColor" stroke-width="1.3" stroke-linecap="round"/></svg>
    批量复制 <kbd style="font-size:.6rem;opacity:.55;margin-left:1px">C</kbd>
  </button>
  <button class="bbtn" onclick="showBatchRename()" title="批量重命名 [R]" style="border-color:#2A3828;color:#78A080">
    <svg width="13" height="13" viewBox="0 0 14 14" fill="none"><path d="M9 2.5L11.5 5L5 11.5H2.5V9L9 2.5Z" stroke="currentColor" stroke-width="1.3" stroke-linejoin="round"/><line x1="7.2" y1="4.2" x2="9.8" y2="6.8" stroke="currentColor" stroke-width="1.2" stroke-linecap="round"/></svg>
    重命名 <kbd style="font-size:.6rem;opacity:.55;margin-left:1px">R</kbd>
  </button>
  <button class="bbtn" onclick="clearSelect()" style="margin-left:auto" title="退出选择 [Esc]">取消</button>
</div>

<!-- Clean Empty Modal -->
<div class="modal-overlay" id="cleanModal">
  <div class="modal-box" style="width:380px" onclick="event.stopPropagation()">
    <div class="modal-title">
      <svg width="14" height="14" viewBox="0 0 14 14" fill="none" style="vertical-align:middle;margin-right:5px"><line x1="2" y1="3.5" x2="12" y2="3.5" stroke="currentColor" stroke-width="1.3" stroke-linecap="round"/><path d="M4.5,3.5 l0,1 h5 l0,-1" stroke="currentColor" stroke-width="1.2" stroke-linecap="round"/><path d="M3,5 l.7,8 h6.6 l.7,-8z" stroke="currentColor" stroke-width="1.2" stroke-linejoin="round"/></svg>
      删除空场次
    </div>
    <div class="modal-desc" id="cleanDesc">正在检测空数据文件夹…</div>
    <div class="clean-list" id="cleanList"></div>
    <div class="modal-result" id="cleanResult"></div>
    <div class="modal-btns" id="cleanBtns" style="display:none">
      <button class="mbtn-cancel" onclick="document.getElementById('cleanModal').classList.remove('show')">取消</button>
      <button class="mbtn-ok" id="cleanOkBtn" onclick="doCleanSelected()" style="background:#882020;border-color:#882020">确认删除</button>
    </div>
  </div>
</div>

<!-- Batch Rename Modal -->
<div class="modal-overlay" id="batchRenameModal">
  <div class="modal-box" style="width:390px" onclick="event.stopPropagation()">
    <div class="modal-title">
      <svg width="13" height="13" viewBox="0 0 14 14" fill="none" style="vertical-align:middle;margin-right:5px"><path d="M9 2.5L11.5 5L5 11.5H2.5V9L9 2.5Z" stroke="currentColor" stroke-width="1.3" stroke-linejoin="round"/><line x1="7.2" y1="4.2" x2="9.8" y2="6.8" stroke="currentColor" stroke-width="1.2" stroke-linecap="round"/></svg>
      批量重命名
    </div>
    <div class="modal-desc">逐行设置名称；留空则保留原名不变</div>
    <div style="display:flex;align-items:center;gap:6px;margin-bottom:10px">
      <input type="text" id="batchRenameAll" class="modal-input" style="flex:1" placeholder="统一命名 → 点右侧按钮填入所有行" maxlength="20">
      <button onclick="fillAllRenames()" class="mbtn-ok" style="padding:5px 10px;white-space:nowrap;flex-shrink:0">→ 填入</button>
    </div>
    <div id="batchRenameList" style="max-height:260px;overflow-y:auto;border:1px solid #2A2418;border-radius:6px;background:#0F0F0C"></div>
    <div id="batchRenameResult" class="modal-result"></div>
    <div class="modal-btns">
      <button class="mbtn-cancel" onclick="closeBatchRename()">取消</button>
      <button class="mbtn-ok" id="batchRenameOkBtn" onclick="doBatchRename()">保存</button>
    </div>
  </div>
</div>

<!-- Migrate Modal -->
<div class="modal-overlay" id="migrateModal">
  <div class="modal-box">
    <div class="modal-title">📅 迁移场次日期</div>
    <div class="modal-desc">将 <b id="mCount" style="color:var(--text)">0</b> 个选中场次迁移到新日期，自动分配场次字母</div>
    <label class="modal-label">新日期（YYMMDD，如 260501）</label>
    <input type="text" id="migrateDate" class="modal-input" maxlength="6" placeholder="260501">
    <div id="migrateResult" class="modal-result"></div>
    <div class="modal-btns">
      <button class="mbtn-cancel" onclick="closeMigrate()">取消</button>
      <button class="mbtn-ok" id="migOkBtn" onclick="doMigrate()">确认迁移</button>
    </div>
  </div>
</div>

<script>
// ── PHP 活动数据 ──────────────────────────────────────────────────────
const _actData=<?=json_encode($contribRaw)?>;

// ── 贡献热图渲染（GitHub 风格）────────────────────────────────────────
function renderContribGraph(){
    const el=document.getElementById('contribGraph');
    if(!el)return;
    const today=new Date();
    const todayStr=today.toISOString().slice(0,10);
    const totalDays=91; // 13×7
    const startDate=new Date(today);
    startDate.setDate(today.getDate()-(totalDays-1));

    // day-of-week labels (left gutter) — Mon/Wed/Fri
    let html='<div style="display:flex;gap:2px">';
    html+='<div style="display:flex;flex-direction:column;gap:2px;margin-right:1px;padding-top:1px">';
    ['','M','','W','','F',''].forEach(l=>{
        html+=`<div style="height:9px;font-size:6px;color:#3A3828;line-height:9px;width:8px;text-align:right">${l}</div>`;
    });
    html+='</div>';

    // 13 week columns
    html+='<div class="contrib-wrap">';
    for(let w=0;w<13;w++){
        html+='<div class="contrib-col">';
        for(let d=0;d<7;d++){
            const date=new Date(startDate);
            date.setDate(startDate.getDate()+w*7+d);
            const key=date.toISOString().slice(0,10);
            const count=_actData[key]||0;
            const future=date>today;
            const lvl=future?'f':(count===0?0:count===1?1:count<=3?2:3);
            const isToday=key===todayStr?' contrib-today':'';
            html+=`<div class="contrib-cell contrib-${lvl}${isToday}" title="${key}：${count} 场"></div>`;
        }
        html+='</div>';
    }
    html+='</div></div>';

    // month labels below
    html+='<div style="display:flex;gap:2px;margin-top:2px;padding-left:11px">';
    let prevM=-1;
    for(let w=0;w<13;w++){
        const d=new Date(startDate);
        d.setDate(startDate.getDate()+w*7);
        const m=d.getMonth();
        html+=`<div style="width:11px;font-size:6px;color:#4A4838;overflow:visible;white-space:nowrap">${m!==prevM?(m+1)+'月':''}</div>`;
        prevM=m===prevM?prevM:m;
    }
    html+='</div>';
    el.innerHTML=html;
}

// ── 键盘导航 ───────────────────────────────────────────────────────────
let _focusIdx=-1,_focusCard=null;
function _visCards(){return[...document.querySelectorAll('.sess-card')].filter(c=>c.style.display!=='none');}
function _moveFocus(delta){
    const cards=_visCards();if(!cards.length)return;
    _focusIdx=Math.max(0,Math.min(cards.length-1,(_focusIdx<0?0:_focusIdx)+delta));
    _setFocus(cards[_focusIdx]);
}
function _setFocus(card){
    if(_focusCard&&_focusCard!==card)_focusCard.classList.remove('card-focus');
    _focusCard=card;
    if(card){
        // 同步 _focusIdx
        const all=_visCards();const i=all.indexOf(card);if(i!==-1)_focusIdx=i;
        card.classList.add('card-focus');card.scrollIntoView({block:'nearest',behavior:'smooth'});
    }
}
// ── 按「日」跳转 ──────────────────────────────────────────────────────
function _moveFocusByDay(dir){
    const rows=[...document.querySelectorAll('.day-row')].filter(r=>r.style.display!=='none');
    if(!rows.length)return;
    let curIdx=-1;
    if(_focusCard){const r=_focusCard.closest('.day-row');curIdx=rows.indexOf(r);}
    const tgtIdx=curIdx===-1?(dir>0?0:rows.length-1):Math.max(0,Math.min(rows.length-1,curIdx+dir));
    const cards=[...rows[tgtIdx].querySelectorAll('.sess-card')].filter(c=>c.style.display!=='none');
    if(cards.length)_setFocus(cards[0]);
}
// ── 按「月」跳转 (PageUp/PageDown) ───────────────────────────────────
function _moveFocusByMonth(dir){
    const grps=[...document.querySelectorAll('.month-grp')].filter(g=>g.style.display!=='none');
    if(!grps.length)return;
    let curIdx=-1;
    if(_focusCard){const g=_focusCard.closest('.month-grp');curIdx=grps.indexOf(g);}
    const tgtIdx=curIdx===-1?(dir>0?0:grps.length-1):Math.max(0,Math.min(grps.length-1,curIdx+dir));
    const cards=[...grps[tgtIdx].querySelectorAll('.sess-card')].filter(c=>c.style.display!=='none');
    if(cards.length)_setFocus(cards[0]);
}

// ── 全局键盘处理 ──────────────────────────────────────────────────────
document.addEventListener('keydown',e=>{
    if(e.target.matches('input,textarea,select'))return;
    const k=e.key.toUpperCase();

    // Escape
    if(e.key==='Escape'){
        if(_activeCard){_activeCard.classList.remove('card-on');_activeCard=null;}
        if(_focusCard){_focusCard.classList.remove('card-focus');_focusCard=null;_focusIdx=-1;}
        closeYearModal();closeMigrate();
        ['cleanModal','newModal','batchRenameModal'].forEach(id=>document.getElementById(id)?.classList.remove('show'));
        return;
    }

    // 方向键：左右=逐卡片，上下=跨日，PageUp/Down=跨月
    if(e.key==='ArrowRight'){e.preventDefault();_moveFocus(1);return;}
    if(e.key==='ArrowLeft') {e.preventDefault();_moveFocus(-1);return;}
    if(e.key==='ArrowDown') {e.preventDefault();_moveFocusByDay(1);return;}
    if(e.key==='ArrowUp')   {e.preventDefault();_moveFocusByDay(-1);return;}
    if(e.key==='PageDown')  {e.preventDefault();_moveFocusByMonth(1);return;}
    if(e.key==='PageUp')    {e.preventDefault();_moveFocusByMonth(-1);return;}

    // 空格：选择/激活焦点卡片
    if(e.key===' '&&_focusCard){
        e.preventDefault();
        if(document.body.classList.contains('sel-mode')){
            const cb=_focusCard.querySelector('.cb');
            cb.checked=!cb.checked;cardSel(cb,null);
        } else {
            if(_activeCard&&_activeCard!==_focusCard){_activeCard.classList.remove('card-on');_activeCard=null;}
            _focusCard.classList.toggle('card-on');
            _activeCard=_focusCard.classList.contains('card-on')?_focusCard:null;
        }
        return;
    }

    // N：新增场次（无激活卡片、无多选时）
    if(k==='N'&&!e.ctrlKey&&!e.altKey&&!_activeCard&&!_selSet.size){showNew();return;}

    // M：切换多选模式（无已选项时）
    if(k==='M'&&!e.ctrlKey&&!e.altKey&&!_activeCard&&!_selSet.size){toggleSelectMode();return;}

    // 数字键 1-6：筛选
    if('123456'.includes(e.key)&&!e.ctrlKey&&!e.altKey&&!_activeCard&&!_selSet.size){
        const opts=document.getElementById('filterSel').options;
        const idx=parseInt(e.key)-1;
        if(idx<opts.length){document.getElementById('filterSel').value=opts[idx].value;applyFilter();}
        return;
    }

    // 批量操作快捷键（有已选项时优先）
    if(_selSet.size>0){
        if(k==='A'){batchAction('batchArchive');return;}
        if(k==='H'){batchAction('batchHide');return;}
        if(k==='D'){batchAction('batchDelete');return;}
        if(k==='T'){showMigrate();return;}
        if(k==='C'){showBatchDuplicate();return;}
        if(k==='R'){e.preventDefault();showBatchRename();return;}
    }

    // 卡片快捷键（有激活卡片时）
    if(_activeCard){
        const st=_activeCard.dataset.status;
        const $b=s=>_activeCard.querySelector(s);
        if     (k==='A'&&st==='active')  $b('.cab-a')?.click();
        else if(k==='H'&&st==='active')  $b('.cab-h')?.click();
        else if(k==='V'&&st!=='active')  $b('.cab-v')?.click();
        else if(k==='D'&&st!=='active')  $b('.cab-d')?.click();
        else if(k==='E')                 $b('.cab-e')?.click();
        else if(k==='G')                 $b('.cab-g')?.click();
        else if(k==='R'){e.preventDefault();const ne=_activeCard.querySelector('.card-name');if(ne)startEditName(ne,null);return;}
        else if(e.key==='Enter')         $b('.card-enter')?.click();
    }
});

// ── 年份折叠 ──────────────────────────────────────────────────────────────
function toggleYear(y){
    const hdr=document.querySelector(`#ysec-${y} .year-hdr`);
    const body=document.getElementById(`ybody-${y}`);
    hdr.classList.toggle('open');body.classList.toggle('open');
}

// ── 侧边栏 ────────────────────────────────────────────────────────────────
function toggleSidebar(){
    const sb=document.getElementById('sidebar');
    sb.classList.toggle('collapsed');
    document.getElementById('sbToggleBtn').classList.toggle('act',!sb.classList.contains('collapsed'));
}

// ── 初始化 ─────────────────────────────────────────────────────────────
renderContribGraph();
// Sidebar default state: open, reflect button
document.getElementById('sbToggleBtn').classList.add('act');

// ── 卡片点击 ──────────────────────────────────────────────────────────────
let _activeCard=null;
function cardClick(card,e){
    if(e.target.closest('.cab,.card-enter,.code-m,.code-f'))return;
    if(document.body.classList.contains('sel-mode')){
        // 多选模式：手动切换内部 checkbox 状态（checkbox 已隐藏）
        const cb=card.querySelector('.cb');
        cb.checked=!cb.checked;
        cardSel(cb,e);
        return;
    }
    if(_activeCard&&_activeCard!==card){_activeCard.classList.remove('card-on');_activeCard=null;}
    card.classList.toggle('card-on');
    _activeCard=card.classList.contains('card-on')?card:null;
}
document.addEventListener('click',e=>{
    if(_activeCard&&!_activeCard.contains(e.target)){_activeCard.classList.remove('card-on');_activeCard=null;}
},{capture:false});

// ── 多选 ─────────────────────────────────────────────────────────────────
let _selSet=new Set();
function toggleSelectMode(){
    const on=document.body.classList.toggle('sel-mode');
    document.getElementById('selModeBtn').classList.toggle('sel-on',on);
    if(!on)clearSelect();
}
function cardSel(cb,e){
    if(e)e.stopPropagation();
    const card=cb.closest('.sess-card');
    const code=card.dataset.code;
    cb.checked?_selSet.add(code):_selSet.delete(code);
    card.classList.toggle('card-sel',cb.checked);
    updateBatch();
}
function clearSelect(){
    _selSet.clear();
    document.querySelectorAll('.sess-card.card-sel').forEach(c=>{c.classList.remove('card-sel');c.querySelector('.cb').checked=false;});
    updateBatch();
}
function updateBatch(){
    const bar=document.getElementById('batchBar');
    const n=_selSet.size;
    bar.classList.toggle('show',n>0);
    if(n>0) document.getElementById('batchInfo').textContent=`已选 ${n} 场`;
}


// ── 全局授权码显示切换 ────────────────────────────────────────────────────
let _codesShown=false;
function toggleAllCodes(){
  _codesShown=!_codesShown;
  document.querySelectorAll('.card-top').forEach(top=>{
    const m=top.querySelector('.code-m'),f=top.querySelector('.code-f');
    if(!m||!f)return;
    m.style.display=_codesShown?'none':'';
    f.style.display=_codesShown?'':'none';
  });
  const btn=document.getElementById('eyeAllBtn');
  if(btn)btn.classList.toggle('sel-on',_codesShown);
}

// ── 复制编号（点击 code-m / code-f）────────────────────────────────────
async function cpCode(code,el,e){
    if(e)e.stopPropagation();
    try{
        await navigator.clipboard.writeText(code);
        const o=el.innerHTML;
        const disp=code.length>9?code.slice(0,7)+'…'+code.slice(-2):code;
        el.innerHTML=`<span style="color:#80D060;font-size:.62rem;letter-spacing:0;white-space:nowrap">✓ 已复制 ${disp}</span>`;
        setTimeout(()=>{el.innerHTML=o},2200);
    }catch{prompt('复制编号：',code);}
}

// ── 复制链接 ──────────────────────────────────────────────────────────────
async function cpTxt(url,btn,orig){
    try{
        await navigator.clipboard.writeText(url);
        btn.textContent='✓';
        setTimeout(()=>{btn.textContent=orig??'?'},1400);
    }catch{prompt('复制链接：',url);}
}

// ── 编辑场次名称 ──────────────────────────────────────────────────────────
async function startEditName(el,e){
    if(e)e.stopPropagation();
    if(el.querySelector('.card-name-input'))return; // already editing
    const code=el.dataset.code;
    const hasPlaceholder=!!el.querySelector('.card-no-name');
    const currentName=hasPlaceholder?'':el.textContent.trim();
    const input=document.createElement('input');
    input.type='text';input.className='card-name-input';
    input.value=currentName;input.maxLength=15;
    input.placeholder='输入名称…';
    el.innerHTML='';el.appendChild(input);
    input.focus();input.select();
    const restore=name=>{
        el.innerHTML=name?name:'<span class="card-no-name">双击添加名称</span>';
    };
    const save=async()=>{
        const newNote=input.value.trim();
        if(newNote===currentName){restore(currentName);return;}
        const fd=new FormData();fd.append('action','renameNote');fd.append('code',code);fd.append('note',newNote);
        const r=await fetch('dashboard.php?api=1',{method:'POST',body:fd}).catch(()=>null);
        const j=r?await r.json().catch(()=>null):null;
        restore(j?.ok?j.note:currentName);
    };
    input.addEventListener('blur',save,{once:true});
    input.addEventListener('keydown',ev=>{
        if(ev.key==='Enter'){ev.preventDefault();input.blur();}
        else if(ev.key==='Escape'){input.removeEventListener('blur',save);restore(currentName);}
        ev.stopPropagation();
    });
}

// ── 状态操作 ─────────────────────────────────────────────────────────────
const _warn={
    archive:'归档此场次？归档后用户只能查看，不可编辑。',
    restore:'恢复为有效场次？',
    hide:'隐藏此场次？用户将完全无法访问，位次编号保留。',
    unhide:'恢复此场次为有效？',
    delete:'彻底删除此场次？数据文件和 auth.json 记录将全部清除，不可恢复！'
};
async function doStatus(code,action,btn){
    if(action==='delete'&&!confirm(_warn.delete))return;
    const o=btn.textContent;
    btn.disabled=true;btn.textContent='…';
    const fd=new FormData();fd.append('action',action);fd.append('code',code);
    const r=await fetch('dashboard.php?api=1',{method:'POST',body:fd}).catch(()=>null);
    if(r&&(await r.json()).ok)location.reload();
    else{alert('操作失败');btn.disabled=false;btn.textContent=o;}
}

// ── 批量复制副本 ──────────────────────────────────────────────────────────
async function showBatchDuplicate(){
    const codes=[..._selSet];
    if(!codes.length){alert('请先选择场次');return;}
    const btn=document.getElementById('batchCopyBtn');
    const o=btn.innerHTML;btn.disabled=true;btn.textContent='处理中…';
    let ok=0,errs=[];
    for(const code of codes){
        const fd=new FormData();fd.append('action','duplicate');fd.append('code',code);
        const r=await fetch('dashboard.php?api=1',{method:'POST',body:fd}).catch(()=>null);
        const j=r?await r.json().catch(()=>null):null;
        if(j&&j.ok)ok++;else errs.push(code+': '+(j?.error||'失败'));
    }
    if(errs.length)alert(`完成：${ok} 成功，${errs.length} 失败\n${errs.join('\n')}`);
    location.reload();
}

// ── 批量操作 ─────────────────────────────────────────────────────────────
async function batchAction(action){
    const codes=[..._selSet];if(!codes.length)return;
    if(action==='batchDelete'&&!confirm(`彻底删除选中的 ${codes.length} 个场次？数据和记录将全部清除，不可恢复！`))return;
    const fd=new FormData();fd.append('action',action);
    codes.forEach(c=>fd.append('codes[]',c));
    const r=await fetch('dashboard.php?api=1',{method:'POST',body:fd}).catch(()=>null);
    if(r&&(await r.json()).ok)location.reload();
    else alert('操作失败');
}

// ── 迁移日期 ─────────────────────────────────────────────────────────────
function showMigrate(){
    if(!_selSet.size){alert('请先选择要迁移的场次');return;}
    document.getElementById('mCount').textContent=_selSet.size;
    document.getElementById('migrateDate').value='';
    const res=document.getElementById('migrateResult');res.style.display='none';res.textContent='';
    document.getElementById('migrateModal').classList.add('show');
    setTimeout(()=>document.getElementById('migrateDate').focus(),60);
}
function closeMigrate(){
    document.getElementById('migrateModal').classList.remove('show');
}
async function doMigrate(){
    const d=document.getElementById('migrateDate').value.replace(/\D/g,'');
    if(d.length!==6){alert('请输入6位日期，如 260501');return;}
    const codes=[..._selSet];
    const btn=document.getElementById('migOkBtn');
    btn.disabled=true;btn.textContent='迁移中…';
    const fd=new FormData();fd.append('action','batchMigrate');fd.append('newDate',d);
    codes.forEach(c=>fd.append('codes[]',c));
    const r=await fetch('dashboard.php?api=1',{method:'POST',body:fd}).catch(()=>null);
    btn.disabled=false;btn.textContent='确认迁移';
    if(!r){alert('网络错误');return;}
    const j=await r.json();
    if(j.ok){
        const res=document.getElementById('migrateResult');
        res.style.display='block';
        const detail=j.results.map(p=>`${p[0]} → ${p[1]}`).join('，');
        res.innerHTML=`✅ 已迁移 ${j.count} 个场次${detail?'<br>'+detail:''}`+(j.errors.length?`<br>⚠ ${j.errors.join('；')}`:'');
        setTimeout(()=>{closeMigrate();location.reload();},1500);
    } else alert(j.error||'迁移失败');
}
// 点 overlay 空白关闭
document.getElementById('migrateModal').addEventListener('click',function(e){if(e.target===this)closeMigrate();});

// ── 删除空场次 Modal ──────────────────────────────────────────────────
async function showCleanModal(){
    const modal=document.getElementById('cleanModal');
    const listEl=document.getElementById('cleanList');
    const desc=document.getElementById('cleanDesc');
    const btns=document.getElementById('cleanBtns');
    const result=document.getElementById('cleanResult');
    listEl.innerHTML='';desc.textContent='正在检测空数据文件夹…';
    btns.style.display='none';result.style.display='none';
    modal.classList.add('show');
    const fd=new FormData();fd.append('action','listEmpty');
    const r=await fetch('dashboard.php?api=1',{method:'POST',body:fd}).catch(()=>null);
    if(!r){desc.textContent='网络错误，请重试';return;}
    const d=await r.json();
    if(!d.ok){desc.textContent='获取失败';return;}
    if(!d.list.length){desc.textContent='✓ 暂无空数据文件夹，无需清理';return;}
    desc.textContent=`共 ${d.list.length} 个空数据文件夹，勾选后确认删除：`;
    let html=`<div class="clean-list-hdr"><label><input type="checkbox" id="cleanAll" checked onchange="toggleCleanAll(this.checked)" style="accent-color:#882020"> 全选/全不选</label><span style="color:#887060">${d.list.length} 项</span></div>`;
    d.list.forEach(item=>{
        const lbl=item.note?`${item.code} <span style="color:#6A6058">— ${item.note}</span>`:item.code;
        html+=`<div class="clean-list-item"><label><input type="checkbox" class="clean-item" data-code="${item.code}" checked style="accent-color:#882020">${lbl}</label></div>`;
    });
    listEl.innerHTML=html;
    btns.style.display='flex';
}
function toggleCleanAll(checked){
    document.querySelectorAll('.clean-item').forEach(cb=>cb.checked=checked);
}
async function doCleanSelected(){
    const codes=[...document.querySelectorAll('.clean-item:checked')].map(cb=>cb.dataset.code);
    if(!codes.length){alert('没有选中任何项');return;}
    const btn=document.getElementById('cleanOkBtn');
    btn.disabled=true;btn.textContent='删除中…';
    const fd=new FormData();fd.append('action','cleanEmpty');
    codes.forEach(c=>fd.append('codes[]',c));
    const r=await fetch('dashboard.php?api=1',{method:'POST',body:fd}).catch(()=>null);
    btn.disabled=false;btn.textContent='确认删除';
    if(!r){alert('网络错误');return;}
    const d=await r.json();
    const result=document.getElementById('cleanResult');
    result.style.display='block';
    result.textContent=d.ok?`✅ 已删除 ${d.count} 个空文件夹`:'操作失败';
    if(d.ok)setTimeout(()=>{document.getElementById('cleanModal').classList.remove('show');location.reload();},1500);
}
document.getElementById('cleanModal')?.addEventListener('click',function(e){if(e.target===this)this.classList.remove('show');});

// ── 新增场次 ──────────────────────────────────────────────────────────────
function showNew(){
    const t=new Date();
    const yy=String(t.getFullYear()).slice(2);
    const mm=String(t.getMonth()+1).padStart(2,'0');
    const dd=String(t.getDate()).padStart(2,'0');
    document.getElementById('newDate').value=yy+mm+dd;
    document.getElementById('newNote').value='';
    const res=document.getElementById('newResult');res.style.display='none';res.textContent='';
    document.getElementById('newModal').classList.add('show');
    setTimeout(()=>document.getElementById('newNote').focus(),60);
}
function closeNew(){document.getElementById('newModal').classList.remove('show');}
async function doCreate(){
    const date=document.getElementById('newDate').value.replace(/\D/g,'');
    if(date.length!==6){alert('请输入6位日期，如 260501');return;}
    const note=document.getElementById('newNote').value.trim();
    const btn=document.getElementById('newOkBtn');
    btn.disabled=true;btn.textContent='创建中…';
    const fd=new FormData();fd.append('action','create');fd.append('date',date);fd.append('note',note);
    const r=await fetch('dashboard.php?api=1',{method:'POST',body:fd}).catch(()=>null);
    btn.disabled=false;btn.textContent='创建';
    if(!r){alert('网络错误');return;}
    const j=await r.json();
    if(j.ok){
        const res=document.getElementById('newResult');res.style.display='block';
        res.innerHTML=`✅ 已创建 <b style="color:var(--text);font-family:monospace">${j.code}</b>`;
        setTimeout(()=>{closeNew();location.reload();},1600);
    }else alert(j.error||'创建失败');
}
document.getElementById('newModal')?.addEventListener('click',function(e){if(e.target===this)closeNew();});

// ── 批量重命名 ────────────────────────────────────────────────────────────
function showBatchRename(){
    if(!_selSet.size)return;
    let html='';
    [..._selSet].forEach(code=>{
        const card=document.querySelector(`.sess-card[data-code="${CSS.escape(code)}"]`);
        const nameEl=card?.querySelector('.card-name');
        const currentName=nameEl?.querySelector('.card-no-name')?'':(nameEl?.textContent.trim()||'');
        const dispCode=code.length>9?code.slice(0,7)+'…':code;
        html+=`<div style="display:flex;align-items:center;gap:7px;padding:6px 10px;border-bottom:1px solid #1A1810">
            <span style="font-family:monospace;font-size:.7rem;color:#5A5848;flex-shrink:0;width:68px;overflow:hidden;text-overflow:ellipsis">${dispCode}</span>
            <input type="text" class="brn-inp" data-code="${code}"
              value="${currentName.replace(/"/g,'&quot;')}"
              placeholder="留空=保留原名"
              maxlength="20"
              style="flex:1;background:transparent;border:none;border-bottom:1px solid #2A2418;
                     color:#C8C090;font-size:.75rem;font-family:inherit;outline:none;padding:2px 4px;
                     transition:border-color .12s"
              onfocus="this.style.borderColor='var(--gold)'"
              onblur="this.style.borderColor='#2A2418'">
        </div>`;
    });
    document.getElementById('batchRenameList').innerHTML=html||'<div style="padding:10px;font-size:.72rem;color:#4A4838">无选中场次</div>';
    document.getElementById('batchRenameAll').value='';
    document.getElementById('batchRenameResult').style.display='none';
    document.getElementById('batchRenameModal').classList.add('show');
    // focus first input
    setTimeout(()=>document.querySelector('#batchRenameList .brn-inp')?.focus(),80);
}
function fillAllRenames(){
    const v=document.getElementById('batchRenameAll').value;
    document.querySelectorAll('.brn-inp').forEach(inp=>inp.value=v);
}
function closeBatchRename(){document.getElementById('batchRenameModal').classList.remove('show');}
async function doBatchRename(){
    const inputs=[...document.querySelectorAll('.brn-inp')];
    const btn=document.getElementById('batchRenameOkBtn');
    btn.disabled=true;btn.textContent='保存中…';
    let ok=0,errs=[];
    for(const inp of inputs){
        const code=inp.dataset.code;
        const note=inp.value.trim();
        if(note==='')continue; // 留空跳过
        const fd=new FormData();fd.append('action','renameNote');fd.append('code',code);fd.append('note',note);
        const r=await fetch('dashboard.php?api=1',{method:'POST',body:fd}).catch(()=>null);
        const j=r?await r.json().catch(()=>null):null;
        if(j?.ok)ok++;else errs.push(code);
    }
    btn.disabled=false;btn.textContent='保存';
    const res=document.getElementById('batchRenameResult');res.style.display='block';
    if(!errs.length){
        res.textContent=ok>0?`✅ 已更新 ${ok} 个名称`:'（无变更）';
        if(ok>0)setTimeout(()=>{closeBatchRename();location.reload();},1400);
    }else{
        res.textContent=`已更新 ${ok} 个，${errs.length} 个失败`;
    }
}
document.getElementById('batchRenameModal')?.addEventListener('click',function(e){if(e.target===this)closeBatchRename();});

// ── 筛选 ─────────────────────────────────────────────────────────────────
function applyFilter(){
    const v=document.getElementById('filterSel').value;
    let shown=0,hidden=0;
    document.querySelectorAll('.sess-card').forEach(card=>{
        const st=card.dataset.status,g=parseInt(card.dataset.guests||0),hd=card.dataset.hasdata==='1';
        let show=true;
        if(v==='active')   show=(st==='active');
        else if(v==='unused')  show=(st==='active'&&!hd);
        else if(v==='few')     show=(g<5&&st==='active');
        else if(v==='archived')show=(st==='archived');
        else if(v==='hidden')  show=(st==='hidden');
        card.style.display=show?'':'none';
        show?shown++:hidden++;
    });
    document.querySelectorAll('.day-row').forEach(row=>{
        const vis=[...row.querySelectorAll('.sess-card')].some(c=>c.style.display!=='none');
        row.style.display=vis?'':'none';
    });
    document.querySelectorAll('.month-grp').forEach(grp=>{
        const vis=[...grp.querySelectorAll('.day-row')].some(r=>r.style.display!=='none');
        grp.style.display=vis?'':'none';
    });
    document.getElementById('fbarInfo').textContent=v==='all'?'':`显示 ${shown} 场，隐藏 ${hidden} 场`;
}

// ── 年份范围弹窗 ─────────────────────────────────────────────────────────
function openYearModal(){document.getElementById('yearModal').classList.add('show');}
function closeYearModal(){document.getElementById('yearModal').classList.remove('show');}
// (Escape for modals handled in main keydown handler above)
// ── 配置保存 ─────────────────────────────────────────────────────────────
async function saveCfg(){
    const ys=parseInt(document.getElementById('cfgYS').value),ye=parseInt(document.getElementById('cfgYE').value);
    if(isNaN(ys)||isNaN(ye)||ys>ye){alert('年份范围无效');return;}
    const fd=new FormData();
    fd.append('action','saveCfg');fd.append('yearStart',ys);fd.append('yearEnd',ye);
    const r=await fetch('dashboard.php?api=1',{method:'POST',body:fd}).catch(()=>null);
    if(r&&(await r.json()).ok)location.reload();
    else{const el=document.getElementById('yrResult');el.style.display='block';el.textContent='保存失败，请重试';}
}
</script>
</body></html>
