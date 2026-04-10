<?php
/**
 * admin.php — SeatCard 场次管理后台
 * 支持单场次格式 YYMMDDXcc（9位，A–Z）与双场次格式 YYMMDDaXcc（10位，aA–zZ）
 * 功能：生成场次、查看/删除会话、注入备份文件
 */

// ── 简单管理密码（修改此处） ──
define('ADMIN_PASS', 'admin888');
define('BASE_DATA_DIR', __DIR__ . '/data/');
define('ADMIN_CFG_FILE', __DIR__ . '/data/sc_config.json');
define('SC_ALPHA_ADMIN', '346789ACDEFGHJKLMNPQRSTUVWXY'); // 28字符校验表

function adminGetCfg(){
    $def=['yearStart'=>2026,'yearEnd'=>2030];
    if(!file_exists(ADMIN_CFG_FILE))return $def;
    return array_merge($def,json_decode(file_get_contents(ADMIN_CFG_FILE),true)??[]);
}

// ── Auth 格式校验（9位 YYMMDDXcc 或 10位 YYMMDDaXcc）──
function scValidateAuth($code) {
    $code = trim($code); // do not strtoupper — lowercase prefix must be preserved for double sessions
    return (bool) preg_match('/^\d{6}([A-Z]|[a-z][A-Z])[A-Z0-9]{2}$/', $code);
}

// ── 新格式校验位计算（CRC32 → 无符号 → 映射28×28）──
function scMod28Checksum($yymmddx) {
    $alpha = SC_ALPHA_ADMIN;
    $r = crc32($yymmddx); $h = ($r < 0) ? $r + 4294967296 : $r;
    return $alpha[intdiv($h, 28) % 28] . $alpha[$h % 28];
}

// ── 生成新格式场次码（YYMMDDXcc 或 YYMMDDaXcc）──
function scGenerateSession($yy, $month, $day, $session) {
    $date = sprintf('%02d%02d%02d', $yy, $month, $day);
    $cc   = scMod28Checksum($date . $session);
    return $date . $session . $cc; // 9 or 10 chars
}

// ── 管理员密码门 ──
session_start();
$adminOk = ($_SESSION['sc_admin'] ?? false);
if (!$adminOk) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['pass'] ?? '') === ADMIN_PASS) {
        $_SESSION['sc_admin'] = true;
        $adminOk = true;
    }
}
if (!$adminOk) { showLoginPage(); exit; }

// ── 操作处理 ──
$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'generate') {
        $yy     = intval($_POST['year']   ?? intval(date('y')));
        $month  = intval($_POST['month']  ?? date('n'));
        $day    = intval($_POST['day']    ?? date('j'));
        $letterRaw = trim($_POST['letter'] ?? 'A');
        if (preg_match('/^[a-z][A-Z]$/', $letterRaw)) {
            $letter = $letterRaw;           // valid double session e.g. 'aA'
        } elseif (preg_match('/^[A-Za-z]$/', $letterRaw)) {
            $letter = strtoupper($letterRaw); // single letter, normalize to uppercase
        } else {
            $msg = '❌ 场次字母无效（单字母 A–Z 或双字母如 aA）';
            $letter = null;
        }
        if ($letter !== null) {
        if ($yy < 20 || $yy > 99 || $month < 1 || $month > 12 || $day < 1 || $day > 31) {
            $msg = '❌ 参数无效，请检查年/月/日';
        } else {
            $adminCfg = adminGetCfg();
            $fullYear = 2000 + $yy;
            if ($fullYear < $adminCfg['yearStart'] || $fullYear > $adminCfg['yearEnd']) {
                $msg = "❌ 年份 <strong>{$fullYear}</strong> 超出允许范围（{$adminCfg['yearStart']} – {$adminCfg['yearEnd']}）。请在看板「年份」设置中调整范围。";
            } else {
                $code = scGenerateSession($yy, $month, $day, $letter);
                $msg = "✅ 已注册场次编号 <strong>{$code}</strong>（数据目录在首次保存时自动创建）";
            }
        }
        }
    }

    if ($action === 'delete_session') {
        $code = strtoupper(trim($_POST['code'] ?? ''));
        if (scValidateAuth($code)) {
            $dir = BASE_DATA_DIR . $code . '/';
            if (is_dir($dir)) {
                array_map('unlink', glob($dir . '{,.[!.]}*', GLOB_BRACE));
                rmdir($dir);
                $msg = "🗑️ 已删除场次 <strong>{$code}</strong> 及其所有备份";
            }
        } else {
            $msg = '❌ 无效场次编号';
        }
    }

    // ── 注入：将根目录备份复制到指定场次目录 ──
    if ($action === 'inject') {
        $srcFile = basename(trim($_POST['srcfile'] ?? ''));
        $tgtCode = strtoupper(trim($_POST['tgtcode'] ?? ''));
        if (!$srcFile || !preg_match('/^wedding-seating-backup-\d{8}-\d{6}\.json$/', $srcFile)) {
            $msg = '❌ 无效源文件名';
        } elseif (!scValidateAuth($tgtCode)) {
            $msg = '❌ 无效目标场次编号';
        } else {
            $src = BASE_DATA_DIR . $srcFile;
            $tgtDir = BASE_DATA_DIR . $tgtCode . '/';
            if (!file_exists($src)) {
                $msg = "❌ 源文件不存在：{$srcFile}";
            } elseif (!is_dir($tgtDir) && !mkdir($tgtDir, 0755, true)) {
                $msg = "❌ 无法创建目录 data/{$tgtCode}/";
            } else {
                $dst = $tgtDir . $srcFile;
                if (copy($src, $dst)) {
                    $msg = "✅ 已将 <code>{$srcFile}</code> 注入到 <strong>data/{$tgtCode}/</strong>";
                } else {
                    $msg = "❌ 复制失败，请检查服务器权限";
                }
            }
        }
    }

    if ($action === 'logout') {
        $_SESSION['sc_admin'] = false;
        session_destroy();
        header('Location: admin.php');
        exit;
    }
}

// ── 读取所有场次会话（支持新9位 + 旧6位）──
$sessions = [];
if (is_dir(BASE_DATA_DIR)) {
    foreach (scandir(BASE_DATA_DIR) as $entry) {
        if ($entry === '.' || $entry === '..') continue;
        $dir = BASE_DATA_DIR . $entry . '/';
        if (!is_dir($dir) || !scValidateAuth($entry)) continue;

        $meta    = [];
        $metaF   = $dir . '_meta.json';
        if (file_exists($metaF)) $meta = json_decode(file_get_contents($metaF), true) ?? [];

        $backups = count(glob($dir . 'wedding-seating-backup-*.json') ?: []);
        // 格式 YYMMDDXcc (9位)
        $yy=$entry[0].$entry[1]; $mm=$entry[2].$entry[3]; $dd=$entry[4].$entry[5]; $x=$entry[6];
        $dateStr = "20{$yy}-{$mm}-{$dd}";
        $sessions[] = [
            'code'      => $entry,
            'date'      => $dateStr,
            'session'   => $x,
            'backups'   => $backups,
            'createdAt' => $meta['createdAt'] ?? '—',
            'note'      => $meta['note'] ?? '',
        ];
    }
}
// 按日期降序排列
usort($sessions, fn($a,$b)=>strcmp($b['date'].$b['session'], $a['date'].$a['session']));

// ── 根目录备份文件列表（旧无auth数据 / 待注入）──
$rootBackupFiles = glob(BASE_DATA_DIR . 'wedding-seating-backup-*.json') ?: [];
rsort($rootBackupFiles);
$rootBackupNames = array_map('basename', $rootBackupFiles);

// ── 今日建议场次编号 ──
$suggestCode = scGenerateSession(intval(date('y')), intval(date('m')), intval(date('d')), 'A');

function showLoginPage() { ?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<title>SeatCard 管理登录</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<style>
  *{box-sizing:border-box;margin:0;padding:0}
  body{font-family:'Noto Sans SC',sans-serif;background:#F5F0EB;display:flex;align-items:center;justify-content:center;min-height:100vh}
  .card{background:#fff;border-radius:16px;padding:40px 36px;width:340px;box-shadow:0 8px 32px rgba(0,0,0,.12)}
  h2{font-size:1.3rem;color:#3D2B1F;margin-bottom:24px;text-align:center}
  input[type=password]{width:100%;padding:10px 14px;border:1.5px solid #D4C5B0;border-radius:8px;font-size:1rem;outline:none;transition:border .2s}
  input[type=password]:focus{border-color:#8B5E3C}
  button{margin-top:16px;width:100%;padding:11px;background:#8B5E3C;color:#fff;border:none;border-radius:8px;font-size:1rem;cursor:pointer;font-weight:600}
  button:hover{background:#6D4A2F}
  .hint{margin-top:12px;font-size:.82rem;color:#999;text-align:center}
</style>
</head>
<body>
<div class="card">
  <h2>🔐 SeatCard 管理后台</h2>
  <form method="POST">
    <input type="password" name="pass" placeholder="管理员密码" autofocus>
    <button type="submit">登录</button>
  </form>
  <p class="hint">仅管理员使用</p>
</div>
</body>
</html>
<?php }

// ── 主页面 ──
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<title>SeatCard 管理后台</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<style>
  *{box-sizing:border-box;margin:0;padding:0}
  body{font-family:'Noto Sans SC',system-ui,sans-serif;background:#F5F0EB;color:#3D2B1F;min-height:100vh}
  header{background:#3D2B1F;color:#F5EFE6;padding:0 28px;height:56px;display:flex;align-items:center;justify-content:space-between}
  header h1{font-size:1.1rem;font-weight:700;letter-spacing:.04em}
  header a{color:#D4C5B0;font-size:.85rem;text-decoration:none}
  header a:hover{color:#fff}
  .wrap{max-width:860px;margin:32px auto;padding:0 20px}
  .card{background:#fff;border-radius:14px;padding:28px;margin-bottom:24px;box-shadow:0 2px 12px rgba(0,0,0,.07)}
  h2{font-size:1.05rem;font-weight:700;color:#3D2B1F;margin-bottom:18px;padding-bottom:10px;border-bottom:1.5px solid #EDE4D6}
  .msg{padding:10px 16px;border-radius:8px;margin-bottom:20px;font-size:.92rem;background:#EBF5EB;border:1px solid #B8D8B8}
  .grid{display:grid;grid-template-columns:1fr 1fr 1fr auto;gap:10px;align-items:end}
  label{font-size:.82rem;color:#7A6655;display:block;margin-bottom:4px}
  input[type=number],input[type=text],select{width:100%;padding:8px 10px;border:1.5px solid #D4C5B0;border-radius:8px;font-size:.95rem;outline:none;background:#FDFAF7}
  input:focus,select:focus{border-color:#8B5E3C}
  .btn{padding:9px 18px;border:none;border-radius:8px;font-size:.9rem;cursor:pointer;font-weight:600;white-space:nowrap}
  .btn-primary{background:#8B5E3C;color:#fff}
  .btn-primary:hover{background:#6D4A2F}
  .btn-danger{background:#C0392B;color:#fff}
  .btn-danger:hover{background:#962d22}
  table{width:100%;border-collapse:collapse;font-size:.9rem}
  th{text-align:left;padding:8px 12px;background:#F5EFE6;color:#7A6655;font-weight:600;border-bottom:2px solid #EDE4D6}
  td{padding:9px 12px;border-bottom:1px solid #F0E8DC}
  tr:last-child td{border-bottom:none}
  .code-badge{font-family:monospace;background:#EBF4F8;color:#1a5276;padding:2px 8px;border-radius:4px;font-size:.95rem;font-weight:700}
  .url-hint{font-size:.78rem;color:#999;font-family:monospace;word-break:break-all}
  .suggest{margin-top:8px;font-size:.83rem;color:#8B5E3C;background:#FEF6EC;padding:8px 12px;border-radius:6px}
  .root-info{display:flex;align-items:center;gap:10px;font-size:.9rem;color:#7A6655}
  .note-col{max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:#7A6655}
</style>
</head>
<body>
<header>
  <h1>🗂 SeatCard 管理后台</h1>
  <div style="display:flex;align-items:center;gap:16px">
    <a href="dashboard.php" style="color:#D4C5B0;font-size:.85rem;text-decoration:none">📊 看板</a>
    <a href="index.php" style="color:#D4C5B0;font-size:.85rem;text-decoration:none">🏠 主页</a>
    <form method="POST" style="display:inline">
      <input type="hidden" name="action" value="logout">
      <a href="#" onclick="this.closest('form').submit()" style="color:#D4C5B0;font-size:.85rem;text-decoration:none">退出登录</a>
    </form>
  </div>
</header>

<div class="wrap">

<?php if ($msg): ?>
<div class="msg"><?= $msg ?></div>
<?php endif; ?>

<!-- 生成场次编号 -->
<?php $adminCfg=adminGetCfg();$yMin=$adminCfg['yearStart']-2000;$yMax=$adminCfg['yearEnd']-2000; ?>
<div class="card">
  <h2>➕ 生成新场次编号</h2>
  <p style="font-size:.82rem;color:#7A6A50;margin-bottom:12px;background:#FDF8F0;border:1px solid #E8D8B0;border-radius:6px;padding:7px 10px">
    📅 当前允许年份范围：<strong><?=$adminCfg['yearStart']?> — <?=$adminCfg['yearEnd']?></strong>
    &nbsp;·&nbsp; <a href="dashboard.php" style="color:#8B6030;font-size:.78rem">在看板中修改</a>
  </p>
  <form method="POST">
    <input type="hidden" name="action" value="generate">
    <div class="grid">
      <div>
        <label>年份（<?=$yMin?>–<?=$yMax?>）</label>
        <input type="number" name="year" min="<?=$yMin?>" max="<?=$yMax?>" value="<?= min(max(intval(date('y')),$yMin),$yMax) ?>">
      </div>
      <div>
        <label>月份（1–12）</label>
        <input type="number" name="month" min="1" max="12" value="<?= intval(date('m')) ?>">
      </div>
      <div>
        <label>日期（1–31）</label>
        <input type="number" name="day" min="1" max="31" value="<?= intval(date('d')) ?>">
      </div>
      <div>
        <label>场次（A–Z 或双场如 aA）</label>
        <input type="text" name="letter" maxlength="2" value="A" style="text-transform:uppercase">
      </div>
    </div>
    <div style="display:grid;grid-template-columns:1fr auto;gap:10px;align-items:end;margin-top:10px">
      <div>
        <label>备注（可选）</label>
        <input type="text" name="note" placeholder="如：张三&李四婚礼 · 下午场" style="margin-top:4px">
      </div>
      <div>
        <label>&nbsp;</label>
        <button type="submit" class="btn btn-primary">生成并创建</button>
      </div>
    </div>
  </form>
  <div class="suggest">
    💡 今日建议场次编号：<strong><?= htmlspecialchars($suggestCode) ?></strong>
    — 示例链接：<span class="url-hint">index.php?auth=<?= htmlspecialchars($suggestCode) ?></span>
  </div>
</div>

<!-- 格式说明 -->
<div class="card">
  <h2>📖 场次编号格式说明（新 9 位）</h2>
  <table>
    <tr><th>字段</th><th>含义</th><th>示例</th></tr>
    <tr><td>YY</td><td>年份后两位（26–40）</td><td>26 = 2026年</td></tr>
    <tr><td>MM</td><td>月份（两位）</td><td>03 = 三月</td></tr>
    <tr><td>DD</td><td>日期（两位）</td><td>27 = 27日</td></tr>
    <tr><td>X</td><td>场次大写字母（A–Z）</td><td>A = 第一场</td></tr>
    <tr><td>cc</td><td>2位校验：CRC32(YYMMDDX) → 无符号 → 28字符表映射</td><td>TW</td></tr>
  </table>
  <p style="margin-top:12px;font-size:.85rem;color:#7A6655">
    示例：<code>260327AJE</code> = 2026年3月27日 · A场 · 校验JE。<br>
    存储目录：<code>data/260327AJE/</code><br>
    字符表（无混淆字符）：<code>346789ACDEFGHJKLMNPQRSTUVWXY</code>
  </p>
</div>

<!-- 现有场次会话 -->
<div class="card">
  <h2>📂 现有场次会话（共 <?= count($sessions) ?> 个）</h2>
  <?php if (empty($sessions)): ?>
    <p style="color:#999;font-size:.9rem">暂无场次会话，请先生成场次编号。</p>
  <?php else: ?>
  <table>
    <tr>
      <th>场次编号</th>
      <th>日期·场</th>
      <th>备注</th>
      <th>备份数</th>
      <th>创建时间</th>
      <th>操作</th>
    </tr>
    <?php foreach ($sessions as $s): ?>
    <tr>
      <td><span class="code-badge"><?= htmlspecialchars($s['code']) ?></span></td>
      <td style="font-size:.85rem"><?= htmlspecialchars($s['date']) ?> · <?= htmlspecialchars($s['session']) ?>场</td>
      <td class="note-col" title="<?= htmlspecialchars($s['note']) ?>"><?= htmlspecialchars($s['note'] ?: '—') ?></td>
      <td><?= $s['backups'] ?> 个</td>
      <td style="font-size:.78rem;color:#999"><?= htmlspecialchars(substr($s['createdAt'], 0, 16)) ?></td>
      <td style="display:flex;gap:6px;align-items:center">
        <a href="index.php?auth=<?= urlencode($s['code']) ?>" target="_blank" class="btn btn-primary" style="font-size:.78rem;padding:4px 8px;text-decoration:none">进入</a>
        <form method="POST" onsubmit="return confirm('确认删除场次 <?= htmlspecialchars($s['code']) ?> 及其 <?= $s['backups'] ?> 个备份？此操作不可恢复！')">
          <input type="hidden" name="action" value="delete_session">
          <input type="hidden" name="code" value="<?= htmlspecialchars($s['code']) ?>">
          <button type="submit" class="btn btn-danger" style="font-size:.78rem;padding:4px 8px">删除</button>
        </form>
      </td>
    </tr>
    <?php endforeach; ?>
  </table>
  <?php endif; ?>
</div>

<!-- 根目录备份文件（注入工具）-->
<div class="card">
  <h2>📁 根目录备份 · 注入到场次</h2>
  <?php if (empty($rootBackupNames)): ?>
    <p style="color:#999;font-size:.9rem">data/ 根目录下无备份文件。</p>
  <?php else: ?>
  <p style="font-size:.85rem;color:#7A6655;margin-bottom:14px">以下是旧版（无 auth 参数）保存在 data/ 根目录的备份。可选择注入到某个场次目录，方便数据迁移。</p>
  <form method="POST">
    <input type="hidden" name="action" value="inject">
    <div style="display:grid;grid-template-columns:1fr 1fr auto;gap:10px;align-items:end">
      <div>
        <label>源备份文件</label>
        <select name="srcfile" style="width:100%;padding:8px 10px;border:1.5px solid #D4C5B0;border-radius:8px;font-size:.88rem;outline:none;background:#FDFAF7">
          <?php foreach ($rootBackupNames as $fn): ?>
          <option value="<?= htmlspecialchars($fn) ?>"><?= htmlspecialchars(str_replace('wedding-seating-backup-','',$fn)) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label>目标场次编号</label>
        <select name="tgtcode" style="width:100%;padding:8px 10px;border:1.5px solid #D4C5B0;border-radius:8px;font-size:.88rem;outline:none;background:#FDFAF7">
          <?php foreach ($sessions as $s): ?>
          <option value="<?= htmlspecialchars($s['code']) ?>"><?= htmlspecialchars($s['code']) ?> — <?= htmlspecialchars($s['date']) ?></option>
          <?php endforeach; ?>
          <?php if (empty($sessions)): ?><option value="">（先创建场次）</option><?php endif; ?>
        </select>
      </div>
      <div>
        <label>&nbsp;</label>
        <button type="submit" class="btn btn-primary" <?= empty($sessions)?'disabled':'' ?>>注入 →</button>
      </div>
    </div>
  </form>
  <p style="margin-top:10px;font-size:.8rem;color:#999">注入 = 复制文件到目标场次目录，原文件保留不动。</p>
  <?php endif; ?>
</div>

<!-- 🔧 技术信息 & 部署说明 -->
<div class="card">
  <h2>🔧 技术信息 &amp; 部署说明</h2>
  <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px 24px;font-size:.88rem;color:#5A5040;line-height:1.8">
    <div><strong>版本</strong>：SeatCard V0.24 · 2026-04</div>
    <div><strong>生成工具</strong>：Claude Sonnet 4.6</div>
    <div><strong>UI 框架</strong>：Tailwind (tw.js 本地，避免 CDN 屏蔽)</div>
    <div><strong>字体</strong>：Noto Serif/Sans SC via fonts.loli.net</div>
    <div><strong>PHP 需求</strong>：PHP 7.4+ · 无需数据库</div>
    <div><strong>管理密码</strong>：修改 admin.php 顶部 <code>ADMIN_PASS</code></div>
    <div class="col-span-2" style="grid-column:1/-1;margin-top:4px">
      <strong>目录权限</strong>：
      <code style="background:#F5EFE6;padding:2px 6px;border-radius:4px">chmod 755 data</code>
      &nbsp;·&nbsp;
      <code style="background:#F5EFE6;padding:2px 6px;border-radius:4px">chown www-data:www-data data</code>
    </div>
    <div class="col-span-2" style="grid-column:1/-1">
      <strong>文件结构</strong>：
      <?php foreach(['index.php','auth.php','save.php','load.php','list.php','delete.php','admin.php','dashboard.php','tw.js','data/auth.json','data/{code}/*.json'] as $f): ?>
      <code style="background:#EBF4F8;color:#1a5276;padding:1px 6px;border-radius:4px;margin:1px 2px;display:inline-block"><?= $f ?></code>
      <?php endforeach; ?>
    </div>
    <div class="col-span-2" style="grid-column:1/-1;margin-top:4px;padding:8px 12px;background:#FEF6EC;border-radius:8px;font-size:.82rem;color:#7A5020">
      ⚠ 服务器存储不加密，勿上传身份证等隐私数据。建议使用 HTTPS 部署。
    </div>
  </div>
</div>

</div><!-- /wrap -->
</body>
</html>
