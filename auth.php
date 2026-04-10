<?php
/**
 * SeatCard Auth Manager — auth.php
 *
 * GET ?action=list&date=MMDD     → 返回该日期已生成的授权码列表
 * GET ?action=generate&date=MMDD → 自动排序生成下一个授权码并写入 auth.json
 * GET ?action=all                → 返回 auth.json 全部记录
 *
 * auth.json 格式：
 * [{"code":"260327AXX","date":"260327","createdAt":"2026-03-27T14:00:00+08:00","note":""}]
 * code 可为 9 位（YYMMDDXcc，单场次 A–Z）或 10 位（YYMMDDaXcc，双场次 aA–zZ）
 */
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');

define('AUTH_FILE', __DIR__ . '/data/auth.json');

function scReadAuth() {
    if (!file_exists(AUTH_FILE)) return [];
    $raw = file_get_contents(AUTH_FILE);
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function scWriteAuth($list) {
    $dir = dirname(AUTH_FILE);
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    $result = file_put_contents(
        AUTH_FILE,
        json_encode($list, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        LOCK_EX
    );
    if ($result === false) {
        echo json_encode(['error' => '无法写入 auth.json，请检查 data/ 目录权限']);
        exit;
    }
}

function scNextLetter($list, $date) {
    $used = [];
    foreach ($list as $e) {
        $code = $e['code'] ?? '';
        if (strpos($code, $date) !== 0) continue;
        $len = strlen($code);
        if ($len === 9) $used[] = $code[6];           // single: A-Z
        elseif ($len === 10) $used[] = $code[6].$code[7]; // double: aA-zZ
    }
    for ($c = ord('A'); $c <= ord('Z'); $c++) {
        $l = chr($c);
        if (!in_array($l, $used, true)) return $l;
    }
    for ($p = ord('a'); $p <= ord('z'); $p++) {
        for ($c = ord('A'); $c <= ord('Z'); $c++) {
            $l = chr($p).chr($c);
            if (!in_array($l, $used, true)) return $l;
        }
    }
    return null; // all 702 slots used
}

/**
 * 校验位：CRC32(YYMMDDX) 映射到28×28字符表
 * 字符表无混淆字符（去掉 0/1/2/5/B/I/O/Z）
 * CRC32 任意一位变化结果完全不可预测，抗枚举能力远强于 ASCII 求和
 */
define('SC_ALPHA', '346789ACDEFGHJKLMNPQRSTUVWXY'); // 28字符
function scChecksum($yymmddx) { // YYMMDDX (7位)
    $r = crc32($yymmddx); $h = ($r < 0) ? $r + 4294967296 : $r; // 转无符号
    return SC_ALPHA[intdiv($h, 28) % 28] . SC_ALPHA[$h % 28];
}

// ── 路由 ──
$action = trim($_GET['action'] ?? '');
$date   = preg_replace('/[^0-9]/', '', $_GET['date'] ?? ''); // 只保留数字，期望 6 位 YYMMDD

switch ($action) {

    // 列出某日期（或全部）的授权码
    case 'list':
        $list = scReadAuth();
        if (strlen($date) === 6) {
            $list = array_values(array_filter($list, function($e) use($date){ return strpos($e['code'] ?? '', $date) === 0; }));
        }
        echo json_encode($list);
        break;

    // 全部记录（管理用）
    case 'all':
        echo json_encode(scReadAuth());
        break;

    // 生成下一个授权码
    case 'generate':
        if (strlen($date) !== 6) {
            echo json_encode(['error' => '日期格式错误（需 6 位：YYMMDD）']);
            exit;
        }
        $list   = scReadAuth();
        $letter = scNextLetter($list, $date);
        if ($letter === null) {
            echo json_encode(['error' => '该日期 A–Z 及 aA–zZ 场次已全部用完（702场）']);
            exit;
        }
        $cc   = scChecksum($date . $letter); // 2位校验（基于 YYMMDDX 共7字符）
        $code = $date . $letter . $cc;       // 格式：YYMMDDXcc (9位)

        // 写入 auth.json
        $list[] = [
            'code'      => $code,
            'date'      => $date,
            'createdAt' => date('c'),
            'note'      => '',
            'status'    => 'active', // active | archived | deleted
        ];
        scWriteAuth($list);

        echo json_encode(['code' => $code, 'letter' => $letter, 'cc' => $cc]);
        break;

    // 删除授权码（需传完整9位 code，验证存在后移除）
    case 'delete':
        $code = strtoupper(trim($_GET['code'] ?? ''));
        if (!preg_match('/^\d{6}([A-Z]|[a-z][A-Z])[A-Z0-9]{2}$/', $code)) {
            echo json_encode(['error' => '编号格式错误']); exit;
        }
        $list = scReadAuth();
        $newList = array_values(array_filter($list, fn($e) => ($e['code'] ?? '') !== $code));
        if (count($newList) === count($list)) {
            echo json_encode(['error' => '编号不存在']); exit;
        }
        scWriteAuth($newList);
        echo json_encode(['ok' => true]);
        break;

    default:
        echo json_encode(['error' => 'unknown action']);
}
