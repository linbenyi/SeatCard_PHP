<?php
/**
 * save.php — 保存座位卡状态
 * 写入 /data/{auth}/ — 严格场次隔离，auth 必须在 auth.json 中已注册
 * 服务器全量保留备份，不自动删除
 */
header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

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
    if (($entry['code'] ?? '') === $rawAuth) {
        $st = $entry['status'] ?? 'active';
        if ($st !== 'active') {
            http_response_code(403);
            echo json_encode(['error' => 'session is ' . $st]); exit;
        }
        $registered = true; break;
    }
}
if (!$registered) {
    http_response_code(403);
    echo json_encode(['error' => 'code not registered']); exit;
}
// ── setAS：更新场次级自动保存开关 ──
if (($_GET['action'] ?? '') === 'setAS') {
    $rawVal = trim($_POST['val'] ?? 'null');
    $val = json_decode($rawVal); // null | true | false
    $newList = [];
    foreach ($authList as &$e) {
        if (($e['code'] ?? '') === $rawAuth) {
            if ($val === null) unset($e['autoSave']);
            else $e['autoSave'] = (bool)$val;
        }
        $newList[] = $e;
    }
    unset($e);
    file_put_contents($authJsonPath, json_encode($newList, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX);
    echo json_encode(['ok' => true]); exit;
}

// ── 3. 严格限定在 data/{auth}/ 子目录 ──
$authDir = BASE_DATA_DIR . $rawAuth . '/';

// ── Validate request ──
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}
$body = file_get_contents('php://input');
if (!$body) {
    http_response_code(400);
    echo json_encode(['error' => 'Empty body']);
    exit;
}
$data = json_decode($body, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON: ' . json_last_error_msg()]);
    exit;
}

// ── Ensure data dir exists ──
if (!is_dir($authDir)) {
    if (!mkdir($authDir, 0755, true)) {
        http_response_code(500);
        echo json_encode(['error' => 'Cannot create data directory']);
        exit;
    }
}

// ── 确定写入文件名 ──
if (isset($_GET['slot'])) {
    $slot = intval($_GET['slot']);
    if ($slot < 1 || $slot > 5) {
        http_response_code(400);
        echo json_encode(['error' => 'slot must be 1-5']); exit;
    }
    $filename = 'wedding-seating-slot-' . $slot . '.json';
} else {
    $timestamp = date('Ymd-His');
    $filename  = FILE_PREFIX . $timestamp . '.json';
}
$filepath = $authDir . $filename;
$written  = file_put_contents($filepath, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
if ($written === false) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to write file']); exit;
}

$total = count(glob($authDir . FILE_PREFIX . '*.json') ?: []);

echo json_encode([
    'ok'       => true,
    'filename' => $filename,
    'savedAt'  => date('c'),
    'backups'  => $total,
    'auth'     => $rawAuth ?: null,
]);
