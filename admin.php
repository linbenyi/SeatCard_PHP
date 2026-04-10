<?php
/**
 * admin.php — SeatCard 超级管理后台 V0.25
 */
define('DATA_DIR',  __DIR__.'/data/');
define('AUTH_FILE', __DIR__.'/data/auth.json');
define('CFG_FILE',  __DIR__.'/data/sc_config.json');
define('LOG_FILE',  __DIR__.'/data/admin_log.json');
define('SC_ALPHA',  '346789ACDEFGHJKLMNPQRSTUVWXY');
define('ADMIN_VER', 'V0.25');

$_cfg=file_exists(CFG_FILE)?(json_decode(file_get_contents(CFG_FILE),true)??[]):[];
define('ADMIN_PASS',$_cfg['admin_pass']??'admin888');unset($_cfg);

// ── 工具 ──────────────────────────────────────────────────────────────────────
function adRA(){if(!file_exists(AUTH_FILE))return[];return json_decode(file_get_contents(AUTH_FILE),true)??[];}
function adWA($l){file_put_contents(AUTH_FILE,json_encode($l,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),LOCK_EX);}
function adRC(){
    $defOps=['batchSeat'=>true,'importList'=>true,'addTable'=>true,'deleteTable'=>true,'deleteGuest'=>true,'seat'=>false,'unseat'=>false,'addGuest'=>false,'moveTable'=>false];
    $d=['yearStart'=>2026,'yearEnd'=>2030,'autoSave'=>['globalEnabled'=>true,'interval'=>10,'minInterval'=>2,'idleMinutes'=>3,'majorOpTrigger'=>true,'majorOps'=>$defOps]];
    if(!file_exists(CFG_FILE))return $d;
    $c=json_decode(file_get_contents(CFG_FILE),true)??[];
    if(isset($c['autoSave'])&&is_array($c['autoSave'])){
        if(isset($c['autoSave']['majorOps']))$c['autoSave']['majorOps']=array_merge($defOps,$c['autoSave']['majorOps']);
        $c['autoSave']=array_merge($d['autoSave'],$c['autoSave']);
    }
    return array_merge($d,$c);
}
function adWC($c){file_put_contents(CFG_FILE,json_encode($c,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE),LOCK_EX);}
function adCK($s){$r=crc32($s);$h=($r<0)?$r+4294967296:$r;return SC_ALPHA[intdiv($h,28)%28].SC_ALPHA[$h%28];}
function adGC($d,$l){return $d.$l.adCK($d.$l);}
function adOK($c){return(bool)preg_match('/^\d{6}([A-Z]|[a-z][A-Z])[346789ACDEFGHJKLMNPQRSTUVWXY]{2}$/',$c);}
function adNL($list,$d6){$used=[];foreach($list as $e){$c=$e['code']??'';if(substr($c,0,6)!==$d6)continue;$l=strlen($c);if($l===9)$used[]=$c[6];elseif($l===10)$used[]=$c[6].$c[7];}
    for($i=65;$i<=90;$i++){$l=chr($i);if(!in_array($l,$used,true))return $l;}
    for($p=97;$p<=122;$p++)for($i=65;$i<=90;$i++){$l=chr($p).chr($i);if(!in_array($l,$used,true))return $l;}return null;}
function adSt($code){$dir=DATA_DIR.$code.'/';$z=['tables'=>0,'guests'=>0,'hasData'=>false,'name'=>'','editTs'=>0,'size'=>0,'files'=>0];
    if(!is_dir($dir))return $z;$fs=array_merge(glob($dir.'wedding-seating-backup-*.json')??[],glob($dir.'wedding-seating-slot-*.json')??[]);
    if(!$fs)return $z;usort($fs,fn($a,$b)=>filemtime($b)-filemtime($a));$size=array_sum(array_map('filesize',$fs));
    $d=json_decode(file_get_contents($fs[0]),true);if(!$d)return array_merge($z,['size'=>$size,'files'=>count($fs)]);
    $et=filemtime($fs[0]);if(($ts=strtotime($d['savedAt']??''))>0)$et=$ts;
    return['tables'=>count($d['tables']??[]),'guests'=>count($d['guests']??[]),'hasData'=>true,'name'=>($d['projectName']??''),'editTs'=>$et,'size'=>$size,'files'=>count($fs)];}
function adFB($b){if($b<1024)return $b.'B';if($b<1048576)return round($b/1024,1).'KB';return round($b/1048576,1).'MB';}
function adRT($ts){if(!$ts)return'';$d=time()-$ts;if($d<60)return'刚刚';if($d<3600)return floor($d/60).'分前';if($d<86400)return floor($d/3600).'时前';if($d<86400*30)return floor($d/86400).'天前';return date('n/j',$ts);}
function adLog(){$log=file_exists(LOG_FILE)?(json_decode(file_get_contents(LOG_FILE),true)??[]):[];array_unshift($log,['ts'=>time(),'ip'=>$_SERVER['REMOTE_ADDR']??'','ua'=>substr($_SERVER['HTTP_USER_AGENT']??'',0,100)]);file_put_contents(LOG_FILE,json_encode(array_slice($log,0,30),JSON_PRETTY_PRINT),LOCK_EX);}

// ── 登录 ──────────────────────────────────────────────────────────────────────
session_start();
$ok=($_SESSION['sc_admin']??false);
if(!$ok&&$_SERVER['REQUEST_METHOD']==='POST'&&($_POST['pass']??'')===ADMIN_PASS){$_SESSION['sc_admin']=true;$ok=true;adLog();}
if(!$ok&&($_GET['api']??'')==='1'){header('Content-Type:application/json');echo json_encode(['error'=>'unauthorized']);exit;}
if(!$ok){showLogin();exit;}

// ── API ───────────────────────────────────────────────────────────────────────
if(($_GET['api']??'')==='1'&&$_SERVER['REQUEST_METHOD']==='POST'){
    header('Content-Type: application/json; charset=UTF-8');
    $act=trim($_POST['action']??'');$list=adRA();

    if($act==='calc'){$d6=preg_replace('/\D/','',trim($_POST['d6']??''));$ltr=trim($_POST['ltr']??'A');
        if(strlen($d6)!==6||!preg_match('/^([A-Z]|[a-z][A-Z])$/',$ltr)){echo json_encode(['error'=>'参数无效']);exit;}
        $code=adGC($d6,$ltr);$reg=false;$st='';foreach($list as $e){if(($e['code']??'')===$code){$reg=true;$st=$e['status']??'active';break;}}
        echo json_encode(['code'=>$code,'reg'=>$reg,'status'=>$st,'ro'=>substr($code,0,-2)]);exit;}

    if($act==='gen'){$d6=preg_replace('/\D/','',trim($_POST['d6']??''));$ltr=trim($_POST['ltr']??'');$note=mb_substr(trim($_POST['note']??''),0,30);
        if(strlen($d6)!==6){echo json_encode(['error'=>'日期格式错误']);exit;}
        if(!$ltr){$ltr=adNL($list,$d6);if(!$ltr){echo json_encode(['error'=>'该日期场次已满']);exit;}}
        if(!preg_match('/^([A-Z]|[a-z][A-Z])$/',$ltr)){echo json_encode(['error'=>'场次字母无效']);exit;}
        $code=adGC($d6,$ltr);foreach($list as $e){if(($e['code']??'')===$code){echo json_encode(['error'=>'已存在','code'=>$code]);exit;}}
        $list[]=['code'=>$code,'status'=>'active','note'=>$note,'createdAt'=>date('c')];adWA($list);@mkdir(DATA_DIR.$code.'/',0755,true);
        echo json_encode(['ok'=>true,'code'=>$code]);exit;}

    if($act==='batchGen'){$d6=preg_replace('/\D/','',trim($_POST['d6']??''));$cnt=max(1,min(26,intval($_POST['cnt']??1)));
        $startL=strtoupper(trim($_POST['startL']??'A'));$note=mb_substr(trim($_POST['note']??''),0,20);$prev=(($_POST['prev']??'0')==='1');
        if(strlen($d6)!==6){echo json_encode(['error'=>'日期格式错误']);exit;}
        $letters='ABCDEFGHIJKLMNOPQRSTUVWXYZ';$si=strpos($letters,$startL);
        if($si===false){echo json_encode(['error'=>'起始字母无效']);exit;}
        $res=[];$errs=[];$cur=$list;
        for($i=0;$i<$cnt;$i++){$idx=$si+$i;if($idx>=26){$errs[]='超出 A-Z 范围';break;}
            $l=$letters[$idx];$code=adGC($d6,$l);$dup=false;foreach($cur as $e){if(($e['code']??'')===$code){$dup=true;break;}}
            if($dup){$errs[]="$code 已存在";continue;}
            $n=$note?($note.($cnt>1?'·'.chr(65+$i).'场':'')):'';$res[]=['code'=>$code,'note'=>$n];
            if(!$prev){$cur[]=['code'=>$code,'status'=>'active','note'=>$n,'createdAt'=>date('c')];@mkdir(DATA_DIR.$code.'/',0755,true);}}
        if(!$prev&&$res)adWA($cur);echo json_encode(['ok'=>true,'res'=>$res,'errs'=>$errs]);exit;}

    if(in_array($act,['archive','restore','hide','unhide'])){
        $codes=$_POST['codes']??[$_POST['code']??''];if(is_string($codes))$codes=[$codes];$ok2=0;
        foreach($list as &$e){if(!in_array($e['code']??'',$codes,true))continue;$e['status']=$act==='archive'?'archived':($act==='hide'?'hidden':'active');$ok2++;}unset($e);
        if($ok2){adWA($list);echo json_encode(['ok'=>true,'n'=>$ok2]);}else echo json_encode(['error'=>'未找到']);exit;}

    if($act==='del'){$codes=$_POST['codes']??[$_POST['code']??''];if(is_string($codes))$codes=[$codes];$nl=[];$ok2=0;
        foreach($list as $e){$c=$e['code']??'';if(in_array($c,$codes,true)){$dir=DATA_DIR.$c.'/';if(is_dir($dir)){foreach(glob($dir.'*.json')??[]as$f)unlink($f);@rmdir($dir);}$ok2++;}else $nl[]=$e;}
        if($ok2){adWA($nl);echo json_encode(['ok'=>true,'n'=>$ok2]);}else echo json_encode(['error'=>'未找到']);exit;}

    if($act==='move'||$act==='copy'){
        $codes=$_POST['codes']??[$_POST['code']??''];if(is_string($codes))$codes=[$codes];
        $nd=preg_replace('/\D/','',trim($_POST['nd']??''));$prev=(($_POST['prev']??'0')==='1');
        if(strlen($nd)!==6){echo json_encode(['error'=>'目标日期格式错误']);exit;}
        $res=[];$errs=[];$cur=$list;
        foreach($codes as $sc){$ei=null;foreach($cur as $i=>$e){if(($e['code']??'')===$sc){$ei=$i;break;}}
            if($ei===null){$errs[]="$sc 不存在";continue;}$ent=$cur[$ei];$nl2=adNL($cur,$nd);if(!$nl2){$errs[]="$sc 目标日期已满";continue;}
            $nc=adGC($nd,$nl2);$res[]=[$sc,$nc];
            if(!$prev){
                if($act==='move'){$od=DATA_DIR.$sc.'/';$ndir=DATA_DIR.$nc.'/';if(is_dir($od)){if(!rename($od,$ndir)){$errs[]="$sc 目录迁移失败";continue;}}$cur[$ei]['code']=$nc;}
                else{$sd=DATA_DIR.$sc.'/';$dd=DATA_DIR.$nc.'/';@mkdir($dd,0755,true);if(is_dir($sd))foreach(glob($sd.'*.json')??[]as$f)copy($f,$dd.basename($f));
                    $cur[]=['code'=>$nc,'status'=>'active','note'=>trim(($ent['note']??'').($nd!==substr($sc,0,6)?'':' (副本)')),'createdAt'=>date('c')];}}}
        if(!$prev&&$res)adWA(array_values($cur));echo json_encode(['ok'=>true,'res'=>$res,'errs'=>$errs,'prev'=>$prev]);exit;}

    if($act==='rename'){$code=trim($_POST['code']??'');$note=mb_substr(trim($_POST['note']??''),0,30);$ok2=false;
        foreach($list as &$e){if(($e['code']??'')===$code){$e['note']=$note;$ok2=true;break;}}unset($e);
        if($ok2){adWA($list);echo json_encode(['ok'=>true,'note'=>$note]);}else echo json_encode(['error'=>'未找到']);exit;}

    if($act==='stat'){$code=trim($_POST['code']??'');$st=adSt($code);$st['editRel']=adRT($st['editTs']);$st['sizeStr']=adFB($st['size']);echo json_encode(['ok'=>true,'st'=>$st]);exit;}

    if($act==='sessAS'){$codes=$_POST['codes']??[$_POST['code']??''];if(is_string($codes))$codes=[$codes];$en=($_POST['en']??'1')==='1';$ok2=0;
        foreach($list as &$e){if(in_array($e['code']??'',$codes,true)){$e['autoSave']=$en;$ok2++;}}unset($e);
        if($ok2){adWA($list);echo json_encode(['ok'=>true,'n'=>$ok2]);}else echo json_encode(['error'=>'未找到']);exit;}

    // ── 导出 ZIP ──
    if($act==='exportZip'){$codes=$_POST['codes']??[];if(is_string($codes))$codes=[$codes];
        if(!class_exists('ZipArchive')){echo json_encode(['error'=>'ZipArchive 不可用']);exit;}
        $tmp=tempnam(sys_get_temp_dir(),'sc_');$zip=new ZipArchive();$zip->open($tmp,ZipArchive::OVERWRITE);
        foreach($codes as $c2){$dir=DATA_DIR.$c2.'/';if(!is_dir($dir))continue;foreach(glob($dir.'*.json')??[]as$f)$zip->addFile($f,$c2.'/'.basename($f));}
        $zip->close();header('Content-Type: application/zip');header('Content-Disposition: attachment; filename="seatcard-export-'.date('Ymd-His').'.zip"');header('Content-Length: '.filesize($tmp));readfile($tmp);unlink($tmp);exit;}

    // ── 导入 JSON ──
    if($act==='import'){$code=trim($_POST['code']??'');if(!adOK($code)){echo json_encode(['error'=>'无效场次编号']);exit;}
        if(!isset($_FILES['jf'])||$_FILES['jf']['error']!==0){echo json_encode(['error'=>'文件上传失败']);exit;}
        $raw=file_get_contents($_FILES['jf']['tmp_name']);$d=json_decode($raw,true);
        if(!$d||!isset($d['tables'])){echo json_encode(['error'=>'不是有效的 SeatCard JSON']);exit;}
        $dir=DATA_DIR.$code.'/';if(!is_dir($dir)&&!mkdir($dir,0755,true)){echo json_encode(['error'=>'无法创建目录']);exit;}
        $fn='wedding-seating-backup-'.date('Ymd-His').'.json';file_put_contents($dir.$fn,$raw);
        $found=false;foreach($list as $e){if(($e['code']??'')===$code){$found=true;break;}}
        if(!$found){$list[]=['code'=>$code,'status'=>'active','note'=>'','createdAt'=>date('c')];adWA($list);}
        echo json_encode(['ok'=>true,'file'=>$fn]);exit;}

    // ── 维护：孤立目录 ──
    if($act==='orphan'){$reg=array_column($list,'code');$orphans=[];
        foreach(scandir(DATA_DIR)as$e2){if($e2==='.'||$e2==='..')continue;if(!is_dir(DATA_DIR.$e2))continue;if(in_array($e2,$reg,true))continue;
            if(!preg_match('/^\d{6}[A-Za-z]{1,2}[346789ACDEFGHJKLMNPQRSTUVWXY]{2}$/',$e2))continue;
            $fs=glob(DATA_DIR.$e2.'/*.json')??[];$sz=array_sum(array_map('filesize',$fs));
            $orphans[]=['dir'=>$e2,'files'=>count($fs),'size'=>adFB($sz),'rawSize'=>$sz];}
        echo json_encode(['ok'=>true,'orphans'=>$orphans]);exit;}

    if($act==='delOrphan'){$dir=basename(trim($_POST['dir']??''));$reg=array_column($list,'code');
        if(!$dir||in_array($dir,$reg,true)){echo json_encode(['error'=>'无效或已注册']);exit;}
        $p=DATA_DIR.$dir.'/';if(!is_dir($p)){echo json_encode(['error'=>'不存在']);exit;}
        foreach(glob($p.'*.json')??[]as$f)unlink($f);@rmdir($p);echo json_encode(['ok'=>true]);exit;}

    if($act==='addOrphan'){$dir=basename(trim($_POST['dir']??''));$reg=array_column($list,'code');
        if(!$dir||in_array($dir,$reg,true)){echo json_encode(['error'=>'无效或已存在']);exit;}
        $yy=substr($dir,0,2);$mm=substr($dir,2,2);$dd=substr($dir,4,2);
        $created="20{$yy}-{$mm}-{$dd}T00:00:00+08:00";
        $list[]=['code'=>$dir,'status'=>'active','note'=>'','createdAt'=>$created];adWA($list);
        echo json_encode(['ok'=>true,'code'=>$dir,'createdAt'=>$created]);exit;}

    // ── 维护：缺失目录 ──
    if($act==='missing'){$res=[];
        foreach($list as $e){$c=$e['code']??'';$dir=DATA_DIR.$c.'/';if(!is_dir($dir))$res[]=['code'=>$c,'status'=>$e['status']??'active','note'=>$e['note']??'','createdAt'=>$e['createdAt']??''];}
        echo json_encode(['ok'=>true,'res'=>$res]);exit;}

    // ── 维护：存储统计 ──
    if($act==='storage'){$stats=[];$total=0;
        foreach($list as $e){$c=$e['code']??'';$dir=DATA_DIR.$c.'/';$fs=glob($dir.'*.json')??[];$sz=array_sum(array_map('filesize',$fs));$total+=$sz;
            $stats[]=['code'=>$c,'note'=>$e['note']??'','status'=>$e['status']??'active','files'=>count($fs),'size'=>$sz,'str'=>adFB($sz)];}
        usort($stats,fn($a,$b)=>$b['size']-$a['size']);echo json_encode(['ok'=>true,'total'=>adFB($total),'totalBytes'=>$total,'stats'=>$stats]);exit;}

    // ── 维护：场次汇总 ──
    if($act==='summary'){$res=[];
        foreach($list as $e){$c=$e['code']??'';if(($e['status']??'active')!=='active')continue;$st=adSt($c);
            $res[]=['code'=>$c,'note'=>$e['note']??'','tables'=>$st['tables'],'guests'=>$st['guests'],'files'=>$st['files'],'editTs'=>$st['editTs'],'sizeStr'=>adFB($st['size'])];}
        echo json_encode(['ok'=>true,'res'=>$res]);exit;}

    // ── 配置保存 ──
    if($act==='saveCfg'){$c2=adRC();
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
            $ops=json_decode($_POST['majorOps']??'{}',true);if(is_array($ops))$c2['autoSave']['majorOps']=array_merge($c2['autoSave']['majorOps'],$ops);}
        adWC($c2);echo json_encode(['ok'=>true]);exit;}

    if($act==='loginLog'){$log=file_exists(LOG_FILE)?(json_decode(file_get_contents(LOG_FILE),true)??[]):[];echo json_encode(['ok'=>true,'log'=>$log]);exit;}
    if($act==='logout'){$_SESSION['sc_admin']=false;session_destroy();echo json_encode(['ok'=>true]);exit;}
    echo json_encode(['error'=>'unknown action']);exit;}

if($_SERVER['REQUEST_METHOD']==='POST'&&($_POST['action']??'')==='logout'){$_SESSION['sc_admin']=false;session_destroy();header('Location: admin.php');exit;}

// ── 数据准备 ──────────────────────────────────────────────────────────────────
$cfg=adRC();$authList=adRA();
$today=date('ymd');$suggestCode=adGC($today,adNL($authList,$today)??'A');
$scheme=(isset($_SERVER['HTTPS'])&&$_SERVER['HTTPS']==='on')?'https':'http';
$selfDir=rtrim(str_replace('\\','/',dirname($_SERVER['PHP_SELF'])),'/');
$baseUrl=$scheme.'://'.$_SERVER['HTTP_HOST'].$selfDir.'/index.php';
$totalAll=count($authList);$totalAct=count(array_filter($authList,fn($e)=>($e['status']??'active')==='active'));
$sessionsJS=[];
foreach($authList as $e){$code=$e['code']??'';if(strlen($code)<9)continue;$sessionsJS[]=['code'=>$code,'status'=>$e['status']??'active','note'=>$e['note']??'','createdAt'=>$e['createdAt']??'','autoSave'=>$e['autoSave']??null];}
usort($sessionsJS,fn($a,$b)=>strcmp(substr($b['code'],0,7),substr($a['code'],0,7)));

function showLogin(){?>
<!DOCTYPE html><html lang="zh-CN"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>SeatCard 管理</title>
<link href="https://fonts.loli.net/css2?family=Noto+Sans+SC:wght@400;700&display=swap" rel="stylesheet">
<style>*{box-sizing:border-box;margin:0;padding:0}body{font-family:'Noto Sans SC',sans-serif;background:#0F0F0C;display:flex;align-items:center;justify-content:center;min-height:100vh}
.lc{background:#1A1A16;border:1px solid #3A3020;border-radius:14px;padding:40px 36px;width:320px;text-align:center}
h2{font-size:1rem;color:#D4AA3C;margin-bottom:4px;font-weight:700;letter-spacing:.12em;text-shadow:0 0 18px rgba(212,170,60,.4)}.sub{font-size:.75rem;color:#4A4838;margin-bottom:22px}
input[type=password]{width:100%;padding:9px 12px;background:#111110;border:1.5px solid #2A2A22;border-radius:7px;font-size:.92rem;outline:none;font-family:inherit;color:#C0BCA8;transition:border .15s}
input[type=password]:focus{border-color:#D4AA3C}button{margin-top:12px;width:100%;padding:10px;background:#D4AA3C;color:#0F0F0C;border:none;border-radius:7px;font-size:.88rem;cursor:pointer;font-family:inherit;font-weight:700}</style></head><body>
<div class="lc"><h2>◈ SEATCARD 管理后台</h2><p class="sub">超级密码</p><form method="POST"><input type="password" name="pass" autofocus><button>登录</button></form></div>
</body></html><?php}
?>
<!DOCTYPE html><html lang="zh-CN"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>SeatCard 管理 <?=ADMIN_VER?></title>
<link href="https://fonts.loli.net/css2?family=Noto+Sans+SC:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
:root{--bg:#0F0F0C;--bg2:#181814;--bg3:#222218;--gold:#D4AA3C;--gold2:#3A2E10;--text:#E4E0D4;--dim:#A8A498;--border:#303028;--red:#C84040;--green:#4A9A5A}
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Noto Sans SC',sans-serif;background:var(--bg);color:var(--text);min-height:100vh;display:flex;flex-direction:column}
header{background:#0A0A08;border-bottom:1px solid #2A2418;height:46px;display:flex;align-items:center;gap:10px;padding:0 16px;flex-shrink:0;position:sticky;top:0;z-index:200}
.hdr-title{font-size:.85rem;font-weight:700;letter-spacing:.12em;color:var(--gold);text-shadow:0 0 18px rgba(212,170,60,.4)}
.hdr-ver{font-size:.6rem;color:#5A5030;align-self:flex-end;padding-bottom:3px;margin-left:-6px}
.hdr-stat{font-size:.7rem;color:#9A9888}.hdr-stat b{color:#CEC8A8}
.hdr-r{margin-left:auto;display:flex;align-items:center;gap:5px}
.hbtn{background:#181610;border:1px solid #3A3220;color:#A09070;font-size:.73rem;cursor:pointer;font-family:inherit;padding:3px 9px;border-radius:4px;text-decoration:none;transition:all .15s;display:inline-flex;align-items:center;gap:4px;height:24px}
.hbtn:hover{color:#E8C860;border-color:#C8A84A;background:#221E0C}.hbtn.danger:hover{color:#E05050;border-color:#803030}
.main{flex:1;padding:12px 16px;max-width:1100px;margin:0 auto;width:100%}
.card{background:var(--bg2);border:1px solid var(--border);border-radius:10px;margin-bottom:10px;overflow:visible}
.card-hdr{display:flex;align-items:center;gap:8px;padding:9px 14px;cursor:pointer;user-select:none;transition:background .12s;border-bottom:1px solid transparent;border-radius:10px}
.card-hdr:hover{background:var(--bg3)}.card-hdr.open{border-bottom-color:var(--border);border-radius:10px 10px 0 0}
.card-title{font-size:.82rem;font-weight:600;color:var(--text);flex:1}
.card-badge{font-size:.65rem;background:var(--gold2);color:var(--gold);padding:1px 6px;border-radius:10px}
.card-toggle{color:#6A6858;font-size:.7rem;transition:transform .2s}
.card-hdr.open .card-toggle{transform:rotate(90deg)}
.card-body{display:none;padding:12px 14px}.card-hdr.open+.card-body{display:block}
/* sub-section within card */
.sub-card{background:#111110;border:1px solid #252520;border-radius:7px;padding:10px 12px;margin-top:10px}
.sub-hdr{display:flex;align-items:center;gap:6px;cursor:pointer;user-select:none;margin-bottom:0}
.sub-hdr.open{margin-bottom:10px}
.sub-title{font-size:.75rem;font-weight:600;color:#A09870;flex:1}
.sub-toggle{font-size:.65rem;color:#4A4838;transition:transform .2s}.sub-hdr.open .sub-toggle{transform:rotate(90deg)}
.sub-body{display:none}.sub-hdr.open+.sub-body{display:block}
label.fl{font-size:.72rem;color:#7A7060;display:block;margin-bottom:3px}
input[type=text],input[type=number],input[type=password],select,textarea{background:#111110;border:1.5px solid #2A2418;border-radius:5px;color:var(--text);font-family:inherit;font-size:.82rem;padding:5px 8px;outline:none;transition:border .15s;width:100%;color-scheme:dark}
input:focus,select:focus,textarea:focus{border-color:var(--gold)}
input[type=checkbox]{accent-color:var(--gold);width:13px;height:13px;cursor:pointer;flex-shrink:0}
.btn{padding:4px 12px;border:none;border-radius:5px;font-size:.76rem;cursor:pointer;font-weight:600;font-family:inherit;white-space:nowrap;transition:all .15s;display:inline-flex;align-items:center;gap:4px}
.btn-g{background:var(--gold);color:#0F0F0C}.btn-g:hover{background:#A88A28}
.btn-s{background:#1E1E1A;border:1px solid #3A3828;color:#A09080}.btn-s:hover{border-color:#6A6458;color:var(--text)}
.btn-r{background:#501010;color:#E05050;border:1px solid #601818}.btn-r:hover{background:#601818}
.btn-b{background:#103050;color:#80B8E8;border:1px solid #184060}.btn-b:hover{background:#184060}
.btn-o{background:#503010;color:#E09050;border:1px solid #604018}.btn-o:hover{background:#604018}
.btn:disabled{opacity:.4;pointer-events:none}
.row{display:flex;gap:8px;align-items:flex-end;flex-wrap:wrap}.row>*{flex:1;min-width:80px}.row>.wa{flex:0 0 auto}
.code{font-family:monospace;background:#1A1C14;color:#E8E0B0;padding:2px 7px;border-radius:4px;font-size:.82rem;font-weight:700;letter-spacing:.4px}
.code.active{color:#D4AA3C}.code.archived{color:#C06060}.code.hidden{color:#6A8A6A}
.sbadge{font-size:.62rem;padding:1px 6px;border-radius:8px;font-weight:600;white-space:nowrap}
.sbadge.active{background:#3A2E10;color:#C8A030}.sbadge.archived{background:#301010;color:#C06060}.sbadge.hidden{background:#0E1E12;color:#5A8A5A}
.as-badge{font-size:.62rem;padding:1px 6px;border-radius:8px;cursor:pointer;font-weight:600;white-space:nowrap;transition:all .15s}
.as-badge.on{background:#183028;color:#50C870}.as-badge.off{background:#281208;color:#C06030}.as-badge.inh{background:#201E14;color:#7A7060}
table.dt{width:100%;border-collapse:collapse;font-size:.78rem}
table.dt th{text-align:left;padding:5px 8px;background:#141412;color:#6A6858;font-weight:600;border-bottom:1px solid #2A2418;white-space:nowrap}
table.dt td{padding:5px 8px;border-bottom:1px solid #1E1C14;vertical-align:middle}
table.dt tr:last-child td{border-bottom:none}table.dt tr:hover td{background:#181612}
.preview{background:#0A0A08;border:1px solid #2A2418;border-radius:5px;padding:8px 10px;font-family:monospace;font-size:.75rem;color:#A8A490;max-height:160px;overflow-y:auto;line-height:1.7;white-space:pre-wrap}
.preview .ok{color:#6AA870}.preview .err{color:#C06050}.preview .arr{color:#505838}
/* Batch bar */
.bbar{display:none;position:fixed;bottom:0;left:0;right:0;background:#0A0A08;border-top:2px solid #2A2418;padding:7px 16px;z-index:300;align-items:center;gap:6px;flex-wrap:wrap}
.bbar.show{display:flex}.bbar-info{font-size:.76rem;color:#8A8878}.bbar-info b{color:var(--gold)}
.bbar-r{margin-left:auto;display:flex;gap:6px}
/* Maintenance */
.maint-btns{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:12px}
.maint-btn{flex:1;min-width:140px;padding:10px 14px;background:#161610;border:1px solid #2A2418;border-radius:8px;cursor:pointer;font-family:inherit;text-align:left;transition:all .15s;color:var(--text)}
.maint-btn:hover{border-color:#5A5030;background:var(--bg3)}.maint-btn.active{border-color:var(--gold);background:var(--gold2)}
.maint-btn .mb-title{font-size:.8rem;font-weight:600;color:var(--text);margin-bottom:3px}
.maint-btn.active .mb-title{color:var(--gold)}
.maint-btn .mb-est{font-size:.68rem;color:#5A5848}
.maint-result{background:#111110;border:1px solid #252520;border-radius:7px;padding:10px;display:none}
.maint-result.show{display:block}
/* Toast */
.tw{position:fixed;top:54px;right:14px;z-index:1000;display:flex;flex-direction:column;gap:6px;pointer-events:none}
.toast{background:#1E1C14;border:1px solid #3A3820;border-radius:6px;padding:7px 14px;font-size:.76rem;color:var(--text);opacity:0;transition:opacity .3s}
.toast.show{opacity:1}.toast.ok{border-color:#3A6020;color:#8ACA70}.toast.err{border-color:#601010;color:#E06060}
/* Modal */
.ov{display:none;position:fixed;inset:0;background:rgba(0,0,0,.82);z-index:400;align-items:center;justify-content:center}
.ov.show{display:flex}
.mbox{background:#1A1A16;border:1px solid #3A3020;border-radius:12px;padding:22px 24px;width:360px;max-width:95vw;font-size:.82rem}
.mtitle{font-size:.9rem;font-weight:700;color:var(--gold);margin-bottom:4px}
.mdesc{color:#5A5848;font-size:.73rem;margin-bottom:13px;line-height:1.6}
.mbtns{margin-top:14px;display:flex;gap:8px;justify-content:flex-end}
/* Seg */
.seg{display:flex;border:1px solid #3A3828;border-radius:5px;overflow:hidden}
.seg-btn{flex:1;padding:4px 8px;background:transparent;border:none;color:#6A6858;font-family:inherit;font-size:.72rem;cursor:pointer;transition:all .12s}
.seg-btn.on{background:var(--gold);color:#0F0F0C;font-weight:700}.seg-btn:hover:not(.on){background:var(--bg3);color:var(--text)}
/* Toggle */
.tog{display:inline-flex;align-items:center;gap:6px;cursor:pointer;font-size:.78rem;color:var(--dim);user-select:none}
.tog-t{width:34px;height:18px;background:#2A2820;border-radius:9px;position:relative;transition:background .2s;flex-shrink:0}
.tog-t.on{background:#5A4A18}.tog-th{width:14px;height:14px;background:#5A5848;border-radius:50%;position:absolute;top:2px;left:2px;transition:all .2s}
.tog-t.on .tog-th{background:var(--gold);left:18px}
/* major ops grid */
.ops-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:6px;margin-top:8px}
.op-item{display:flex;align-items:center;gap:6px;font-size:.75rem;padding:5px 8px;background:#0A0A08;border:1px solid #2A2418;border-radius:5px;cursor:pointer}
.op-item:hover{border-color:#4A4030}.op-item.rec{border-color:#3A3020}
.op-item .rec-tag{font-size:.6rem;color:var(--gold);margin-left:auto;flex-shrink:0}
.dim{color:var(--dim)}.gold{color:var(--gold)}.red{color:var(--red)}.mono{font-family:monospace}
.hint{font-size:.7rem;color:#4A4838;line-height:1.6}.ellip{white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.pager{display:flex;align-items:center;gap:6px;margin-top:8px;font-size:.75rem;color:var(--dim)}
#statTip{display:none;position:fixed;z-index:500;background:#1A1A16;border:1px solid #3A3020;border-radius:8px;padding:10px 14px;font-size:.73rem;pointer-events:none;min-width:160px;box-shadow:0 4px 20px rgba(0,0,0,.6)}
</style>
</head><body>

<header>
  <span class="hdr-title">SEATCARD</span><span class="hdr-ver"><?=ADMIN_VER?></span>
  <span class="hdr-stat">共 <b><?=$totalAll?></b> 场 &nbsp;·&nbsp; 有效 <b><?=$totalAct?></b></span>
  <div class="hdr-r">
    <a href="dashboard.php" class="hbtn">📊 看板</a>
    <a href="index.php" class="hbtn">🏠 主页</a>
    <button class="hbtn" onclick="openModal('pwModal')">⚙ 密码</button>
    <button class="hbtn danger" onclick="doLogout()">退出</button>
  </div>
</header>

<div class="main">
<div class="tw" id="tw"></div>

<!-- ── 卡片 A: 即时生成器 ── -->
<div class="card">
  <div class="card-hdr open" onclick="toggleCard(this)"><span class="card-title">◈ 即时授权码生成器</span><span class="card-toggle">▶</span></div>
  <div class="card-body">
    <div class="row">
      <div style="max-width:110px"><label class="fl">日期 YYMMDD</label><input type="text" id="calcD6" maxlength="6" value="<?=htmlspecialchars($today)?>" oninput="calcLive()"></div>
      <div style="max-width:70px"><label class="fl">场次字母</label><input type="text" id="calcLtr" maxlength="2" value="A" oninput="calcLive()" style="text-transform:uppercase"></div>
      <div style="flex:2">
        <label class="fl">生成的授权码</label>
        <div style="display:flex;gap:6px;align-items:center">
          <span id="calcResult" class="code active" style="font-size:1rem;padding:5px 12px;min-width:120px">──────</span>
          <button class="btn btn-s" onclick="copyCalc()">📋</button>
          <button class="btn btn-g" id="calcWriteBtn" onclick="writeCalc()" disabled>写入</button>
        </div>
      </div>
      <div style="max-width:200px"><label class="fl">宴会名称</label><input type="text" id="calcNote" placeholder="可选" maxlength="30"></div>
    </div>
    <div id="calcInfo" class="hint" style="margin-top:6px"></div>
    <div style="margin-top:8px;font-size:.73rem;color:#5A5848" id="roLink"></div>
  </div>
</div>

<!-- ── 卡片 B: 场次管理 ── -->
<div class="card">
  <div class="card-hdr open" onclick="toggleCard(this)">
    <span class="card-title">◈ 场次管理</span>
    <span id="sessCount" class="card-badge"><?=count($sessionsJS)?> 场次</span>
    <span class="card-toggle">▶</span>
  </div>
  <div class="card-body">
    <!-- 批量生成 -->
    <div class="section-title" style="font-size:.7rem;font-weight:700;color:#5A5848;text-transform:uppercase;letter-spacing:.1em;margin-bottom:7px">批量生成</div>
    <div class="row">
      <div style="max-width:110px"><label class="fl">起始日期 YYMMDD</label><input type="text" id="bgD6" maxlength="6" value="<?=htmlspecialchars($today)?>"></div>
      <div style="max-width:60px"><label class="fl">场次数量</label><input type="number" id="bgCnt" value="1" min="1" max="26"></div>
      <div style="max-width:55px"><label class="fl">起始字母</label><input type="text" id="bgStartL" value="A" maxlength="1" style="text-transform:uppercase"></div>
      <div><label class="fl">名称前缀</label><input type="text" id="bgNote" placeholder="如：张三婚礼" maxlength="20"></div>
      <div class="wa" style="display:flex;gap:6px">
        <button class="btn btn-s" onclick="batchGenPrev()">预览</button>
        <button class="btn btn-g" id="bgExecBtn" onclick="batchGenExec()" disabled>确认写入</button>
      </div>
    </div>
    <div id="bgPreview" class="preview" style="margin-top:8px;display:none"></div>

    <!-- 筛选栏 -->
    <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin:10px 0 8px">
      <div class="seg" id="stSeg">
        <button class="seg-btn on" onclick="setFilter(this,'all')">全部</button>
        <button class="seg-btn" onclick="setFilter(this,'active')">有效</button>
        <button class="seg-btn" onclick="setFilter(this,'archived')">存档</button>
        <button class="seg-btn" onclick="setFilter(this,'hidden')">隐藏</button>
      </div>
      <input type="text" id="sessSearch" placeholder="搜索…" style="width:120px" oninput="renderSess()">
      <select id="sessYear" onchange="renderSess()" style="width:75px">
        <option value="">全部年份</option>
        <?php for($y=$cfg['yearStart'];$y<=$cfg['yearEnd'];$y++){$yy=sprintf('%02d',$y-2000);echo"<option value=\"$yy\">$y</option>";}?>
      </select>
      <div style="margin-left:auto;display:flex;gap:5px">
        <button class="btn btn-s" onclick="selAll()">全选</button>
        <button class="btn btn-s" onclick="clearSel()">清选</button>
      </div>
    </div>

    <!-- 场次表 -->
    <table class="dt" id="sessTable">
      <thead><tr>
        <th style="width:24px"></th>
        <th>授权码</th><th>状态</th><th>日期</th><th>宴会名称</th>
        <th title="存档文件数">存档</th><th>最后编辑</th><th>保存</th><th>操作</th>
      </tr></thead>
      <tbody id="sessTb"></tbody>
    </table>
    <div id="sessPager" class="pager"></div>

    <!-- 自动保存策略（子卡片） -->
    <div class="sub-card">
      <div class="sub-hdr open" onclick="toggleSub(this)">
        <span class="sub-title">⚙ 自动保存策略</span>
        <span class="sub-toggle">▶</span>
      </div>
      <div class="sub-body">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
          <div>
            <label class="tog" onclick="togClick('asGlobal')" style="margin-bottom:10px">
              <div class="tog-t<?=($cfg['autoSave']['globalEnabled']??true)?' on':''?>" id="asGlobal_t"><div class="tog-th"></div></div>
              全局自动保存
            </label><input type="hidden" id="asGlobal" value="<?=($cfg['autoSave']['globalEnabled']??true)?'1':'0'?>">
            <div class="row" style="margin-top:8px">
              <div><label class="fl">定时间隔（分钟）</label><input type="number" id="asIv" value="<?=$cfg['autoSave']['interval']??10?>" min="1" max="60"></div>
              <div><label class="fl">最短间隔</label><input type="number" id="asMi" value="<?=$cfg['autoSave']['minInterval']??2?>" min="1" max="30"></div>
              <div><label class="fl">空闲触发</label><input type="number" id="asIdle" value="<?=$cfg['autoSave']['idleMinutes']??3?>" min="1" max="30"></div>
            </div>
            <label class="tog" onclick="togClick('asMaj')" style="margin-top:10px">
              <div class="tog-t<?=($cfg['autoSave']['majorOpTrigger']??true)?' on':''?>" id="asMaj_t"><div class="tog-th"></div></div>
              大操作触发保存
            </label><input type="hidden" id="asMaj" value="<?=($cfg['autoSave']['majorOpTrigger']??true)?'1':'0'?>">
            <button class="btn btn-g" style="margin-top:10px" onclick="saveASCfg()">保存策略</button>
          </div>
          <div>
            <div style="font-size:.72rem;color:#7A7060;margin-bottom:6px">大操作触发项目（✓ 推荐默认）</div>
            <div class="ops-grid" id="opsGrid"></div>
            <div class="hint" style="margin-top:8px">保存策略按钮同时保存大操作配置</div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- ── 卡片 C: 系统维护 ── -->
<div class="card">
  <div class="card-hdr" onclick="toggleCard(this)"><span class="card-title">◈ 系统维护</span><span class="card-toggle">▶</span></div>
  <div class="card-body">
    <div class="maint-btns" id="maintBtns">
      <button class="maint-btn" id="mb_orphan" onclick="runMaint('orphan')">
        <div class="mb-title">🔍 扫描孤立目录</div>
        <div class="mb-est" id="est_orphan">data/ 有文件夹，auth.json 无记录</div>
      </button>
      <button class="maint-btn" id="mb_missing" onclick="runMaint('missing')">
        <div class="mb-title">⚠ 检查缺失目录</div>
        <div class="mb-est" id="est_missing">auth.json 有记录，data/ 无文件夹</div>
      </button>
      <button class="maint-btn" id="mb_storage" onclick="runMaint('storage')">
        <div class="mb-title">📦 存储用量统计</div>
        <div class="mb-est" id="est_storage">各场次占用磁盘空间</div>
      </button>
      <button class="maint-btn" id="mb_summary" onclick="runMaint('summary')">
        <div class="mb-title">📊 场次数据汇总</div>
        <div class="mb-est" id="est_summary">各场次桌数/宾客数</div>
      </button>
    </div>
    <div class="maint-result" id="maintResult">
      <table class="dt" id="maintTable">
        <thead id="maintThead"></thead>
        <tbody id="maintTb"></tbody>
      </table>
      <div id="maintPager" class="pager"></div>
    </div>
  </div>
</div>

<!-- ── 卡片 D: 系统配置 ── -->
<div class="card">
  <div class="card-hdr" onclick="toggleCard(this)"><span class="card-title">◈ 系统配置</span><span class="card-toggle">▶</span></div>
  <div class="card-body">
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
      <div>
        <div style="font-size:.7rem;font-weight:700;color:#5A5848;text-transform:uppercase;letter-spacing:.1em;margin-bottom:7px">年份范围</div>
        <div class="row">
          <div><label class="fl">起始年</label><input type="number" id="cfgYS" value="<?=$cfg['yearStart']?>" min="2020" max="2099"></div>
          <div><label class="fl">结束年</label><input type="number" id="cfgYE" value="<?=$cfg['yearEnd']?>" min="2020" max="2099"></div>
          <div class="wa"><label class="fl">&nbsp;</label><button class="btn btn-g" onclick="saveYears()">保存</button></div>
        </div>
        <div class="hint" style="margin-top:4px">授权码格式：<span class="mono gold">YYMMDDXcc</span>（9位）CRC32校验<br>只读链接：去末尾2位校验位</div>
      </div>
      <div>
        <div style="font-size:.7rem;font-weight:700;color:#5A5848;text-transform:uppercase;letter-spacing:.1em;margin-bottom:7px">登录记录
          <button class="btn btn-s" style="margin-left:8px;padding:1px 8px;font-size:.65rem" onclick="loadLog()">刷新</button>
        </div>
        <table class="dt" id="logTable" style="display:none">
          <thead><tr><th>时间</th><th>IP</th><th>UA</th></tr></thead>
          <tbody id="logTb"></tbody>
        </table>
        <p id="logHint" class="hint">点击刷新加载</p>
      </div>
    </div>
  </div>
</div>

<div style="height:60px"></div>
</div>

<!-- ── 批量操作栏 ── -->
<div class="bbar" id="bbar">
  <span class="bbar-info">已选 <b id="bbarN">0</b> 个</span>
  <button class="btn btn-s" onclick="batchOp('archive')">存档</button>
  <button class="btn btn-s" onclick="batchOp('restore')">恢复</button>
  <button class="btn btn-s" onclick="batchOp('hide')">隐藏</button>
  <button class="btn btn-b" onclick="openOpModal('move')">移动…</button>
  <button class="btn btn-b" onclick="openOpModal('copy')">复制…</button>
  <button class="btn btn-o" onclick="doExportZip()">⬇ 导出ZIP</button>
  <button class="btn btn-r" onclick="batchOp('del')">删除</button>
  <div class="bbar-r"><button class="btn btn-s" onclick="clearSel()">取消</button></div>
</div>

<!-- ── 密码弹窗 ── -->
<div class="ov" id="pwModal"><div class="mbox">
  <div class="mtitle">⚙ 密码设置</div>
  <div class="mdesc">写入 data/sc_config.json，留空不修改。</div>
  <label class="fl">超级密码（admin.php）</label><input type="password" id="pw_admin" placeholder="留空不修改" style="margin-bottom:8px">
  <label class="fl">看板主密码</label><input type="password" id="pw_dash" placeholder="留空不修改" style="margin-bottom:8px">
  <label class="fl">看板辅助密码 1</label><input type="password" id="pw_dash2" placeholder="留空不修改" style="margin-bottom:8px">
  <label class="fl">看板辅助密码 2</label><input type="password" id="pw_dash3" placeholder="留空不修改" style="margin-bottom:8px">
  <div class="hint">辅助密码可访问完整看板，但无法进入管理后台。</div>
  <div class="mbtns"><button class="btn btn-s" onclick="closeModal('pwModal')">取消</button><button class="btn btn-g" onclick="savePw()">保存</button></div>
</div></div>

<!-- ── 移动/复制弹窗 ── -->
<div class="ov" id="opModal"><div class="mbox">
  <div class="mtitle" id="opTitle">移动场次</div>
  <div class="mdesc" id="opDesc"></div>
  <label class="fl">目标日期 YYMMDD</label>
  <input type="text" id="opDate" maxlength="6" placeholder="如 260512">
  <div class="preview" id="opPreview" style="margin-top:10px;display:none"></div>
  <div class="mbtns">
    <button class="btn btn-s" onclick="closeModal('opModal')">取消</button>
    <button class="btn btn-s" onclick="doOpPrev()">预览</button>
    <button class="btn btn-g" id="opExecBtn" onclick="doOpExec()" disabled>执行</button>
  </div>
</div></div>

<!-- ── 重命名弹窗 ── -->
<div class="ov" id="rnModal"><div class="mbox" style="width:300px">
  <div class="mtitle">编辑宴会名称</div>
  <input type="hidden" id="rnCode">
  <input type="text" id="rnName" maxlength="30" placeholder="宴会名称" style="margin-top:10px">
  <div class="mbtns"><button class="btn btn-s" onclick="closeModal('rnModal')">取消</button><button class="btn btn-g" onclick="doRename()">保存</button></div>
</div></div>

<!-- ── 导入弹窗 ── -->
<div class="ov" id="importModal"><div class="mbox" style="width:300px">
  <div class="mtitle">导入 JSON 到场次</div>
  <div class="mdesc" id="importDesc">将外部 JSON 文件注入到该场次</div>
  <input type="hidden" id="importCode">
  <input type="file" id="importFile" accept=".json" style="display:none" onchange="doImport()">
  <div id="importInfo" class="hint" style="margin-top:8px"></div>
  <div class="mbtns">
    <button class="btn btn-s" onclick="closeModal('importModal')">关闭</button>
    <button class="btn btn-g" onclick="document.getElementById('importFile').click()">选择 JSON 文件</button>
  </div>
</div></div>

<!-- ── 孤立目录操作弹窗 ── -->
<div class="ov" id="orphanModal"><div class="mbox">
  <div class="mtitle">处理孤立目录</div>
  <div id="orphanModalBody"></div>
  <div class="mbtns">
    <button class="btn btn-s" onclick="closeModal('orphanModal')">关闭</button>
    <button class="btn btn-r" id="orphanDelBtn" onclick="doOrphanDel()">删除目录</button>
    <button class="btn btn-g" id="orphanAddBtn" onclick="doOrphanAdd()">添加到记录</button>
  </div>
</div></div>

<div id="statTip"></div>

<script>
const API='admin.php?api=1';
const ALPHA='346789ACDEFGHJKLMNPQRSTUVWXY';
const CRC_T=(()=>{const t=new Uint32Array(256);for(let i=0;i<256;i++){let c=i;for(let j=0;j<8;j++)c=(c&1)?0xEDB88320^(c>>>1):(c>>>1);t[i]=c;}return t;})();
function crc32(s){let c=0xFFFFFFFF;for(let i=0;i<s.length;i++)c=CRC_T[(c^s.charCodeAt(i))&0xFF]^(c>>>8);return(c^0xFFFFFFFF)>>>0;}
function calcCode(d6,ltr){const h=crc32(d6+ltr);return d6+ltr+ALPHA[Math.floor(h/28)%28]+ALPHA[h%28];}

let SESSIONS=<?=json_encode($sessionsJS,JSON_UNESCAPED_UNICODE)?>;
const CFG=<?=json_encode($cfg['autoSave'],JSON_UNESCAPED_UNICODE)?>;
const BASE_URL=<?=json_encode($baseUrl)?>;
let selCodes=new Set(),filterSt='all',filterYr='',searchQ='',curPage=1,curMaintPage=1;
const PER=20,PER_M=15;
let pendingOp=null,maintMode=null,maintData=[],maintOrphanCur=null;

// ── Toast ─────────────────────────────────────────────────────────────────
function toast(msg,type='ok',dur=2500){const w=document.getElementById('tw');const d=document.createElement('div');d.className=`toast ${type}`;d.textContent=msg;w.appendChild(d);setTimeout(()=>d.classList.add('show'),10);setTimeout(()=>{d.classList.remove('show');setTimeout(()=>d.remove(),300);},dur);}

// ── API ───────────────────────────────────────────────────────────────────
async function api(data){const fd=new FormData();Object.entries(data).forEach(([k,v])=>{if(Array.isArray(v))v.forEach(x=>fd.append(k+'[]',x));else fd.append(k,v);});return fetch(API,{method:'POST',body:fd}).then(r=>r.json()).catch(()=>({error:'网络错误'}));}

// ── Card / Sub toggle ─────────────────────────────────────────────────────
function toggleCard(h){h.classList.toggle('open');}
function toggleSub(h){h.classList.toggle('open');}

// ── Modal ─────────────────────────────────────────────────────────────────
function openModal(id){document.getElementById(id).classList.add('show');}
function closeModal(id){document.getElementById(id).classList.remove('show');}
document.querySelectorAll('.ov').forEach(ov=>ov.addEventListener('click',e=>{if(e.target===ov)ov.classList.remove('show');}));

// ── Toggle helper ─────────────────────────────────────────────────────────
function togClick(id){const el=document.getElementById(id),tr=document.getElementById(id+'_t');const v=el.value==='1'?'0':'1';el.value=v;tr.className='tog-t'+(v==='1'?' on':'');}

// ── Instant code calculator ───────────────────────────────────────────────
function calcLive(){
  const d6=document.getElementById('calcD6').value.trim(),ltr=document.getElementById('calcLtr').value.trim().toUpperCase();
  const res=document.getElementById('calcResult'),info=document.getElementById('calcInfo'),ro=document.getElementById('roLink'),wb=document.getElementById('calcWriteBtn');
  if(d6.length!==6||!/^\d{6}$/.test(d6)||!ltr.match(/^([A-Z]|[a-z][A-Z])$/)){res.textContent='──────';res.className='code';info.textContent='';ro.textContent='';wb.disabled=true;return;}
  const code=calcCode(d6,ltr);const ex=SESSIONS.find(s=>s.code===code);
  res.textContent=code;res.className='code '+(ex?ex.status:'active');
  ro.innerHTML=`只读码：<span class="mono dim">${code.slice(0,-2)}</span> · <span class="dim">${BASE_URL}?auth=${code.slice(0,-2)}</span>`;
  if(ex){info.innerHTML=`<span class="gold">⚠ 已存在</span> · ${ex.status} · ${ex.note||'未命名'}`;wb.disabled=true;}
  else{info.innerHTML=`<span style="color:var(--green)">✓ 可用</span>`;wb.disabled=false;}
}
function copyCalc(){const c=document.getElementById('calcResult').textContent;if(!c.includes('─'))navigator.clipboard.writeText(c).then(()=>toast('已复制 '+c));}
async function writeCalc(){
  const d6=document.getElementById('calcD6').value.trim(),ltr=document.getElementById('calcLtr').value.trim().toUpperCase(),note=document.getElementById('calcNote').value.trim();
  const r=await api({action:'gen',d6,ltr,note});if(r.ok){toast('已写入 '+r.code);await reload();calcLive();}else toast(r.error,'err');
}

// ── Sessions render ───────────────────────────────────────────────────────
function escH(s){if(!s)return'';return s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');}
function getFiltered(){const q=searchQ.toLowerCase();return SESSIONS.filter(s=>{if(filterSt!=='all'&&s.status!==filterSt)return false;if(filterYr&&!s.code.startsWith(filterYr))return false;if(q&&!s.code.toLowerCase().includes(q)&&!s.note.toLowerCase().includes(q))return false;return true;});}
function setFilter(btn,st){document.querySelectorAll('#stSeg .seg-btn').forEach(b=>b.classList.remove('on'));btn.classList.add('on');filterSt=st;curPage=1;renderSess();}
function selAll(){getFiltered().slice((curPage-1)*PER,curPage*PER).forEach(s=>selCodes.add(s.code));renderSess();updateBbar();}
function clearSel(){selCodes.clear();renderSess();updateBbar();}
function updateBbar(){const n=selCodes.size;document.getElementById('bbar').classList.toggle('show',n>0);document.getElementById('bbarN').textContent=n;}
function asBadge(v){if(v===null||v===undefined)return`<span class="as-badge inh" title="继承全局设置">继承</span>`;return v?`<span class="as-badge on" title="点击切换为手动">自动</span>`:`<span class="as-badge off" title="点击切换为自动">手动</span>`;}
function relTime(ts){if(!ts)return'—';const d=Math.floor(Date.now()/1000)-ts;if(d<60)return'刚刚';if(d<3600)return Math.floor(d/60)+'分前';if(d<86400)return Math.floor(d/3600)+'时前';if(d<86400*30)return Math.floor(d/86400)+'天前';return new Date(ts*1000).toLocaleDateString('zh-CN',{month:'numeric',day:'numeric'});}

const stCache={};
async function loadSt(code){if(stCache[code])return;const r=await api({action:'stat',code});if(r.ok){stCache[code]=r.st;const fc=document.getElementById('fc_'+code);const et=document.getElementById('et_'+code);if(fc)fc.textContent=r.st.files;if(et)et.textContent=r.st.editTs?relTime(r.st.editTs):'—';}}

function renderSess(){
  searchQ=document.getElementById('sessSearch').value;filterYr=document.getElementById('sessYear').value;
  const filtered=getFiltered(),total=filtered.length,pages=Math.max(1,Math.ceil(total/PER));
  curPage=Math.min(curPage,pages);const items=filtered.slice((curPage-1)*PER,curPage*PER);
  document.getElementById('sessTb').innerHTML=items.map(s=>{
    const d6=s.code.substring(0,6),date=`20${d6.substring(0,2)}-${d6.substring(2,2+2)}-${d6.substring(4)}`;
    const sel=selCodes.has(s.code);
    return`<tr><td><input type="checkbox" class="sess-cb" ${sel?'checked':''} onchange="toggleSel('${s.code}',this)"></td>
      <td><span class="code ${s.status}" style="cursor:pointer" onmouseenter="showTip(event,'${s.code}')" onmouseleave="hideTip()" onclick="navigator.clipboard.writeText('${s.code}').then(()=>toast('已复制 ${s.code}'))">${s.code}</span></td>
      <td><span class="sbadge ${s.status}">${{active:'有效',archived:'存档',hidden:'隐藏'}[s.status]||s.status}</span></td>
      <td class="mono dim" style="font-size:.73rem">${date}</td>
      <td class="ellip" style="max-width:130px;color:#C0B880" title="${escH(s.note)}">${escH(s.note)||'<span class="dim" style="font-style:italic">未命名</span>'}</td>
      <td id="fc_${s.code}" class="dim">—</td>
      <td id="et_${s.code}" class="dim">—</td>
      <td onclick="toggleSessAS('${s.code}')" style="cursor:pointer">${asBadge(s.autoSave)}</td>
      <td style="display:flex;gap:4px;flex-wrap:nowrap">
        <a href="${BASE_URL}?auth=${s.code}" target="_blank" class="btn btn-s" style="font-size:.68rem;padding:2px 6px;text-decoration:none">进入</a>
        <button class="btn btn-s" style="font-size:.68rem;padding:2px 6px" onclick="openRename('${s.code}','${escH(s.note)}')">改名</button>
        <button class="btn btn-s" style="font-size:.68rem;padding:2px 6px" onclick="openImport('${s.code}')">导入</button>
      </td>
    </tr>`;
  }).join('');
  const pg=document.getElementById('sessPager');
  pg.innerHTML=total?`第${curPage}/${pages}页 · 共${total}场次 &nbsp;<button class="btn btn-s" style="padding:2px 8px" onclick="goPage(${curPage-1})" ${curPage<=1?'disabled':''}>‹</button><button class="btn btn-s" style="padding:2px 8px" onclick="goPage(${curPage+1})" ${curPage>=pages?'disabled':''}>›</button>`:'暂无场次';
  items.forEach(s=>loadSt(s.code));
}
function goPage(p){curPage=p;renderSess();}
function toggleSel(code,cb){if(cb.checked)selCodes.add(code);else selCodes.delete(code);updateBbar();}

// Hover tip
let tipTimer=null;
function showTip(e,code){clearTimeout(tipTimer);tipTimer=setTimeout(async()=>{let st=stCache[code];if(!st){const r=await api({action:'stat',code});if(r.ok){st=r.st;stCache[code]=st;}}if(!st)return;const tip=document.getElementById('statTip');tip.innerHTML=`<div class="gold" style="font-weight:600;margin-bottom:4px">${code}</div>桌：<b>${st.tables}</b> &nbsp;宾客：<b>${st.guests}</b><br>存档：<b>${st.files}</b>个 &nbsp;${st.sizeStr||'—'}<br>最后：<b>${st.editTs?relTime(st.editTs):'—'}</b>`;tip.style.display='block';tip.style.left=Math.min(e.pageX+12,window.innerWidth-180)+'px';tip.style.top=(e.pageY-10)+'px';},300);}
function hideTip(){clearTimeout(tipTimer);document.getElementById('statTip').style.display='none';}

// ── Batch ops ─────────────────────────────────────────────────────────────
async function batchOp(op){const codes=[...selCodes];if(!codes.length)return;if(op==='del'&&!confirm(`确认删除 ${codes.length} 个场次及其所有备份？`))return;const r=await api({action:op,codes});if(r.ok){toast(`完成 ${r.n||codes.length} 个`);clearSel();await reload();}else toast(r.error,'err');}
function openOpModal(op){pendingOp=op;document.getElementById('opTitle').textContent=op==='move'?'移动场次':'复制场次';document.getElementById('opDesc').textContent=`${selCodes.size} 个场次 → 新日期`;document.getElementById('opPreview').style.display='none';document.getElementById('opExecBtn').disabled=true;document.getElementById('opDate').value='';openModal('opModal');}
async function doOpPrev(){const nd=document.getElementById('opDate').value.trim(),codes=[...selCodes];const r=await api({action:pendingOp,codes,nd,prev:'1'});const el=document.getElementById('opPreview');el.style.display='block';el.innerHTML='';if(r.res){r.res.forEach(([o,n])=>el.innerHTML+=`<span class="ok">${o}</span> <span class="arr">→</span> <span class="ok">${n}</span>\n`);}if(r.errs?.length)r.errs.forEach(e=>el.innerHTML+=`<span class="err">⚠ ${e}</span>\n`);document.getElementById('opExecBtn').disabled=!r.res?.length;}
async function doOpExec(){const nd=document.getElementById('opDate').value.trim(),codes=[...selCodes];const r=await api({action:pendingOp,codes,nd,prev:'0'});if(r.ok){toast(`完成 ${r.res?.length||0} 个`);closeModal('opModal');clearSel();await reload();}else toast(r.error,'err');}

// ── Rename ────────────────────────────────────────────────────────────────
function openRename(code,name){document.getElementById('rnCode').value=code;document.getElementById('rnName').value=name;openModal('rnModal');}
async function doRename(){const code=document.getElementById('rnCode').value,note=document.getElementById('rnName').value.trim();const r=await api({action:'rename',code,note});if(r.ok){const s=SESSIONS.find(x=>x.code===code);if(s)s.note=r.note;closeModal('rnModal');renderSess();toast('已保存');}else toast(r.error,'err');}

// ── Import ────────────────────────────────────────────────────────────────
function openImport(code){document.getElementById('importCode').value=code;document.getElementById('importDesc').textContent=`导入 JSON 到场次 ${code}`;document.getElementById('importInfo').textContent='';openModal('importModal');}
async function doImport(){const code=document.getElementById('importCode').value,file=document.getElementById('importFile').files[0];if(!file)return;const fd=new FormData();fd.append('action','import');fd.append('code',code);fd.append('jf',file);const r=await fetch(API,{method:'POST',body:fd}).then(x=>x.json());const el=document.getElementById('importInfo');if(r.ok){el.innerHTML=`<span style="color:var(--green)">✓ ${r.file}</span>`;await reload();}else el.innerHTML=`<span class="red">❌ ${r.error}</span>`;document.getElementById('importFile').value='';}

// ── Export ZIP ────────────────────────────────────────────────────────────
async function doExportZip(){const codes=[...selCodes];if(!codes.length){toast('请先选择场次','err');return;}const fd=new FormData();fd.append('action','exportZip');codes.forEach(c=>fd.append('codes[]',c));const res=await fetch(API,{method:'POST',body:fd});if(res.ok&&res.headers.get('Content-Type')?.includes('zip')){const blob=await res.blob();const a=document.createElement('a');a.href=URL.createObjectURL(blob);a.download='seatcard-export-'+new Date().toISOString().slice(0,10)+'.zip';a.click();}else{const j=await res.json().catch(()=>({}));toast(j.error||'导出失败','err');}}

// ── Auto-save toggle ──────────────────────────────────────────────────────
async function toggleSessAS(code){const s=SESSIONS.find(x=>x.code===code);if(!s)return;const nv=s.autoSave===true?false:true;s.autoSave=nv;renderSess();await api({action:'sessAS',code,en:nv?'1':'0'});}

// ── Batch generate ────────────────────────────────────────────────────────
async function batchGenPrev(){const d6=document.getElementById('bgD6').value.trim(),cnt=parseInt(document.getElementById('bgCnt').value)||1,startL=document.getElementById('bgStartL').value.trim().toUpperCase(),note=document.getElementById('bgNote').value.trim();const r=await api({action:'batchGen',d6,cnt,startL,note,prev:'1'});const el=document.getElementById('bgPreview');el.style.display='block';el.innerHTML='';if(r.res)r.res.forEach(({code,note:n})=>el.innerHTML+=`<span class="ok">${code}</span>${n?' · '+n:''}\n`);if(r.errs?.length)r.errs.forEach(e=>el.innerHTML+=`<span class="err">⚠ ${e}</span>\n`);document.getElementById('bgExecBtn').disabled=!r.res?.length;}
async function batchGenExec(){const d6=document.getElementById('bgD6').value.trim(),cnt=parseInt(document.getElementById('bgCnt').value)||1,startL=document.getElementById('bgStartL').value.trim().toUpperCase(),note=document.getElementById('bgNote').value.trim();const r=await api({action:'batchGen',d6,cnt,startL,note,prev:'0'});if(r.ok){toast(`已创建 ${r.res?.length||0} 个场次`);document.getElementById('bgPreview').style.display='none';document.getElementById('bgExecBtn').disabled=true;await reload();}else toast(r.error,'err');}

// ── Maintenance ───────────────────────────────────────────────────────────
const EST={orphan:n=>(0.1+n*0.02).toFixed(1),missing:n=>(0.05+n*0.01).toFixed(1),storage:n=>(0.1+n*0.05).toFixed(1),summary:n=>(0.2+n*0.15).toFixed(1)};
const MAINT_HEADS={
  orphan:'<tr><th>目录名</th><th>文件数</th><th>大小</th><th>推断日期</th><th>操作</th></tr>',
  missing:'<tr><th>授权码</th><th>状态</th><th>名称</th><th>创建时间</th><th>说明</th></tr>',
  storage:'<tr><th>授权码</th><th>名称</th><th>状态</th><th>文件数</th><th>大小</th></tr>',
  summary:'<tr><th>授权码</th><th>名称</th><th>桌数</th><th>宾客数</th><th>存档数</th><th>最后编辑</th></tr>'
};
function updateEstimates(){const n=SESSIONS.length;Object.keys(EST).forEach(k=>{const el=document.getElementById('est_'+k);if(el)el.dataset.full=el.textContent;document.getElementById('est_'+k).textContent=el?.dataset?.full?.split('·')[0]?.trim()||'';});['orphan','missing','storage','summary'].forEach(k=>{const el=document.getElementById('est_'+k);if(el)el.textContent+=' · 预估约 '+EST[k](n)+'s';});}
async function runMaint(mode){
  maintMode=mode;maintData=[];curMaintPage=1;
  document.querySelectorAll('.maint-btn').forEach(b=>b.classList.remove('active'));
  document.getElementById('mb_'+mode).classList.add('active');
  const res=document.getElementById('maintResult');res.classList.add('show');
  document.getElementById('maintThead').innerHTML=MAINT_HEADS[mode];
  document.getElementById('maintTb').innerHTML='<tr><td colspan="6" class="dim" style="padding:14px;text-align:center">加载中…</td></tr>';
  document.getElementById('maintPager').innerHTML='';
  const actMap={orphan:'orphan',missing:'missing',storage:'storage',summary:'summary'};
  const r=await api({action:actMap[mode]});
  if(!r.ok){document.getElementById('maintTb').innerHTML=`<tr><td colspan="6" class="red" style="padding:10px">${r.error||'请求失败'}</td></tr>`;return;}
  if(mode==='orphan')maintData=r.orphans||[];
  else if(mode==='missing')maintData=r.res||[];
  else if(mode==='storage')maintData=r.stats||[];
  else if(mode==='summary')maintData=r.res||[];
  renderMaint();
}
function renderMaint(){
  const total=maintData.length,pages=Math.max(1,Math.ceil(total/PER_M));
  curMaintPage=Math.min(curMaintPage,pages);
  const items=maintData.slice((curMaintPage-1)*PER_M,curMaintPage*PER_M);
  const tb=document.getElementById('maintTb');
  if(!items.length){tb.innerHTML='<tr><td colspan="6" class="dim" style="padding:14px;text-align:center">无数据</td></tr>';document.getElementById('maintPager').innerHTML='';return;}
  if(maintMode==='orphan'){
    tb.innerHTML=items.map(o=>{
      const d6=o.dir.substring(0,6),date=d6.length===6?`20${d6.substring(0,2)}-${d6.substring(2,2+2)}-${d6.substring(4)}`:'—';
      return`<tr><td class="mono gold">${o.dir}</td><td>${o.files}</td><td class="dim">${o.size}</td><td class="dim">${date}</td>
        <td style="display:flex;gap:4px"><button class="btn btn-r" style="font-size:.68rem;padding:2px 7px" onclick="openOrphanModal('${o.dir}')">处理…</button></td></tr>`;}).join('');
  }else if(maintMode==='missing'){
    tb.innerHTML=items.map(e=>`<tr><td><span class="code ${e.status}">${e.code}</span></td><td><span class="sbadge ${e.status}">${{active:'有效',archived:'存档',hidden:'隐藏'}[e.status]||e.status}</span></td><td class="dim ellip" style="max-width:120px">${escH(e.note)||'—'}</td><td class="dim" style="font-size:.72rem">${e.createdAt?.substring(0,10)||'—'}</td><td class="hint">⚠ 目录不存在，可能是从未保存或已手动删除。无需额外操作。</td></tr>`).join('');
  }else if(maintMode==='storage'){
    tb.innerHTML=items.map(s=>`<tr><td><span class="code ${s.status}">${s.code}</span></td><td class="dim ellip" style="max-width:120px">${escH(s.note)||'—'}</td><td><span class="sbadge ${s.status}">${{active:'有效',archived:'存档',hidden:'隐藏'}[s.status]||s.status}</span></td><td>${s.files}</td><td class="gold">${s.str}</td></tr>`).join('');
  }else if(maintMode==='summary'){
    tb.innerHTML=items.map(s=>`<tr><td><span class="code active">${s.code}</span></td><td class="dim ellip" style="max-width:100px">${escH(s.note)||'—'}</td><td class="gold">${s.tables}</td><td class="gold">${s.guests}</td><td>${s.files}</td><td class="dim">${s.editTs?relTime(s.editTs):'—'}</td></tr>`).join('');
  }
  document.getElementById('maintPager').innerHTML=total>PER_M?`第${curMaintPage}/${pages}页 · 共${total}条 &nbsp;<button class="btn btn-s" style="padding:2px 8px" onclick="goMaint(${curMaintPage-1})" ${curMaintPage<=1?'disabled':''}>‹</button><button class="btn btn-s" style="padding:2px 8px" onclick="goMaint(${curMaintPage+1})" ${curMaintPage>=pages?'disabled':''}>›</button>`:'';
}
function goMaint(p){curMaintPage=p;renderMaint();}

// ── Orphan modal ──────────────────────────────────────────────────────────
function openOrphanModal(dir){
  maintOrphanCur=dir;const d6=dir.substring(0,6);const date=d6.length===6?`20${d6.substring(0,2)}-${d6.substring(2,2+2)}-${d6.substring(4)}`:'—';
  const o=maintData.find(x=>x.dir===dir)||{};
  document.getElementById('orphanModalBody').innerHTML=`<div class="mdesc">目录：<span class="mono gold">${dir}</span><br>文件数：${o.files||0} &nbsp;大小：${o.size||'—'}<br>推断日期：${date}</div>
    <div class="hint" style="margin-bottom:10px">
      <b>删除目录</b>：永久删除该目录及其所有文件，不可恢复。<br>
      <b>添加到记录</b>：将该目录以「有效」状态写入 auth.json，日期从目录名推断（${date}），宴会名称留空。
    </div>`;
  openModal('orphanModal');
}
async function doOrphanDel(){if(!maintOrphanCur||!confirm(`确认删除目录 ${maintOrphanCur}？`))return;const r=await api({action:'delOrphan',dir:maintOrphanCur});if(r.ok){toast('已删除 '+maintOrphanCur);closeModal('orphanModal');maintData=maintData.filter(x=>x.dir!==maintOrphanCur);maintOrphanCur=null;renderMaint();}else toast(r.error,'err');}
async function doOrphanAdd(){if(!maintOrphanCur)return;const r=await api({action:'addOrphan',dir:maintOrphanCur});if(r.ok){toast(`已添加记录 ${r.code}`);closeModal('orphanModal');maintData=maintData.filter(x=>x.dir!==maintOrphanCur);maintOrphanCur=null;await reload();renderMaint();}else toast(r.error,'err');}

// ── Config ────────────────────────────────────────────────────────────────
async function saveYears(){const r=await api({action:'saveCfg',ys:document.getElementById('cfgYS').value,ye:document.getElementById('cfgYE').value});if(r.ok)toast('年份范围已保存');else toast(r.error,'err');}
async function savePw(){const d={action:'saveCfg',admin_pass:document.getElementById('pw_admin').value,dash_pass:document.getElementById('pw_dash').value,dash_pass2:document.getElementById('pw_dash2').value,dash_pass3:document.getElementById('pw_dash3').value};const r=await api(d);if(r.ok){toast('密码已保存');closeModal('pwModal');['pw_admin','pw_dash','pw_dash2','pw_dash3'].forEach(id=>document.getElementById(id).value='');}else toast(r.error,'err');}
async function loadLog(){const r=await api({action:'loginLog'});document.getElementById('logHint').style.display='none';const tb=document.getElementById('logTb'),t=document.getElementById('logTable');t.style.display='table';tb.innerHTML=(r.log||[]).map(l=>`<tr><td class="mono dim" style="white-space:nowrap">${new Date(l.ts*1000).toLocaleString('zh-CN')}</td><td class="mono dim">${l.ip}</td><td class="dim ellip" style="max-width:220px" title="${escH(l.ua)}">${escH(l.ua)}</td></tr>`).join('')||'<tr><td colspan="3" class="dim">暂无记录</td></tr>';}

// ── Auto-save config ──────────────────────────────────────────────────────
const MAJOR_OPS=[
  {key:'batchSeat',label:'批量落座',rec:true},{key:'importList',label:'导入名单/CSV',rec:true},
  {key:'addTable',label:'新增桌子',rec:true},{key:'deleteTable',label:'删除桌子',rec:true},
  {key:'deleteGuest',label:'删除宾客',rec:true},{key:'seat',label:'单次落座/取消',rec:false},
  {key:'addGuest',label:'新增单个宾客',rec:false},{key:'moveTable',label:'桌子大幅移动',rec:false},
];
function renderOpsGrid(){
  const ops=CFG.majorOps||{};
  document.getElementById('opsGrid').innerHTML=MAJOR_OPS.map(o=>{
    const on=ops[o.key]!==undefined?ops[o.key]:o.rec;
    return`<label class="op-item${o.rec?' rec':''}" title="${o.rec?'推荐开启':''}">
      <input type="checkbox" id="op_${o.key}" ${on?'checked':''}>
      <span>${o.label}</span>
      ${o.rec?'<span class="rec-tag">推荐</span>':''}
    </label>`;}).join('');}
async function saveASCfg(){
  const majorOps={};MAJOR_OPS.forEach(o=>{majorOps[o.key]=document.getElementById('op_'+o.key).checked;});
  const r=await api({action:'saveCfg',as_on:document.getElementById('asGlobal').value,as_iv:document.getElementById('asIv').value,as_mi:document.getElementById('asMi').value,as_idle:document.getElementById('asIdle').value,as_maj:document.getElementById('asMaj').value,majorOps:JSON.stringify(majorOps)});
  if(r.ok)toast('自动保存策略已保存');else toast(r.error,'err');}

// ── Reload sessions ───────────────────────────────────────────────────────
async function reload(){const r=await fetch(location.href).then(x=>x.text());const m=r.match(/SESSIONS=(\[[\s\S]*?\]);/);if(m)try{SESSIONS=JSON.parse(m[1]);}catch(e){}Object.keys(stCache).forEach(k=>delete stCache[k]);calcLive();renderSess();document.getElementById('sessCount').textContent=SESSIONS.length+' 场次';}
async function doLogout(){await api({action:'logout'});location.href='admin.php';}

// ── Init ──────────────────────────────────────────────────────────────────
updateEstimates();renderOpsGrid();calcLive();renderSess();
</script>
</body></html>
