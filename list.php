<?php
/**
 * list.php — 返回指定场次目录最近 N 个备份摘要
 * GET /list.php?n=3&auth=YYMMDDXcc
 * 严格隔离：只返回 data/{auth}/ 下的文件，不访问根目录
 * 双重校验：① 格式合法 ② 在 auth.json 中已注册
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
    echo json_encode(['ok' => false, 'error' => 'invalid auth format']);
    exit;
}

// ── 2. auth.json 注册校验（强制）──
$authJsonPath = __DIR__ . '/data/auth.json';
if (!file_exists($authJsonPath)) {
    echo json_encode(['ok' => false, 'error' => 'server not configured']); exit;
}
$authList = json_decode(file_get_contents($authJsonPath), true) ?? [];
$registered = false;
foreach ($authList as $entry) {
    if (($entry['code'] ?? '') === $rawAuth) { $registered = true; break; }
}
if (!$registered) {
    echo json_encode(['ok' => false, 'error' => 'code not registered', 'auth' => $rawAuth]); exit;
}

// ── 3. 严格限定在 data/{auth}/ 子目录 ──
$authDir = BASE_DATA_DIR . $rawAuth . '/';

// ── mode=slots: 返回5个固定存档位状态 ──
if (($_GET['mode'] ?? '') === 'slots') {
    $out = [];
    for ($i = 1; $i <= 5; $i++) {
        $p = $authDir . 'wedding-seating-slot-' . $i . '.json';
        if (!file_exists($p)) { $out[] = ['slot'=>$i,'empty'=>true]; continue; }
        $raw = file_get_contents($p);
        $s = $raw ? json_decode($raw, true) : null;
        $out[] = [
            'slot'        => $i,
            'empty'       => false,
            'savedAt'     => ($s['savedAt'] ?? date('c', filemtime($p))),
            'projectName' => ($s['projectName'] ?? ''),
            'tables'      => count($s['tables'] ?? []),
            'guests'      => count($s['guests'] ?? []),
        ];
    }
    echo json_encode(['ok' => true, 'slots' => $out, 'auth' => $rawAuth], JSON_UNESCAPED_UNICODE);
    exit;
}

$n = min(100, max(1, intval($_GET['n'] ?? 10)));

if (!is_dir($authDir)) {
    echo json_encode(['ok' => true, 'total' => 0, 'backups' => [], 'auth' => $rawAuth ?: null]);
    exit;
}

$files = glob($authDir . FILE_PREFIX . '*.json');
if (!$files) {
    echo json_encode(['ok' => true, 'total' => 0, 'backups' => [], 'auth' => $rawAuth ?: null]);
    exit;
}

// Sort descending (newest first)
usort($files, fn($a, $b) => strcmp($b, $a));
$total  = count($files);
$files  = array_slice($files, 0, $n);

$backups = [];
foreach ($files as $path) {
    $raw = file_get_contents($path);
    if (!$raw) continue;
    $s = json_decode($raw, true);
    if (!$s) continue;

    $backups[] = [
        'filename' => basename($path),
        'savedAt'  => $s['savedAt']  ?? date('c', filemtime($path)),
        'tables'   => count($s['tables']  ?? []),
        'guests'   => count($s['guests']  ?? []),
        'roomW'    => $s['roomW']    ?? 20,
        'roomH'    => $s['roomH']    ?? 20,
        'version'  => $s['version']  ?? 1,
    ];
}

echo json_encode([
    'ok'      => true,
    'total'   => $total,
    'backups' => $backups,
    'auth'    => $rawAuth ?: null,
], JSON_UNESCAPED_UNICODE);
