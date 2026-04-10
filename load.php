<?php
/**
 * load.php — 返回指定备份文件的完整内容
 * GET /load.php?filename=wedding-seating-backup-20260304-182304.json&auth=MMDDXn
 */
header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');

define('BASE_DATA_DIR', __DIR__ . '/data/');
define('FILE_PREFIX', 'wedding-seating-backup-');

// ── 1. 格式校验 ──
function scCheckFormat($code) {
    return (bool) preg_match('/^\d{6}([A-Z]|[a-z][A-Z])[A-Z0-9]{2}$/', $code); // 9位 YYMMDDXcc 或 10位 YYMMDDaXcc
}
function scNormalize($raw) {
    $s = trim($raw);
    $len = strlen($s);
    if ($len < 7) return strtoupper($s);
    return strtoupper(substr($s,0,6)) . $s[6] . ($len > 7 ? strtoupper(substr($s,7)) : '');
}
$rawAuth = scNormalize($_GET['auth'] ?? $_GET['Auth'] ?? '');
if (!$rawAuth || !scCheckFormat($rawAuth)) {
    http_response_code(403);
    echo json_encode(['error' => 'invalid auth format']);
    exit;
}
// ── 2. auth.json 注册校验（强制）──
$authJsonPath = __DIR__ . '/data/auth.json';
if (!file_exists($authJsonPath)) {
    http_response_code(503);
    echo json_encode(['error' => 'server not configured']); exit;
}
$authList = json_decode(file_get_contents($authJsonPath), true) ?? [];
$registered = false;
foreach ($authList as $entry) {
    if (($entry['code'] ?? '') === $rawAuth) { $registered = true; break; }
}
if (!$registered) {
    http_response_code(403);
    echo json_encode(['error' => 'code not registered']); exit;
}
// ── 3. 严格限定在 data/{auth}/ 子目录 ──
$authDir = BASE_DATA_DIR . $rawAuth . '/';

$filename = $_GET['filename'] ?? '';

// Security: only allow expected filename patterns (backup or slot), no path traversal
if (!preg_match('/^wedding-seating-backup-\d{8}-\d{6}\.json$/', $filename) &&
    !preg_match('/^wedding-seating-slot-[1-5]\.json$/', $filename)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid filename']);
    exit;
}

$path = $authDir . $filename;
if (!file_exists($path)) {
    http_response_code(404);
    echo json_encode(['error' => 'File not found']);
    exit;
}

$content = file_get_contents($path);
if ($content === false) {
    http_response_code(500);
    echo json_encode(['error' => 'Cannot read file']);
    exit;
}

// Return raw JSON content (already valid JSON)
echo $content;
