<?php
/**
 * admin.php — SeatCard 超级管理后台
 * 超级密码保护 · 黑金主题 · 完整场次管理 · 系统维护
 */

// ── 配置 ──────────────────────────────────────────────────────────────────────
define('DATA_DIR',  __DIR__.'/data/');
define('AUTH_FILE', __DIR__.'/data/auth.json');
define('CFG_FILE',  __DIR__.'/data/sc_config.json');
define('LOG_FILE',  __DIR__.'/data/admin_log.json');
define('SC_ALPHA',  '346789ACDEFGHJKLMNPQRSTUVWXY');
define('ADMIN_VER', 'V0.25');

$_cfg = file_exists(CFG_FILE) ? (json_decode(file_get_contents(CFG_FILE),true)??[]) : [];
define('ADMIN_PASS', $_cfg['admin_pass'] ?? 'admin888');
unset($_cfg);

// ── 工具函数 ──────────────────────────────────────────────────────────────────
function adRA(){ if(!file_exists(AUTH_FILE))return []; return json_decode(file_get_contents(AUTH_FILE),true)??[]; }
function adWA($l){ file_put_contents(AUTH_FILE,json_encode($l,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),LOCK_EX); }
function adRC(){
    $d=['yearStart'=>2026,'yearEnd'=>2030,'autoSave'=>['globalEnabled'=>true,'interval'=>10,'minInterval'=>2,'majorOpTrigger'=>true,'idleMinutes'=>3]];
    if(!file_exists(CFG_FILE))return $d;
    $c=json_decode(file_get_contents(CFG_FILE),true)??[];
    if(isset($c['autoSave'])&&is_array($c['autoSave']))$c['autoSave']=array_merge($d['autoSave'],$c['autoSave']);
    return array_merge($d,$c);
}
function adWC($c){ file_put_contents(CFG_FILE,json_encode($c,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE),LOCK_EX); }

function adCK($s){
    $r=crc32($s); $h=($r<0)?$r+4294967296:$r;
    return SC_ALPHA[intdiv($h,28)%28].SC_ALPHA[$h%28];
}
function adGC($yymmdd,$ltr){ return $yymmdd.$ltr.adCK($yymmdd.$ltr); }
function adOK($code){ return (bool)preg_match('/^\d{6}([A-Z]|[a-z][A-Z])[346789ACDEFGHJKLMNPQRSTUVWXY]{2}$/',$code); }

function adNL($list,$d6){
    $used=[];
    foreach($list as $e){ $c=$e['code']??''; if(substr($c,0,6)!==$d6)continue; $l=strlen($c); if($l===9)$used[]=$c[6]; elseif($l===10)$used[]=$c[6].$c[7]; }
    for($i=65;$i<=90;$i++){$l=chr($i);if(!in_array($l,$used,true))return $l;}
    for($p=97;$p<=122;$p++)for($i=65;$i<=90;$i++){$l=chr($p).chr($i);if(!in_array($l,$used,true))return $l;}
    return null;
}

function adSt($code){
    $dir=DATA_DIR.$code.'/'; $z=['tables'=>0,'guests'=>0,'hasData'=>false,'name'=>'','editTs'=>0,'size'=>0,'files'=>0];
    if(!is_dir($dir))return $z;
    $fs=array_merge(glob($dir.'wedding-seating-backup-*.json')??[],glob($dir.'wedding-seating-slot-*.json')??[]);
    if(!$fs)return $z;
    usort($fs,fn($a,$b)=>filemtime($b)-filemtime($a));
    $size=array_sum(array_map('filesize',$fs));
    $d=json_decode(file_get_contents($fs[0]),true);
    if(!$d)return array_merge($z,['size'=>$size,'files'=>count($fs)]);
    $et=filemtime($fs[0]);
    if(($ts=strtotime($d['savedAt']??''))>0)$et=$ts;
    return['tables'=>count($d['tables']??[]),'guests'=>count($d['guests']??[]),'hasData'=>true,'name'=>($d['projectName']??''),'editTs'=>$et,'size'=>$size,'files'=>count($fs)];
}

function adFB($b){ if($b<1024)return $b.'B'; if($b<1048576)return round($b/1024,1).'KB'; return round($b/1048576,1).'MB'; }
function adRT($ts){ if(!$ts)return''; $d=time()-$ts; if($d<60)return'刚刚'; if($d<3600)return floor($d/60).'分前'; if($d<86400)return floor($d/3600).'时前'; if($d<86400*30)return floor($d/86400).'天前'; return date('n/j',$ts); }

function adLog(){ $log=file_exists(LOG_FILE)?(json_decode(file_get_contents(LOG_FILE),true)??[]):[];
    array_unshift($log,['ts'=>time(),'ip'=>$_SERVER['REMOTE_ADDR']??'','ua'=>substr($_SERVER['HTTP_USER_AGENT']??'',0,100)]);
    file_put_contents(LOG_FILE,json_encode(array_slice($log,0,30),JSON_PRETTY_PRINT),LOCK_EX);
}

// ── 登录 ─────────────────────────────────────────────────────────────────────
session_start();
$ok=($_SESSION['sc_admin']??false);
if(!$ok && $_SERVER['REQUEST_METHOD']==='POST' && ($_POST['pass']??'')===ADMIN_PASS){ $_SESSION['sc_admin']=true; $ok=true; adLog(); }
if(!$ok && ($_GET['api']??'')==='1'){ header('Content-Type:application/json'); echo json_encode(['error'=>'unauthorized']); exit; }
if(!$ok){ showLogin(); exit; }

// ── API ───────────────────────────────────────────────────────────────────────
if(($_GET['api']??'')==='1' && $_SERVER['REQUEST_METHOD']==='POST'){
    header('Content-Type: application/json; charset=UTF-8');
    $act=trim($_POST['action']??'');
    $list=adRA();

    // 即时计算授权码
    if($act==='calc'){
        $d6=preg_replace('/\D/','',trim($_POST['d6']??'')); $ltr=trim($_POST['ltr']??'A');
        if(strlen($d6)!==6||!preg_match('/^([A-Z]|[a-z][A-Z])$/',$ltr)){echo json_encode(['error'=>'参数无效']);exit;}
        $code=adGC($d6,$ltr); $reg=false; $st='';
        foreach($list as $e){if(($e['code']??'')===$code){$reg=true;$st=$e['status']??'active';break;}}
        echo json_encode(['code'=>$code,'reg'=>$reg,'status'=>$st,'ro'=>substr($code,0,-2)]);exit;
    }

    // 验证任意授权码
    if($act==='validate'){
        $code=strtoupper(trim($_POST['code']??'')); $valid=adOK($code); $reg=false; $st='';
        if($valid)foreach($list as $e){if(($e['code']??'')===$code){$reg=true;$st=$e['status']??'active';break;}}
        echo json_encode(['valid'=>$valid,'reg'=>$reg,'status'=>$st,'ro'=>substr($code,0,-2)]);exit;
    }

    // 生成单场次
    if($act==='gen'){
        $d6=preg_replace('/\D/','',trim($_POST['d6']??'')); $ltr=trim($_POST['ltr']??'');
        $note=mb_substr(trim($_POST['note']??''),0,30);
        if(strlen($d6)!==6){echo json_encode(['error'=>'日期格式错误']);exit;}
        if(!$ltr){$ltr=adNL($list,$d6); if(!$ltr){echo json_encode(['error'=>'该日期场次已满']);exit;}}
        if(!preg_match('/^([A-Z]|[a-z][A-Z])$/',$ltr)){echo json_encode(['error'=>'场次字母无效']);exit;}
        $code=adGC($d6,$ltr);
        foreach($list as $e){if(($e['code']??'')===$code){echo json_encode(['error'=>'已存在','code'=>$code]);exit;}}
        $list[]=['code'=>$code,'status'=>'active','note'=>$note,'createdAt'=>date('c')];
        adWA($list); $dir=DATA_DIR.$code.'/'; if(!is_dir($dir))@mkdir($dir,0755,true);
        echo json_encode(['ok'=>true,'code'=>$code]);exit;
    }

    // 批量生成（预览/执行）
    if($act==='batchGen'){
        $d6=preg_replace('/\D/','',trim($_POST['d6']??'')); $cnt=max(1,min(26,intval($_POST['cnt']??1)));
        $startL=strtoupper(trim($_POST['startL']??'A')); $note=mb_substr(trim($_POST['note']??''),0,20);
        $prev=(($_POST['prev']??'0')==='1');
        if(strlen($d6)!==6){echo json_encode(['error'=>'日期格式错误']);exit;}
        $letters='ABCDEFGHIJKLMNOPQRSTUVWXYZ'; $si=strpos($letters,$startL);
        if($si===false){echo json_encode(['error'=>'起始字母无效']);exit;}
        $res=[];$errs=[];$cur=$list;
        for($i=0;$i<$cnt;$i++){
            $idx=$si+$i; if($idx>=26){$errs[]='超出 A-Z 范围';break;}
            $ltr=$letters[$idx]; $code=adGC($d6,$ltr); $dup=false;
            foreach($cur as $e){if(($e['code']??'')===$code){$dup=true;break;}}
            if($dup){$errs[]="$code 已存在";continue;}
            $n=$note?($note.($cnt>1?'·'.chr(65+$i).'场':'')):'';
            $res[]=['code'=>$code,'note'=>$n];
            if(!$prev){$cur[]=['code'=>$code,'status'=>'active','note'=>$n,'createdAt'=>date('c')]; @mkdir(DATA_DIR.$code.'/',0755,true);}
        }
        if(!$prev&&$res)adWA($cur);
        echo json_encode(['ok'=>true,'res'=>$res,'errs'=>$errs]);exit;
    }

    // 状态变更
    if(in_array($act,['archive','restore','hide','unhide'])){
        $codes=$_POST['codes']??[$_POST['code']??'']; if(is_string($codes))$codes=[$codes];
        $ok2=0;
        foreach($list as &$e){
            if(!in_array($e['code']??'',$codes,true))continue;
            $e['status']=$act==='archive'?'archived':($act==='hide'?'hidden':'active'); $ok2++;
        }unset($e);
        if($ok2){adWA($list);echo json_encode(['ok'=>true,'n'=>$ok2]);}else echo json_encode(['error'=>'未找到']);exit;
    }

    // 删除
    if($act==='del'){
        $codes=$_POST['codes']??[$_POST['code']??'']; if(is_string($codes))$codes=[$codes];
        $nl=[];$ok2=0;
        foreach($list as $e){ $c=$e['code']??''; if(in_array($c,$codes,true)){$dir=DATA_DIR.$c.'/';if(is_dir($dir)){foreach(glob($dir.'*.json')??[]as$f)unlink($f);@rmdir($dir);}$ok2++;}else $nl[]=$e;}
        if($ok2){adWA($nl);echo json_encode(['ok'=>true,'n'=>$ok2]);}else echo json_encode(['error'=>'未找到']);exit;
    }

    // 移动（预览/执行）
    if($act==='move'){
        $codes=$_POST['codes']??[$_POST['code']??'']; if(is_string($codes))$codes=[$codes];
        $nd=preg_replace('/\D/','',trim($_POST['nd']??''));
        $prev=(($_POST['prev']??'0')==='1');
        if(strlen($nd)!==6){echo json_encode(['error'=>'目标日期格式错误']);exit;}
        $res=[];$errs=[];$cur=$list;
        foreach($codes as $oc){
            $ei=null; foreach($cur as $i=>$e){if(($e['code']??'')===$oc){$ei=$i;break;}}
            if($ei===null){$errs[]="$oc 不存在";continue;}
            $nl2=adNL($cur,$nd); if(!$nl2){$errs[]="$oc 目标日期场次已满";continue;}
            $nc=adGC($nd,$nl2); $res[]=[$oc,$nc];
            if(!$prev){
                $od=DATA_DIR.$oc.'/'; $ndir=DATA_DIR.$nc.'/';
                if(is_dir($od)){if(!rename($od,$ndir)){$errs[]="$oc 目录迁移失败";continue;}}
                $cur[$ei]['code']=$nc;
            }
        }
        if(!$prev&&$res)adWA(array_values($cur));
        echo json_encode(['ok'=>true,'res'=>$res,'errs'=>$errs,'prev'=>$prev]);exit;
    }

    // 复制（预览/执行）
    if($act==='copy'){
        $codes=$_POST['codes']??[$_POST['code']??'']; if(is_string($codes))$codes=[$codes];
        $nd=preg_replace('/\D/','',trim($_POST['nd']??''));
        $prev=(($_POST['prev']??'0')==='1');
        $res=[];$errs=[];$cur=$list;
        foreach($codes as $sc2){
            $ent=null; foreach($cur as $e){if(($e['code']??'')===$sc2){$ent=$e;break;}}
            if(!$ent){$errs[]="$sc2 不存在";continue;}
            $td=$nd?:substr($sc2,0,6); $nl2=adNL($cur,$td); if(!$nl2){$errs[]="$sc2 目标日期已满";continue;}
            $nc=adGC($td,$nl2); $res[]=[$sc2,$nc];
            if(!$prev){
                $sd=DATA_DIR.$sc2.'/'; $dd2=DATA_DIR.$nc.'/'; @mkdir($dd2,0755,true);
                if(is_dir($sd))foreach(glob($sd.'*.json')??[]as$f)copy($f,$dd2.basename($f));
                $nn=trim(($ent['note']??'').($nd&&$nd!==substr($sc2,0,6)?'':' (副本)'));
                $cur[]=['code'=>$nc,'status'=>'active','note'=>$nn,'createdAt'=>date('c')];
            }
        }
        if(!$prev&&$res)adWA($cur);
        echo json_encode(['ok'=>true,'res'=>$res,'errs'=>$errs,'prev'=>$prev]);exit;
    }

    // 重命名
    if($act==='rename'){
        $code=trim($_POST['code']??''); $note=mb_substr(trim($_POST['note']??''),0,30); $ok2=false;
        foreach($list as &$e){if(($e['code']??'')===$code){$e['note']=$note;$ok2=true;break;}}unset($e);
        if($ok2){adWA($list);echo json_encode(['ok'=>true,'note'=>$note]);}else echo json_encode(['error'=>'未找到']);exit;
    }

    // 单场次统计（悬浮详情）
    if($act==='stat'){
        $code=trim($_POST['code']??''); $st=adSt($code);
        $st['editRel']=adRT($st['editTs']); $st['sizeStr']=adFB($st['size']);
        echo json_encode(['ok'=>true,'st'=>$st]);exit;
    }

    // 导出 ZIP
    if($act==='exportZip'){
        $codes=$_POST['codes']??[]; if(is_string($codes))$codes=[$codes];
        if(!class_exists('ZipArchive')){echo json_encode(['error'=>'ZipArchive 扩展不可用']);exit;}
        $tmp=tempnam(sys_get_temp_dir(),'sc_');
        $zip=new ZipArchive(); $zip->open($tmp,ZipArchive::OVERWRITE);
        foreach($codes as $c2){ $dir=DATA_DIR.$c2.'/'; if(!is_dir($dir))continue; foreach(glob($dir.'*.json')??[]as$f)$zip->addFile($f,$c2.'/'.basename($f)); }
        $zip->close();
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="seatcard-export-'.date('Ymd-His').'.zip"');
        header('Content-Length: '.filesize($tmp));
        readfile($tmp); unlink($tmp); exit;
    }

    // 导入 JSON 到场次
    if($act==='import'){
        $code=trim($_POST['code']??'');
        if(!adOK($code)){echo json_encode(['error'=>'无效场次编号']);exit;}
        if(!isset($_FILES['jf'])||$_FILES['jf']['error']!==0){echo json_encode(['error'=>'文件上传失败']);exit;}
        $raw=file_get_contents($_FILES['jf']['tmp_name']); $d=json_decode($raw,true);
        if(!$d||!isset($d['tables'])){echo json_encode(['error'=>'不是有效的 SeatCard JSON']);exit;}
        $dir=DATA_DIR.$code.'/'; if(!is_dir($dir)&&!mkdir($dir,0755,true)){echo json_encode(['error'=>'无法创建目录']);exit;}
        $fn='wedding-seating-backup-'.date('Ymd-His').'.json'; file_put_contents($dir.$fn,$raw);
        $found=false; foreach($list as $e){if(($e['code']??'')===$code){$found=true;break;}}
        if(!$found){$list[]=['code'=>$code,'status'=>'active','note'=>'','createdAt'=>date('c')];adWA($list);}
        echo json_encode(['ok'=>true,'file'=>$fn]);exit;
    }

    // 孤立目录扫描
    if($act==='orphan'){
        $reg=array_column($list,'code'); $orphans=[];
        foreach(scandir(DATA_DIR)as$e2){ if($e2==='.'||$e2==='..')continue; if(!is_dir(DATA_DIR.$e2))continue; if(in_array($e2,$reg,true))continue; if(!preg_match('/^\d{6}[A-Za-z]{1,2}[346789ACDEFGHJKLMNPQRSTUVWXY]{2}$/',$e2))continue; $fs=glob(DATA_DIR.$e2.'/*.json')??[]; $sz=array_sum(array_map('filesize',$fs)); $orphans[]=['dir'=>$e2,'files'=>count($fs),'size'=>adFB($sz)]; }
        echo json_encode(['ok'=>true,'orphans'=>$orphans]);exit;
    }
    if($act==='delOrphan'){
        $dir=basename(trim($_POST['dir']??'')); if(!$dir){echo json_encode(['error'=>'无效']);exit;}
        $reg=array_column($list,'code'); if(in_array($dir,$reg,true)){echo json_encode(['error'=>'已注册场次']);exit;}
        $p=DATA_DIR.$dir.'/'; if(!is_dir($p)){echo json_encode(['error'=>'不存在']);exit;}
        foreach(glob($p.'*.json')??[]as$f)unlink($f); @rmdir($p);
        echo json_encode(['ok'=>true]);exit;
    }

    // 完整性检查
    if($act==='integrity'){
        $res=[];
        foreach($list as $e){ $code=$e['code']??''; $dir=DATA_DIR.$code.'/'; $iss=[];
            if(!is_dir($dir))$iss[]='目录不存在'; else{ if(!is_writable($dir))$iss[]='目录不可写'; $fs=glob($dir.'*.json')??[]; $bad=0; foreach($fs as $f){if(json_decode(file_get_contents($f),true)===null)$bad++;} if($bad)$iss[]=$bad.'个JSON损坏'; if(!$fs)$iss[]='无备份文件'; }
            if($iss)$res[]=['code'=>$code,'note'=>$e['note']??'','issues'=>$iss];
        }
        echo json_encode(['ok'=>true,'total'=>count($list),'issues'=>count($res),'res'=>$res]);exit;
    }

    // 存储统计
    if($act==='storage'){
        $stats=[];$total=0;
        foreach($list as $e){ $code=$e['code']??''; $dir=DATA_DIR.$code.'/'; $fs=glob($dir.'*.json')??[]; $sz=array_sum(array_map('filesize',$fs)); $total+=$sz; $stats[]=['code'=>$code,'note'=>$e['note']??'','files'=>count($fs),'size'=>$sz,'str'=>adFB($sz)]; }
        usort($stats,fn($a,$b)=>$b['size']-$a['size']);
        echo json_encode(['ok'=>true,'total'=>adFB($total),'stats'=>$stats]);exit;
    }

    // 全局汇总
    if($act==='globalSt'){
        $t=0;$g=0;$s=0;$wd=0;
        foreach($list as $e){ if(($e['status']??'active')!=='active')continue; $st=adSt($e['code']??''); $t+=$st['tables'];$g+=$st['guests'];$s++;if($st['hasData'])$wd++; }
        echo json_encode(['ok'=>true,'sessions'=>$s,'withData'=>$wd,'tables'=>$t,'guests'=>$g]);exit;
    }

    // 登录记录
    if($act==='loginLog'){
        $log=file_exists(LOG_FILE)?(json_decode(file_get_contents(LOG_FILE),true)??[]):[];
        echo json_encode(['ok'=>true,'log'=>$log]);exit;
    }

    // 保存配置
    if($act==='saveCfg'){
        $c2=adRC();
        if(isset($_POST['admin_pass'])&&trim($_POST['admin_pass'])!=='')$c2['admin_pass']=mb_substr(trim($_POST['admin_pass']),0,50);
        if(isset($_POST['dash_pass']) &&trim($_POST['dash_pass']) !=='')$c2['dash_pass'] =mb_substr(trim($_POST['dash_pass']),0,50);
        if(isset($_POST['dash_pass2']))$c2['dash_pass2']=mb_substr(trim($_POST['dash_pass2']),0,50);
        if(isset($_POST['dash_pass3']))$c2['dash_pass3']=mb_substr(trim($_POST['dash_pass3']),0,50);
        if(isset($_POST['ys'])){$c2['yearStart']=max(2020,min(2099,intval($_POST['ys'])));$c2['yearEnd']=max($c2['yearStart'],min(2099,intval($_POST['ye']??$c2['yearEnd'])));}
        if(isset($_POST['as_on'])){
            $c2['autoSave']['globalEnabled']=($_POST['as_on']==='1');
            $c2['autoSave']['interval']=max(1,min(60,intval($_POST['as_iv']??10)));
            $c2['autoSave']['minInterval']=max(1,min(30,intval($_POST['as_mi']??2)));
            $c2['autoSave']['idleMinutes']=max(1,min(30,intval($_POST['as_idle']??3)));
            $c2['autoSave']['majorOpTrigger']=($_POST['as_maj']==='1');
        }
        adWC($c2); echo json_encode(['ok'=>true]);exit;
    }

    // 场次自动保存开关
    if($act==='sessAS'){
        $codes=$_POST['codes']??[$_POST['code']??'']; if(is_string($codes))$codes=[$codes];
        $en=($_POST['en']??'1')==='1'; $ok2=0;
        foreach($list as &$e){if(in_array($e['code']??'',$codes,true)){$e['autoSave']=$en;$ok2++;}}unset($e);
        if($ok2){adWA($list);echo json_encode(['ok'=>true,'n'=>$ok2]);}else echo json_encode(['error'=>'未找到']);exit;
    }

    // 登出
    if($act==='logout'){ $_SESSION['sc_admin']=false; session_destroy(); echo json_encode(['ok'=>true]); exit; }

    echo json_encode(['error'=>'unknown action']); exit;
}

// 普通 POST 登出
if($_SERVER['REQUEST_METHOD']==='POST'&&($_POST['action']??'')==='logout'){ $_SESSION['sc_admin']=false; session_destroy(); header('Location: admin.php'); exit; }

// ── 数据准备 ──────────────────────────────────────────────────────────────────
$cfg=adRC(); $authList=adRA();
$today=date('ymd');
$suggestCode=adGC($today,adNL($authList,$today)??'A');
$scheme=(isset($_SERVER['HTTPS'])&&$_SERVER['HTTPS']==='on')?'https':'http';
$selfDir=rtrim(str_replace('\\','/',dirname($_SERVER['PHP_SELF'])),'/');
$baseUrl=$scheme.'://'.$_SERVER['HTTP_HOST'].$selfDir.'/index.php';

// 统计
$totalAll=count($authList);
$totalAct=count(array_filter($authList,fn($e)=>($e['status']??'active')==='active'));

// 场次列表 JSON for JS（不预加载stats，悬浮时按需加载）
$sessionsJS=[];
foreach($authList as $e){
    $code=$e['code']??''; if(strlen($code)<9)continue;
    $sessionsJS[]=['code'=>$code,'status'=>$e['status']??'active','note'=>$e['note']??'','createdAt'=>$e['createdAt']??'','autoSave'=>$e['autoSave']??null];
}
usort($sessionsJS,fn($a,$b)=>strcmp(substr($b['code'],0,7),substr($a['code'],0,7)));

// ── 登录页 ────────────────────────────────────────────────────────────────────
function showLogin(){ ?>
<!DOCTYPE html><html lang="zh-CN"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>SeatCard 管理后台</title>
<link href="https://fonts.loli.net/css2?family=Noto+Sans+SC:wght@400;600;700&display=swap" rel="stylesheet">
<style>*{box-sizing:border-box;margin:0;padding:0}body{font-family:'Noto Sans SC',sans-serif;background:#0F0F0C;display:flex;align-items:center;justify-content:center;min-height:100vh}
.lc{background:#1A1A16;border:1px solid #3A3020;border-radius:14px;padding:40px 36px;width:320px;box-shadow:0 8px 32px rgba(0,0,0,.65);text-align:center}
h2{font-size:1rem;color:#D4AA3C;margin-bottom:4px;font-weight:700;letter-spacing:.12em;text-shadow:0 0 18px rgba(212,170,60,.4)}
.sub{font-size:.75rem;color:#4A4838;margin-bottom:22px}
input[type=password]{width:100%;padding:9px 12px;background:#111110;border:1.5px solid #2A2A22;border-radius:7px;font-size:.92rem;outline:none;font-family:inherit;color:#C0BCA8;transition:border .15s}
input[type=password]:focus{border-color:#D4AA3C}
button{margin-top:12px;width:100%;padding:10px;background:#D4AA3C;color:#0F0F0C;border:none;border-radius:7px;font-size:.88rem;cursor:pointer;font-family:inherit;font-weight:700;transition:background .15s}
button:hover{background:#A88A28}</style></head><body>
<div class="lc"><h2>◈ SEATCARD 管理后台</h2><p class="sub">超级密码</p>
<form method="POST"><input type="password" name="pass" placeholder="超级密码" autofocus><button>登录</button></form></div>
</body></html>
<?php }
?>
<!DOCTYPE html><html lang="zh-CN"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>SeatCard 管理后台 <?=ADMIN_VER?></title>
<link href="https://fonts.loli.net/css2?family=Noto+Sans+SC:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
:root{--bg:#0F0F0C;--bg2:#181814;--bg3:#222218;--gold:#D4AA3C;--gold2:#3A2E10;--text:#E4E0D4;--dim:#A8A498;--border:#303028;--red:#C84040;--green:#4A9A5A}
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Noto Sans SC',sans-serif;background:var(--bg);color:var(--text);min-height:100vh;display:flex;flex-direction:column}
/* Header */
header{background:#0A0A08;border-bottom:1px solid #2A2418;height:46px;display:flex;align-items:center;gap:10px;padding:0 16px;flex-shrink:0;position:sticky;top:0;z-index:200}
.hdr-title{font-size:.85rem;font-weight:700;letter-spacing:.12em;color:var(--gold);text-shadow:0 0 18px rgba(212,170,60,.4)}
.hdr-ver{font-size:.6rem;color:#5A5030;align-self:flex-end;padding-bottom:3px;margin-left:-6px}
.hdr-stat{font-size:.7rem;color:#9A9888}.hdr-stat b{color:#CEC8A8}
.hdr-r{margin-left:auto;display:flex;align-items:center;gap:5px}
.hbtn{background:#181610;border:1px solid #3A3220;color:#A09070;font-size:.73rem;cursor:pointer;font-family:inherit;padding:3px 9px;border-radius:4px;text-decoration:none;transition:all .15s;display:inline-flex;align-items:center;gap:4px;height:24px;line-height:1}
.hbtn:hover{color:#E8C860;border-color:#C8A84A;background:#221E0C}
.hbtn.danger:hover{color:#E05050;border-color:#803030}
/* Layout */
.main{flex:1;padding:12px 16px;max-width:1080px;margin:0 auto;width:100%}
/* Card */
.card{background:var(--bg2);border:1px solid var(--border);border-radius:10px;margin-bottom:10px;overflow:hidden}
.card-hdr{display:flex;align-items:center;gap:8px;padding:9px 14px;cursor:pointer;user-select:none;transition:background .12s;border-bottom:1px solid transparent}
.card-hdr:hover{background:var(--bg3)}
.card-hdr.open{border-bottom-color:var(--border)}
.card-title{font-size:.82rem;font-weight:600;color:var(--text);flex:1}
.card-badge{font-size:.65rem;background:var(--gold2);color:var(--gold);padding:1px 6px;border-radius:10px}
.card-toggle{color:#6A6858;font-size:.7rem;transition:transform .2s}
.card-hdr.open .card-toggle{transform:rotate(90deg)}
.card-body{display:none;padding:12px 14px}
.card-hdr.open+.card-body{display:block}
/* Form elements */
label.fl{font-size:.72rem;color:#7A7060;display:block;margin-bottom:3px}
input[type=text],input[type=number],input[type=password],select,textarea{background:#111110;border:1.5px solid #2A2418;border-radius:5px;color:var(--text);font-family:inherit;font-size:.82rem;padding:5px 8px;outline:none;transition:border .15s;width:100%;color-scheme:dark}
input:focus,select:focus,textarea:focus{border-color:var(--gold)}
input[type=number]{-moz-appearance:textfield}
input[type=checkbox]{accent-color:var(--gold);width:13px;height:13px;cursor:pointer}
/* Buttons */
.btn{padding:4px 12px;border:none;border-radius:5px;font-size:.76rem;cursor:pointer;font-weight:600;font-family:inherit;white-space:nowrap;transition:all .15s;display:inline-flex;align-items:center;gap:4px}
.btn-g{background:var(--gold);color:#0F0F0C}.btn-g:hover{background:#A88A28}
.btn-s{background:#1E1E1A;border:1px solid #3A3828;color:#A09080}.btn-s:hover{border-color:#6A6458;color:var(--text)}
.btn-r{background:#501010;color:#E05050;border:1px solid #601818}.btn-r:hover{background:#601818}
.btn-b{background:#103050;color:#80B8E8;border:1px solid #184060}.btn-b:hover{background:#184060}
.btn:disabled{opacity:.4;pointer-events:none}
/* Grid helpers */
.row{display:flex;gap:8px;align-items:flex-end;flex-wrap:wrap}
.row>*{flex:1;min-width:80px}.row>.w-auto{flex:0 0 auto}
/* Code badge */
.code{font-family:monospace;background:#1A1C14;color:#E8E0B0;padding:2px 7px;border-radius:4px;font-size:.85rem;font-weight:700;letter-spacing:.5px}
.code.active{color:#D4AA3C}.code.archived{color:#C06060}.code.hidden{color:#6A8A6A}
/* Status badge */
.sbadge{font-size:.62rem;padding:1px 6px;border-radius:8px;font-weight:600}
.sbadge.active{background:#3A2E10;color:#C8A030}
.sbadge.archived{background:#301010;color:#C06060}
.sbadge.hidden{background:#0E1E12;color:#5A8A5A}
/* Table */
table.dt{width:100%;border-collapse:collapse;font-size:.78rem}
table.dt th{text-align:left;padding:5px 8px;background:#141412;color:#6A6858;font-weight:600;border-bottom:1px solid #2A2418;white-space:nowrap}
table.dt td{padding:5px 8px;border-bottom:1px solid #1E1C14;vertical-align:middle}
table.dt tr:last-child td{border-bottom:none}
table.dt tr:hover td{background:#181612}
table.dt td.mono{font-family:monospace}
/* Preview box */
.preview{background:#0A0A08;border:1px solid #2A2418;border-radius:5px;padding:8px 10px;font-family:monospace;font-size:.76rem;color:#A8A490;max-height:160px;overflow-y:auto;line-height:1.7;white-space:pre-wrap}
.preview .p-ok{color:#6AA870}.preview .p-err{color:#C06050}.preview .p-arrow{color:#505838}
/* Batch bar */
.bbar{display:none;position:fixed;bottom:0;left:0;right:0;background:#0A0A08;border-top:2px solid #2A2418;padding:8px 16px;z-index:300;align-items:center;gap:8px;flex-wrap:wrap}
.bbar.show{display:flex}
.bbar-info{font-size:.76rem;color:#8A8878}
.bbar-info b{color:var(--gold)}
.bbar-sep{width:1px;height:18px;background:#2A2418;flex-shrink:0}
.bbar-r{margin-left:auto;display:flex;gap:6px;align-items:center}
/* Toast */
.toast-wrap{position:fixed;top:54px;right:14px;z-index:1000;display:flex;flex-direction:column;gap:6px;pointer-events:none}
.toast{background:#1E1C14;border:1px solid #3A3820;border-radius:6px;padding:7px 14px;font-size:.76rem;color:var(--text);box-shadow:0 4px 16px rgba(0,0,0,.5);animation:tIn .2s;opacity:0;transition:opacity .3s}
.toast.show{opacity:1}.toast.ok{border-color:#3A6020;color:#8ACA70}.toast.err{border-color:#601010;color:#E06060}
@keyframes tIn{from{transform:translateX(20px);opacity:0}to{transform:translateX(0);opacity:1}}
/* Modal */
.ov{display:none;position:fixed;inset:0;background:rgba(0,0,0,.82);z-index:400;align-items:center;justify-content:center}
.ov.show{display:flex}
.mbox{background:#1A1A16;border:1px solid #3A3020;border-radius:12px;padding:22px 24px;width:340px;max-width:95vw;font-size:.82rem}
.mtitle{font-size:.9rem;font-weight:700;color:var(--gold);margin-bottom:4px}
.mdesc{color:#5A5848;font-size:.73rem;margin-bottom:13px;line-height:1.6}
.mbtns{margin-top:14px;display:flex;gap:8px;justify-content:flex-end}
/* Seg control */
.seg{display:flex;gap:0;border:1px solid #3A3828;border-radius:5px;overflow:hidden}
.seg-btn{flex:1;padding:4px 8px;background:transparent;border:none;color:#6A6858;font-family:inherit;font-size:.72rem;cursor:pointer;transition:all .12s}
.seg-btn.on{background:var(--gold);color:#0F0F0C;font-weight:700}
.seg-btn:hover:not(.on){background:var(--bg3);color:var(--text)}
/* Toggle */
.tog{display:inline-flex;align-items:center;gap:6px;cursor:pointer;font-size:.78rem;color:var(--dim)}
.tog-track{width:34px;height:18px;background:#2A2820;border-radius:9px;position:relative;transition:background .2s;flex-shrink:0}
.tog-track.on{background:#6A5A18}
.tog-thumb{width:14px;height:14px;background:#5A5848;border-radius:50%;position:absolute;top:2px;left:2px;transition:all .2s}
.tog-track.on .tog-thumb{background:var(--gold);left:18px}
/* Misc */
.dim{color:var(--dim)}.gold{color:var(--gold)}.red{color:var(--red)}.mono{font-family:monospace}
.gap{height:8px}
.section-title{font-size:.7rem;font-weight:700;color:#5A5848;text-transform:uppercase;letter-spacing:.1em;margin-bottom:7px}
.hint{font-size:.7rem;color:#4A4838;margin-top:4px;line-height:1.6}
.ellip{white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
</style>
</head><body>

<header>
  <span class="hdr-title">SEATCARD</span>
  <span class="hdr-ver"><?=ADMIN_VER?></span>
  <span class="hdr-stat">共 <b><?=$totalAll?></b> 场 &nbsp;·&nbsp; 有效 <b><?=$totalAct?></b></span>
  <div class="hdr-r">
    <a href="dashboard.php" class="hbtn">📊 看板</a>
    <a href="index.php" class="hbtn">🏠 主页</a>
    <button class="hbtn" onclick="openModal('pwModal')">⚙ 密码</button>
    <button class="hbtn danger" onclick="doLogout()">退出</button>
  </div>
</header>

<div class="main">
<div class="toast-wrap" id="toastWrap"></div>

<!-- ─── 卡片 A: 即时授权码 ─────────────────────────────────────────── -->
<div class="card">
  <div class="card-hdr open" onclick="toggleCard(this)">
    <span class="card-title">◈ 即时授权码生成器</span>
    <span class="card-toggle">▶</span>
  </div>
  <div class="card-body">
    <div class="row">
      <div style="max-width:120px">
        <label class="fl">日期 YYMMDD</label>
        <input type="text" id="calcD6" maxlength="6" value="<?=htmlspecialchars($today)?>" placeholder="260419" oninput="calcLive()">
      </div>
      <div style="max-width:80px">
        <label class="fl">场次字母</label>
        <input type="text" id="calcLtr" maxlength="2" value="A" oninput="calcLive()" style="text-transform:uppercase">
      </div>
      <div style="flex:2;min-width:160px">
        <label class="fl">生成的授权码</label>
        <div style="display:flex;gap:6px;align-items:center">
          <span id="calcResult" class="code active" style="font-size:1.05rem;padding:5px 12px;min-width:120px">──────</span>
          <button class="btn btn-s" onclick="copyCalc()" title="复制">📋</button>
          <button class="btn btn-g" id="calcWriteBtn" onclick="writeCalc()" disabled>写入</button>
        </div>
      </div>
      <div style="max-width:200px">
        <label class="fl">宴会名称（可选）</label>
        <input type="text" id="calcNote" placeholder="如：张三&李四婚礼" maxlength="30">
      </div>
    </div>
    <div id="calcInfo" class="hint" style="margin-top:6px"></div>
    <div class="gap"></div>
    <div class="row" style="align-items:flex-start">
      <div>
        <div class="section-title">只读链接</div>
        <div id="roLink" class="mono" style="font-size:.73rem;color:#6A6858;word-break:break-all">—</div>
      </div>
      <div style="flex:0 0 auto">
        <div class="section-title">验证任意授权码</div>
        <div style="display:flex;gap:6px">
          <input type="text" id="validateCode" placeholder="输入授权码" maxlength="10" style="width:130px;text-transform:uppercase">
          <button class="btn btn-s" onclick="doValidate()">验证</button>
        </div>
        <div id="validateResult" class="hint" style="margin-top:4px"></div>
      </div>
    </div>
  </div>
</div>

<!-- ─── 卡片 B: 场次管理 ──────────────────────────────────────────── -->
<div class="card">
  <div class="card-hdr open" onclick="toggleCard(this)">
    <span class="card-title">◈ 场次管理</span>
    <span id="sessCount" class="card-badge"><?=count($sessionsJS)?> 场次</span>
    <span class="card-toggle">▶</span>
  </div>
  <div class="card-body">

    <!-- 批量生成 -->
    <div class="section-title">批量生成</div>
    <div class="row">
      <div style="max-width:110px"><label class="fl">起始日期 YYMMDD</label><input type="text" id="bgD6" maxlength="6" value="<?=htmlspecialchars($today)?>" placeholder="260419"></div>
      <div style="max-width:70px"><label class="fl">场次数量</label><input type="number" id="bgCnt" value="1" min="1" max="26"></div>
      <div style="max-width:60px"><label class="fl">起始字母</label><input type="text" id="bgStartL" value="A" maxlength="1" style="text-transform:uppercase"></div>
      <div><label class="fl">宴会名称前缀</label><input type="text" id="bgNote" placeholder="如：张三婚礼" maxlength="20"></div>
      <div class="w-auto" style="display:flex;gap:6px">
        <button class="btn btn-s" onclick="batchGenPreview()">预览</button>
        <button class="btn btn-g" id="bgExecBtn" onclick="batchGenExec()" disabled>确认写入</button>
      </div>
    </div>
    <div id="bgPreview" class="preview" style="margin-top:8px;display:none"></div>
    <div class="gap"></div>

    <!-- 筛选栏 -->
    <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:8px">
      <div class="seg" id="statusSeg">
        <button class="seg-btn on" data-s="all" onclick="setFilter(this,'all')">全部</button>
        <button class="seg-btn" data-s="active" onclick="setFilter(this,'active')">有效</button>
        <button class="seg-btn" data-s="archived" onclick="setFilter(this,'archived')">存档</button>
        <button class="seg-btn" data-s="hidden" onclick="setFilter(this,'hidden')">隐藏</button>
      </div>
      <input type="text" id="sessSearch" placeholder="搜索…" style="width:130px" oninput="renderSessions()">
      <select id="sessYear" onchange="renderSessions()" style="width:80px">
        <option value="">全部年份</option>
        <?php for($y=$cfg['yearStart'];$y<=$cfg['yearEnd'];$y++){$yy=sprintf('%02d',$y-2000);echo"<option value=\"$yy\">$y</option>";} ?>
      </select>
      <div style="margin-left:auto;display:flex;gap:6px">
        <button class="btn btn-s" onclick="selectAllVisible()">全选</button>
        <button class="btn btn-s" onclick="clearSel()">清选</button>
      </div>
    </div>

    <!-- 场次表格 -->
    <table class="dt" id="sessTable">
      <thead><tr>
        <th style="width:28px"></th>
        <th>授权码</th>
        <th>状态</th>
        <th>日期·场</th>
        <th>宴会名称</th>
        <th>存档数</th>
        <th>最后编辑</th>
        <th>操作</th>
      </tr></thead>
      <tbody id="sessTbody"></tbody>
    </table>

    <!-- 分页 -->
    <div id="sessPager" style="display:flex;align-items:center;gap:6px;margin-top:10px;font-size:.75rem;color:var(--dim)"></div>
  </div>
</div>

<!-- ─── 卡片 C: 导入/导出 ──────────────────────────────────────────── -->
<div class="card">
  <div class="card-hdr" onclick="toggleCard(this)">
    <span class="card-title">◈ 导入 / 导出</span>
    <span class="card-toggle">▶</span>
  </div>
  <div class="card-body">
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
      <div>
        <div class="section-title">导出 ZIP（选中场次）</div>
        <p class="hint">在场次列表中勾选场次，然后点击下方按钮打包下载所有 JSON 备份文件。</p>
        <div style="margin-top:8px"><button class="btn btn-g" onclick="doExportZip()">⬇ 导出选中场次</button></div>
        <div id="exportInfo" class="hint" style="margin-top:6px"></div>
      </div>
      <div>
        <div class="section-title">导入 JSON 到场次</div>
        <div class="row">
          <div><label class="fl">目标场次授权码</label><input type="text" id="importCode" placeholder="260419AN3" maxlength="10" style="text-transform:uppercase"></div>
        </div>
        <div style="margin-top:7px;display:flex;gap:6px;align-items:center">
          <input type="file" id="importFile" accept=".json" style="display:none" onchange="doImport()">
          <button class="btn btn-g" onclick="document.getElementById('importFile').click()">📂 选择 JSON 文件</button>
        </div>
        <div id="importInfo" class="hint" style="margin-top:6px"></div>
      </div>
    </div>
  </div>
</div>

<!-- ─── 卡片 D: 系统维护 ──────────────────────────────────────────── -->
<div class="card">
  <div class="card-hdr" onclick="toggleCard(this)">
    <span class="card-title">◈ 系统维护</span>
    <span class="card-toggle">▶</span>
  </div>
  <div class="card-body">
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
      <!-- 孤立目录 -->
      <div>
        <div class="section-title">孤立目录清理</div>
        <p class="hint">扫描 data/ 下存在但 auth.json 无记录的目录。</p>
        <button class="btn btn-s" style="margin-top:7px" onclick="doOrphanScan()">🔍 扫描</button>
        <div id="orphanResult" style="margin-top:8px"></div>
      </div>
      <!-- 完整性检查 -->
      <div>
        <div class="section-title">数据完整性检查</div>
        <p class="hint">验证所有场次的 JSON 文件是否可解析、目录权限是否正常。</p>
        <button class="btn btn-s" style="margin-top:7px" onclick="doIntegrity()">🔍 检查</button>
        <div id="integrityResult" style="margin-top:8px"></div>
      </div>
      <!-- 存储统计 -->
      <div>
        <div class="section-title">存储用量统计</div>
        <p class="hint">统计各场次磁盘占用。</p>
        <button class="btn btn-s" style="margin-top:7px" onclick="doStorage()">📊 统计</button>
        <div id="storageResult" style="margin-top:8px"></div>
      </div>
      <!-- 跨场次汇总 -->
      <div>
        <div class="section-title">跨场次汇总</div>
        <p class="hint">统计所有有效场次的总桌数、总宾客数。</p>
        <button class="btn btn-s" style="margin-top:7px" onclick="doGlobalSt()">📊 汇总</button>
        <div id="globalStResult" style="margin-top:8px"></div>
      </div>
    </div>
  </div>
</div>

<!-- ─── 卡片 E: 配置 & 日志 ───────────────────────────────────────── -->
<div class="card">
  <div class="card-hdr" onclick="toggleCard(this)">
    <span class="card-title">◈ 系统配置</span>
    <span class="card-toggle">▶</span>
  </div>
  <div class="card-body">
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
      <!-- 年份范围 -->
      <div>
        <div class="section-title">年份范围</div>
        <div class="row">
          <div><label class="fl">起始年</label><input type="number" id="cfgYS" value="<?=$cfg['yearStart']?>" min="2020" max="2099"></div>
          <div><label class="fl">结束年</label><input type="number" id="cfgYE" value="<?=$cfg['yearEnd']?>" min="2020" max="2099"></div>
          <div class="w-auto"><label class="fl">&nbsp;</label><button class="btn btn-g" onclick="saveYears()">保存</button></div>
        </div>
        <div class="hint" style="margin-top:4px">当前：<?=$cfg['yearStart']?> — <?=$cfg['yearEnd']?></div>
      </div>
      <!-- 授权码说明 -->
      <div>
        <div class="section-title">授权码格式</div>
        <div class="hint" style="line-height:2">
          格式：<span class="mono gold">YYMMDDXcc</span>（9位）&nbsp; 扩展：<span class="mono">YYMMDDaXcc</span>（10位）<br>
          校验：CRC32→无符号→28字符表双映射<br>
          只读码：去掉末尾2位校验位<br>
          字符表：<span class="mono" style="font-size:.68rem">346789ACDEFGHJKLMNPQRSTUVWXY</span>
        </div>
      </div>
      <!-- 登录记录 -->
      <div style="grid-column:1/-1">
        <div class="section-title" style="display:flex;align-items:center;gap:8px">
          登录记录（最近30次）
          <button class="btn btn-s" style="font-size:.65rem;padding:2px 8px" onclick="loadLoginLog()">刷新</button>
        </div>
        <table class="dt" id="loginLogTable" style="display:none">
          <thead><tr><th>时间</th><th>IP</th><th>User-Agent</th></tr></thead>
          <tbody id="loginLogBody"></tbody>
        </table>
        <p id="loginLogHint" class="hint">点击「刷新」加载</p>
      </div>
    </div>
  </div>
</div>

<!-- ─── 卡片 F: 自动保存策略 ──────────────────────────────────────── -->
<div class="card">
  <div class="card-hdr" onclick="toggleCard(this)">
    <span class="card-title">◈ 自动保存策略</span>
    <span class="card-toggle">▶</span>
  </div>
  <div class="card-body">
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:14px">
      <!-- 全局设置 -->
      <div>
        <div class="section-title">全局设置</div>
        <label class="tog" onclick="togClick('asGlobal')" style="margin-bottom:10px">
          <div class="tog-track<?=($cfg['autoSave']['globalEnabled']??true)?' on':''?>" id="asGlobal_track"><div class="tog-thumb"></div></div>
          全局自动保存
        </label><input type="hidden" id="asGlobal" value="<?=($cfg['autoSave']['globalEnabled']??true)?'1':'0'?>">
        <div class="row">
          <div><label class="fl">定时保存间隔（分钟）</label><input type="number" id="asInterval" value="<?=$cfg['autoSave']['interval']??10?>" min="1" max="60"></div>
          <div><label class="fl">最短保存间隔（分钟）</label><input type="number" id="asMinIv" value="<?=$cfg['autoSave']['minInterval']??2?>" min="1" max="30"></div>
        </div>
        <div class="row" style="margin-top:8px">
          <div><label class="fl">空闲触发（分钟无操作）</label><input type="number" id="asIdle" value="<?=$cfg['autoSave']['idleMinutes']??3?>" min="1" max="30"></div>
        </div>
        <label class="tog" onclick="togClick('asMajOp')" style="margin-top:10px">
          <div class="tog-track<?=($cfg['autoSave']['majorOpTrigger']??true)?' on':''?>" id="asMajOp_track"><div class="tog-thumb"></div></div>
          大操作后触发保存
        </label><input type="hidden" id="asMajOp" value="<?=($cfg['autoSave']['majorOpTrigger']??true)?'1':'0'?>">
        <div class="hint" style="margin-top:8px">
          大操作包括：落座/取消落座、批量落座、导入名单/CSV、新增/删除宾客、新增/删除桌子、桌子大幅移动
        </div>
        <button class="btn btn-g" style="margin-top:12px" onclick="saveAutoSaveCfg()">保存全局设置</button>
      </div>
      <!-- 场次级别 -->
      <div>
        <div class="section-title">单场次自动保存开关</div>
        <div class="hint" style="margin-bottom:8px">覆盖全局设置。选中场次后操作，或在下方列表单独设置。</div>
        <div style="display:flex;gap:6px;margin-bottom:8px">
          <button class="btn btn-s" onclick="batchSessAS(true)">✅ 开启选中</button>
          <button class="btn btn-s" onclick="batchSessAS(false)">⛔ 关闭选中</button>
        </div>
        <table class="dt" id="asTable" style="max-height:280px;display:block;overflow-y:auto">
          <thead><tr><th>授权码</th><th>名称</th><th>自动保存</th></tr></thead>
          <tbody id="asTbody"></tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<div style="height:60px"></div>
</div><!-- /main -->

<!-- ─── 批量操作栏 ─────────────────────────────────────────────────── -->
<div class="bbar" id="bbar">
  <span class="bbar-info">已选 <b id="bbarCount">0</b> 个场次</span>
  <div class="bbar-sep"></div>
  <button class="btn btn-s" onclick="batchOp('archive')">存档</button>
  <button class="btn btn-s" onclick="batchOp('restore')">恢复有效</button>
  <button class="btn btn-s" onclick="batchOp('hide')">隐藏</button>
  <button class="btn btn-b" onclick="openOpModal('move')">移动…</button>
  <button class="btn btn-b" onclick="openOpModal('copy')">复制…</button>
  <button class="btn btn-r" onclick="batchOp('del')">删除</button>
  <div class="bbar-r">
    <button class="btn btn-s" onclick="clearSel()">取消选择</button>
  </div>
</div>

<!-- ─── 密码弹窗 ─────────────────────────────────────────────────────── -->
<div class="ov" id="pwModal">
  <div class="mbox">
    <div class="mtitle">⚙ 密码设置</div>
    <div class="mdesc">写入 data/sc_config.json，不影响代码文件。留空则不修改。</div>
    <label class="fl">超级密码（admin.php）</label>
    <input type="password" id="pw_admin" placeholder="留空不修改" style="margin-bottom:8px">
    <label class="fl">看板主密码（dashboard.php）</label>
    <input type="password" id="pw_dash" placeholder="留空不修改" style="margin-bottom:8px">
    <label class="fl">看板辅助密码 1</label>
    <input type="password" id="pw_dash2" placeholder="留空不修改" style="margin-bottom:8px">
    <label class="fl">看板辅助密码 2</label>
    <input type="password" id="pw_dash3" placeholder="留空不修改" style="margin-bottom:8px">
    <div class="hint">辅助密码可访问完整看板，但无法进入管理后台。</div>
    <div class="mbtns">
      <button class="btn btn-s" onclick="closeModal('pwModal')">取消</button>
      <button class="btn btn-g" onclick="savePw()">保存</button>
    </div>
  </div>
</div>

<!-- ─── 移动/复制弹窗 ──────────────────────────────────────────────── -->
<div class="ov" id="opModal">
  <div class="mbox">
    <div class="mtitle" id="opModalTitle">移动场次</div>
    <div class="mdesc" id="opModalDesc"></div>
    <label class="fl">目标日期 YYMMDD</label>
    <input type="text" id="opTargetDate" maxlength="6" placeholder="如 260512">
    <div class="preview" id="opPreview" style="margin-top:10px;display:none"></div>
    <div class="mbtns">
      <button class="btn btn-s" onclick="closeModal('opModal')">取消</button>
      <button class="btn btn-s" onclick="doOpPreview()">预览</button>
      <button class="btn btn-g" id="opExecBtn" onclick="doOpExec()" disabled>执行</button>
    </div>
  </div>
</div>

<!-- ─── 重命名弹窗 ────────────────────────────────────────────────── -->
<div class="ov" id="renameModal">
  <div class="mbox" style="width:300px">
    <div class="mtitle">编辑宴会名称</div>
    <input type="hidden" id="renameCode">
    <input type="text" id="renameName" maxlength="30" placeholder="宴会名称" style="margin-top:10px">
    <div class="mbtns">
      <button class="btn btn-s" onclick="closeModal('renameModal')">取消</button>
      <button class="btn btn-g" onclick="doRename()">保存</button>
    </div>
  </div>
</div>

<!-- ─── 场次详情悬浮提示 ───────────────────────────────────────────── -->
<div id="statTip" style="display:none;position:fixed;z-index:500;background:#1A1A16;border:1px solid #3A3020;border-radius:8px;padding:10px 14px;font-size:.73rem;color:var(--text);pointer-events:none;min-width:160px;box-shadow:0 4px 20px rgba(0,0,0,.6)"></div>

<script>
const API='admin.php?api=1';
const ALPHA='346789ACDEFGHJKLMNPQRSTUVWXY';

// ── CRC32 (matches PHP crc32) ──────────────────────────────────────────────
const CRC_TABLE=(()=>{const t=new Uint32Array(256);for(let i=0;i<256;i++){let c=i;for(let j=0;j<8;j++)c=(c&1)?0xEDB88320^(c>>>1):(c>>>1);t[i]=c;}return t;})();
function crc32(s){let crc=0xFFFFFFFF;for(let i=0;i<s.length;i++)crc=CRC_TABLE[(crc^s.charCodeAt(i))&0xFF]^(crc>>>8);return(crc^0xFFFFFFFF)>>>0;}
function calcCode(d6,ltr){const h=crc32(d6+ltr);return d6+ltr+ALPHA[Math.floor(h/28)%28]+ALPHA[h%28];}

// ── Sessions data ────────────────────────────────────────────────────────
let SESSIONS = <?=json_encode($sessionsJS,JSON_UNESCAPED_UNICODE)?>;
let selCodes = new Set();
let filterStatus='all', filterYear='', searchQ='', curPage=1;
const PER_PAGE=20;
let pendingOp=null; // 'move' | 'copy'
let statTipTimer=null;

// ── Toast ────────────────────────────────────────────────────────────────
function toast(msg,type='ok',dur=2500){
  const w=document.getElementById('toastWrap');
  const d=document.createElement('div');
  d.className=`toast ${type}`;d.textContent=msg;w.appendChild(d);
  setTimeout(()=>d.classList.add('show'),10);
  setTimeout(()=>{d.classList.remove('show');setTimeout(()=>d.remove(),300);},dur);
}

// ── API helper ───────────────────────────────────────────────────────────
async function api(data,isForm=false){
  const fd=new FormData();
  if(!isForm){Object.entries(data).forEach(([k,v])=>{if(Array.isArray(v))v.forEach(i=>fd.append(k+'[]',i));else fd.append(k,v);})}
  return fetch(API,{method:'POST',body:isForm?data:fd}).then(r=>r.json()).catch(()=>({error:'网络错误'}));
}

// ── Card toggle ──────────────────────────────────────────────────────────
function toggleCard(hdr){hdr.classList.toggle('open');}

// ── Modal ────────────────────────────────────────────────────────────────
function openModal(id){document.getElementById(id).classList.add('show');}
function closeModal(id){document.getElementById(id).classList.remove('show');}
document.querySelectorAll('.ov').forEach(ov=>ov.addEventListener('click',e=>{if(e.target===ov)ov.classList.remove('show');}));

// ── Toggle helper ────────────────────────────────────────────────────────
function togClick(id){
  const el=document.getElementById(id), tr=document.getElementById(id+'_track');
  const v=el.value==='1'?'0':'1'; el.value=v;
  tr.className='tog-track'+(v==='1'?' on':'');
}

// ── Instant code calculator ──────────────────────────────────────────────
function calcLive(){
  const d6=document.getElementById('calcD6').value.trim();
  const ltr=document.getElementById('calcLtr').value.trim().toUpperCase();
  const resEl=document.getElementById('calcResult');
  const info=document.getElementById('calcInfo');
  const roEl=document.getElementById('roLink');
  const writeBtn=document.getElementById('calcWriteBtn');
  if(d6.length!==6||!/\d{6}/.test(d6)||!ltr.match(/^([A-Z]|[a-z][A-Z])$/i)){
    resEl.textContent='──────';resEl.className='code';info.textContent='';roEl.textContent='—';writeBtn.disabled=true;return;
  }
  const code=calcCode(d6,ltr.length===1?ltr.toUpperCase():ltr);
  const existing=SESSIONS.find(s=>s.code===code);
  resEl.textContent=code;
  resEl.className='code '+(existing?existing.status:'active');
  const ro=code.slice(0,-2);
  roEl.innerHTML=`只读链接：<span style="color:#6A6858"><?=htmlspecialchars($baseUrl)?>?auth=<b style="color:var(--dim)">${ro}</b></span>`;
  if(existing){info.innerHTML=`<span class="gold">⚠ 已存在</span> · 状态：${existing.status} · 名称：${existing.note||'—'}`;writeBtn.disabled=true;}
  else{info.innerHTML=`<span style="color:var(--green)">✓ 可用</span>`;writeBtn.disabled=false;}
}
function copyCalc(){
  const code=document.getElementById('calcResult').textContent;
  if(code.includes('─'))return;
  navigator.clipboard.writeText(code).then(()=>toast('已复制：'+code));
}
async function writeCalc(){
  const d6=document.getElementById('calcD6').value.trim();
  const ltr=document.getElementById('calcLtr').value.trim().toUpperCase();
  const note=document.getElementById('calcNote').value.trim();
  const r=await api({action:'gen',d6,ltr,note});
  if(r.ok){toast('已写入：'+r.code);reloadSessions();}else toast(r.error,'err');
}
async function doValidate(){
  const code=document.getElementById('validateCode').value.trim().toUpperCase();
  const r=await api({action:'validate',code});
  const el=document.getElementById('validateResult');
  if(!r.valid){el.innerHTML='<span class="red">❌ 格式无效</span>';return;}
  if(r.reg){el.innerHTML=`<span class="gold">✓ 已注册</span> · 状态：${r.status}`;}
  else{el.innerHTML='<span style="color:var(--green)">✓ 格式合法</span> · <span class="dim">未注册</span>';}
  el.innerHTML+=` · 只读码：<span class="mono dim">${r.ro}</span>`;
}

// ── Sessions render ──────────────────────────────────────────────────────
function getFiltered(){
  const q=searchQ.toLowerCase();
  return SESSIONS.filter(s=>{
    if(filterStatus!=='all'&&s.status!==filterStatus)return false;
    if(filterYear&&!s.code.startsWith(filterYear))return false;
    if(q&&!s.code.toLowerCase().includes(q)&&!s.note.toLowerCase().includes(q))return false;
    return true;
  });
}

function setFilter(btn,st){
  document.querySelectorAll('#statusSeg .seg-btn').forEach(b=>b.classList.remove('on'));
  btn.classList.add('on'); filterStatus=st; curPage=1; renderSessions();
}

function renderSessions(){
  searchQ=document.getElementById('sessSearch').value;
  filterYear=document.getElementById('sessYear').value;
  const filtered=getFiltered();
  const total=filtered.length;
  const pages=Math.max(1,Math.ceil(total/PER_PAGE));
  curPage=Math.min(curPage,pages);
  const items=filtered.slice((curPage-1)*PER_PAGE,curPage*PER_PAGE);

  const tb=document.getElementById('sessTbody');
  tb.innerHTML=items.map(s=>{
    const d6=s.code.substring(0,6);
    const yy=d6.substring(0,2),mm=d6.substring(2,4),dd=d6.substring(4,6);
    const dateStr=`20${yy}-${mm}-${dd}`;
    const sel=selCodes.has(s.code);
    return `<tr data-code="${s.code}">
      <td><input type="checkbox" class="sess-cb" data-code="${s.code}" ${sel?'checked':''} onchange="toggleSel(this)"></td>
      <td><span class="code ${s.status}" style="cursor:pointer"
        onmouseenter="showStatTip(event,'${s.code}')" onmouseleave="hideStatTip()"
        onclick="copyCode('${s.code}')">${s.code}</span></td>
      <td><span class="sbadge ${s.status}">${statusLabel(s.status)}</span></td>
      <td class="mono dim" style="font-size:.75rem">${dateStr}</td>
      <td class="ellip" style="max-width:140px;color:#C0B880" title="${escH(s.note)}">${escH(s.note)||'<span class="dim" style="font-style:italic">未命名</span>'}</td>
      <td id="fc_${s.code}" class="dim">—</td>
      <td id="et_${s.code}" class="dim">—</td>
      <td style="display:flex;gap:4px">
        <a href="<?=htmlspecialchars($baseUrl)?>?auth=${s.code}" target="_blank" class="btn btn-s" style="font-size:.68rem;padding:2px 7px">进入</a>
        <button class="btn btn-s" style="font-size:.68rem;padding:2px 7px" onclick="openRename('${s.code}','${escH(s.note)}')">改名</button>
      </td>
    </tr>`;
  }).join('');

  // Pager
  const pg=document.getElementById('sessPager');
  pg.innerHTML=total?`第 ${curPage}/${pages} 页 · 共 ${total} 场次 &nbsp;
    <button class="btn btn-s" style="padding:2px 8px" onclick="goPage(${curPage-1})" ${curPage<=1?'disabled':''}>‹</button>
    <button class="btn btn-s" style="padding:2px 8px" onclick="goPage(${curPage+1})" ${curPage>=pages?'disabled':''}>›</button>`:'暂无场次';

  // Load file counts async for visible rows
  items.forEach(s=>loadStatLight(s.code));

  // AS table
  renderASTable();
}

function goPage(p){curPage=p;renderSessions();}
function statusLabel(s){return s==='active'?'有效':s==='archived'?'存档':s==='hidden'?'隐藏':s;}
function escH(s){if(!s)return'';return s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');}
function copyCode(c){navigator.clipboard.writeText(c).then(()=>toast('已复制 '+c));}

// Load file count (light stat)
const statCache={};
async function loadStatLight(code){
  if(statCache[code])return applyStatLight(code,statCache[code]);
  const r=await api({action:'stat',code});
  if(r.ok){statCache[code]=r.st;applyStatLight(code,r.st);}
}
function applyStatLight(code,st){
  const fc=document.getElementById('fc_'+code);
  const et=document.getElementById('et_'+code);
  if(fc)fc.textContent=st.files||0;
  if(et)et.textContent=st.editTs?relTime(st.editTs):'—';
}
function relTime(ts){const d=Math.floor(Date.now()/1000)-ts;if(d<60)return'刚刚';if(d<3600)return Math.floor(d/60)+'分前';if(d<86400)return Math.floor(d/3600)+'时前';if(d<86400*30)return Math.floor(d/86400)+'天前';return new Date(ts*1000).toLocaleDateString('zh-CN',{month:'numeric',day:'numeric'});}

// Hover tooltip
function showStatTip(e,code){
  clearTimeout(statTipTimer);
  statTipTimer=setTimeout(async()=>{
    let st=statCache[code];
    if(!st){const r=await api({action:'stat',code});if(r.ok){st=r.st;statCache[code]=st;}}
    if(!st)return;
    const tip=document.getElementById('statTip');
    tip.innerHTML=`<div style="color:var(--gold);font-weight:600;margin-bottom:4px">${code}</div>
      桌位：<b>${st.tables}</b> &nbsp; 宾客：<b>${st.guests}</b><br>
      存档：<b>${st.files}</b> 个 &nbsp; 占用：<b>${st.sizeStr||'—'}</b><br>
      最后编辑：<b>${st.editTs?relTime(st.editTs):'—'}</b><br>
      ${st.name?`方案名：<span style="color:#C0B880">${escH(st.name)}</span>`:''}`;
    tip.style.display='block';
    tip.style.left=Math.min(e.pageX+12,window.innerWidth-180)+'px';
    tip.style.top=(e.pageY-10)+'px';
  },300);
}
function hideStatTip(){clearTimeout(statTipTimer);document.getElementById('statTip').style.display='none';}

// ── Selection ────────────────────────────────────────────────────────────
function toggleSel(cb){
  const code=cb.dataset.code;
  if(cb.checked)selCodes.add(code); else selCodes.delete(code);
  updateBbar();
}
function selectAllVisible(){getFiltered().slice((curPage-1)*PER_PAGE,curPage*PER_PAGE).forEach(s=>selCodes.add(s.code));renderSessions();updateBbar();}
function clearSel(){selCodes.clear();renderSessions();updateBbar();}
function updateBbar(){
  const n=selCodes.size;
  document.getElementById('bbar').classList.toggle('show',n>0);
  document.getElementById('bbarCount').textContent=n;
}

// ── Batch operations ─────────────────────────────────────────────────────
async function batchOp(op){
  const codes=[...selCodes]; if(!codes.length)return;
  if(op==='del'&&!confirm(`确认删除 ${codes.length} 个场次及其所有备份文件？此操作不可恢复！`))return;
  const actMap={archive:'archive',restore:'restore',hide:'hide',del:'del'};
  const r=await api({action:actMap[op],codes});
  if(r.ok){toast(`操作完成 (${r.n||r.count||codes.length} 个)`);clearSel();reloadSessions();}else toast(r.error,'err');
}

function openOpModal(op){
  pendingOp=op;
  document.getElementById('opModalTitle').textContent=op==='move'?'移动场次':'复制场次';
  document.getElementById('opModalDesc').textContent=op==='move'?`将 ${selCodes.size} 个场次迁移到新日期（文件夹随之重命名）`:`将 ${selCodes.size} 个场次的数据复制到新日期`;
  document.getElementById('opPreview').style.display='none';
  document.getElementById('opExecBtn').disabled=true;
  document.getElementById('opTargetDate').value='';
  openModal('opModal');
}
async function doOpPreview(){
  const nd=document.getElementById('opTargetDate').value.trim();
  const codes=[...selCodes];
  const r=await api({action:pendingOp,codes,nd,prev:'1'});
  const el=document.getElementById('opPreview'); el.style.display='block'; el.innerHTML='';
  if(r.res){r.res.forEach(([o,n])=>el.innerHTML+=`<span class="p-ok">${o}</span> <span class="p-arrow">→</span> <span class="p-ok">${n}</span>\n`);}
  if(r.errs&&r.errs.length)r.errs.forEach(e=>el.innerHTML+=`<span class="p-err">⚠ ${e}</span>\n`);
  document.getElementById('opExecBtn').disabled=!r.res||r.res.length===0;
}
async function doOpExec(){
  const nd=document.getElementById('opTargetDate').value.trim();
  const codes=[...selCodes];
  const r=await api({action:pendingOp,codes,nd,prev:'0'});
  if(r.ok){toast(`完成 ${r.res?.length||0} 个`+(r.errs?.length?`，${r.errs.length} 个错误`:''));closeModal('opModal');clearSel();reloadSessions();}else toast(r.error,'err');
}

// ── Rename ───────────────────────────────────────────────────────────────
function openRename(code,name){
  document.getElementById('renameCode').value=code;
  document.getElementById('renameName').value=name;
  openModal('renameModal');
}
async function doRename(){
  const code=document.getElementById('renameCode').value;
  const note=document.getElementById('renameName').value.trim();
  const r=await api({action:'rename',code,note});
  if(r.ok){toast('已保存');const s=SESSIONS.find(s=>s.code===code);if(s)s.note=r.note;closeModal('renameModal');renderSessions();}else toast(r.error,'err');
}

// ── Batch generate ───────────────────────────────────────────────────────
let bgPendingRes=[];
async function batchGenPreview(){
  const d6=document.getElementById('bgD6').value.trim();
  const cnt=parseInt(document.getElementById('bgCnt').value)||1;
  const startL=document.getElementById('bgStartL').value.trim().toUpperCase();
  const note=document.getElementById('bgNote').value.trim();
  const r=await api({action:'batchGen',d6,cnt,startL,note,prev:'1'});
  const el=document.getElementById('bgPreview'); el.style.display='block'; el.innerHTML='';
  bgPendingRes=[];
  if(r.res){bgPendingRes=r.res;r.res.forEach(({code,note:n})=>el.innerHTML+=`<span class="p-ok">${code}</span>${n?' · '+n:''}\n`);}
  if(r.errs&&r.errs.length)r.errs.forEach(e=>el.innerHTML+=`<span class="p-err">⚠ ${e}</span>\n`);
  document.getElementById('bgExecBtn').disabled=bgPendingRes.length===0;
}
async function batchGenExec(){
  const d6=document.getElementById('bgD6').value.trim();
  const cnt=parseInt(document.getElementById('bgCnt').value)||1;
  const startL=document.getElementById('bgStartL').value.trim().toUpperCase();
  const note=document.getElementById('bgNote').value.trim();
  const r=await api({action:'batchGen',d6,cnt,startL,note,prev:'0'});
  if(r.ok){toast(`已创建 ${r.res?.length||0} 个场次`);document.getElementById('bgPreview').style.display='none';document.getElementById('bgExecBtn').disabled=true;reloadSessions();}else toast(r.error,'err');
}

// ── Maintenance ───────────────────────────────────────────────────────────
async function doOrphanScan(){
  const r=await api({action:'orphan'}); const el=document.getElementById('orphanResult');
  if(!r.ok){el.innerHTML='<span class="red">'+r.error+'</span>';return;}
  if(!r.orphans.length){el.innerHTML='<span style="color:var(--green)">✓ 无孤立目录</span>';return;}
  el.innerHTML=`<div class="hint" style="margin-bottom:6px">发现 ${r.orphans.length} 个孤立目录：</div>`+
    r.orphans.map(o=>`<div style="display:flex;align-items:center;gap:8px;margin-bottom:4px"><span class="mono dim">${o.dir}</span><span class="dim">${o.files}文件·${o.size}</span><button class="btn btn-r" style="padding:1px 8px;font-size:.68rem" onclick="delOrphan('${o.dir}',this)">删除</button></div>`).join('');
}
async function delOrphan(dir,btn){
  if(!confirm('确认删除孤立目录 '+dir+'？'))return;
  const r=await api({action:'delOrphan',dir});
  if(r.ok){toast('已删除 '+dir);btn.closest('div').remove();}else toast(r.error,'err');
}
async function doIntegrity(){
  const r=await api({action:'integrity'}); const el=document.getElementById('integrityResult');
  if(!r.ok){el.innerHTML='<span class="red">'+r.error+'</span>';return;}
  if(!r.issues){el.innerHTML=`<span style="color:var(--green)">✓ 全部 ${r.total} 个场次正常</span>`;return;}
  el.innerHTML=`<span class="red">⚠ 发现 ${r.issues} 个问题</span><div style="margin-top:6px">`+
    r.res.map(x=>`<div class="hint"><span class="mono dim">${x.code}</span>：${x.issues.join('，')}</div>`).join('')+'</div>';
}
async function doStorage(){
  const r=await api({action:'storage'}); const el=document.getElementById('storageResult');
  if(!r.ok){el.innerHTML='<span class="red">'+r.error+'</span>';return;}
  el.innerHTML=`<div class="hint" style="margin-bottom:6px">总计：<b class="gold">${r.total}</b></div>`+
    `<div style="max-height:160px;overflow-y:auto">`+r.stats.slice(0,10).map(s=>`<div style="display:flex;gap:8px;font-size:.73rem;padding:2px 0"><span class="mono dim">${s.code}</span><span class="dim">${s.str}</span>${s.note?`<span style="color:#A09870">${escH(s.note)}</span>`:''}</div>`).join('')+'</div>';
}
async function doGlobalSt(){
  const r=await api({action:'globalSt'}); const el=document.getElementById('globalStResult');
  if(!r.ok){el.innerHTML='<span class="red">'+r.error+'</span>';return;}
  el.innerHTML=`有效场次：<b class="gold">${r.sessions}</b> · 有数据：<b>${r.withData}</b><br>总桌位：<b class="gold">${r.tables}</b> · 总宾客：<b class="gold">${r.guests}</b>`;
}

// ── Config ────────────────────────────────────────────────────────────────
async function saveYears(){
  const ys=document.getElementById('cfgYS').value, ye=document.getElementById('cfgYE').value;
  const r=await api({action:'saveCfg',ys,ye});
  if(r.ok)toast('年份范围已保存');else toast(r.error,'err');
}
async function savePw(){
  const d={action:'saveCfg',
    admin_pass:document.getElementById('pw_admin').value,
    dash_pass:document.getElementById('pw_dash').value,
    dash_pass2:document.getElementById('pw_dash2').value,
    dash_pass3:document.getElementById('pw_dash3').value};
  const r=await api(d);
  if(r.ok){toast('密码已保存');closeModal('pwModal');['pw_admin','pw_dash','pw_dash2','pw_dash3'].forEach(id=>document.getElementById(id).value='');}else toast(r.error,'err');
}
async function loadLoginLog(){
  const r=await api({action:'loginLog'});
  document.getElementById('loginLogHint').style.display='none';
  const tb=document.getElementById('loginLogBody'), t=document.getElementById('loginLogTable');
  t.style.display='table';
  if(!r.log||!r.log.length){tb.innerHTML='<tr><td colspan="3" class="dim">暂无记录</td></tr>';return;}
  tb.innerHTML=r.log.map(l=>`<tr><td class="mono dim" style="white-space:nowrap">${new Date(l.ts*1000).toLocaleString('zh-CN')}</td><td class="mono dim">${l.ip}</td><td class="dim ellip" style="max-width:300px" title="${escH(l.ua)}">${escH(l.ua)}</td></tr>`).join('');
}

// ── Auto-save config ─────────────────────────────────────────────────────
async function saveAutoSaveCfg(){
  const r=await api({action:'saveCfg',
    as_on:document.getElementById('asGlobal').value,
    as_iv:document.getElementById('asInterval').value,
    as_mi:document.getElementById('asMinIv').value,
    as_idle:document.getElementById('asIdle').value,
    as_maj:document.getElementById('asMajOp').value});
  if(r.ok)toast('自动保存设置已保存');else toast(r.error,'err');
}

function renderASTable(){
  const tb=document.getElementById('asTbody'); if(!tb)return;
  const filtered=getFiltered();
  tb.innerHTML=filtered.map(s=>{
    const v=s.autoSave; const inh=(v===null||v===undefined);
    return `<tr><td><span class="code ${s.status}" style="font-size:.75rem">${s.code}</span></td>
      <td class="dim ellip" style="max-width:100px">${escH(s.note)||'—'}</td>
      <td><label class="tog" onclick="toggleSessAS('${s.code}',this)">
        <div class="tog-track${(!inh&&!v)?'':' on'}" id="ast_${s.code}"><div class="tog-thumb"></div></div>
        <span id="asl_${s.code}">${inh?'<span class="dim">继承</span>':(v?'开启':'关闭')}</span>
      </label></td></tr>`;
  }).join('');
}

async function toggleSessAS(code,label){
  const tr=document.getElementById('ast_'+code), lbl=document.getElementById('asl_'+code);
  const s=SESSIONS.find(x=>x.code===code); if(!s)return;
  const newVal=s.autoSave===true?false:true; s.autoSave=newVal;
  tr.className='tog-track'+(newVal?' on':'');
  lbl.innerHTML=newVal?'开启':'关闭';
  await api({action:'sessAS',code,en:newVal?'1':'0'});
}
async function batchSessAS(en){
  const codes=[...selCodes]; if(!codes.length){toast('请先选择场次','err');return;}
  const r=await api({action:'sessAS',codes,en:en?'1':'0'});
  if(r.ok){toast((en?'已开启':'已关闭')+` ${r.n} 个场次的自动保存`);codes.forEach(c=>{const s=SESSIONS.find(x=>x.code===c);if(s)s.autoSave=en;});renderASTable();}else toast(r.error,'err');
}

// ── Import/Export ─────────────────────────────────────────────────────────
async function doExportZip(){
  const codes=[...selCodes];
  if(!codes.length){toast('请先选择场次','err');return;}
  document.getElementById('exportInfo').textContent='正在打包 '+codes.length+' 个场次…';
  const fd=new FormData(); fd.append('action','exportZip'); codes.forEach(c=>fd.append('codes[]',c));
  const res=await fetch(API,{method:'POST',body:fd});
  if(res.ok&&res.headers.get('Content-Type')?.includes('zip')){
    const blob=await res.blob(); const a=document.createElement('a');
    a.href=URL.createObjectURL(blob); a.download='seatcard-export-'+new Date().toISOString().slice(0,10)+'.zip';
    a.click(); document.getElementById('exportInfo').textContent='下载完成。';
  }else{const j=await res.json();document.getElementById('exportInfo').textContent='错误：'+(j.error||'未知');}
}
async function doImport(){
  const code=document.getElementById('importCode').value.trim().toUpperCase();
  const file=document.getElementById('importFile').files[0];
  if(!code){toast('请输入目标场次授权码','err');return;}
  if(!file)return;
  const fd=new FormData(); fd.append('action','import'); fd.append('code',code); fd.append('jf',file);
  const r=await fetch(API,{method:'POST',body:fd}).then(x=>x.json());
  const el=document.getElementById('importInfo');
  if(r.ok){el.innerHTML=`<span style="color:var(--green)">✓ 已导入：${r.file}</span>`;reloadSessions();}else{el.innerHTML=`<span class="red">❌ ${r.error}</span>`;}
  document.getElementById('importFile').value='';
}

// ── Reload sessions ───────────────────────────────────────────────────────
async function reloadSessions(){
  // Reload page data via lightweight full-refresh (simplest for session list)
  const r=await fetch(location.href).then(x=>x.text());
  const m=r.match(/SESSIONS\s*=\s*(\[[\s\S]*?\]);/);
  if(m){try{SESSIONS=JSON.parse(m[1]);}catch(e){}}
  Object.keys(statCache).forEach(k=>delete statCache[k]);
  calcLive(); renderSessions();
  document.getElementById('sessCount').textContent=SESSIONS.length+' 场次';
}

// ── Logout ───────────────────────────────────────────────────────────────
async function doLogout(){
  await api({action:'logout'}); location.href='admin.php';
}

// ── Init ─────────────────────────────────────────────────────────────────
calcLive();
renderSessions();
</script>
</body></html>
