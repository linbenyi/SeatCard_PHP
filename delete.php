<?php
/**
 * SeatCard delete.php
 * DELETE ?filename=xxx&auth=CODE
 * 只允许删除 data/{auth}/ 目录下的 .json 文件
 */
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');

define('BASE_DATA_DIR', __DIR__ . '/data/');

// ── 1. 格式校验 ──
function scCheckFormat($code){
    return (bool) preg_match('/^\d{6}([A-Z]|[a-z][A-Z])[A-Z0-9]{2}$/',$code); // 9位 YYMMDDXcc 或 10位 YYMMDDaXcc
}
function scNormalize($raw) {
    $s = trim($raw);
    $len = strlen($s);
    if ($len < 7) return strtoupper($s);
    return strtoupper(substr($s,0,6)) . $s[6] . ($len > 7 ? strtoupper(substr($s,7)) : '');
}
$rawAuth = scNormalize($_GET['auth'] ?? $_GET['Auth'] ?? '');
if(!$rawAuth || !scCheckFormat($rawAuth)){
    echo json_encode(['ok'=>false,'error'=>'invalid auth format']);
    exit;
}
// ── 2. auth.json 注册校验（强制）──
$authJsonPath = __DIR__ . '/data/auth.json';
if(!file_exists($authJsonPath)){ echo json_encode(['ok'=>false,'error'=>'server not configured']); exit; }
$authList = json_decode(file_get_contents($authJsonPath),true)??[];
$registered = false;
foreach($authList as $e){ if(($e['code']??'')===$rawAuth){$registered=true;break;} }
if(!$registered){ echo json_encode(['ok'=>false,'error'=>'code not registered']); exit; }

// str_ends_with 兼容 PHP 7.x
if(!function_exists('str_ends_with')){
    function str_ends_with($str,$end){ return substr($str,-strlen($end))===$end; }
}

$authDir  = BASE_DATA_DIR . $rawAuth . '/';
$filename = basename($_GET['filename'] ?? '');

// 只允许 .json，不含路径穿越
if(!$filename || !str_ends_with($filename,'.json') || strpos($filename,'/')!==false || strpos($filename,'..')!==false){
    echo json_encode(['ok'=>false,'error'=>'invalid filename']);
    exit;
}

$fullPath = $authDir . $filename;
if(!file_exists($fullPath)){
    echo json_encode(['ok'=>false,'error'=>'file not found']);
    exit;
}

if(unlink($fullPath)){
    echo json_encode(['ok'=>true,'deleted'=>$filename]);
} else {
    echo json_encode(['ok'=>false,'error'=>'delete failed (permission?)']);
}
