<?php
/**
 * Auth Gate
 * 验证优先级：
 *   1. 在 data/auth.json 中找到记录 → 'valid'
 *   2. 格式正确 + 校验位通过          → 'valid'
 *   3. 格式正确 + 校验位错误          → 'warned'（放行但警告）
 *   4. 格式错误                        → false（拦截）
 */
/**
 * 格式：YYMMDDXcc (9位)
 * cc = CRC32(YYMMDDX) 映射到28×28字符表（无混淆字符）
 * CRC32 任意一位变化结果完全不可预测，抗枚举能力远强于 ASCII 求和
 */
define('SC_ALPHA','346789ACDEFGHJKLMNPQRSTUVWXY'); // 28字符，无0/1/2/5/B/I/O/Z
function scMod28Checksum($yymmddx){ // YYMMDDX (7位) → 2位校验
  $r=crc32($yymmddx); $h=($r<0)?$r+4294967296:$r; // 转无符号
  return SC_ALPHA[intdiv($h,28)%28].SC_ALPHA[$h%28];
}
function scNormalize($raw) {
    $s = trim($raw);
    $len = strlen($s);
    if ($len < 7) return strtoupper($s);
    return strtoupper(substr($s,0,6)) . $s[6] . ($len > 7 ? strtoupper(substr($s,7)) : '');
}
function scValidateAuth($code){
  $code=strtoupper(trim($code));
  // ── 1. 优先查 auth.json（最可靠）──
  $authFile=__DIR__.'/data/auth.json';
  if(file_exists($authFile)){
    $list=json_decode(file_get_contents($authFile),true)??[];
    foreach($list as $e){
      if(($e['code']??'')===$code){
        $st=$e['status']??'active';
        return ($st==='active')?'valid':(($st==='archived')?'archived':false);
      }
    }
  }
  // ── 2. 格式 YYMMDDXcc (9位) 或 YYMMDDaXcc (10位) ──
  if(preg_match('/^(\d{6})([A-Z]|[a-z][A-Z])([A-Z0-9]{2})$/',$code,$m)){
    return scMod28Checksum($m[1].$m[2])===$m[3]?'valid':'warned';
  }
  return false; // 格式不对 → 拦截
}
$rawAuth=scNormalize($_GET['Auth']??$_GET['auth']??'');
$VIEW_ONLY=false;
// ── 7位仅查看码（YYMMDDx，无校验位）──
if($rawAuth && preg_match('/^\d{6}([A-Z]|[a-z][A-Z])$/',$rawAuth)){
  $authFile=__DIR__.'/data/auth.json';
  $AUTH_CODE=''; $VIEW_ONLY=false;
  if(file_exists($authFile)){
    $_authList=json_decode(file_get_contents($authFile),true)??[];
    foreach($_authList as $_e){
      if(strpos($_e['code']??'',$rawAuth)===0){
        $AUTH_CODE=$_e['code'];
        $VIEW_ONLY=true;
        break;
      }
    }
  }
  $authStatus=$AUTH_CODE?'valid':false;
  $AUTH_WARNED=false;
} else {
  $authStatus=$rawAuth?scValidateAuth($rawAuth):false;
  $AUTH_CODE=($authStatus!==false)?$rawAuth:'';
  $AUTH_WARNED=($authStatus==='warned');
}
if(!$AUTH_CODE){
  header('Content-Type: text/html; charset=UTF-8');
  $curYY=date('y'); $curMM=date('m'); $curDD=date('d');
  // 读年份范围配置（与 dashboard/admin 共享同一 sc_config.json）
  $_cfgFile=__DIR__.'/data/sc_config.json';
  $_cfg=file_exists($_cfgFile)?(json_decode(file_get_contents($_cfgFile),true)??[]):[];
  $cfgYearStart=max(2026,intval($_cfg['yearStart']??2026))-2000; // 两位年，如 26
  $cfgYearEnd  =max($cfgYearStart,intval($_cfg['yearEnd']??2030))-2000;
?><!DOCTYPE html>
<html lang="zh-CN"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>SeatCard · 进入系统</title>
<link href="https://fonts.loli.net/css2?family=Noto+Serif+SC:wght@400;600&family=Noto+Sans+SC:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Noto Sans SC',sans-serif;min-height:100vh;background:linear-gradient(135deg,#EBF8F3 0%,#FBF7E6 55%,#F8EBF1 100%);display:flex;align-items:center;justify-content:center;padding:20px;transition:background .25s}
.card{background:#fff;border-radius:20px;box-shadow:0 16px 48px rgba(31,59,47,.12);padding:28px 24px;width:min(440px,100%);border:1px solid #DDE6DC;position:relative;transition:background .25s,border-color .25s}
/* ── 暗夜模式 ── */
body.dark{background:linear-gradient(135deg,#1A1412 0%,#201A16 55%,#1E1418 100%)}
body.dark .card{background:#241E1C;border-color:#4A4038;box-shadow:0 16px 48px rgba(0,0,0,.4)}
body.dark h1{color:#EDE0D0}
body.dark .sub{color:#907870}
body.dark .tabs{border-bottom-color:#4A4038}
body.dark .tab.act{color:#C07070;border-bottom-color:#9B3A3A}
body.dark .tab:not(.act){color:#907870}
body.dark .lbl{color:#907870}
body.dark .sel,body.dark .inp{background:#302824;border-color:#4A4038;color:#EDE0D0}
body.dark .sel:focus,body.dark .inp:focus{border-color:#9B3A3A;background:#3A2E2A}
body.dark .btn-main{background:#7B3030}body.dark .btn-main:hover{background:#9B3A3A}
body.dark .btn-dark{background:#2D2420;color:#EDE0D0;border:none}body.dark .btn-dark:hover{background:#3D3430}
body.dark .btn-outline{background:#302824;color:#907870;border-color:#4A4038}body.dark .btn-outline:hover{border-color:#9B3A3A;color:#C07070}
body.dark .hint{background:#2A211E;color:#907870}
body.dark .hint strong{color:#EDE0D0}
body.dark .foot{color:#504848}
body.dark .hist-title{color:#907870}
body.dark .hist-item{background:#302824;border-color:#4A4038}
body.dark .hist-item:hover{border-color:#9B3A3A;background:#3D2A24}
body.dark .hist-item.sel{border-color:#9B3A3A;background:#3D2820;box-shadow:0 0 0 2px #9B3A3A40}
body.dark .hist-code{color:#EDE0D0}
body.dark .hist-cc{color:#5A4A48}
body.dark .hist-meta{color:#907870}
body.dark .hist-enter-btn{background:#7B3030}body.dark .hist-enter-btn:hover{background:#9B3A3A}
body.dark .hist-empty{background:#2A211E;color:#6A5A58}
body.dark .loading{color:#5A4A48}
body.dark .action-bar{background:#2A211E;border-color:#9B3A3A}
body.dark .copy-tip{color:#C07070}
body.dark .cc-box{background:#241E1C}
body.dark .cc-title{color:#EDE0D0}
body.dark .cc-desc{color:#907870}
body.dark .cc-prefix-chip{background:#3D2820;border-color:#9B3A3A;color:#EDE0D0}
body.dark .cc-input{background:#302824;border-color:#4A4038;color:#EDE0D0}
body.dark .cc-input:focus{border-color:#9B3A3A}
body.dark .cc-cancel{background:#302824;border-color:#4A4038;color:#907870}
body.dark .cc-confirm{background:#7B3030}body.dark .cc-confirm:hover{background:#9B3A3A}
body.dark .gen-err{color:#E07070}
body.dark .action-desc-note{background:#3D2020!important;color:#C09080!important;border:1px solid #5A2828!important}
body.dark .action-desc-note strong{color:#E0A080!important}
/* 暗夜切换按钮 */
#authDarkBtn{position:absolute;top:14px;right:16px;background:none;border:none;font-size:16px;cursor:pointer;opacity:.6;padding:2px 4px;border-radius:6px;transition:opacity .15s}
#authDarkBtn:hover{opacity:1}
.logo-svg{display:block;margin:0 auto 10px;color:#2FBB7A}
body.dark .logo-svg{color:#7AD4B0}
h1{font-family:'Noto Serif SC',serif;font-size:20px;font-weight:600;color:#1F3B2F;text-align:center}
.sub{font-size:11px;color:#5A7A6A;text-align:center;margin-top:3px;margin-bottom:20px}
.tabs{display:flex;border-bottom:2px solid #DDE6DC;margin-bottom:18px}
.tab{flex:1;padding:8px;font-size:12px;font-weight:600;background:none;border:none;border-bottom:2px solid transparent;cursor:pointer;transition:all .15s;margin-bottom:-2px;font-family:'Noto Sans SC',sans-serif}
.tab.act{color:#2FBB7A;border-bottom-color:#2FBB7A}
.tab:not(.act){color:#5A7A6A}
.lbl{font-size:11px;color:#5A7A6A;display:block;margin-bottom:4px}
.sel,.inp{width:100%;padding:8px 10px;border:2px solid #DDE6DC;border-radius:8px;font-size:14px;outline:none;background:#F2F6F1;color:#1F3B2F;font-family:'Noto Sans SC',sans-serif;transition:border-color .15s}
.sel:focus,.inp:focus{border-color:#2FBB7A;background:#fff}
.inp{text-align:center;letter-spacing:4px;font-family:monospace;font-size:22px}
.row3{display:grid;grid-template-columns:1fr 1fr 1fr;gap:8px;margin-bottom:14px}
.btn{width:100%;padding:10px;border:none;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;transition:all .15s;font-family:'Noto Sans SC',sans-serif;margin-bottom:6px}
.btn-main{background:#2FBB7A;color:#fff}.btn-main:hover{background:#239A65}
.btn-dark{background:#1F3B2F;color:#fff}.btn-dark:hover{background:#2a5040}
.btn-outline{background:#fff;color:#5A7A6A;border:1.5px solid #DDE6DC}.btn-outline:hover{border-color:#2FBB7A;color:#2FBB7A}
.hint{font-size:11px;color:#5A7A6A;line-height:1.7;margin-bottom:14px;background:#F2F6F1;border-radius:8px;padding:9px 11px}
.err{font-size:11px;color:#e74c3c;min-height:16px;text-align:center;margin-bottom:8px}
.foot{font-size:10px;color:#ccc;text-align:center;margin-top:14px}

/* ── 历史列表 ── */
.hist-wrap{margin-bottom:12px}
.hist-title{font-size:10px;color:#5A7A6A;margin-bottom:6px;font-weight:600}
.hist-list{display:flex;flex-direction:column;gap:4px;max-height:160px;overflow-y:auto}
.hist-item{display:flex;align-items:center;justify-content:space-between;padding:7px 10px 7px 12px;border-radius:8px;border:1.5px solid #DDE6DC;transition:all .12s;background:#F2F6F1;gap:6px}
.hist-item:hover{border-color:#2FBB7A;background:#EBF8F3}
.hist-item.sel{border-color:#2FBB7A;background:#d6f5e7;box-shadow:0 0 0 2px #2FBB7A40}
.hist-code{font-family:monospace;font-size:14px;font-weight:700;color:#1F3B2F;letter-spacing:2px}
.hist-cc{font-family:monospace;font-size:12px;color:#bbb;letter-spacing:2px}
.hist-meta{font-size:10px;color:#5A7A6A;margin-top:1px}
.hist-del-btn{font-size:10px;color:#e74c3c;background:none;border:none;cursor:pointer;padding:1px 4px;border-radius:4px;font-family:inherit;flex-shrink:0;opacity:.7}
.hist-del-btn:hover{opacity:1;background:#fff0f0}
.hist-enter-btn{font-size:11px;font-weight:600;color:#fff;background:#2FBB7A;border:none;border-radius:6px;padding:4px 10px;cursor:pointer;font-family:inherit;flex-shrink:0;white-space:nowrap}
.hist-enter-btn:hover{background:#239A65}
.hist-empty{font-size:11px;color:#bbb;text-align:center;padding:12px;background:#F7F7F7;border-radius:8px}
.loading{font-size:11px;color:#bbb;text-align:center;padding:8px}

/* ── 新建后操作区 ── */
.action-bar{background:#EBF8F3;border:2px solid #2FBB7A;border-radius:14px;padding:14px;margin-bottom:12px}
.action-segs{display:flex;align-items:flex-end;justify-content:center;gap:0;margin-bottom:10px}
.seg-col{display:flex;flex-direction:column;align-items:center}
.seg-chars{font-family:monospace;font-weight:700;letter-spacing:3px;border-bottom:2px solid currentColor;padding-bottom:3px;line-height:1}
.seg-lbl{font-size:9px;font-weight:600;margin-top:4px;letter-spacing:.5px}
.seg-dot{font-family:monospace;font-weight:300;color:#ccc;padding:0 2px;padding-bottom:15px;font-size:22px;line-height:1}
.action-btns{display:flex;gap:6px;flex-wrap:wrap}
.copy-tip{font-size:10px;color:#2FBB7A;text-align:center;margin-top:6px;min-height:14px}

/* ── 校验码确认弹框 ── */
.cc-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:200;align-items:center;justify-content:center}
.cc-overlay.open{display:flex}
.cc-box{background:#fff;border-radius:16px;padding:22px 20px;width:min(300px,90vw);box-shadow:0 16px 48px rgba(0,0,0,.22)}
.cc-title{font-size:13px;font-weight:700;color:#1F3B2F;margin-bottom:6px}
.cc-desc{font-size:11px;color:#5A7A6A;line-height:1.6;margin-bottom:12px}
.cc-prefix-chip{font-family:monospace;font-weight:700;font-size:13px;color:#1F3B2F;background:#EBF8F3;border:1.5px solid #2FBB7A;border-radius:6px;padding:2px 8px;letter-spacing:2px;display:inline-block}
.cc-input{width:100%;padding:10px;border:2px solid #DDE6DC;border-radius:8px;font-size:24px;letter-spacing:10px;text-align:center;font-family:monospace;text-transform:uppercase;outline:none;font-weight:700;transition:border-color .15s}
.cc-input:focus{border-color:#2FBB7A}
.cc-err{font-size:11px;color:#e74c3c;min-height:16px;text-align:center;margin-top:4px}
.cc-row{display:flex;gap:8px;margin-top:12px}
.cc-cancel{flex:1;padding:8px;border:1.5px solid #DDE6DC;border-radius:8px;background:#fff;color:#5A7A6A;cursor:pointer;font-family:inherit;font-size:12px}
.cc-confirm{flex:2;padding:8px;border:none;border-radius:8px;background:#2FBB7A;color:#fff;cursor:pointer;font-family:inherit;font-size:12px;font-weight:600}
.cc-confirm:hover{background:#239A65}
</style>
</head>
<body>
<div class="card">
  <button id="authDarkBtn" onclick="toggleAuthDark()" title="切换至喜夜模式">🌙</button>
  <svg class="logo-svg" viewBox="0 0 56 56" width="56" height="56" xmlns="http://www.w3.org/2000/svg">
    <!-- round table -->
    <circle cx="28" cy="28" r="11" fill="currentColor" fill-opacity=".13" stroke="currentColor" stroke-width="2"/>
    <!-- 6 chairs at 0°/60°/120°/180°/240°/300°, each a small rounded rect -->
    <rect x="22" y="5" width="12" height="7" rx="3.5" fill="currentColor" opacity=".8" transform="rotate(0,28,28)"/>
    <rect x="22" y="5" width="12" height="7" rx="3.5" fill="currentColor" opacity=".8" transform="rotate(60,28,28)"/>
    <rect x="22" y="5" width="12" height="7" rx="3.5" fill="currentColor" opacity=".8" transform="rotate(120,28,28)"/>
    <rect x="22" y="5" width="12" height="7" rx="3.5" fill="currentColor" opacity=".8" transform="rotate(180,28,28)"/>
    <rect x="22" y="5" width="12" height="7" rx="3.5" fill="currentColor" opacity=".8" transform="rotate(240,28,28)"/>
    <rect x="22" y="5" width="12" height="7" rx="3.5" fill="currentColor" opacity=".8" transform="rotate(300,28,28)"/>
  </svg>
  <h1>SeatCard</h1>
  <p class="sub">婚宴座位安排 · 按场次独立隔离</p>

  <div class="tabs">
    <button class="tab act" id="tab-gen" onclick="showTab('gen')">📅 按日期选场次</button>
    <button class="tab" id="tab-man" onclick="showTab('man')">🔑 直接输入编号</button>
  </div>

  <!-- ── 按日期选场次 ── -->
  <div id="pane-gen">
    <p class="hint">请选择意向中的日期。<strong>选择后不能更改。</strong><br>⚠ 请妥善保存 <strong>9 位场次编号</strong>，勿将其泄露给他人。推荐 JSON 本地备份。</p>
    <div class="row3">
      <div>
        <label class="lbl">年份</label>
        <select id="gen-yy" class="sel" onchange="onDateChange()">
          <?php for($y=$cfgYearStart;$y<=$cfgYearEnd;$y++){$v=str_pad($y,2,'0',STR_PAD_LEFT);$s=($v==$curYY)?' selected':'';echo "<option value='$v'$s>20{$v}年</option>";}?>
        </select>
      </div>
      <div>
        <label class="lbl">月份</label>
        <select id="gen-mm" class="sel" onchange="onDateChange()">
          <?php for($m=1;$m<=12;$m++){$v=str_pad($m,2,'0',STR_PAD_LEFT);$s=($m==intval($curMM))?' selected':'';echo "<option value='$v'$s>{$v}月</option>";}?>
        </select>
      </div>
      <div>
        <label class="lbl">日期</label>
        <select id="gen-dd" class="sel" onchange="onDateChange()">
          <?php for($d=1;$d<=31;$d++){$v=str_pad($d,2,'0',STR_PAD_LEFT);$s=($d==intval($curDD))?' selected':'';echo "<option value='$v'$s>{$v}日</option>";}?>
        </select>
      </div>
    </div>

    <!-- 已有场次列表（校验码隐藏，进入/删除需确认）-->
    <div class="hist-wrap">
      <div class="hist-title">该日期已有场次（进入 / 删除需确认校验码）</div>
      <div id="hist-list" class="hist-list"><div class="loading">加载中…</div></div>
    </div>

    <!-- 新建成功后才显示：完整编码 + 复制链接 -->
    <div id="action-bar" class="action-bar" style="display:none">
      <div id="action-segs" class="action-segs"></div>
      <div class="action-btns">
        <button class="btn btn-main" onclick="copyEditUrl()" style="flex:2;margin-bottom:0">🔗 复制编辑链接</button>
        <button class="btn btn-outline" onclick="copyViewUrl()" style="flex:1;margin-bottom:0">👁 复制查看链接</button>
      </div>
      <div class="action-desc-note" style="font-size:9.5px;color:#5A7A6A;margin-top:8px;line-height:1.6;background:#F2F6F1;border-radius:6px;padding:5px 8px">
        🔗 <strong>编辑链接</strong>：完整 9 位，可编辑保存。<br>
        👁 <strong>查看链接</strong>：7 位前缀，<u>仅查看</u>，适合分享给宾客预览座位图。
      </div>
      <div id="copy-tip" class="copy-tip"></div>
    </div>

    <div style="display:flex;gap:8px;margin-top:4px">
      <button class="btn btn-dark" id="btn-gen" onclick="onBtnGenClick()" style="flex:1;margin-bottom:0">＋ 新建场次</button>
    </div>
    <div id="gen-err" style="font-size:11px;color:#e74c3c;text-align:center;margin-top:6px;min-height:14px"></div>
  </div>

  <!-- ── 直接输入场次编号 ── -->
  <div id="pane-man" style="display:none">
    <p class="hint">输入已有的 9 位场次编号（格式 <code style="background:#f0f0f0;padding:1px 4px;border-radius:3px">YYMMDDXcc</code>）。<br>若编号不在系统记录中会显示警告，但仍可进入。</p>
    <input id="authInput" class="inp" type="text" maxlength="10" placeholder="YYMMDDXcc"
      oninput="this.value=scNormInput(this.value);manErr('')" style="font-size:18px;letter-spacing:3px">
    <div id="authErr" class="err"></div>
    <button class="btn btn-main" onclick="tryAuth()">进入系统</button>
  </div>

  <p class="foot">SeatCard · 场次隔离 · Beta · 仅供内部使用</p>
</div>

<!-- ── 校验码确认弹框 ── -->
<div class="cc-overlay" id="cc-overlay" onclick="if(event.target===this)closeCCModal()">
  <div class="cc-box">
    <div class="cc-title">🔐 请输入校验位</div>
    <div class="cc-desc">请输入 <span id="cc-prefix" class="cc-prefix-chip"></span> 的最后 2 位校验码以确认身份</div>
    <input id="cc-input" class="cc-input" type="text" maxlength="2" placeholder="—"
      oninput="this.value=this.value.toUpperCase();document.getElementById('cc-err').textContent=''">
    <div id="cc-err" class="cc-err"></div>
    <div class="cc-row">
      <button class="cc-cancel" onclick="closeCCModal()">取消</button>
      <button class="cc-confirm" onclick="verifyAndProceed()">确认进入</button>
    </div>
  </div>
</div>

<script>
const BASE=location.pathname.replace(/\/[^/]*$/,'')+'/auth.php';
let _selectedCode='';   // 当前操作中的完整9位码
let _justCreated=false; // 是否刚刚新建了场次
let _pendingAction=null;// {action:'enter'|'delete', code:string}

function showTab(t){
  ['gen','man'].forEach(k=>{
    document.getElementById('pane-'+k).style.display=k===t?'block':'none';
    document.getElementById('tab-'+(k==='gen'?'gen':'man')).classList.toggle('act',k===t);
  });
}

function getDate(){
  return document.getElementById('gen-yy').value
       + document.getElementById('gen-mm').value
       + document.getElementById('gen-dd').value;
}

// 日期选择改变时：重置状态，重新加载历史
function onDateChange(){
  _justCreated=false;
  _selectedCode='';
  document.getElementById('action-bar').style.display='none';
  const btn=document.getElementById('btn-gen');
  btn.textContent='＋ 新建场次';btn.className='btn btn-dark';btn.disabled=false;
  document.getElementById('gen-err').textContent='';
  loadHistory();
}

// ── 新建成功后：渲染编码区 ──
function showNewCode(code){
  _selectedCode=code;
  _justCreated=true;
  // 拆段渲染
  const _dk=document.body.classList.contains('dark');
  const segs=[
    {chars:code.slice(0,2), lbl:'年',  color:_dk?'#C0B0A0':'#1F3B2F'},
    {chars:code.slice(2,6), lbl:'月日', color:_dk?'#C0B0A0':'#1F3B2F'},
    {chars:code[6],          lbl:'场次', color:_dk?'#C07070':'#2FBB7A'},
    {chars:code.slice(7),    lbl:'校验', color:_dk?'#706870':'#9B8FA0'},
  ];
  const fs='clamp(24px,7vw,38px)';
  const segsEl=document.getElementById('action-segs');
  segsEl.innerHTML=segs.map((s,i)=>`
    ${i>0?'<div class="seg-dot">·</div>':''}
    <div class="seg-col">
      <span class="seg-chars" style="font-size:${fs};color:${s.color}">${s.chars}</span>
      <span class="seg-lbl" style="color:${s.color}">${s.lbl}</span>
    </div>`).join('');
  document.getElementById('action-bar').style.display='block';
  document.getElementById('copy-tip').textContent='';
  // 按钮变"进入"
  const btn=document.getElementById('btn-gen');
  btn.textContent='→ 进入'; btn.className='btn btn-main';
  // 高亮列表行
  document.querySelectorAll('.hist-item').forEach(el=>{
    el.classList.toggle('sel', el.dataset.code===code);
  });
}

// 点击已有场次的"进入"或"删除"
function askAction(action, code){
  _pendingAction={action, code};
  document.getElementById('cc-prefix').textContent=code.slice(0,7);
  document.getElementById('cc-input').value='';
  document.getElementById('cc-err').textContent='';
  const confirmBtn=document.querySelector('.cc-confirm');
  confirmBtn.textContent=action==='delete'?'确认删除':'确认进入';
  confirmBtn.style.background=action==='delete'?'#e74c3c':'#2FBB7A';
  document.getElementById('cc-overlay').classList.add('open');
  setTimeout(()=>document.getElementById('cc-input').focus(),150);
}

function closeCCModal(){
  document.getElementById('cc-overlay').classList.remove('open');
  _pendingAction=null;
}

function verifyAndProceed(){
  const input=document.getElementById('cc-input').value.trim().toUpperCase();
  if(!_pendingAction)return;
  const{action,code}=_pendingAction;
  const expectedCC=code.slice(7);
  if(input!==expectedCC){
    document.getElementById('cc-err').textContent='校验码不正确，请重新查看编号';
    return;
  }
  closeCCModal();
  if(action==='enter'){
    location.href=location.pathname+'?Auth='+encodeURIComponent(code);
  }else if(action==='delete'){
    deleteSession(code);
  }
}

async function deleteSession(code){
  try{
    const r=await fetch(BASE+'?action=delete&code='+encodeURIComponent(code));
    const data=await r.json();
    if(data.error){alert('删除失败：'+data.error);return;}
    if(_selectedCode===code){_selectedCode='';_justCreated=false;document.getElementById('action-bar').style.display='none';}
    loadHistory(true);
  }catch(ex){alert('网络请求失败');}
}

// 新建场次后按钮改为"进入"，点击时直接进入
function onBtnGenClick(){
  if(_justCreated&&_selectedCode){
    location.href=location.pathname+'?Auth='+encodeURIComponent(_selectedCode);
  }else{
    genCode();
  }
}

async function copyEditUrl(){
  if(!_selectedCode)return;
  const url=location.origin+location.pathname+'?Auth='+encodeURIComponent(_selectedCode);
  try{await navigator.clipboard.writeText(url);showCopyTip('编辑链接已复制 ✓');}
  catch(e){showCopyTip(url);}
}
async function copyViewUrl(){
  if(!_selectedCode)return;
  const prefix=_selectedCode.slice(0,7);
  const url=location.origin+location.pathname+'?Auth='+encodeURIComponent(prefix);
  try{await navigator.clipboard.writeText(url);showCopyTip('查看链接已复制 ✓ ('+prefix+'…)');}
  catch(e){showCopyTip(url);}
}
function showCopyTip(msg){document.getElementById('copy-tip').textContent=msg;}

// keepSelected=true：后台刷新列表，不重置已选中的新建状态
async function loadHistory(keepSelected=false){
  const listEl=document.getElementById('hist-list');
  listEl.innerHTML='<div class="loading">加载中…</div>';
  document.getElementById('gen-err').textContent='';
  try{
    const r=await fetch(BASE+'?action=list&date='+getDate());
    const data=await r.json();
    if(!data.length){
      listEl.innerHTML='<div class="hist-empty">该日期暂无场次，点击下方「新建场次」</div>';
      return;
    }
    listEl.innerHTML=data.map(e=>{
      const d=new Date(e.createdAt);
      const ts=isNaN(d)?'':' · '+(d.getMonth()+1)+'/'+(d.getDate())+' '+d.getHours()+':'+String(d.getMinutes()).padStart(2,'0');
      const letter=e.code[6];
      const shortCode=e.code.slice(0,7); // 隐藏校验位
      const isSel=_justCreated&&e.code===_selectedCode;
      return `<div class="hist-item${isSel?' sel':''}" data-code="${e.code}">
        <div style="flex:1;min-width:0">
          <span class="hist-code">${shortCode}</span><span class="hist-cc">··</span>
          <button class="hist-del-btn" onclick="askAction('delete','${e.code}')">删除</button>
          <div class="hist-meta">场次 ${letter}${ts}${e.note?' · '+e.note:''}</div>
        </div>
        <button class="hist-enter-btn" onclick="askAction('enter','${e.code}')">进入</button>
      </div>`;
    }).join('');
  }catch(ex){
    listEl.innerHTML='<div class="hist-empty" style="color:#e74c3c">无法连接 auth.php，请检查服务器</div>';
  }
}

async function genCode(){
  document.getElementById('gen-err').textContent='';
  const btn=document.getElementById('btn-gen');
  btn.textContent='生成中…';btn.disabled=true;
  try{
    const r=await fetch(BASE+'?action=generate&date='+getDate());
    const data=await r.json();
    if(data.error){document.getElementById('gen-err').textContent=data.error;return;}
    showNewCode(data.code);
    loadHistory(true);
  }catch(ex){
    document.getElementById('gen-err').textContent='请求失败，请检查 auth.php 是否存在';
  }finally{
    btn.disabled=false;
    if(!_justCreated){btn.textContent='＋ 新建场次';btn.className='btn btn-dark';}
  }
}

// 手动输入
function scNormInput(v){
    if(v.length<7)return v.toUpperCase();
    return v.substring(0,6).toUpperCase()+v[6]+v.substring(7).toUpperCase();
}
function manErr(msg){document.getElementById('authErr').textContent=msg;}
function tryAuth(){
  const v=scNormInput(document.getElementById('authInput').value.trim());
  if(/^\d{6}([A-Z]|[a-z][A-Z])[A-Z0-9]{2}$/.test(v)){
    location.href=location.pathname+'?Auth='+encodeURIComponent(v);return;
  }
  manErr('格式不正确（需 9 位 YYMMDDXcc 或 10 位 YYMMDDaXcc）');
}
document.getElementById('authInput').addEventListener('keydown',e=>{if(e.key==='Enter')tryAuth();});
document.getElementById('cc-input').addEventListener('keydown',e=>{if(e.key==='Enter')verifyAndProceed();});

// ── 暗夜模式 ──
function toggleAuthDark(){
  const on=document.body.classList.toggle('dark');
  localStorage.setItem('sc-dark',on?'1':'');
  document.getElementById('authDarkBtn').textContent=on?'☀':'🌙';
  document.getElementById('authDarkBtn').title=on?'切换至春日模式':'切换至喜夜模式';
}
(function(){
  if(localStorage.getItem('sc-dark')==='1'){
    document.body.classList.add('dark');
    const b=document.getElementById('authDarkBtn');if(b){b.textContent='☀';b.title='切换至春日模式';}
  }
})();

loadHistory();
</script>
</body></html>
<?php exit; } ?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Seat Card · 座位卡</title>
<!-- Tailwind Play CDN — 本地缓存 tw.js（避免国内CDN问题） -->
<script src="tw.js"></script>
<script>if(typeof tailwind!=='undefined')tailwind.config={theme:{extend:{colors:{warm:'#FBF7E6',surface:'#F2F6F1',line:'#DDE6DC',primary:'#2FBB7A',ink:'#1F3B2F',muted:'#5A7A6A'}}}}</script>
<link rel="icon" type="image/svg+xml" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 64 64'%3E%3Ccircle cx='32' cy='32' r='20' fill='%232C6E8A' opacity='.25'/%3E%3Ccircle cx='32' cy='32' r='20' fill='none' stroke='%232C6E8A' stroke-width='2.5'/%3E%3C!-- 9 seat chips around table --%3E%3Cg id='s'%3E%3Crect x='-9' y='-4' width='18' height='8' rx='4' fill='%23B99390'/%3E%3C/g%3E%3Cuse href='%23s' transform='translate(32,7)'/%3E%3Cuse href='%23s' transform='translate(50,15) rotate(40 0 0)'/%3E%3Cuse href='%23s' transform='translate(56,32) rotate(80 0 0)'/%3E%3Cuse href='%23s' transform='translate(50,49) rotate(120 0 0)'/%3E%3Cuse href='%23s' transform='translate(32,57)'/%3E%3Cuse href='%23s' transform='translate(14,49) rotate(-120 0 0)'/%3E%3Cuse href='%23s' transform='translate(8,32) rotate(-80 0 0)'/%3E%3Cuse href='%23s' transform='translate(14,15) rotate(-40 0 0)'/%3E%3C/svg%3E">
<link href="https://fonts.loli.net/css2?family=Noto+Serif+SC:wght@400;600&family=Noto+Sans+SC:wght@300;400;500;600&display=swap" rel="stylesheet">

<!-- ══ EDITABLE COLOR CONFIG ══
  Edit PAL_COLORS below to customize seat chip colors.
  Each entry: { "name":"色名", "bg":"#hex", "txt":"#hex" }
  Special entries have "special":true (shown separately in picker)
-->
<script id="colorConfig" type="application/json">
{
  "palette": [
    {"name":"浅灰",    "bg":"#B8B8B6","txt":"#2a2a2a"},
    {"name":"雾蓝灰",  "bg":"#B7C2C8","txt":"#2a2a2a"},
    {"name":"鼠尾草",  "bg":"#A3B0A2","txt":"#2a2a2a"},
    {"name":"雾橄榄",  "bg":"#AAAC94","txt":"#2a2a2a"},
    {"name":"灰粉",    "bg":"#D1B8B6","txt":"#2a2a2a"},
    {"name":"雾杏",    "bg":"#D6C1B4","txt":"#2a2a2a"},
    {"name":"暖米",    "bg":"#D4CFC7","txt":"#2a2a2a"},
    {"name":"雾紫",    "bg":"#B6AFC1","txt":"#2a2a2a"},
    {"name":"深灰蓝",  "bg":"#5C6A74","txt":"#ffffff"},
    {"name":"苔藓绿",  "bg":"#5B6A57","txt":"#ffffff"},
    {"name":"橄榄暗",  "bg":"#7A7D67","txt":"#ffffff"},
    {"name":"莫兰迪粉","bg":"#A7817E","txt":"#ffffff"},
    {"name":"暖灰棕",  "bg":"#958678","txt":"#ffffff"},
    {"name":"烟棕",    "bg":"#7D6F63","txt":"#ffffff"},
    {"name":"灰紫",    "bg":"#8A8394","txt":"#ffffff"},
    {"name":"暗蓝灰",  "bg":"#6D7880","txt":"#ffffff"}
  ],
  "special": [
    {"name":"喜红·新娘",  "bg":"#C03838","txt":"#ffffff","special":true},
    {"name":"天蓝·新郎",  "bg":"#3070C0","txt":"#ffffff","special":true},
    {"name":"金橙·主婚",  "bg":"#C47818","txt":"#ffffff","special":true},
    {"name":"翠绿·证婚",  "bg":"#22954E","txt":"#ffffff","special":true},
    {"name":"紫兰·伴娘",  "bg":"#6C3CA4","txt":"#ffffff","special":true},
    {"name":"橙粉·伴郎",  "bg":"#A84830","txt":"#ffffff","special":true}
  ]
}
</script>

<style>
/* ── 自定义主题色（替代 tailwind.config 中的 extend.colors） ── */
.bg-warm{background-color:#FBF7E6}.bg-surface{background-color:#F2F6F1}
.bg-primary{background-color:#2FBB7A}.bg-line{background-color:#DDE6DC}
.text-primary{color:#2FBB7A}.text-ink{color:#1F3B2F}.text-muted{color:#5A7A6A}
.border-line{border-color:#DDE6DC}.border-primary{border-color:#2FBB7A}
.bg-warm\/80{background-color:rgba(251,247,230,.8)}
.hover\:bg-primary\/10:hover{background-color:rgba(47,187,122,.1)}
.focus\:border-primary:focus{border-color:#2FBB7A!important}
*{box-sizing:border-box}
body{font-family:'Noto Sans SC',sans-serif;overflow:hidden;transition:background .3s,color .3s}
/* ── DARK MODE: warm gray, low saturation ── */
body.dark-mode{background:#2A2623!important;color:#E0D8D0}
body.dark-mode header{background:#1E1C1A!important;border-color:#3A3530}
body.dark-mode .bg-warm,body.dark-mode .bg-warm\/80{background:#2A2623!important}
body.dark-mode #canvasWrap svg{background:#3A3835!important}
body.dark-mode .bg-white{background:#242220!important}
body.dark-mode .border-line{border-color:#3A3530!important}
body.dark-mode .bg-surface{background:#302E2B!important}
body.dark-mode .text-ink{color:#E8E0D8!important}
body.dark-mode .text-muted{color:#989088!important}
body.dark-mode .btn-ghost{background:#302E2B!important;border-color:#4A4540!important;color:#D8D0C8!important}
body.dark-mode .btn-ghost:hover{background:#3A3530!important;border-color:#9B3A3A!important;color:#D07070!important}
body.dark-mode .btn-ghost.active{background:#3A2020!important;border-color:#9B3A3A!important;color:#D07070!important}
body.dark-mode .btn-primary{background:#9B3A3A!important;border-color:#9B3A3A!important}
body.dark-mode .btn-primary:hover{background:#B04040!important}
body.dark-mode .btn-accent{background:#3A2E28!important;border-color:#5A4A40!important}
body.dark-mode .fi{background:#302E2B!important;border-color:#4A4540!important;color:#E0D8D0!important}
body.dark-mode .modal-box{background:#242220!important;border-color:#3A3530!important}
body.dark-mode .modal-title{color:#E8E0D8!important}
body.dark-mode #seatCard{background:#242220!important;border-color:#3A3530!important}
body.dark-mode #seatCard .scn{color:#E8E0D8!important}
body.dark-mode .scp{background:#302E2B!important;border-color:#4A4540!important;color:#B8B0A8!important}
body.dark-mode #statsWrapper{background:#1E1C1A!important;border-color:#3A3530!important}
body.dark-mode #statsFooter{background:transparent!important}
body.dark-mode #csvMobileToggleBtn{color:#989088!important;border-color:#3A3530!important}
/* 桌位设置暗夜 */
body.dark-mode #tableListPanel{background:#1E1C1A!important;border-color:#3A3530!important}
body.dark-mode #tableListPanel .bg-surface{background:#242220!important}
body.dark-mode #tableListPanel .text-muted{color:#989088!important}
body.dark-mode #tableListPanel .border-b{border-color:#3A3530!important}
body.dark-mode #tableListPanel .hover\:bg-surface:hover{background:#302E2B!important}
body.dark-mode #tableListPanel .bg-emerald-50{background:#1A2820!important}
body.dark-mode #tableListPanel .text-ink{color:#E8E0D8!important}
body.dark-mode #tableConfigPanel{background:#242220!important}
body.dark-mode #tableConfigPanel .bg-surface{background:#302E2B!important}
body.dark-mode #tableConfigPanel .border-line{border-color:#4A4540!important}
body.dark-mode #tableConfigPanel .text-muted{color:#989088!important}
body.dark-mode #tableConfigPanel .text-ink{color:#E8E0D8!important}
body.dark-mode #tableConfigPanel .bg-white{background:#2A2623!important}
body.dark-mode #tableConfigPanel .border-primary{border-color:#9B3A3A!important}
body.dark-mode #tableConfigPanel button.border-line{border-color:#4A4540!important;background:#302E2B!important;color:#B8B0A8!important}
/* WTN 工具栏暗夜 */
body.dark-mode #wtn-toolbar{background:#1E1C1A!important;border-color:#3A3530!important}
body.dark-mode #wtn-body>div{border-color:#3A3530!important;background:#1E1C1A!important}
body.dark-mode #wtn-toolbar span[style*="color:#5A7A6A"]{color:#989088!important}
body.dark-mode #wtn-body div[style*="color:#5A7A6A"]{color:#989088!important}
body.dark-mode #wtn-tabs button{background:#302E2B!important;color:#E0D8D0!important}
body.dark-mode #wtn-tabs button[style*="background:#2FBB7A"]{background:#2FBB7A!important;color:#fff!important}
body.dark-mode .wtn-chip-word{background:#2A2623!important;border-color:#4A4540!important;color:#E0D8D0!important}
body.dark-mode .wtn-chip-anno{background:#2A2820!important;border-color:#3A5040!important;color:#A0C8B0!important}
body.dark-mode #wtn-num-btns button{background:#2A2623!important;border-color:#4A4540!important;color:#E0D8D0!important}
body.dark-mode #sfx-0,body.dark-mode #sfx-1,body.dark-mode #sfx-2{background:#302E2B!important;border-color:#4A4540!important;color:#989088!important}
body.dark-mode #sfx-0.active,body.dark-mode #sfx-1.active,body.dark-mode #sfx-2.active{background:#3D2020!important;border-color:#9B3A3A!important;color:#D07070!important}
body.dark-mode #disp-num,body.dark-mode #disp-label,body.dark-mode #disp-anno{accent-color:#9B3A3A}
/* 主桌徽章暗夜 */
body.dark-mode .main-table-badge{background:#3D3000!important;color:#F5D060!important}
body.dark-mode .btn-main-toggle-on{background:#7B3030!important;color:#fff!important}
body.dark-mode .btn-main-toggle-off{background:#302E2B!important;border-color:#4A4540!important;color:#989088!important}
/* 桌位设置配置面板暗夜（补全）*/
body.dark-mode #tableConfigPanel .space-y-3>div{color:#E0D8D0}
body.dark-mode #tableConfigPanel input[type=range]{accent-color:#9B3A3A}
body.dark-mode #tableConfigPanel select{background:#302E2B!important;border-color:#4A4540!important;color:#E0D8D0!important}
body.dark-mode #wtn-toolbar button[onclick^="wtnApplyAll"]{background:#7B3030!important}
body.dark-mode #vResizeHandle{background:#3A3530!important}
body.dark-mode .tab-btn{color:#989088!important}
body.dark-mode .tab-btn.active{color:#D07070!important;border-bottom-color:#9B3A3A!important}
body.dark-mode .tag-chip{background:#302E2B!important;border-color:#4A4540!important;color:#B8B0A8!important}
body.dark-mode #zoomControls button{background:rgba(36,34,32,.92)!important;border-color:#4A4540!important;color:#D8D0C8!important}
body.dark-mode #zoomLevel{background:rgba(36,34,32,.85)!important;border-color:#4A4540!important;color:#B8B0A8!important}
body.dark-mode #bottomToolbar{background:#1E1C1A!important;border-color:#3A3530!important;color:#E0D8D0!important}
body.dark-mode #bottomToolbar .text-muted{color:#989088!important}
body.dark-mode #bottomToolbar input{background:#302E2B!important;border-color:#4A4540!important;color:#E0D8D0!important}
body.dark-mode #bottomToolbar input:focus{border-color:#9B3A3A!important}
/* 画布提示：移动/桌面双态 */
.hint-mobile{display:none}
.hint-desktop{display:inline}
@media(hover:none)and(pointer:coarse){.hint-desktop{display:none}.hint-mobile{display:inline}}
/* WTN 桌名工具暗夜补全 */
body.dark-mode #wtn-toolbar div[style*="color:#5A7A6A"]{color:#989088!important}
body.dark-mode #wtn-toolbar span[style*="color:#A0B8A8"]{color:#4A5048!important}
::-webkit-scrollbar{width:4px}::-webkit-scrollbar-thumb{background:#DDE6DC;border-radius:2px}
body.dark-mode ::-webkit-scrollbar-thumb{background:#4A4540!important}
body.dark-mode .guest-row:hover{background:#302E2B!important}
body.dark-mode .family-sat .guest-row{background:#262422!important}
body.dark-mode .family-sat .guest-row .text-ink{color:#807060!important}
/* N1: 深色模式分割线/边框加深 */
body.dark-mode #resizeHandle{background:#3A3530!important}
body.dark-mode #resizeHandle:hover{background:#4A3030!important}
body.dark-mode #resizeHandle::after{background:#6A5A50!important}
body.dark-mode header .bg-line{background:#5A5248!important}
body.dark-mode #paneRight{border-color:#4A4540!important}
body.dark-mode .border-line{border-color:#4A4540!important}
body.dark-mode #bottomToolbar{border-color:#4A4540!important}
/* N2: 分类条深色模式 — 不用 filter（子文字无法逃脱），直接替换背景色 */
body.dark-mode .side-cat-header{background:#2D2320!important;filter:none!important}
body.dark-mode .side-cat-header .cat-label{color:#EDE0D8!important}
body.dark-mode .side-cat-header .cat-stat{color:#B0A098!important}
body.dark-mode .side-cat-header .side-chevron{color:#B0A098!important}
body.dark-mode .side-cat-header .w-2{background:#8A7A70!important;opacity:1!important}
body.dark-mode .tab-btn:hover{background:#302E2B!important}
body.dark-mode #canvasWrap .absolute.bottom-3{background:rgba(30,28,26,.85)!important;border-color:#3A3530!important;color:#989088!important}
/* 暗夜模式文字修复 */
body.dark-mode #versionBadge{color:#F0E8E0!important}
body.dark-mode #projectTitle{color:#D07070!important}
body.dark-mode #guestList .text-ink{color:#F0E8E0!important}
body.dark-mode #guestList .text-muted{color:#E0D8D0!important}
body.dark-mode #guestList .family-sat .text-ink{color:#B2A090!important}
body.dark-mode #guestList .tag-chip{color:#E0D8D0!important;border-color:#5A5248!important;background:#302E2B!important}
body.dark-mode #guestList .guest-row{background:#242220!important}
body.dark-mode #guestList .guest-row:hover{background:#302E2B!important}
body.dark-mode #guestList .side-section-body{background:#242220!important}
/* 手风琴展开区 */
.side-section-body{flex:1;overflow-y:auto;background:#fff}
.btn{padding:5px 12px;border-radius:7px;font-size:11px;font-family:'Noto Sans SC',sans-serif;cursor:pointer;transition:all .15s;white-space:nowrap;border:1.5px solid transparent;display:inline-flex;align-items:center;gap:4px}
.btn-ghost{background:#fff;border-color:#DDE6DC;color:#1F3B2F}.btn-ghost:hover{background:#F2F6F1;border-color:#2FBB7A;color:#2FBB7A}
.btn-ghost.active{background:#e6f7ee;border-color:#2FBB7A;color:#2FBB7A}
.btn-primary{background:#2FBB7A;border-color:#2FBB7A;color:#fff}.btn-primary:hover{background:#239A65}
.btn-accent{background:#1F3B2F;border-color:#1F3B2F;color:#fff;font-size:13px;padding:7px 18px;font-weight:600}.btn-accent:hover{background:#2a5040}
.btn-danger{background:#fff;border-color:#e74c3c;color:#e74c3c}.btn-danger:hover{background:#e74c3c;color:#fff}
.btn-sm{padding:3px 9px;font-size:10px}.btn-icon{padding:5px 9px;font-size:13px;line-height:1}
.modal-wrap{position:fixed;inset:0;backdrop-filter:blur(4px);background:rgba(31,59,47,.25);display:none;align-items:center;justify-content:center;z-index:60}
.modal-wrap.open{display:flex}
.modal-box{background:#fff;border-radius:14px;box-shadow:0 12px 48px rgba(0,0,0,.16);border:1px solid #DDE6DC;padding:20px}
.modal-title{font-family:'Noto Serif SC',serif;font-size:13px;font-weight:600;color:#1F3B2F;margin-bottom:12px}
label.fl{display:block;font-size:11px;color:#5A7A6A;margin-bottom:3px}
.fi{width:100%;font-size:11px;padding:6px 8px;border:1.5px solid #DDE6DC;border-radius:7px;background:#F2F6F1;color:#1F3B2F;outline:none;font-family:'Noto Sans SC',sans-serif}
.fi:focus{border-color:#2FBB7A}
.status-pill{display:inline-flex;align-items:center;padding:2px 8px;border-radius:20px;font-size:10px;cursor:pointer;border:1.5px solid #DDE6DC;transition:all .12s;user-select:none;background:#F2F6F1;color:#5A7A6A}
.sp-confirmed{background:#e6f7ee;color:#239A65;border-color:#2FBB7A}
.sp-pending{background:#fff7e6;color:#b07a20;border-color:#e8a44a}
.sp-declined{background:#fdecea;color:#c0392b;border-color:#c0392b}
#seatCard{position:fixed;background:#fff;border:1px solid #DDE6DC;border-radius:10px;padding:10px 12px;min-width:172px;max-width:230px;pointer-events:auto;z-index:500;display:none;box-shadow:0 6px 24px rgba(31,59,47,.16)}
#seatCard .scn{font-size:13px;font-weight:600;color:#1F3B2F;margin-bottom:5px}
#seatCard .scno{font-size:10px;color:#5A7A6A;margin-bottom:7px;line-height:1.4;border-left:2px solid #DDE6DC;padding-left:6px}
#seatCard .scr{display:flex;gap:4px;flex-wrap:wrap}
#seatCard .scp{font-size:10px;padding:2px 8px;border-radius:20px;border:1.5px solid #DDE6DC;cursor:pointer;background:#F2F6F1;color:#5A7A6A;transition:all .12s}
#seatCard .sch{font-size:9px;color:#bbb;margin-top:6px;text-align:center}
#dragGhost{position:fixed;background:#2FBB7A;color:#fff;font-size:12px;padding:4px 12px;border-radius:20px;pointer-events:none;z-index:9999;display:none;box-shadow:0 4px 16px rgba(47,187,122,.35)}
.sub-item[draggable="true"]{cursor:grab}
.sub-item[draggable="true"]:hover{background:#f0faf4}
/* T7: 入座后分层样式——背景变淡，文字仅轻微变淡 */
.family-sat .guest-row{background:#f6f8f6!important}
.family-sat .guest-row .text-ink{color:#5a7a5a!important}
.sub-item.seated-sub{pointer-events:auto}
.sub-item.seated-sub .sub-chip{opacity:.45}
.sub-item.seated-sub .sub-loc{opacity:.65}
.guest-row:hover{background:#f7fbf8}
/* T6: 悬停提示光标 */
.guest-row[title]{cursor:default}
/* T5: 分类卷展栏 */
.side-section-body{transition:max-height .2s ease}
.side-chevron{transition:transform .2s ease;display:inline-block}
.tag-chip{font-size:9px;padding:1px 5px;border-radius:20px;border:1px solid #DDE6DC;color:#5A7A6A;background:#F2F6F1;white-space:nowrap}
.tab-btn{flex:1;padding:8px 4px;font-size:11px;border-bottom:2px solid transparent;color:#5A7A6A;cursor:pointer;background:transparent;transition:all .15s}
.tab-btn.active{color:#2FBB7A;border-bottom-color:#2FBB7A}
.swatch{width:22px;height:22px;border-radius:50%;cursor:pointer;border:2px solid transparent;transition:all .12s;flex-shrink:0;position:relative}
.swatch:hover,.swatch.sel{border-color:#1F3B2F;transform:scale(1.15)}
.swatch .swtip{display:none;position:absolute;bottom:26px;left:50%;transform:translateX(-50%);background:#1F3B2F;color:#fff;font-size:9px;padding:2px 5px;border-radius:4px;white-space:nowrap;pointer-events:none}
.swatch:hover .swtip{display:block}
#canvasWrap{cursor:default;user-select:none;touch-action:none}
#canvasWrap.panning{cursor:grabbing}
#canvasWrap.pan-mode{cursor:grab}
#zoomControls{position:absolute;left:12px;bottom:48px;display:flex;flex-direction:column;gap:4px;z-index:10}
#zoomControls button{width:30px;height:30px;border-radius:8px;border:1.5px solid #DDE6DC;background:rgba(255,255,255,.92);font-size:14px;cursor:pointer;display:flex;align-items:center;justify-content:center;transition:all .15s}
#zoomControls button:hover{border-color:#2FBB7A;color:#2FBB7A}
#zoomLevel{font-size:9px;text-align:center;color:#5A7A6A;background:rgba(255,255,255,.85);border-radius:6px;padding:2px 4px;border:1px solid #DDE6DC}
#bgDropZone{border:2px dashed #DDE6DC;border-radius:10px;padding:24px;text-align:center;transition:all .2s;cursor:pointer}
#bgDropZone.drag-over{border-color:#2FBB7A;background:#e6f7ee}
.dropdown-menu{position:absolute;top:calc(100% + 4px);background:#fff;border:1.5px solid #DDE6DC;border-radius:10px;box-shadow:0 8px 24px rgba(0,0,0,.12);z-index:200;padding:6px}
.dropdown-menu .dm-item{display:flex;flex-direction:column;gap:1px;padding:7px 10px;border-radius:7px;cursor:pointer;transition:background .12s}
.dropdown-menu .dm-item:hover{background:#F2F6F1}
.dropdown-menu .dm-item .dm-name{font-size:11px;font-weight:600;color:#1F3B2F}
.dropdown-menu .dm-item .dm-sub{font-size:9px;color:#5A7A6A}
.dropdown-menu .dm-sep{height:1px;background:#DDE6DC;margin:4px 0}
.dropdown-menu .dm-slot{display:flex;align-items:center;justify-content:space-between;padding:6px 10px;border-radius:7px;border:1.5px solid #DDE6DC;margin-bottom:4px;cursor:pointer;transition:all .12s}
.dropdown-menu .dm-slot:hover{border-color:#2FBB7A;background:#e6f7ee}
.dropdown-menu .dm-slot.empty{opacity:.5;border-style:dashed}
.dropdown-menu .dm-slot .dm-slot-name{font-size:10px;font-weight:600;color:#1F3B2F}
.dropdown-menu .dm-slot .dm-slot-sub{font-size:9px;color:#5A7A6A}
body.dark-mode .dropdown-menu{background:#242220!important;border-color:#3A3530!important}
body.dark-mode .dropdown-menu .dm-item:hover{background:#302E2B!important}
body.dark-mode .dropdown-menu .dm-item .dm-name{color:#E8E0D8!important}
body.dark-mode .dropdown-menu .dm-item .dm-sub{color:#989088!important}
body.dark-mode .dropdown-menu .dm-sep{background:#3A3530}
body.dark-mode .dropdown-menu .dm-slot{border-color:#4A4540!important;background:#2A2623!important}
body.dark-mode .dropdown-menu .dm-slot:hover{border-color:#9B3A3A!important;background:#3A2020!important}
body.dark-mode .dropdown-menu .dm-slot .dm-slot-name{color:#E8E0D8}
body.dark-mode .dropdown-menu .dm-slot .dm-slot-sub{color:#989088}

/* ── 仅查看模式 ── */
body.view-only-mode #saveServerWrap,
body.view-only-mode #paneRight,
body.view-only-mode #bottomToolbar .btn-primary,
body.view-only-mode #bottomToolbar button:not(#gridBtn):not(#bgVisBtn){pointer-events:none;opacity:.45}
body.view-only-mode #paneRight{position:relative}
body.view-only-mode #paneRight::after{
  content:'👁 仅查看';position:absolute;top:8px;right:10px;
  font-size:10px;color:#92400E;background:#FEF3C7;border:1px solid #F59E0B;
  border-radius:10px;padding:1px 8px;pointer-events:none;z-index:10}

/* ═══════════════════════════════════════════════════
   TABLET — 宽度 ≤999px：顶部菜单折叠为两行
═══════════════════════════════════════════════════ */
@media (max-width:999px){
  header{flex-wrap:wrap!important;height:auto!important;min-height:auto!important;padding:0!important}
  header>div:first-child{flex:1 1 auto!important;padding:5px 12px!important;gap:6px!important;overflow:hidden}
  header>div:last-child{
    flex:0 0 100%!important;padding:3px 10px 5px!important;gap:3px!important;
    flex-wrap:wrap!important;border-top:1px solid #EEF2EE;background:#FAFCFA;
  }
  header .btn-sm{font-size:10px!important;padding:2px 7px!important}
  header .btn-icon{font-size:12px!important;padding:3px 7px!important}
}
@media (max-width:999px){
  body.dark-mode header>div:last-child{border-top-color:#3A3530!important;background:#1E1C1A!important}
}

/* 警告徽章：1000–1200px 宽度区间 header 较紧，隐藏 */
@media (min-width:1000px) and (max-width:1200px){#warnBadge{display:none!important}}

/* ═══════════════════════════════════════════════════
   PHONE-PORTRAIT — 上下分割布局
   条件：宽度 <768px 且 高宽比 >3:2（竖屏手机）
   iPad / 平板横屏均不触发此规则
═══════════════════════════════════════════════════ */
@media (max-width:767px) and (max-aspect-ratio:2/3){
  #mainLayout{flex-direction:column!important}
  #paneLeft{flex:0 0 48vh!important;min-width:0!important;width:100%!important;min-height:180px!important}
  #resizeHandle{display:flex!important;width:100%!important;height:10px!important;cursor:row-resize!important;
    flex-shrink:0!important;align-items:center!important;justify-content:center!important;
    background:#DDE6DC!important;border-top:none!important;border-left:none!important}
  #resizeHandle::after{content:'';width:36px;height:3px;background:#AABCAA;border-radius:2px}
  #paneRight{flex:1 1 0!important;min-width:0!important;width:100%!important;min-height:160px!important;border-left:none!important;border-top:1px solid #DDE6DC!important}
  #paneRight .tab-btn{font-size:11px!important;padding:6px 12px!important}
}
@media (max-width:767px) and (max-aspect-ratio:2/3){
  body.dark-mode #paneRight{border-top-color:#3A3530!important}
}

/* ═══════════════════════════════════════════════════
   MOBILE — iPhone 竖版适配 (≤540px)
═══════════════════════════════════════════════════ */
@media (max-width:540px){
  /* ── Header：两行布局 ── */
  header{min-height:auto!important;height:auto!important;flex-wrap:wrap!important;padding:4px 8px!important;gap:0!important;align-items:flex-start!important}
  /* 第一行：左对齐，占满整行 */
  #hdrLeft{flex:0 0 100%!important;min-width:0!important;align-items:center!important}
  /* 第二行：右对齐（保存/加载下拉向左展开，故按钮组靠右） */
  #hdrActions{width:100%!important;justify-content:flex-end!important;flex-wrap:wrap!important;gap:3px!important;padding:3px 0 2px!important;border-top:1px solid #EEE;margin-top:3px!important}
  #hdrActions .btn-sm{font-size:10px!important;padding:2px 7px!important}
  /* 手机端：显示项目标题（截断）+ 显示版本号（隐藏授权码）*/
  #projectTitle{max-width:25vw!important;font-size:10px!important;white-space:nowrap!important;overflow:hidden!important;text-overflow:ellipsis!important}
  #versionBadgeWrap{display:inline-flex!important;flex-shrink:0}
  #vBadgeCode{display:none!important}
  #versionBadge{font-size:11px!important;letter-spacing:.5px!important}
  header .hidden.md\:inline{display:none!important}

  /* ── CSV 开关按钮（统计栏右侧）── */
  #csvMobileToggleBtn{display:inline-flex!important}
  #csvMobileBar.open{display:flex!important}

  /* ── 下拉菜单：固定到右边，不依赖父容器位置 ── */
  .dropdown-menu{
    position:fixed!important;
    right:8px!important;
    left:auto!important;
    top:82px!important;
    max-height:60vh!important;
    overflow-y:auto!important;
    max-width:calc(100vw - 16px)!important;
    min-width:calc(100vw - 16px)!important;
  }

  /* ── 底部工具栏 ── */
  #bottomToolbar{flex-wrap:wrap!important;gap:3px!important;padding:5px 8px!important}
  #bottomToolbar .btn-sm{font-size:9px!important;padding:2px 5px!important}
  #bottomToolbar .btn-primary{font-size:9px!important;padding:2px 7px!important}
  #bottomToolbar>.ml-auto{width:100%;justify-content:flex-start!important;margin-left:0!important}
  #roomWInput,#roomHInput{width:38px!important}

  /* ── CSV：宾客面板内的 csvRow 完全隐藏，改由下方 csvMobileBar 承担 ── */
  #csvRow{display:none!important}
}

/* 暗夜模式移动端补丁 */
@media (max-width:540px){
  body.dark-mode #paneRight{border-top-color:#3A3530!important}
  body.dark-mode #hdrActions{border-top-color:#3A3530!important}
  body.dark-mode #csvMobileBar{background:#1E1C1A!important;border-color:#3A3530!important}
}
/* csvMobileBar 仅手机可见（desktop 默认隐藏） */
#csvMobileBar{display:none;flex-shrink:0;border-top:1px solid #DDE6DC;padding:4px 10px;align-items:center;gap:4px;background:#fff}
/* 导航栏自动换行：所有屏幕尺寸下，操作区折行时始终靠右 */
header.shadow-sm{flex-wrap:wrap}
#hdrActions{margin-left:auto;justify-content:flex-end}
/* 首次引导动画 */
@keyframes guideFadeIn{from{opacity:0}to{opacity:1}}
@keyframes guidePulse{0%,100%{opacity:1;transform:translate(-50%,-50%) scale(1)}50%{opacity:.75;transform:translate(-50%,-50%) scale(.97)}}
/* 首次确认弹窗暗夜 */
body.dark-mode #firstRunModal .modal-box{background:#242220!important;border-color:#3A3530!important}
body.dark-mode #firstRunModal>div>div:first-child{color:#E8E0D8!important}
body.dark-mode #frInfo{background:#2A2623!important;border-color:#3A3530!important;color:#C0C8B8!important}
body.dark-mode #firstRunModal [style*="background:#FFF7E0"]{background:#2A2218!important;border-color:#5A4A20!important;color:#C8A860!important}
body.dark-mode #frTitle{background:#302E2B!important;border-color:#4A4540!important;color:#E0D8D0!important}

/*
╔══════════════════════════════════════════════════════════════╗
║  SeatCard 暗夜配色总表 (Dark Mode Color Reference)           ║
╠══════════════════════════════════════════════════════════════╣
║  基础底色                                                     ║
║    Body bg        #2A2623   Panel bg      #242220            ║
║    Header bg      #1E1C1A   Surface       #302E2B            ║
║    Canvas bg      #3A3835   Border        #3A3530 / #4A4540  ║
║    Text primary   #E8E0D8   Text muted    #989088            ║
║                                                              ║
║  强调色（替换亮绿 #2FBB7A）                                   ║
║    通用暗红          #9B3A3A   (btn-primary, selected)        ║
║    灰红标签          #C07070   (labels, +/-, slider, seated)  ║
║    激活态暗红        #7B3030   (所属方/尺寸 active btn)        ║
║    悬停暗红          #D07070   (btn-ghost hover)              ║
║                                                              ║
║  SVG 画布                                                    ║
║    桌碟 fill alpha  32 (vs 18 normal)                        ║
║    选中描边          #9B3A3A                                  ║
║    空座 fill         #484240  (淡灰，非纯黑)                  ║
║    空座 stroke       #605850                                  ║
║    空座 + 号         #8A7870                                  ║
║    空座 序号         #706860                                  ║
╚══════════════════════════════════════════════════════════════╝
*/

/* ── SC 配色变量（亮色模式默认值）── */
:root{
  --sc-label-c:#1F3B2F;         /* 底部 桌号/桌名/标注 文字 */
  --sc-adj-bg:#F2F6F1;          /* +/- 按钮背景 */
  --sc-adj-border:#DDE6DC;      /* +/- 按钮边框 */
  --sc-adj-c:#5A7A6A;           /* +/- 按钮文字 */
  --sc-type-bg:#fff;            /* 所属方/尺寸 非激活背景 */
  --sc-type-border:#DDE6DC;     /* 所属方/尺寸 非激活边框 */
  --sc-type-c:#5A7A6A;          /* 所属方/尺寸 非激活文字 */
  --sc-type-on-bg:#2FBB7A;      /* 所属方/尺寸 激活背景 */
  --sc-type-on-border:#2FBB7A;  /* 所属方/尺寸 激活边框 */
  --sc-slider-c:#2FBB7A;        /* 滑块 accent */
  --sc-seated-c:#2FBB7A;        /* 就坐计数文字 */
  --sc-dir-bg:#e6f7ee;          /* 服务器目录标签背景 */
  --sc-dir-border:#b2dfcc;      /* 服务器目录标签下边框 */
  --sc-dir-c:#1F3B2F;           /* 服务器目录标签文字 */
  --sc-rename-bg:#2FBB7A;       /* 全部重命名按钮背景 */
  --sc-rename-c:#fff;
  --sc-sel-stroke:#2FBB7A;      /* 画布选中圆桌描边 */
}
/* 暗夜模式 — 朱砂灰红覆盖 */
body.dark-mode{
  --sc-label-c:#C07070;
  --sc-adj-bg:#2A1E1E;
  --sc-adj-border:#5A3A3A;
  --sc-adj-c:#C07070;
  --sc-type-bg:#261E1E;
  --sc-type-border:#5A3A3A;
  --sc-type-c:#C07070;
  --sc-type-on-bg:#7B3030;
  --sc-type-on-border:#7B3030;
  --sc-slider-c:#C07070;
  --sc-seated-c:#C07070;
  --sc-dir-bg:#261E1E;
  --sc-dir-border:#5A3A3A;
  --sc-dir-c:#C07070;
  --sc-rename-bg:#7B3030;
  --sc-rename-c:#fff;
  --sc-sel-stroke:#9B3A3A;
}

/* ── 应用变量的 CSS 规则 ── */
label.disp-label{color:var(--sc-label-c)!important}
/* ── 显示选项切换按钮 ── */
.disp-opt-btn{padding:2px 8px;border:1.5px solid #DDE6DC;border-radius:5px;font-size:10px;font-weight:500;background:#fff;color:#7A9A8A;cursor:pointer;transition:all .12s;white-space:nowrap;line-height:1.6}
.disp-opt-btn.disp-opt-active{background:#2FBB7A;border-color:#2FBB7A;color:#fff}
.disp-opt-btn:hover:not(.disp-opt-active){border-color:#2FBB7A;color:#2FBB7A}
body.dark-mode .disp-opt-btn{background:#302E2B!important;border-color:#4A4540!important;color:#A0A0A0!important}
body.dark-mode .disp-opt-btn.disp-opt-active{background:#9B3A3A!important;border-color:#9B3A3A!important;color:#fff!important}
body.dark-mode .disp-opt-btn:hover:not(.disp-opt-active){border-color:#9B3A3A!important;color:#C07070!important}
.adj-num-btn{border:1px solid var(--sc-adj-border)!important;background:var(--sc-adj-bg)!important;color:var(--sc-adj-c)!important}
.cfg-type-btn-off{background:var(--sc-type-bg)!important;border-color:var(--sc-type-border)!important;color:var(--sc-type-c)!important}
.cfg-type-btn-off:hover{border-color:var(--sc-slider-c)!important;color:var(--sc-slider-c)!important}
.cfg-type-btn-on{background:var(--sc-type-on-bg)!important;border-color:var(--sc-type-on-border)!important;color:#fff!important}
strong.sc-seated{color:var(--sc-seated-c)!important}
.dm-dir-label{background:var(--sc-dir-bg)!important;border-bottom:1px solid var(--sc-dir-border)!important;color:var(--sc-dir-c)!important}
.dm-dir-label code{color:inherit!important}
.wtn-rename-btn{background:var(--sc-rename-bg)!important;color:var(--sc-rename-c)!important}

/* ── 顶部按钮高度统一 ── */
header .btn-sm{height:28px!important;display:inline-flex!important;align-items:center!important;justify-content:center!important}
header .btn-icon{height:28px!important;display:inline-flex!important;align-items:center!important;justify-content:center!important}

/* ── versionBadge 悬浮提示框 ── */
#versionBadgeWrap{position:relative;display:inline-flex;flex-shrink:0}
#versionBadge{cursor:pointer;border-radius:5px;padding:1px 5px;transition:background .15s}
#versionBadge:hover{background:rgba(47,187,122,.1)}
#badgeTip{display:none;position:fixed;z-index:9999;
  background:#fff;border:1.5px solid #DDE6DC;border-radius:10px;
  padding:10px 12px;box-shadow:0 8px 24px rgba(0,0,0,.18);min-width:240px;white-space:normal}
#badgeTip.open{display:block}
#badgeTip .tip-code{font-family:monospace;font-size:13px;font-weight:700;color:#1F3B2F;
  text-align:center;padding-bottom:6px;margin-bottom:0;border-bottom:1px solid #DDE6DC;
  letter-spacing:2px;word-break:break-all}
#badgeTip .tip-tab{padding:3px 12px;border:1.5px solid #DDE6DC;border-radius:6px;font-size:11px;background:#fff;color:#5A7A6A;cursor:pointer;transition:all .12s}
#badgeTip .tip-tab-active{background:#2FBB7A!important;border-color:#2FBB7A!important;color:#fff!important}
#badgeTip .tip-url{width:100%;font-size:9px;font-family:monospace;border:1px solid #DDE6DC;border-radius:4px;padding:3px 5px;outline:none;background:#F8FAF8;color:#1F3B2F;box-sizing:border-box}
#badgeTip .tip-url:focus{border-color:#9BCFB8}
#badgeTip .tip-copy-btn{padding:3px 10px;border:1.5px solid #DDE6DC;background:#fff;border-radius:6px;font-size:11px;cursor:pointer;color:#5A7A6A;white-space:nowrap}
#badgeTip .tip-copy-btn:hover{border-color:#2FBB7A;color:#2FBB7A;background:#f0faf5}
#badgeTip .tip-note{font-size:9px;color:#5A7A6A;line-height:1.5;margin-top:6px}
#badgeTipMsg{font-size:10px;color:#2FBB7A;text-align:center;min-height:14px;margin-top:4px}
body.dark-mode #badgeTip{background:#242220!important;border-color:#3A3530!important}
body.dark-mode #badgeTip .tip-code{color:#E8E0D8!important;border-color:#3A3530!important}
body.dark-mode #badgeTip .tip-tab{background:#302E2B!important;border-color:#4A4540!important;color:#A0A0A0!important}
body.dark-mode #badgeTip .tip-tab-active{background:#9B3A3A!important;border-color:#9B3A3A!important;color:#fff!important}
body.dark-mode #badgeTip .tip-url{background:#2A2826!important;border-color:#4A4540!important;color:#D8D0C8!important}
body.dark-mode #badgeTip .tip-copy-btn{background:#302E2B!important;border-color:#4A4540!important;color:#A0A0A0!important}
body.dark-mode #badgeTip .tip-copy-btn:hover{border-color:#9B3A3A!important;color:#C07070!important}
body.dark-mode #badgeTip .tip-note{color:#888080!important}
</style>
</head>
<body class="bg-warm text-ink flex flex-col select-none<?= $VIEW_ONLY?' view-only-mode':'' ?>" id="appBody" style="min-height:600px">

<?php if($VIEW_ONLY): ?>
<div id="viewOnlyBanner" style="background:#FEF3C7;border-bottom:2px solid #F59E0B;padding:5px 16px;display:flex;align-items:center;gap:8px;flex-shrink:0">
  <span style="font-size:12px;font-weight:700;color:#92400E">👁 仅查看模式</span>
  <span style="font-size:11px;color:#78350F">此链接只能查看，无法编辑或保存数据</span>
</div>
<?php endif; ?>

<header class="flex items-center px-4 py-1.5 bg-white border-b border-line flex-shrink-0 shadow-sm gap-2">
  <!-- 第一行左侧：logo + 标题 + 版本徽章 + 警告 -->
  <div id="hdrLeft" class="flex items-center gap-2 flex-1 min-w-0">
    <button id="shareBtn" onclick="toggleBadgeTip(event)" class="btn btn-ghost btn-icon" title="分享/复制链接" style="font-size:14px;flex-shrink:0;padding:2px 8px">🔗</button>
    <button id="helpLogoBtn" onclick="openHelpAndMark()" class="btn btn-ghost btn-icon" title="使用说明" style="padding:3px;width:30px;height:30px;border-radius:8px;flex-shrink:0">
      <svg id="logoSvg" viewBox="0 0 32 32" width="24" height="24" xmlns="http://www.w3.org/2000/svg">
        <circle cx="16" cy="16" r="9" fill="#2FBB7A" opacity="0.18" stroke="#2FBB7A" stroke-width="1.5"/>
        <g id="seatChips">
          <rect x="-5" y="-3" width="10" height="6" rx="3" fill="#B99390" transform="rotate(0)  translate(16,5.5)"/>
          <rect x="-5" y="-3" width="10" height="6" rx="3" fill="#8AAABB" transform="rotate(40) translate(16,5.5)"/>
          <rect x="-5" y="-3" width="10" height="6" rx="3" fill="#A8B8A0" transform="rotate(80) translate(16,5.5)"/>
          <rect x="-5" y="-3" width="10" height="6" rx="3" fill="#C0AA88" transform="rotate(120) translate(16,5.5)"/>
          <rect x="-5" y="-3" width="10" height="6" rx="3" fill="#B0A8B8" transform="rotate(160) translate(16,5.5)"/>
          <rect x="-5" y="-3" width="10" height="6" rx="3" fill="#A8B099" transform="rotate(200) translate(16,5.5)"/>
          <rect x="-5" y="-3" width="10" height="6" rx="3" fill="#C09090" transform="rotate(240) translate(16,5.5)"/>
          <rect x="-5" y="-3" width="10" height="6" rx="3" fill="#90B0A8" transform="rotate(280) translate(16,5.5)"/>
          <rect x="-5" y="-3" width="10" height="6" rx="3" fill="#B8A0A8" transform="rotate(320) translate(16,5.5)"/>
        </g>
        <text id="logoQ" x="16" y="19.5" text-anchor="middle" font-size="11" font-weight="700" fill="#2FBB7A" font-family="serif">?</text>
      </svg>
    </button>
    <span id="projectTitle" class="font-serif text-primary text-sm font-semibold tracking-wide cursor-pointer hover:bg-primary/10 px-2 py-0.5 rounded transition-colors" title="双击编辑项目名称" ondblclick="editProjectName()" style="text-decoration:underline;text-decoration-color:rgba(47,187,122,0.35);text-underline-offset:3px;border:1px dashed rgba(47,187,122,0.22);padding:1px 7px;border-radius:5px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;flex-shrink:1;min-width:0;max-width:clamp(80px,20vw,260px)">婚宴座位图</span>
    <div id="versionBadgeWrap" class="hidden sm:inline-flex">
      <span class="text-muted whitespace-nowrap leading-none" id="versionBadge"
        style="font-size:13px;font-weight:700;letter-spacing:1.5px;line-height:1"
        onclick="toggleBadgeTip(event)" title="点击查看分享选项">SEATCARD V0.24<span id="vBadgeCode"> ·<?= htmlspecialchars($VIEW_ONLY?($rawAuth.'…'):$AUTH_CODE,ENT_QUOTES) ?></span></span>
    </div>
    <span id="warnBadge" class="hidden md:inline" style="font-size:9.5px;color:#d97706;background:#fef3c7;border:1px solid #fcd34d;padding:1px 7px;border-radius:10px;line-height:1.4;white-space:nowrap;flex-shrink:0">⚠ 服务器存储不加密，勿上传隐私数据</span>
  </div>
  <!-- mobileShareBtn 已被 hdrLeft 内的 shareBtn 取代，保留占位但不显示 -->
  <span id="mobileShareBtn" style="display:none"></span>
  <!-- 操作按钮区（桌面第一行右侧 / 手机第二行全宽）-->
  <div id="hdrActions" class="flex gap-1 items-center flex-shrink-0">
    <button onclick="undo()" class="btn btn-ghost btn-icon" title="撤销上一步 Ctrl+Z">↩</button>
    <div class="w-px h-4 bg-line mx-0.5 hdr-sep"></div>
    <button onclick="saveSnapshot()" class="btn btn-ghost btn-sm" title="下载JSON到本地（同时静默备份到服务器）">⬇ 下载JSON</button>
    <button onclick="triggerJsonLoad()" class="btn btn-ghost btn-sm" title="从本地上载JSON（⚠ 将覆盖当前数据）">⬆ 上载JSON</button>
    <div class="w-px h-4 bg-line mx-0.5"></div>
    <div class="relative" id="loadServerWrap">
      <button onclick="toggleDropdown('loadServerDrop')" class="btn btn-ghost btn-sm" title="从服务器加载备份">📥 从服务器加载 ▾</button>
      <div id="loadServerDrop" class="dropdown-menu" style="display:none;right:0;left:auto;min-width:300px;max-height:400px;overflow-y:auto"></div>
    </div>
    <div class="relative" id="saveServerWrap">
      <button onclick="toggleDropdown('saveServerDrop');populateSaveSlots()" class="btn btn-ghost btn-sm" title="保存到服务器（5个存档位＋自动备份）">☁ 保存到服务器 ▾</button>
      <div id="saveServerDrop" class="dropdown-menu" style="display:none;right:0;left:auto;min-width:300px"></div>
    </div>
    <button onclick="toggleDark()" id="darkBtn" title="切换喜夜模式" class="btn btn-ghost btn-icon" style="font-size:14px">🌙</button>
  </div>
</header>

<div class="flex flex-1 overflow-hidden" id="mainLayout">
  <div class="flex flex-col overflow-hidden" id="paneLeft" style="flex:0 0 65%;min-width:400px">
    <div class="relative flex-1 overflow-hidden" id="canvasWrap">
      <svg id="canvas" class="absolute inset-0 w-full h-full" style="background:#FBF7E6">
        <defs>
          <pattern id="grid1m" width="50" height="50" patternUnits="userSpaceOnUse">
            <path d="M 50 0 L 0 0 0 50" fill="none" stroke="#DDE6DC" stroke-width="0.5"/>
          </pattern>
          <pattern id="grid5m" width="250" height="250" patternUnits="userSpaceOnUse">
            <rect width="250" height="250" fill="url(#grid1m)"/>
            <path d="M 250 0 L 0 0 0 250" fill="none" stroke="#C8D8C0" stroke-width="1"/>
          </pattern>
          <!-- 拖动区斜条纹 -->
          <pattern id="dragStripe" patternUnits="userSpaceOnUse" width="6" height="6" patternTransform="rotate(45)">
            <line x1="0" y1="0" x2="0" y2="6" stroke="rgba(255,255,255,0.7)" stroke-width="3"/>
          </pattern>
        </defs>
        <g id="worldGroup">
          <image id="bgImage" x="0" y="0" width="2000" height="2000" preserveAspectRatio="none" style="display:none;opacity:0.2"/>
          <g id="gridGroup">
            <rect id="gridRect" x="-2000" y="-2000" width="8000" height="8000" fill="url(#grid5m)"/>
          </g>
          <rect id="roomRect" x="0" y="0" width="1000" height="1000" fill="none" stroke="#2FBB7A" stroke-width="1.5" stroke-dasharray="8,4" opacity="0.5"/>
          <g id="roomLabels"></g>
          <g id="tablesGroup"></g>
          <g id="distLabels"></g>
          <rect id="selBox" x="0" y="0" width="0" height="0" fill="rgba(47,187,122,0.08)" stroke="#2FBB7A" stroke-width="1" stroke-dasharray="4,3" display="none"/>
        </g>
      </svg>
      <div id="zoomControls">
        <button onclick="zoomIn()">＋</button>
        <div id="zoomLevel">100%</div>
        <button onclick="zoomOut()">－</button>
        <button onclick="zoomReset()" style="font-size:10px">⊡</button>
      </div>
      <button onclick="exportPdf()" class="btn btn-primary btn-sm absolute top-3 right-3 z-10" style="font-size:11px">📄 导出 PDF</button>
      <div class="absolute bottom-3 left-1/2 -translate-x-1/2 text-[10px] text-muted bg-warm/80 px-3 py-1 rounded-full border border-line whitespace-nowrap canvas-hint"
           style="cursor:pointer;user-select:none" onclick="applyHintMode(true)" title="点击强制刷新操作提示">
        <span class="hint-desktop">滚轮缩放 · 空格+拖动 或 中键 平移 · 单击选桌 · 拖动框选</span>
        <span class="hint-mobile">移动模式：双指拖动缩放，单击选择</span>
      </div>
    </div>
    <div class="flex-shrink-0 flex items-center gap-3 px-4 py-2.5 flex-wrap border-t border-line" style="background:rgba(255,251,230,.95)" id="bottomToolbar">
      <button onclick="addTable()" class="btn btn-primary btn-sm" style="font-weight:600">＋ 添加圆桌</button>
      <div class="w-px h-5 bg-line"></div>
      <button onclick="openBgModal()" class="btn btn-ghost btn-sm">🖼 背景图</button>
      <button onclick="toggleBgVisible()" id="bgVisBtn" class="btn btn-ghost btn-sm">👁 显示底图</button>
      <button onclick="toggleGrid()" id="gridBtn" class="btn btn-ghost btn-sm active">▦ 网格</button>
      <div class="w-px h-5 bg-line"></div>
      <button id="disp-num-btn"   onclick="toggleDispOpt('num')"   class="disp-opt-btn"             title="显示/隐藏桌号">桌号</button>
      <button id="disp-label-btn" onclick="toggleDispOpt('label')" class="disp-opt-btn disp-opt-active" title="显示/隐藏桌名">桌名</button>
      <button id="disp-anno-btn"  onclick="toggleDispOpt('anno')"  class="disp-opt-btn"             title="显示/隐藏标注">标注</button>
      <div class="ml-auto flex items-center gap-2 text-xs text-muted">
        <button onclick="refreshCheck()" class="btn btn-ghost btn-sm" style="font-size:11px">🔄 校验</button>
        <div class="w-px h-4 bg-line"></div>
        <span title="设置场地尺寸（米）&#10;影响：平面图坐标、碰撞检测、PDF导出SVG的实际比例&#10;建议与背景底图保持一致">场地:</span>
        <input id="roomWInput" type="number" value="20" min="10" max="80" step="1"
          class="w-12 text-xs px-1 py-0.5 border border-line rounded text-center bg-surface outline-none focus:border-primary text-ink"
          onchange="updateRoomSize()"> m ×
        <input id="roomHInput" type="number" value="20" min="10" max="80" step="1"
          class="w-12 text-xs px-1 py-0.5 border border-line rounded text-center bg-surface outline-none focus:border-primary text-ink"
          onchange="updateRoomSize()"> m
      </div>
    </div>
  </div>

  <!-- 座位数滑块刻度 -->
  <datalist id="seats-ticks">
    <?php for($i=6;$i<=14;$i++) echo "<option value='$i'></option>"; ?>
  </datalist>

  <div id="resizeHandle" style="width:5px;cursor:col-resize;background:#DDE6DC;flex-shrink:0;transition:background .15s" onmouseenter="this.style.background='#2FBB7A'" onmouseleave="this.style.background='#DDE6DC'"></div>
  <div class="flex flex-col bg-white border-l border-line overflow-hidden" id="paneRight" style="flex:0 0 35%;min-width:320px">
    <div class="flex border-b border-line flex-shrink-0">
      <button class="tab-btn active" id="tab-guests-btn" onclick="switchTab('guests')">宾客名单</button>
      <button class="tab-btn" id="tab-config-btn" onclick="switchTab('config')">桌位设置</button>
    </div>
    <div id="panel-guests" class="flex flex-col flex-1 overflow-hidden">
      <div class="flex gap-2 px-3 py-2 border-b border-line flex-shrink-0">
        <input id="searchInput" type="text" placeholder="搜索宾客…" class="flex-1 text-xs px-2 py-1.5 rounded-md border border-line bg-surface outline-none focus:border-primary text-ink" oninput="filterGuests(this.value)">
        <button id="mobileEditBtn" onclick="mobileEditSelected()" class="btn btn-ghost btn-sm" style="display:none;padding:4px 9px;font-size:12px" title="编辑选中宾客">✏ 编辑</button>
        <button onclick="openGuestModal(null)" class="btn btn-primary" style="padding:4px 12px;font-size:14px;line-height:1">＋</button>
      </div>
      <div id="guestList" class="flex-1 py-1 px-1" style="display:flex;flex-direction:column;overflow:hidden"></div>
      <div id="csvRow" class="flex-shrink-0 border-t border-line px-3 py-1.5 flex items-center gap-1">
        <div id="csvToolbar" class="flex gap-1 flex-wrap flex-1">
          <button onclick="triggerListImport()" class="btn btn-ghost btn-sm flex-1" style="min-width:80px" title="每行一人，逗号后为配偶，首行自动识别标题">📋 导入名单</button>
          <button onclick="triggerCsvImport()" class="btn btn-ghost btn-sm flex-1" style="min-width:80px">📥 导入CSV</button>
          <button onclick="exportCsv()" class="btn btn-ghost btn-sm flex-1" style="min-width:80px">📤 导出CSV</button>
          <button onclick="exportCsvTemplate()" class="btn btn-ghost btn-sm flex-1" style="min-width:80px">📋 样板</button>
        </div>
        <!-- 手机专属：展开/折叠 CSV 工具栏的按钮 -->
        <button id="csvToggleBtn" onclick="toggleCsvToolbar()" class="btn btn-ghost btn-icon" title="CSV 工具" style="display:none;flex-shrink:0;font-size:13px;margin-left:auto">📋</button>
      </div>
    </div>
    <div id="panel-config" class="flex-col flex-1 overflow-hidden" style="display:none">
      <!-- Table list -->
      <div id="tableListPanel" class="flex-shrink-0 border-b border-line overflow-y-auto" style="max-height:38%">
        <div class="px-3 py-1.5 bg-surface flex items-center justify-between sticky top-0 z-10">
          <span class="text-[11px] font-semibold text-muted">所有圆桌</span>
          <span id="tableListCount" class="text-[10px] text-muted"></span>
        </div>
        <div id="tableListItems"></div>
      </div>
      <!-- WTN 桌名工具（折叠） -->
      <div id="wtn-toolbar" class="flex-shrink-0 border-b border-line bg-surface">
        <!-- 折叠头：桌名工具  拖动命名  后缀  全部重命名  ▶ -->
        <div class="flex items-center px-2 py-1 cursor-pointer" onclick="wtnToggle()" style="user-select:none;gap:3px">
          <span style="font-size:10px;font-weight:600;color:#5A7A6A;flex-shrink:0">🏷 桌名工具</span>
          <span style="font-size:9px;color:#A0B8A8;margin:0 4px;flex-shrink:0">拖动命名</span>
          <div onclick="event.stopPropagation()" style="display:flex;align-items:center;gap:2px;flex-shrink:0">
            <span style="font-size:9px;color:#5A7A6A">后缀</span>
            <button onclick="wtnSetSfx('')"  id="sfx-0" style="font-size:9px;padding:1px 5px;border-radius:4px;border:1.5px solid #2FBB7A;background:#2FBB7A;color:#fff;cursor:pointer;transition:all .12s;line-height:1.6">无</button>
            <button onclick="wtnSetSfx('桌')" id="sfx-1" style="font-size:9px;padding:1px 5px;border-radius:4px;border:1.5px solid #DDE6DC;background:#F2F6F1;color:#5A7A6A;cursor:pointer;transition:all .12s;line-height:1.6">桌</button>
            <button onclick="wtnSetSfx('席')" id="sfx-2" style="font-size:9px;padding:1px 5px;border-radius:4px;border:1.5px solid #DDE6DC;background:#F2F6F1;color:#5A7A6A;cursor:pointer;transition:all .12s;line-height:1.6">席</button>
          </div>
          <button onclick="event.stopPropagation();wtnApplyAll()" class="wtn-rename-btn" style="font-size:9px;border:none;border-radius:5px;padding:1px 6px;cursor:pointer;font-family:inherit;font-weight:600;white-space:nowrap;flex-shrink:0;line-height:1.7;margin-left:3px">全部重命名</button>
          <span id="wtn-chevron" style="font-size:9px;color:#5A7A6A;transition:transform .2s;display:inline-block;margin-left:auto">▶</span>
        </div>
        <!-- 展开体 -->
        <div id="wtn-body" style="display:none">
          <!-- Row 1: 桌名模板 -->
          <div style="border-top:1px solid #DDE6DC;padding:4px 8px 4px">
            <div style="font-size:9px;color:#5A7A6A;font-weight:600;margin-bottom:3px">桌名模板</div>
            <div id="wtn-tabs" class="flex overflow-x-auto gap-0.5" style="scrollbar-width:none;-ms-overflow-style:none;flex-wrap:nowrap;padding-bottom:2px"></div>
            <div id="wtn-chips" class="flex overflow-x-auto gap-1 py-1" style="flex-wrap:nowrap;scrollbar-width:thin;min-height:26px"></div>
          </div>
          <!-- Row 2: 标注 + 桌号快填 -->
          <div style="border-top:1px solid #DDE6DC;padding:4px 8px 4px">
            <div style="font-size:9px;color:#5A7A6A;font-weight:600;margin-bottom:3px">标注（拖到桌子）</div>
            <div id="wtn-anno-chips" class="flex overflow-x-auto gap-1" style="flex-wrap:nowrap;scrollbar-width:thin;padding-bottom:3px"></div>
          </div>
        </div>
      </div>
      <!-- Selected table config -->
      <div id="tableConfigPanel" class="flex-1 overflow-y-auto p-3"
        ondragover="if(event.dataTransfer.types.includes('wtn-type')){event.preventDefault();this.style.outline='2px dashed #2FBB7A'}"
        ondragleave="this.style.outline=''"
        ondrop="this.style.outline='';wtnDropOnConfigPanel(event)">
        <p class="text-xs text-muted text-center pt-8">请先点击选择一张圆桌</p>
      </div>
    </div>
    <!-- 统计栏 + 手机端 CSV 开关 -->
    <div id="statsWrapper" class="flex-shrink-0 border-t border-line bg-surface flex items-stretch">
      <div id="statsFooter" class="flex-1 px-3 py-2 space-y-1 text-xs text-muted min-w-0"></div>
      <button id="csvMobileToggleBtn" onclick="toggleCsvMobileBar()"
        class="btn btn-ghost btn-sm flex-shrink-0 self-center"
        title="CSV 工具" style="display:none;margin:0 6px;font-size:11px">📋 CSV</button>
    </div>
  </div>
</div>

<!-- 手机端 CSV 工具条（mainLayout 下方独立层，仅手机可见） -->
<div id="csvMobileBar">
  <span style="font-size:9px;color:#5A7A6A;font-weight:600;white-space:nowrap;flex-shrink:0">CSV</span>
  <button onclick="triggerListImport()" class="btn btn-ghost btn-sm" style="flex:1;min-width:0" title="每行一人">📋 名单</button>
  <button onclick="triggerCsvImport()" class="btn btn-ghost btn-sm" style="flex:1;min-width:0">📥 导入CSV</button>
  <button onclick="exportCsv()" class="btn btn-ghost btn-sm" style="flex:1;min-width:0">📤 导出CSV</button>
  <button onclick="exportCsvTemplate()" class="btn btn-ghost btn-sm" style="flex:1;min-width:0">📋 样板</button>
</div>

<!-- HELP MODAL -->
<div id="helpModal" class="modal-wrap" onclick="if(event.target===this)document.getElementById('helpModal').classList.remove('open')">
  <div class="modal-box" style="width:min(720px,95vw);max-height:85vh;overflow-y:auto" onclick="event.stopPropagation()">
    <div class="modal-title">📖 SeatCard V0.24 · 使用说明</div>
    <div class="grid gap-4" style="grid-template-columns:1fr 1fr;font-size:11px;line-height:1.7">

      <div>
        <div class="font-semibold text-ink mb-1">🔑 授权码 · 多用户隔离</div>
        <div class="space-y-1 text-muted">
          <div>每次使用需要一个 <strong class="text-ink">9位授权码</strong>（格式 <code style="background:#f0f0f0;padding:1px 3px;border-radius:3px">YYMMDDXcc</code>）</div>
          <div><strong class="text-ink">按日期进入</strong>：选择年/月/日 → 点「生成新授权码」→ 系统自动分配下一个场次字母（A→B→C…）</div>
          <div><strong class="text-ink">历史码</strong>：同一日期已生成的码会列出，点击直接进入</div>
          <div><strong class="text-ink">直接输入码</strong>：输入已有码，若不在记录中会显示橙色警告但仍可进入</div>
          <div>仅支持 9 位新格式（<code style="background:#f0f0f0;padding:1px 3px;border-radius:3px">YYMMDDXcc</code>）</div>
        </div>
      </div>

      <div>
        <div class="font-semibold text-ink mb-1">🚀 基本流程</div>
        <ol class="space-y-1 text-muted list-decimal list-inside">
          <li>设置场地尺寸（右下角 m×m 输入框）</li>
          <li>点「＋ 添加圆桌」或在画布空白处<strong class="text-ink">双击</strong>添加</li>
          <li>点击桌子选中 → 右侧「桌位设置」调整名称 / 所属方 / 桌号 / 尺寸 / 座位数</li>
          <li>右侧「宾客名单」→「＋」添加宾客，选颜色、所属、状态</li>
          <li>从宾客列表把人名<strong class="text-ink">拖到桌子</strong>（自动找空位）或<strong class="text-ink">拖到指定座位胶囊</strong></li>
          <li>点空座位（＋）也可快速搜索指派</li>
          <li>点击「📄 导出 PDF」生成 A4 横版单页</li>
        </ol>
      </div>

      <div>
        <div class="font-semibold text-ink mb-1">🖱 画布操作</div>
        <div class="space-y-1 text-muted">
          <div><strong class="text-ink">滚轮</strong> 缩放</div>
          <div><strong class="text-ink">空格+拖动</strong> 或 <strong class="text-ink">中键拖动</strong> 平移</div>
          <div><strong class="text-ink">单击桌子</strong> 选中（底盘变实色）</div>
          <div><strong class="text-ink">拖动桌子</strong> 移动位置（拖动时显示相邻距离，红色=不足 1.8m）</div>
          <div><strong class="text-ink">空白处拖出框</strong> 框选多桌，再拖动整组移动</div>
          <div><strong class="text-ink">空白处点击</strong> 取消选择</div>
          <div><strong class="text-ink">双击桌心</strong> 编辑桌子名称</div>
          <div><strong class="text-ink">双击座位胶囊</strong> 移除该人</div>
          <div><strong class="text-ink">悬停座位</strong> 查看详情、改状态、编辑宾客</div>
          <div><strong class="text-ink">Ctrl+Z</strong> 撤销（最近 40 步）</div>
        </div>
      </div>

      <div>
        <div class="font-semibold text-ink mb-1">🪑 桌位设置</div>
        <div class="space-y-1 text-muted">
          <div><strong class="text-ink">所属方</strong>：男方 / 女方 / 共同 / 自定义（可在设置里扩展至多 6 方）</div>
          <div><strong class="text-ink">桌号</strong>：自由填写，用于 PDF 名单中的序号显示</div>
          <div><strong class="text-ink">春联名称</strong>：为 PDF 桌牌起别称（如「鸿运桌」）</div>
          <div><strong class="text-ink">就坐列表拖排</strong>：拖动行可调整 PDF 打印顺序</div>
          <div>右侧侧边栏按所属方折叠分区，颜色自动对应</div>
        </div>
      </div>

      <div>
        <div class="font-semibold text-ink mb-1">🎨 座位名牌</div>
        <div class="space-y-1 text-muted">
          <div>30 种莫兰迪色 + 6 种特殊色（喜红 / 天蓝 / 金橙 / 翠绿 / 紫兰 / 橙粉）</div>
          <div>状态小点：<span style="color:#22c55e">●绿</span>=确认 <span style="color:#f59e0b">●黄</span>=待定 <span style="color:#ef4444">●红</span>=标注</div>
          <div><strong class="text-ink">小孩</strong>：名牌左上角橙点，两行显示（家长 / 小孩名）</div>
          <div><strong class="text-ink">配偶</strong>：两行显示（主人名 / 丈·妻或具体姓名）</div>
          <div>工具栏「显示配偶名」复选框控制第二行是否展开</div>
        </div>
      </div>

      <div>
        <div class="font-semibold text-ink mb-1">📄 PDF 导出</div>
        <div class="space-y-1 text-muted">
          <div>A4 横版单页：左侧平面图（场地撑满）+ 右侧 68mm 桌位清单</div>
          <div>同一家庭多人同桌自动合并为「张伟全家」</div>
          <div>名单顺序按桌位设置里的拖排顺序输出</div>
          <div>浏览器打印 → 「另存为 PDF」即得矢量 SVG 品质输出</div>
        </div>
      </div>

      <div>
        <div class="font-semibold text-ink mb-1">💾 存储说明</div>
        <div class="space-y-1 text-muted">
          <div><strong class="text-ink">⬇ 下载 JSON</strong>：完整数据下载到本地（含底图），<span style="color:#d97706">推荐首选</span></div>
          <div><strong class="text-ink">⬆ 上载 JSON</strong>：从本地 JSON 文件恢复完整状态</div>
          <div><strong class="text-ink">☁ 服务器存档</strong>：5 个固定存档位（可命名），底图不含在内</div>
          <div><strong class="text-ink">📥 服务器加载</strong>：列出云端备份，可按需加载或删除</div>
        </div>
      </div>

      <div>
        <div class="font-semibold text-ink mb-1">🗂 其他功能</div>
        <div class="space-y-1 text-muted">
          <div><strong class="text-ink">底图</strong>：加载本地图片（JPG/PNG）到浏览器内存，不上传服务器；可调透明度和 XY 偏移</div>
          <div><strong class="text-ink">CSV 导入/导出</strong>：批量导入宾客名单</div>
          <div><strong class="text-ink">🔄 校验</strong>：检查数据冲突、座位溢出、未落座人员</div>
          <div><strong class="text-ink">春日 / 喜夜模式</strong>：右上角按钮切换</div>
          <div><strong class="text-ink">Ctrl+Z</strong> 撤销（最近 40 步）</div>
        </div>
      </div>

      <div class="col-span-2 border-t border-line pt-3" style="font-size:10px;color:#5A7A6A">
        <span><strong class="text-ink">版本</strong>：SeatCard V0.24 · 2026-04</span>
        <span class="mx-2 opacity-40">|</span>
        <span><strong class="text-ink">技术</strong>：PHP 7.4+ · Tailwind · Claude Sonnet 4.6</span>
        <span class="mx-2 opacity-40">|</span>
        <span>部署配置请查看 <code class="bg-surface px-1 rounded">admin.php</code></span>
      </div>

    </div>
    <div class="flex justify-end mt-4">
      <button onclick="document.getElementById('helpModal').classList.remove('open')" class="btn btn-primary btn-sm">关闭</button>
    </div>
  </div>
</div>

<!-- Overlays -->
<div id="seatCard">
  <div class="scn" id="scName"></div>
  <div id="scMeta" class="flex items-center gap-2 mt-0.5 mb-1" style="font-size:9px;color:#5A7A6A"></div>
  <div class="scno" id="scNote" style="display:none"></div>
  <div class="scr" id="scStatusRow"></div>
  <div class="flex items-center justify-between mt-2">
    <span class="sch" style="margin:0">左区拖出 · 右区悬浮</span>
    <button id="scEditBtn" class="btn btn-ghost btn-sm" style="padding:2px 8px;font-size:10px;border-radius:5px">✏ 编辑</button>
  </div>
</div>
<div id="dragGhost"></div>
<input type="file" id="bgInput" accept="image/*" style="display:none" onchange="loadBgFile(this)">
<input type="file" id="csvInput" accept=".csv" style="display:none" onchange="importCsv(this)">
<input type="file" id="listInput" accept=".txt,.text,.csv" style="display:none" onchange="importList(this)">
<input type="file" id="jsonInput" accept=".json" style="display:none" onchange="loadJsonFile(this)">

<!-- BG MODAL -->
<div id="bgModal" class="modal-wrap" onclick="if(event.target===this)closeBgModal()">
  <div class="modal-box w-96" onclick="event.stopPropagation()">
    <div class="modal-title">🖼 背景图设置</div>
    <div id="bgDropZone" onclick="document.getElementById('bgInput').click()"
      ondragover="event.preventDefault();this.classList.add('drag-over')"
      ondragleave="this.classList.remove('drag-over')"
      ondrop="handleBgDrop(event)">
      <div id="bgDropContent">
        <div class="text-2xl mb-2">📁</div>
        <div class="text-sm font-medium text-ink mb-1">点击或拖放图片到此处</div>
        <div class="text-xs text-muted">推荐 2000×2000 px · JPG / PNG</div>
        <div class="text-xs text-muted mt-1">2000×2000px ≈ 40m×40m（1:200）</div>
      </div>
      <img id="bgThumb" src="" alt="" style="display:none;max-height:120px;border-radius:6px;margin:0 auto">
    </div>
    <div class="mt-4 space-y-3">
      <div class="flex gap-3">
        <div class="flex-1"><label class="fl">图片宽度代表 (m)</label><input id="bgRealW" type="number" value="40" min="5" max="200" step="1" class="fi"></div>
        <div class="flex-1"><label class="fl">图片高度代表 (m)</label><input id="bgRealH" type="number" value="40" min="5" max="200" step="1" class="fi"></div>
      </div>
      <div class="flex gap-3">
        <div class="flex-1"><label class="fl">水平偏移 X (m)</label><input id="bgOffX" type="number" value="0" min="-80" max="80" step="0.5" class="fi"></div>
        <div class="flex-1"><label class="fl">垂直偏移 Y (m)</label><input id="bgOffY" type="number" value="0" min="-80" max="80" step="0.5" class="fi"></div>
      </div>
      <div>
        <label class="fl">透明度</label>
        <input id="bgOpacity" type="range" min="0" max="100" value="20" class="w-full accent-primary" oninput="updateBgOpacity(this.value)">
        <div class="text-[10px] text-muted text-right" id="bgOpacityLabel">20%</div>
      </div>
      <div class="bg-surface rounded-lg p-3 text-xs text-muted space-y-1">
        <div>📐 1m = 50px · 2000×2000px = 40m×40m</div>
        <div>• X/Y 偏移将背景图向右/下平移（负值反向）</div>
      </div>
    </div>
    <div class="flex gap-2 justify-end mt-4">
      <button onclick="removeBg()" class="btn btn-danger btn-sm">移除背景</button>
      <button onclick="closeBgModal()" class="btn btn-ghost btn-sm">取消</button>
      <button onclick="applyBg()" class="btn btn-primary btn-sm">应用</button>
    </div>
  </div>
</div>

<!-- 首次进入确认弹窗 -->
<div id="firstRunModal" class="modal-wrap" style="z-index:9990">
  <div class="modal-box" style="width:min(380px,92vw);padding:24px 22px 20px" onclick="event.stopPropagation()">
    <div style="font-family:'Noto Serif SC',serif;font-size:17px;font-weight:600;color:#1F3B2F;margin-bottom:4px;letter-spacing:.3px">欢迎使用 SeatCard 🎊</div>
    <div style="font-size:11px;color:#5A7A6A;margin-bottom:16px">已创建新场次，请确认信息并设置标题</div>
    <div id="frInfo" style="background:#F2F6F1;border:1px solid #DDE6DC;border-radius:8px;padding:10px 14px;margin-bottom:16px;font-size:12px;color:#3A5A4A;line-height:1.9"></div>
    <label style="display:block;font-size:11px;color:#5A7A6A;font-weight:500;margin-bottom:5px">场次标题</label>
    <input id="frTitle" class="fi" type="text" style="font-size:14px;padding:9px 11px;margin-bottom:18px">
    <button onclick="confirmFirstRun()" class="btn btn-accent" style="width:100%;justify-content:center;font-size:13px">确认进入 →</button>
  </div>
</div>

<!-- RECENTS MODAL -->
<div id="recentsModal" class="modal-wrap" onclick="if(event.target===this)closeRecentsModal()">
  <div class="modal-box w-80" onclick="event.stopPropagation()">
    <div class="modal-title">🕐 最近保存的默认状态</div>
    <div id="recentsList" class="space-y-2 mb-4"></div>
    <div class="text-xs text-muted bg-surface rounded p-2 mb-4">提示：加载会覆盖当前状态。建议先保存当前。</div>
    <div class="flex justify-end"><button onclick="closeRecentsModal()" class="btn btn-ghost btn-sm">关闭</button></div>
  </div>
</div>

<!-- GUEST MODAL -->
<div id="guestModal" class="modal-wrap" onclick="if(event.target===this)closeGuestModal()">
  <div class="modal-box w-80" onclick="event.stopPropagation()">
    <div class="modal-title" id="gModalTitle">添加宾客</div>
    <div class="mb-2"><label class="fl">主要姓名</label><input id="g-name" type="text" class="fi" placeholder="张伟"></div>
    <div id="g-n2row" class="mb-2 hidden">
      <label class="fl">伴侣姓名</label>
      <input id="g-name2" type="text" class="fi" placeholder="李芳">
      <label class="flex items-center gap-1.5 mt-1.5 text-xs text-muted cursor-pointer">
        <input type="checkbox" id="g-showPartner" checked class="accent-primary">
        <span>名牌显示伴侣姓名（关闭后只显示「伴侣」）</span>
      </label>
    </div>
    <div class="mb-2"><label class="fl">所属方</label><select id="g-side" class="fi"><option value="groom">男方</option><option value="bride">女方</option><option value="shared">共同</option></select></div>
    <div class="mb-2"><label class="fl">人员类型</label><select id="g-type" onchange="updateGTF()" class="fi"><option value="single">单人</option><option value="elder">老人</option><option value="couple">夫妻（2人）</option><option value="couple_child">夫妻带小孩</option><option value="single_child">1人带小孩</option></select></div>
    <div id="g-crow" class="mb-2 hidden"><label class="fl">小孩人数</label><input id="g-children" type="number" value="1" min="1" max="6" class="fi"></div>
    <div id="g-cnrow" class="mb-2 hidden"><label class="fl">小孩姓名（逗号分隔）</label><input id="g-childnames" type="text" class="fi" placeholder="小明,小红"></div>
    <div class="mb-2">
      <label class="fl">座位名牌颜色 <span class="text-[9px] text-primary cursor-pointer ml-1" onclick="randColor()">随机</span></label>
      <div class="flex gap-1 flex-wrap mt-1" id="colorSwatches"></div>
      <div class="flex gap-1 flex-wrap mt-1" id="specialSwatches"></div>
      <input type="hidden" id="g-color" value="">
    </div>
    <div class="mb-2">
      <label class="fl">邀请状态</label>
      <div class="flex gap-1.5 mt-1">
        <span class="status-pill sp-confirmed" data-s="confirmed" onclick="selStatus('confirmed')">✓ 已确认</span>
        <span class="status-pill" data-s="pending" onclick="selStatus('pending')">⏳ 待定</span>
        <span class="status-pill" data-s="declined" onclick="selStatus('declined')">⚑ 标注</span>
      </div>
      <input type="hidden" id="g-status" value="confirmed">
    </div>
    <div class="mb-3"><label class="fl">备注</label><textarea id="g-note" rows="2" class="fi resize-none"></textarea></div>
    <div class="flex gap-2 justify-end">
      <button onclick="closeGuestModal()" class="btn btn-ghost">取消</button>
      <button id="delGuestBtn" onclick="deleteGuest()" class="btn btn-danger" style="display:none">删除</button>
      <button onclick="saveGuest()" class="btn btn-primary">保存</button>
    </div>
  </div>
</div>

<!-- QA MODAL -->
<div id="qaModal" class="modal-wrap" onclick="if(event.target===this)closeQA()">
  <div class="modal-box w-72" onclick="event.stopPropagation()">
    <div class="modal-title">选择入座 / 添加新人</div>
    <p id="qaInfo" class="text-xs text-muted mb-2"></p>
    <input id="qaSearch" type="text" placeholder="搜索…" class="fi mb-2" oninput="renderQaList(this.value)">
    <div id="qaList" class="max-h-44 overflow-y-auto space-y-0.5 mb-2"></div>
    <div class="border-t border-line pt-2 flex justify-between">
      <button onclick="openGuestModalFromQA()" class="btn btn-primary btn-sm">＋ 添加新人并入座</button>
      <button onclick="closeQA()" class="btn btn-ghost btn-sm">取消</button>
    </div>
  </div>
</div>

<!-- T9: SWAP MODAL 换人对话框 -->
<div id="swapModal" class="modal-wrap" onclick="if(event.target===this)closeSwapModal()">
  <div class="modal-box w-80" onclick="event.stopPropagation()">
    <div class="modal-title">🔄 座位冲突</div>
    <p class="text-xs text-muted mb-3">目标座位 <span id="swapSeatInfo" class="font-semibold text-ink"></span> 已有人，请选择操作方式：</p>
    <div class="bg-surface rounded-lg p-3 mb-4 text-xs space-y-1">
      <div class="flex items-center gap-2"><span class="font-semibold text-ink">拖入：</span><span id="swapFromName" class="text-primary font-semibold"></span></div>
      <div class="flex items-center gap-2"><span class="font-semibold text-ink">原座：</span><span id="swapToName" class="text-amber-600 font-semibold"></span></div>
    </div>
    <div class="flex gap-2">
      <button onclick="execSwap('swap')" class="btn btn-primary flex-1">⇄ 对换座位</button>
      <button onclick="execSwap('override')" class="btn btn-ghost flex-1 text-amber-600" style="border-color:#e8a44a">↓ 覆盖（原座移出）</button>
      <button onclick="closeSwapModal()" class="btn btn-ghost">取消</button>
    </div>
  </div>
</div>

<!-- REFRESH MODAL -->
<div id="refreshModal" class="modal-wrap" onclick="if(event.target===this)closeRefreshModal()">
  <div class="modal-box w-96 max-h-[80vh] flex flex-col" onclick="event.stopPropagation()">
    <div class="modal-title">🔄 座位校验报告</div>
    <div id="refreshContent" class="flex-1 overflow-y-auto text-xs space-y-1.5"></div>
    <div class="flex justify-end mt-4 gap-2">
      <button onclick="clearOrphanedSeats()" class="btn btn-danger btn-sm">清除无效</button>
      <button onclick="closeRefreshModal()" class="btn btn-primary btn-sm">关闭</button>
    </div>
  </div>
</div>

<script>
// T7/T8: Auth Code — PHP 注入，前端/后端双重校验
const AUTH_CODE='<?= htmlspecialchars($AUTH_CODE,ENT_QUOTES) ?>';
const VIEW_ONLY=<?= $VIEW_ONLY?'true':'false' ?>;
// 7位查看前缀（仅查看模式时有值，用于生成查看链接）
const VIEW_PREFIX=VIEW_ONLY?'<?= htmlspecialchars($rawAuth,ENT_QUOTES) ?>':'';
// WTN 桌名词库（来自 WTN.json）
const WTN_DATA=<?= file_exists(__DIR__.'/WTN.json')?file_get_contents(__DIR__.'/WTN.json'):'{}' ?>;
const AUTH_WARNED=<?= $AUTH_WARNED?'true':'false' ?>;
function authUrl(base){return AUTH_CODE?base+(base.includes('?')?'&':'?')+'auth='+encodeURIComponent(AUTH_CODE):base;}
// 校验警告横幅
if(AUTH_WARNED){
  const bar=document.createElement('div');
  bar.id='authWarnBar';
  bar.style.cssText='position:fixed;top:0;left:0;right:0;z-index:9999;background:#F5A623;color:#fff;font-size:12px;font-weight:600;text-align:center;padding:6px 40px;box-shadow:0 2px 8px rgba(0,0,0,.2)';
  bar.innerHTML='⚠ 授权码 <code style="background:rgba(255,255,255,.25);padding:1px 6px;border-radius:4px">'+AUTH_CODE+'</code> 不在系统记录中且校验位不匹配，请确认是否输入正确。<button onclick="this.parentElement.remove()" style="position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;color:#fff;font-size:16px;cursor:pointer">✕</button>';
  document.body.prepend(bar);
  // 给 header 加 padding-top
  document.querySelector('header').style.marginTop='34px';
}
// 在 projectTitle 提示中注入场次日期
(function(){
  const m=AUTH_CODE.match(/^(\d{2})(\d{2})/);
  if(!m)return;
  const pt=document.getElementById('projectTitle');
  if(pt)pt.title=parseInt(m[1])+'月'+parseInt(m[2])+'日 · 双击编辑项目名称';
})();
// 仅查看模式：versionBadge 和 服务器目录只显示7位前缀
if(VIEW_ONLY&&VIEW_PREFIX){
  const vb=document.getElementById('versionBadge');
  if(vb)vb.textContent=vb.textContent.replace(AUTH_CODE,VIEW_PREFIX+'…');
}
// ══════════════════════════════════════════════════
// COLOR SYSTEM — loaded from embedded JSON config
// ══════════════════════════════════════════════════
const COLOR_CFG = JSON.parse(document.getElementById('colorConfig').textContent);
const PAL_ALL   = COLOR_CFG.palette;          // array of {name,bg,txt}
const PAL_SPL   = COLOR_CFG.special || [];    // special colors
const PAL_BG    = PAL_ALL.map(c=>c.bg);       // compat
const PAL_TXT   = PAL_ALL.map(c=>c.txt);

function colorByBg(bg){
  const hit=[...PAL_ALL,...PAL_SPL].find(c=>c.bg===bg);
  return hit||{bg,txt:'#ffffff',name:'自定义'};
}
function gBg(g){return g.color||PAL_BG[g.id%PAL_BG.length];}
function gTc(g){return colorByBg(gBg(g)).txt;}
function randPalColor(){return PAL_ALL[Math.floor(Math.random()*PAL_ALL.length)].bg;}

// ══════════════════════════════════════════════════
// SCALE CONSTANTS
// ══════════════════════════════════════════════════
const M = 50; // px per metre
// Table sizes: 1.8m / 2.0m / 2.2m  (radius in px)
const TABLE_SIZES = {
  '1.8': { r: 45, orb: 62,  sw: 44, sh: 26, label:'1.8m' },
  '2.0': { r: 50, orb: 68,  sw: 46, sh: 27, label:'2.0m' },
  '2.2': { r: 55, orb: 74,  sw: 48, sh: 28, label:'2.2m' },
};
const DEFAULT_SIZE = '2.0';
const MIN_GAP = 1.8;

// ══════════════════════════════════════════════════
// STATE
// ══════════════════════════════════════════════════
let tables=[], guests=[];
let _isTouchDevice=navigator.maxTouchPoints>0||'ontouchstart' in window;
let projectName='婚宴座位图';
// 画布桌子显示选项
let tableDispOpts={showNum:false,showLabel:true,showAnnotation:false,suffix:''};
let selectedTableId=null, tableCounter=0, guestCounter=0;
let editingGuestId=null, filterText='';
let customCategories=[]; // [{id,label,color,bg}] — T4 自定义分类
let sideCollapsed={}; // {groom:false,...} — T5 卷展状态
let _qaTableId=null, _qaSeatIdx=null, _pendingQA=false;
let _pendingSwap=null; // {key,toTid,toSeat} — T9 换人对话框
let _undoStack=[], _activeTableDragId=null;
let _autoSaveCounter=0;
const UNDO_MAX=40;
let roomW=20, roomH=20;
let bgState={src:null,realW:40,realH:40,opacity:0.2,offX:0,offY:0};
let gridVisible=true;
let vp={x:0,y:0,scale:1};
let _panState=null, _spaceDown=false;
let _boxSelStart=null;
// Table drag state (world coords)
let _tDrag=null;       // {tid, ox, oy}  — single table drag
let _boxSel=null;     // {sx,sy,ex,ey} — rubber-band selection in world coords
let _selIds=new Set();// currently selected table ids (multi-select)
let _multiDrag=null;  // {starts:[{id,x,y}], ox,oy} — multi-table drag
let _tcDrag=null;     // 触控拖拽激活态 {slotKey,ghost,ghostShown}（V4 移动激活）

const COL={groom:'#2C6E8A',bride:'#8A3A5A',shared:'#2FBB7A'};
const LABEL={groom:'男方',bride:'女方',shared:'共同'};
const SIDE_BG={groom:'#EBF4F8',bride:'#F8EBF1',shared:'#EBF8F3'};
const STATUS_LABEL={confirmed:'已确认',pending:'待定',declined:'标注'};

// T4: 动态 side 辅助
function getSideLabel(s){return LABEL[s]||(customCategories.find(c=>c.id===s)?.label)||s;}
function getSideColor(s){return COL[s]||(customCategories.find(c=>c.id===s)?.color)||'#888888';}
function getSideBg(s){return SIDE_BG[s]||(customCategories.find(c=>c.id===s)?.bg)||'#F5F5F5';}
function getAllSides(){return['groom','bride','shared',...customCategories.map(c=>c.id)];}

// T1: 独占式手风琴——打开一个，其他折叠到底部显示标题
function toggleSideCollapse(side){
  const willOpen=!!sideCollapsed[side];
  getAllSides().forEach(s=>{sideCollapsed[s]=true;});
  if(willOpen)sideCollapsed[side]=false;
  renderGuestList(filterText);
}
// 初始化：只展开第一个有宾客的分类
function initSideCollapsed(){
  const sides=getAllSides();
  let opened=false;
  sides.forEach(s=>{
    const hasGuests=guests.some(g=>g.side===s);
    if(!opened&&hasGuests){sideCollapsed[s]=false;opened=true;}
    else{sideCollapsed[s]=true;}
  });
  if(!opened&&sides.length>0)sideCollapsed[sides[0]]=false;
}

// T2: 状态循环切换（确认→待定→标注→确认）
function cycleStatus(gid){
  const g=guests.find(g=>g.id===gid);if(!g)return;
  pushUndo();
  const order=['confirmed','pending','declined'];
  g.status=order[(order.indexOf(g.status||'confirmed')+1)%3];
  render();
}

// 画布胶囊拖动（mousedown/mousemove/mouseup，SVG 兼容）
let _svgDrag=null; // slotKey string
document.addEventListener('mousemove',e=>{
  if(!_svgDrag)return;
  const gh=document.getElementById('dragGhost');
  gh.style.left=(e.clientX+12)+'px';gh.style.top=(e.clientY-10)+'px';
});
document.addEventListener('mouseup',e=>{
  if(!_svgDrag)return;
  const slotKey=_svgDrag;_svgDrag=null;
  document.body.style.cursor='';
  document.getElementById('dragGhost').style.display='none';
  document.querySelectorAll('[data-svgov]').forEach(el=>el.remove());
  const target=document.elementFromPoint(e.clientX,e.clientY);
  if(target&&target.dataset.tid!==undefined&&target.dataset.sidx!==undefined){
    dropOnSeat(slotKey,Number(target.dataset.tid),parseInt(target.dataset.sidx));
  } else if(!target?.closest('#canvasWrap')){
    // 拖到画布外 → 取消落座
    const[gs,ss]=slotKey.split(':');
    const guest=guests.find(g=>g.id===parseInt(gs));
    const slot=(guest?.slots||[]).find(s=>s.subId===parseInt(ss));
    if(slot&&slot.seatedTableId){pushUndo();slot.seatedTableId=null;slot.seatedSeat=null;render();}
  }
});

// T3: 家庭行拖动——排序 & 跨分类换类型
let _guestDragId=null;
function guestDragStart(e,gid){
  _guestDragId=gid;
  e.dataTransfer.setData('guestReorder',String(gid));
  e.dataTransfer.effectAllowed='move';
  const g=guests.find(g=>g.id===gid);
  const gh=document.getElementById('dragGhost');
  gh.textContent=(g?g.name:'')+'  ↕';gh.style.display='block';
  e.dataTransfer.setDragImage(new Image(),0,0);
  document.addEventListener('dragover',movGhost);
}
function guestDragEnd(){_guestDragId=null;endDrag();}
function guestDropOnRow(e,targetGid){
  e.preventDefault();e.stopPropagation();
  const srcId=_guestDragId;if(srcId===null||srcId===targetGid)return;
  const srcIdx=guests.findIndex(g=>g.id===srcId);
  const tgtIdx=guests.findIndex(g=>g.id===targetGid);
  if(srcIdx<0||tgtIdx<0)return;
  const rect=e.currentTarget.getBoundingClientRect();
  const insertBefore=e.clientY<rect.top+rect.height/2;
  pushUndo();
  const[moved]=guests.splice(srcIdx,1);
  const newTgt=guests.findIndex(g=>g.id===targetGid);
  guests.splice(insertBefore?newTgt:newTgt+1,0,moved);
  render();
}
function guestDropOnSide(e,side){
  e.preventDefault();e.stopPropagation();
  const srcId=_guestDragId;if(srcId===null)return;
  const g=guests.find(g=>g.id===srcId);
  if(!g||g.side===side)return;
  pushUndo();g.side=side;render();
}

// T4: 添加自定义分类（可传入拖拽宾客 ID 直接归类）
function addCustomCategory(assignGuestId=null){
  const label=prompt('新分类名称：','其他');
  if(!label||!label.trim())return;
  const colors=['#7B6A8A','#6A8A7B','#8A7B6A','#6A7B8A','#8A6A7B'];
  const bgs=['#F0EBF5','#EBF5F0','#F5F0EB','#EBF0F5','#F5EBF0'];
  const idx=customCategories.length%colors.length;
  const id='custom_'+(Date.now());
  customCategories.push({id,label:label.trim(),color:colors[idx],bg:bgs[idx]});
  if(assignGuestId!==null){
    pushUndo();
    const g=guests.find(g=>g.id===assignGuestId);
    if(g)g.side=id;
  }
  updateGuestSideSelect();
  renderGuestList(filterText);
}
// 拖入"添加分类"区域
function addCatDragOver(e){
  if(!e.dataTransfer.types.some(t=>t.toLowerCase()==='guestreorder'))return;
  e.preventDefault();
  const z=document.getElementById('addCatDropZone');
  if(z){z.style.background='#e6f7ee';const b=document.getElementById('addCatBtn');if(b){b.style.borderColor='#2FBB7A';b.style.color='#2FBB7A';}}
}
function addCatDragLeave(){
  const z=document.getElementById('addCatDropZone');
  if(z){z.style.background='';const b=document.getElementById('addCatBtn');if(b){b.style.borderColor='';b.style.color='';}}
}
function addCatDrop(e){
  e.preventDefault();addCatDragLeave();
  const srcId=_guestDragId;_guestDragId=null;endDrag();
  if(srcId===null)return;
  addCustomCategory(srcId);
}

// T4: 更新宾客编辑弹窗的所属方下拉
function updateGuestSideSelect(){
  const sel=document.getElementById('g-side');
  if(!sel)return;
  const cur=sel.value;
  sel.innerHTML='<option value="groom">男方</option><option value="bride">女方</option><option value="shared">共同</option>';
  customCategories.forEach(c=>{
    const opt=document.createElement('option');
    opt.value=c.id;opt.textContent=c.label;sel.appendChild(opt);
  });
  if(cur)sel.value=cur;
}

function tSize(t){return TABLE_SIZES[t.size||DEFAULT_SIZE]||TABLE_SIZES[DEFAULT_SIZE];}

// ══════════════════════════════════════════════════
// UNDO
// ══════════════════════════════════════════════════
function pushUndo(){
  _undoStack.push(JSON.stringify({tables,guests,tableCounter,guestCounter,roomW,roomH}));
  if(_undoStack.length>UNDO_MAX)_undoStack.shift();
  // 每 5 次操作自动静默保存到服务器
  if(AUTH_CODE&&!VIEW_ONLY){
    _autoSaveCounter++;
    if(_autoSaveCounter>=5){_autoSaveCounter=0;saveJsonServerDirect();}
  }
}
function undo(){
  if(!_undoStack.length)return;
  const s=JSON.parse(_undoStack.pop());
  tables=s.tables;guests=s.guests;tableCounter=s.tableCounter;guestCounter=s.guestCounter;
  roomW=s.roomW||20;roomH=s.roomH||20;
  document.getElementById('roomWInput').value=roomW;
  document.getElementById('roomHInput').value=roomH;
  selectedTableId=null;render();
}
document.addEventListener('keydown',e=>{
  if((e.ctrlKey||e.metaKey)&&e.key==='z'){e.preventDefault();undo();}
  if(e.code==='Space'&&!e.target.matches('input,textarea,select')){e.preventDefault();_spaceDown=true;canvasWrap.classList.add('pan-mode');}
});
document.addEventListener('keyup',e=>{
  if(e.code==='Space'){_spaceDown=false;if(!_panState)canvasWrap.classList.remove('pan-mode');}
});

// ══════════════════════════════════════════════════
// SLOTS
// ══════════════════════════════════════════════════
function buildSlots(g){
  const cn=g.childNames||[];
  const mk=(id,n,t)=>({subId:id,subName:n,subType:t,seatedTableId:null,seatedSeat:null});
  if(g.type==='single') return[mk(0,g.name,'adult')];
  if(g.type==='elder')  return[mk(0,g.name,'elder')];
  if(g.type==='couple') return[mk(0,g.name,'adult'),mk(1,g.name2||'丈夫/妻子','spouse')];
  if(g.type==='couple_child'){const s=[mk(0,g.name,'adult'),mk(1,g.name2||'丈夫/妻子','spouse')];for(let i=0;i<(g.children||1);i++)s.push(mk(2+i,cn[i]||`小孩${i+1}`,'child'));return s;}
  if(g.type==='single_child'){const s=[mk(0,g.name,'adult')];for(let i=0;i<(g.children||1);i++)s.push(mk(1+i,cn[i]||`小孩${i+1}`,'child'));return s;}
  return[mk(0,g.name,'adult')];
}
function slotDN(g,s){
  if(s.subType==='child') return g.name.slice(0,3)+'·'+(s.subName||'');
  if(s.subType==='spouse') return (s.subName||'配偶');
  return s.subName||g.name;
}
function rebuildSlots(g,old){
  const snap={};(old||[]).forEach(s=>{snap[s.subId]={seatedTableId:s.seatedTableId,seatedSeat:s.seatedSeat};});
  g.slots=buildSlots(g);
  g.slots.forEach(s=>{if(snap[s.subId]){s.seatedTableId=snap[s.subId].seatedTableId;s.seatedSeat=snap[s.subId].seatedSeat;}});
}
function gCount(g){return(g.slots||[]).length;}
function findSlot(tid,si){for(const g of guests)for(const s of(g.slots||[]))if(s.seatedTableId===tid&&s.seatedSeat===si)return{g,s};return null;}
function countOcc(t){let c=0;for(let i=0;i<t.seats;i++)if(findSlot(t.id,i))c++;return c;}

// ══════════════════════════════════════════════════
// SVG / VIEWPORT
// ══════════════════════════════════════════════════
const canvasEl=document.getElementById('canvas');
const canvasWrap=document.getElementById('canvasWrap');
const worldGroup=document.getElementById('worldGroup');

function applyViewport(){
  worldGroup.setAttribute('transform',`translate(${vp.x},${vp.y}) scale(${vp.scale})`);

  // 自适应网格：根据缩放选合适的格子密度
  // M=50 => 50世界单位=1米；pxPerM = 1米在屏幕上的像素数
  const pxPerM = M * vp.scale;
  let minorM; // 小格间距（米）
  if      (pxPerM >= 120) minorM = 0.5;   // 缩放>2.4x : 0.5m
  else if (pxPerM >=  50) minorM = 1;     // 1x-2.4x  : 1m
  else if (pxPerM >=  22) minorM = 2;     // ~0.4x-1x : 2m
  else if (pxPerM >=   9) minorM = 5;     // ~0.18x   : 5m
  else                    minorM = 10;    // 极小缩放  : 10m

  const minorU = minorM * M;   // 小格，世界单位
  const majorU = minorU * 5;   // 大格，世界单位（5倍小格）

  // stroke-width 保持约 0.5 屏幕像素（与缩放无关）
  const sw = (0.5 / vp.scale).toFixed(3);

  const g1 = document.getElementById('grid1m');
  const g5 = document.getElementById('grid5m');

  // 更新小格 pattern 尺寸及内部路径
  g1.setAttribute('width',  minorU);
  g1.setAttribute('height', minorU);
  const p1 = g1.querySelector('path');
  if(p1){ p1.setAttribute('d', `M ${minorU} 0 L 0 0 0 ${minorU}`); p1.setAttribute('stroke-width', sw); }

  // 更新大格 pattern 尺寸及内部元素
  g5.setAttribute('width',  majorU);
  g5.setAttribute('height', majorU);
  g5.setAttribute('x', 0); g5.setAttribute('y', 0); // 固定对齐世界原点
  const p5r = g5.querySelector('rect');
  if(p5r){ p5r.setAttribute('width', majorU); p5r.setAttribute('height', majorU); }
  const p5p = g5.querySelector('path');
  if(p5p){ p5p.setAttribute('d', `M ${majorU} 0 L 0 0 0 ${majorU}`); p5p.setAttribute('stroke-width', (parseFloat(sw)*1.8).toFixed(3)); }

  document.getElementById('zoomLevel').textContent = Math.round(vp.scale*100)+'%';
}
function clientToWorld(cx,cy){
  const r=canvasWrap.getBoundingClientRect();
  return{x:(cx-r.left-vp.x)/vp.scale,y:(cy-r.top-vp.y)/vp.scale};
}
function zoomAt(cx,cy,factor){
  const r=canvasWrap.getBoundingClientRect(),px=cx-r.left,py=cy-r.top;
  const ns=Math.min(4,Math.max(0.15,vp.scale*factor));
  vp.x=px-(px-vp.x)*(ns/vp.scale);
  vp.y=py-(py-vp.y)*(ns/vp.scale);
  vp.scale=ns; applyViewport();
}
function zoomIn(){zoomAt(canvasWrap.offsetWidth/2,canvasWrap.offsetHeight/2,1.25);}
function zoomOut(){zoomAt(canvasWrap.offsetWidth/2,canvasWrap.offsetHeight/2,0.8);}
function zoomReset(){
  const cw=canvasWrap.offsetWidth,ch=canvasWrap.offsetHeight,pad=60;
  const sx=(cw-pad*2)/(roomW*M),sy=(ch-pad*2)/(roomH*M);
  vp.scale=Math.min(sx,sy,2);
  vp.x=pad+(cw-pad*2-roomW*M*vp.scale)/2;
  vp.y=pad+(ch-pad*2-roomH*M*vp.scale)/2;
  applyViewport();
}
canvasWrap.addEventListener('dblclick',e=>{
  // Only fire if target is the SVG background (not a table or seat)
  if(e.target!==canvasEl&&!e.target.id?.startsWith('grid')&&e.target.id!=='roomRect'&&e.target.id!=='bgImage'&&e.target.id!=='gridRect')return;
  if(_spaceDown)return;
  const wp=clientToWorld(e.clientX,e.clientY);
  pushUndo();tableCounter++;
  tables.push({id:tableCounter,x:wp.x,y:wp.y,seats:10,type:'shared',label:`第${tableCounter}桌`,size:DEFAULT_SIZE});
  selectTable(tableCounter);render();
});
canvasWrap.addEventListener('wheel',e=>{e.preventDefault();zoomAt(e.clientX,e.clientY,e.deltaY<0?1.1:0.909);},{passive:false});
canvasWrap.addEventListener('mousedown',e=>{
  if(e.button===1||_spaceDown){
    e.preventDefault();
    _panState={sx:e.clientX,sy:e.clientY,vx:vp.x,vy:vp.y};
    canvasWrap.classList.add('panning');
    return;
  }
  // Left click on canvas background (not on a table) = start box-select
  if(e.button===0&&e.target===canvasEl||e.button===0&&e.target.closest&&!e.target.closest('[data-tid]')){
    const wp=clientToWorld(e.clientX,e.clientY);
    _boxSel={sx:wp.x,sy:wp.y,ex:wp.x,ey:wp.y};
    _boxSelStart={cx:e.clientX,cy:e.clientY};
  }
});

// ── Touch canvas: 双指 pan + 捏合缩放（iOS Safari）──
(function(){
  let _tc=null; // {mid:{x,y}, dist:num, vx, vy, scale}
  const mid=(t1,t2)=>({x:(t1.clientX+t2.clientX)/2,y:(t1.clientY+t2.clientY)/2});
  const dist=(t1,t2)=>Math.hypot(t2.clientX-t1.clientX,t2.clientY-t1.clientY);
  canvasWrap.addEventListener('touchstart',function(e){
    if(e.touches.length===2){
      e.preventDefault();
      const t1=e.touches[0],t2=e.touches[1];
      _tc={mid:mid(t1,t2),dist:dist(t1,t2),vx:vp.x,vy:vp.y,scale:vp.scale};
      canvasWrap.classList.add('panning');
    }else{
      _tc=null;
    }
  },{passive:false});
  canvasWrap.addEventListener('touchmove',function(e){
    if(e.touches.length===2&&_tc){
      e.preventDefault();
      const t1=e.touches[0],t2=e.touches[1];
      const newMid=mid(t1,t2),newDist=dist(t1,t2);
      const r=canvasWrap.getBoundingClientRect();
      const imx=_tc.mid.x-r.left,imy=_tc.mid.y-r.top; // 初始触点中心（容器坐标）
      const nmx=newMid.x-r.left,nmy=newMid.y-r.top;   // 当前触点中心
      const pf=newDist/_tc.dist;                         // 捏合因子
      const ns=Math.min(4,Math.max(0.15,_tc.scale*pf));
      // 初始中心对应的世界坐标（保持不动）
      const wx=(imx-_tc.vx)/_tc.scale;
      const wy=(imy-_tc.vy)/_tc.scale;
      // 新视口使该世界坐标落在当前触点中心
      vp.x=nmx-wx*ns;
      vp.y=nmy-wy*ns;
      vp.scale=ns;
      applyViewport();
    }else if(e.touches.length<2){
      _tc=null;
    }
  },{passive:false});
  canvasWrap.addEventListener('touchend',function(e){
    if(e.touches.length<2){
      _tc=null;
      canvasWrap.classList.remove('panning');
    }
  },{passive:true});
  canvasWrap.addEventListener('touchcancel',function(){
    _tc=null;canvasWrap.classList.remove('panning');
  },{passive:true});
})();
window.addEventListener('mousemove',e=>{
  if(_panState){vp.x=_panState.vx+(e.clientX-_panState.sx);vp.y=_panState.vy+(e.clientY-_panState.sy);applyViewport();}
  if(_tDrag){
    const wp=clientToWorld(e.clientX,e.clientY);
    const t=tables.find(t=>t.id===_tDrag.tid);
    if(t){t.x=wp.x-_tDrag.ox;t.y=wp.y-_tDrag.oy;renderTablesOnly();showDistLabels(t);}
  }
  if(_multiDrag){
    const wp=clientToWorld(e.clientX,e.clientY);
    const dx=wp.x-_multiDrag.ox,dy=wp.y-_multiDrag.oy;
    _multiDrag.starts.forEach(s=>{
      const t=tables.find(t=>t.id===s.id);
      if(t){t.x=s.x+dx;t.y=s.y+dy;}
    });
    renderTablesOnly();
  }
  if(_boxSel){
    const wp=clientToWorld(e.clientX,e.clientY);
    _boxSel.ex=wp.x;_boxSel.ey=wp.y;
    const bx=Math.min(_boxSel.sx,_boxSel.ex),by=Math.min(_boxSel.sy,_boxSel.ey);
    const bw=Math.abs(_boxSel.ex-_boxSel.sx),bh=Math.abs(_boxSel.ey-_boxSel.sy);
    const sb=document.getElementById('selBox');
    sb.setAttribute('x',bx);sb.setAttribute('y',by);sb.setAttribute('width',bw);sb.setAttribute('height',bh);
    sb.setAttribute('display','');
  }
});
window.addEventListener('mouseup',e=>{
  if(_panState){_panState=null;canvasWrap.classList.remove('panning');if(!_spaceDown)canvasWrap.classList.remove('pan-mode');}
  if(_tDrag){_tDrag=null;clearDistLabels();render();}
  if(_multiDrag){_multiDrag=null;render();}
  if(_boxSel){
    const bx=Math.min(_boxSel.sx,_boxSel.ex),by=Math.min(_boxSel.sy,_boxSel.ey);
    const bw=Math.abs(_boxSel.ex-_boxSel.sx),bh=Math.abs(_boxSel.ey-_boxSel.sy);
    document.getElementById('selBox').setAttribute('display','none');
    _boxSel=null;
    // Only commit selection if dragged more than 8px (not a bare click)
    const cdx=e.clientX-(_boxSelStart?.cx||e.clientX);
    const cdy=e.clientY-(_boxSelStart?.cy||e.clientY);
    if(Math.sqrt(cdx*cdx+cdy*cdy)>8){
      _selIds=new Set(tables.filter(t=>t.x>=bx&&t.x<=bx+bw&&t.y>=by&&t.y<=by+bh).map(t=>t.id));
      if(_selIds.size>0)selectedTableId=null; // deselect single when multi active
      render();
    } else {
      // bare click on canvas = deselect all
      _selIds=new Set();
      selectedTableId=null;
      render();
    }
    _boxSelStart=null;
  }
});

// ══════════════════════════════════════════════════
// ROOM
// ══════════════════════════════════════════════════
function updateRoomSize(){
  roomW=Math.max(10,Math.min(80,parseInt(document.getElementById('roomWInput').value)||20));
  roomH=Math.max(10,Math.min(80,parseInt(document.getElementById('roomHInput').value)||20));
  document.getElementById('roomWInput').value=roomW;
  document.getElementById('roomHInput').value=roomH;
  document.getElementById('roomRect').setAttribute('width',roomW*M);
  document.getElementById('roomRect').setAttribute('height',roomH*M);
  renderRoomLabels();updateBgImagePos();
}
function renderRoomLabels(){
  const g=document.getElementById('roomLabels');g.innerHTML='';
  const w=roomW*M,h=roomH*M;
  const _rc=_darkMode?'#9B3A3A':'#2FBB7A';
  // 同步更新场地边框颜色
  const rr=document.getElementById('roomRect');
  if(rr){rr.setAttribute('stroke',_rc);}
  const lbl=(txt,x,y,anc)=>{const t=document.createElementNS('http://www.w3.org/2000/svg','text');t.setAttribute('x',x);t.setAttribute('y',y);t.setAttribute('text-anchor',anc||'middle');t.setAttribute('font-family',"'Noto Sans SC',sans-serif");t.setAttribute('font-size','11');t.setAttribute('fill',_rc);t.setAttribute('opacity','0.7');t.textContent=txt;g.appendChild(t);};
  lbl(roomW+'m',w/2,-6);lbl(roomH+'m',-8,h/2,'end');
}
function toggleGrid(){
  gridVisible=!gridVisible;
  document.getElementById('gridGroup').style.display=gridVisible?'':'none';
  document.getElementById('gridBtn').classList.toggle('active',gridVisible);
}

// ══════════════════════════════════════════════════
// BG IMAGE
// ══════════════════════════════════════════════════
let _bgPending=null;
function openBgModal(){
  document.getElementById('bgRealW').value=bgState.realW||40;
  document.getElementById('bgRealH').value=bgState.realH||40;
  document.getElementById('bgOffX').value=(bgState.offX||0)/M;
  document.getElementById('bgOffY').value=(bgState.offY||0)/M;
  document.getElementById('bgOpacity').value=Math.round((bgState.opacity||0.2)*100);
  document.getElementById('bgOpacityLabel').textContent=Math.round((bgState.opacity||0.2)*100)+'%';
  if(bgState.src){document.getElementById('bgThumb').src=bgState.src;document.getElementById('bgThumb').style.display='block';document.getElementById('bgDropContent').style.display='none';}
  else{document.getElementById('bgThumb').style.display='none';document.getElementById('bgDropContent').style.display='';}
  document.getElementById('bgModal').classList.add('open');
}
function closeBgModal(){document.getElementById('bgModal').classList.remove('open');_bgPending=null;}
function handleBgDrop(e){e.preventDefault();e.currentTarget.classList.remove('drag-over');const f=e.dataTransfer.files[0];if(f&&f.type.startsWith('image/'))loadBgFromFile(f);}
function loadBgFile(input){const f=input.files[0];if(f)loadBgFromFile(f);input.value='';}
function loadBgFromFile(file){
  const r=new FileReader();
  r.onload=e=>{_bgPending=e.target.result;document.getElementById('bgThumb').src=_bgPending;document.getElementById('bgThumb').style.display='block';document.getElementById('bgDropContent').style.display='none';};
  r.readAsDataURL(file);
}
function updateBgOpacity(v){document.getElementById('bgOpacityLabel').textContent=v+'%';bgState.opacity=v/100;document.getElementById('bgImage').setAttribute('opacity',bgState.opacity);}
function updateBgImagePos(){
  const img=document.getElementById('bgImage');
  if(!bgState.src){img.style.display='none';return;}
  img.setAttribute('x',bgState.offX||0);img.setAttribute('y',bgState.offY||0);
  img.setAttribute('width',bgState.realW*M);img.setAttribute('height',bgState.realH*M);
  img.setAttribute('opacity',bgState.opacity);img.style.display='';
}
let bgVisible=true;
function toggleBgVisible(){
  bgVisible=!bgVisible;
  const img=document.getElementById('bgImage');
  img.style.display=(bgVisible&&bgState.src)?'':'none';
  const btn=document.getElementById('bgVisBtn');
  if(btn){btn.classList.toggle('active',bgVisible);btn.textContent=bgVisible?'👁 显示底图':'👁 隐藏底图';}
}
function applyBg(){
  if(_bgPending){bgState.src=_bgPending;document.getElementById('bgImage').setAttribute('href',bgState.src);}
  bgState.realW=parseFloat(document.getElementById('bgRealW').value)||40;
  bgState.realH=parseFloat(document.getElementById('bgRealH').value)||40;
  bgState.offX=(parseFloat(document.getElementById('bgOffX').value)||0)*M;
  bgState.offY=(parseFloat(document.getElementById('bgOffY').value)||0)*M;
  bgState.opacity=parseInt(document.getElementById('bgOpacity').value)/100;
  updateBgImagePos();closeBgModal();
}
function removeBg(){
  bgState.src=null;document.getElementById('bgImage').style.display='none';
  document.getElementById('bgThumb').style.display='none';document.getElementById('bgDropContent').style.display='';
  closeBgModal();
}

// ══════════════════════════════════════════════════
// DISTANCE LABELS
// ══════════════════════════════════════════════════
function showDistLabels(dt){
  const dg=document.getElementById('distLabels');dg.innerHTML='';
  const r1=tSize(dt).r;
  tables.forEach(t=>{
    if(t.id===dt.id)return;
    const r2=tSize(t).r;
    const dx=t.x-dt.x,dy=t.y-dt.y,cd=Math.sqrt(dx*dx+dy*dy);
    if(cd>600)return;
    const gap=(cd-r1-r2)/M;
    const mx=(dt.x+t.x)/2,my=(dt.y+t.y)/2;
    const isBad=gap<MIN_GAP,isWarn=gap>=MIN_GAP&&gap<MIN_GAP+0.5;
    const lc=isBad?'#e74c3c':isWarn?'#e8a44a':'#2FBB7A';
    const ang=Math.atan2(dy,dx);
    const x1=dt.x+r1*Math.cos(ang),y1=dt.y+r1*Math.sin(ang);
    const x2=t.x-r2*Math.cos(ang),y2=t.y-r2*Math.sin(ang);
    const ln=mkEl('line',{x1,y1,x2,y2,stroke:lc,'stroke-width':'1','stroke-dasharray':'4,3',opacity:'0.8'});
    dg.appendChild(ln);
    const lbl=gap.toFixed(1)+'m',rw=lbl.length*6+10,rh=18;
    dg.appendChild(mkEl('rect',{x:mx-rw/2,y:my-rh/2,width:rw,height:rh,rx:'4',fill:isBad?'#fdecea':'#fff',stroke:lc,'stroke-width':'1'}));
    const lt=mkTx(lbl,mx,my,{'font-size':'10','font-weight':'600','fill':lc});dg.appendChild(lt);
    if(isBad){const w=mkTx('⚠',mx+rw/2+8,my,{'font-size':'12'});dg.appendChild(w);}
  });
}
function clearDistLabels(){document.getElementById('distLabels').innerHTML='';}

// ══════════════════════════════════════════════════
// RENDER
// ══════════════════════════════════════════════════
function render(){renderTablesOnly();renderGuestList(filterText);renderTableConfig();renderTableList();updateStats();}
function renderTablesOnly(){const g=document.getElementById('tablesGroup');g.innerHTML='';tables.forEach(t=>drawTable(t,g));}

function mkEl(tag,attrs){const el=document.createElementNS('http://www.w3.org/2000/svg',tag);Object.entries(attrs).forEach(([k,v])=>el.setAttribute(k,v));return el;}
function mkTx(txt,x,y,attrs={}){const el=document.createElementNS('http://www.w3.org/2000/svg','text');el.setAttribute('x',x);el.setAttribute('y',y);el.setAttribute('text-anchor','middle');el.setAttribute('dominant-baseline','middle');el.setAttribute('font-family',"'Noto Sans SC',sans-serif");Object.entries(attrs).forEach(([k,v])=>el.setAttribute(k,v));el.textContent=txt;return el;}

// ── 桌名/号/标注 智能排版 ──
function drawTableText(grp,t,sz,col){
  const o=tableDispOpts;
  const r=sz.r;
  const rawLabel=t.label||`桌${t.id}`;
  const fullLabel=rawLabel+(o.suffix||'');
  const num=t.num||'';
  const anno=t.annotation||'';
  const showN=o.showNum&&num;
  const showL=o.showLabel;
  const showA=o.showAnnotation&&anno;

  // Helper: single text line
  const tx=(txt,y,fs,fw,opacity,extra)=>{
    const _isDark=document.body.classList.contains('dark-mode');
    let fill;
    if(col==='#fff'){fill='#fff';}
    else if(_isDark){fill=opacity?'#D8D0C866':'#E8E0D8';}
    else{fill=opacity?col+opacity:col;}
    const attrs={'font-size':String(fs),'font-weight':fw||'600','fill':fill};
    if(fs>=16)attrs['font-family']="'Noto Serif SC','Noto Sans SC',sans-serif";
    if(extra)Object.assign(attrs,extra);
    grp.appendChild(mkTx(txt,t.x,y,attrs));
  };
  // Helper: fit label to circle
  // ≤3字：单行大字；4字：2+2分行且再大2号；5-6字：2+2；更长：缩
  const splitLabel=(label,y0,fsBig)=>{
    const n=label.length;
    if(n<=3){tx(label,y0,fsBig+3,'700');return;}
    if(n===4){
      tx(label.slice(0,2),y0-12,fsBig+6,'700',null,{'letter-spacing':'2'}); // 字号27(+4), 字间距+2
      tx(label.slice(2),  y0+13,fsBig+6,'700',null,{'letter-spacing':'2'}); // 下行+13，合计25px
      return;
    }
    if(n<=6){
      const m=Math.ceil(n/2);
      const fs=fsBig-1;
      tx(label.slice(0,m),y0-9,fs,'700');
      tx(label.slice(m),y0+9,fs,'700');
      return;
    }
    const disp=n>9?label.slice(0,8)+'…':label;
    tx(disp,y0,n<=8?11:9,'600');
  };

  const BASE_FS=21; // 桌名基础字号（仅显示桌名时）
  const _annoDelta = sz.r < 50 ? -5 : 0; // 1.8m小桌标注上移避免与座位数重叠
  if(!showN&&showL&&!showA){
    // 仅桌名：全尺寸居中
    splitLabel(fullLabel,t.y,BASE_FS);
  }else if(showN&&!showL&&!showA){
    // 仅桌号：净上移10px（原+5→-5）
    tx(num,t.y-5,27,'700');
  }else if(!showN&&!showL&&showA){
    // 仅标注：净下移10px（原+5→+15）
    tx(anno,t.y+15+_annoDelta,15,'600');
  }else if(showN&&showL&&!showA){
    // 桌号（净上移10px）+ 桌名
    tx(num,t.y-32,9,'500','55');
    splitLabel(fullLabel,t.y,BASE_FS);
  }else if(!showN&&showL&&showA){
    // 桌名 + 标注（净下移10px）
    splitLabel(fullLabel,t.y-5,BASE_FS);
    tx(anno,t.y+28+_annoDelta,10,'400','55');
  }else if(showN&&!showL&&showA){
    // 桌号（净上移10px）+ 标注（净下移10px）
    tx(num,t.y-14,18,'700');
    tx(anno,t.y+26+_annoDelta,11,'400','66');
  }else if(showN&&showL&&showA){
    // 全部：桌号（净上移10px），桌名中，标注（净下移10px）
    tx(num,t.y-36,9,'500','55');
    splitLabel(fullLabel,t.y-2,BASE_FS-3);
    tx(anno,t.y+29+_annoDelta,10,'400','55');
  }else{
    // 全关：显示桌名小字防止完全空白
    tx(rawLabel,t.y+4,10,'400','66');
  }
}

// ── 12/13/14 座显式偏移查找表 ──
// HALF_OFFSETS[n] = 前半圆各座 [dx,dy]（索引0=顶部，顺时针到纯右侧再向下）
// 后半圆由中心对称推导：seat(i+half) → (-dx, -dy)
// 右侧向外横推(+dx)，上侧微向下(+dy)，下侧微向上(-dy)；左侧自动对称
const _SEAT_HALF_OFFSETS = {
  //         0       1        2         3        4        5        6
  12: [[0,0],[5,2],[6,2],[6,0],[6,-2],[5,-2]],
  13: [[0,0],[7,2],[8,2],[8,0],[8,-2],[7,-2],[4,-2]],
  14: [[0,0],[8,2],[10,2],[10,0],[10,-2],[8,-2],[5,-2]]
};
function seatOverlapOffset(angle,idx,seats){
  const tbl=_SEAT_HALF_OFFSETS[seats];
  if(!tbl)return{dx:0,dy:0};
  const half=Math.round(seats/2);
  let i=idx,sign=1;
  if(i>=half){i=i-half;sign=-1;}
  if(i<0||i>=tbl.length)return{dx:0,dy:0};
  return{dx:sign*tbl[i][0],dy:sign*tbl[i][1]};
}

// ── drawTable: event isolation via _activeTableDragId + seatHitIds set ──
// Strategy: track seat SVG element IDs in a Set per table; mousedown checks if target is in that set
function drawTable(t,parent){
  const col=COL[t.type]||getSideColor(t.type),isSel=t.id===selectedTableId,isMultiSel=_selIds.has(t.id);
  const sz=tSize(t);
  const grp=document.createElementNS('http://www.w3.org/2000/svg','g');
  grp.setAttribute('data-tid',t.id);

  // Seat element references for hit-test
  const seatHitEls=new Set();

  // ── Table core ──
  grp.appendChild(mkEl('circle',{cx:t.x+3,cy:t.y+6,r:sz.r,fill:'rgba(0,0,0,.08)'}));
  const _ink=document.body.classList.contains('dark-mode');
  const discFill = isSel ? col : (isMultiSel?(_ink?'#7B303040':'#2FBB7A40'):col+(_ink?'32':'18'));
  const labelCol = isSel ? '#fff' : col;
  const _selStroke=_ink?'#9B3A3A':'#2FBB7A';
  const disc=mkEl('circle',{cx:t.x,cy:t.y,r:sz.r,fill:discFill,stroke:(isSel||isMultiSel)?_selStroke:col,'stroke-width':(isSel||isMultiSel)?3:1.5});
  grp.appendChild(disc);
  // 尺寸小标（圆外底部）
  grp.appendChild(mkTx(sz.label,t.x,t.y+sz.r+12,{'font-size':'7','fill':isSel?'#fff6':col+'55'}));
  // 入座人数（圆内底部，更小更低）
  grp.appendChild(mkTx(`${countOcc(t)}/${t.seats}`,t.x,t.y+sz.r-10,{'font-size':'7','fill':isSel?'#ffffffAA':col+'66'}));
  // 桌名/桌号/标注 — 智能排版
  drawTableText(grp,t,sz,labelCol);

  // ── Seats ──
  for(let i=0;i<t.seats;i++){
    const angle=(2*Math.PI*i/t.seats)-Math.PI/2;
    const {dx:_odx,dy:_ody}=seatOverlapOffset(angle,i,t.seats);
    const sx=t.x+sz.orb*Math.cos(angle)+_odx, sy=t.y+sz.orb*Math.sin(angle)+_ody;
    const found=findSlot(t.id,i);
    const rw=sz.sw,rh=sz.sh;

    if(found){
      const {g,s}=found,bg=gBg(g),tc=gTc(g);
      const isTwoLine = s.subType==='child' || s.subType==='spouse';
      grp.appendChild(mkEl('rect',{x:sx-rw/2,y:sy-rh/2,width:rw,height:rh,rx:rh/2,fill:bg,stroke:tc,'stroke-width':'1.2'}));
      if(isTwoLine){
        // Line 1: parent name (3 chars max) or self name
        const line1 = s.subType==='child' ? g.name.slice(0,3) : (g.name.slice(0,3));
        // Line 2: child name or spouse name
        const line2 = s.subType==='child' ? (s.subName||'') : (g.showPartner!==false?(s.subName||'伴侣'):'伴侣');
        grp.appendChild(mkTx(line1,sx,sy-5,{'font-size':'8','font-weight':'600','fill':tc}));
        grp.appendChild(mkTx(line2,sx,sy+5,{'font-size':'8','fill':tc+'CC'}));
      } else {
        const dn=slotDN(g,s);
        const fl=dn.length,fs=fl<=2?12:fl<=4?10:fl<=6?9:8;
        grp.appendChild(mkTx(dn.length>7?dn.slice(0,6)+'…':dn,sx,sy,{'font-size':fs,'font-weight':'600','fill':tc}));
      }
      if(s.subType==='elder')grp.appendChild(mkEl('circle',{cx:sx+rw/2-4,cy:sy-rh/2+4,r:4,fill:col}));
      grp.appendChild(mkTx(i+1,sx-rw/2+6,sy+rh/2-4,{'font-size':'6','fill':tc+'88'}));
      // ── Status dot center-top; child indicator top-right ──
      const dotCol=g.status==='confirmed'?'#2FBB7A':g.status==='pending'?'#F5A623':'#E53935';
      // Normal: status dot at center-top; child: status dot at top-right corner
      const sdX2 = s.subType==='child' ? sx+rw/2-3 : sx;
      const sdY2 = s.subType==='child' ? sy-rh/2-3 : sy-rh/2-4;
      grp.appendChild(mkEl('circle',{cx:sdX2,cy:sdY2,r:3.5,fill:dotCol,'stroke':'#fff','stroke-width':'1'}));
      // Child orange indicator at top-left
      if(s.subType==='child')grp.appendChild(mkEl('circle',{cx:sx-rw/2+5,cy:sy-rh/2+5,r:4,fill:'#e8a44a','stroke':'#fff','stroke-width':'1'}));
      // T4: 芯片左右分区——左侧(38%)拖出把手，右侧(62%)悬浮详情
      const dragW=Math.floor(rw*0.38), infoW=rw-dragW;
      // 左侧：拖动区（mousedown/mousemove/mouseup，SVG 全兼容）
      const czDrag=mkEl('rect',{x:sx-rw/2,y:sy-rh/2,width:dragW,height:rh,rx:rh/2,fill:'rgba(0,0,0,0.04)'});
      czDrag.style.cursor='grab';czDrag.style.touchAction='none';
      czDrag.dataset.tid=String(t.id);czDrag.dataset.sidx=String(i);
      // 触控拖拽：画布座位胶囊左侧拖动区（touch-action:none，阈值3px）
      czDrag.addEventListener('touchstart',function(te){
        if(te.touches.length>1||_tcDrag)return;
        te.preventDefault();te.stopPropagation();
        const slotKey=`${g.id}:${s.subId}`;
        _tcPress={slotKey,sx:te.touches[0].clientX,sy:te.touches[0].clientY,thresh:3,fromHandle:true};
        _tcLog('▶ touchstart czDrag(画布座位) slotKey='+slotKey+' 阈值3px');
      },{passive:false});
      czDrag.addEventListener('mousedown',e=>{
        e.preventDefault();e.stopPropagation();
        hideSC(); // 立即隐藏悬浮详情
        _svgDrag=`${g.id}:${s.subId}`;
        const gh=document.getElementById('dragGhost');
        gh.textContent=slotDN(g,s);gh.style.left=(e.clientX+12)+'px';gh.style.top=(e.clientY-10)+'px';gh.style.display='block';
        document.body.style.cursor='grabbing';
        const ov=mkEl('rect',{x:sx-rw/2,y:sy-rh/2,width:dragW,height:rh,rx:rh/2,fill:'url(#dragStripe)',opacity:'0.55','pointer-events':'none'});
        ov.setAttribute('data-svgov','1');grp.appendChild(ov);
      });
      czDrag.addEventListener('mouseenter',()=>{
        if(_svgDrag)return;
        const ov=mkEl('rect',{x:sx-rw/2,y:sy-rh/2,width:dragW,height:rh,rx:rh/2,fill:'url(#dragStripe)',opacity:'0.35','pointer-events':'none'});
        ov.setAttribute('data-svgov','hover');grp.appendChild(ov);
      });
      czDrag.addEventListener('mouseleave',()=>{
        if(!_svgDrag)grp.querySelectorAll('[data-svgov="hover"]').forEach(el=>el.remove());
      });
      grp.appendChild(czDrag);
      seatHitEls.add(czDrag);
      // 右侧：详情/跳转区
      const czInfo=mkEl('rect',{x:sx-rw/2+dragW,y:sy-rh/2,width:infoW,height:rh,rx:0,fill:'transparent'});
      czInfo.style.cursor='pointer';
      czInfo.dataset.tid=String(t.id);czInfo.dataset.sidx=String(i);
      let _czMd2=null;
      czInfo.addEventListener('mousedown',e=>{e.stopPropagation();_czMd2={x:e.clientX,y:e.clientY};});
      czInfo.addEventListener('mouseup',e=>{
        if(!_czMd2)return;
        const dx=e.clientX-_czMd2.x,dy=e.clientY-_czMd2.y;_czMd2=null;
        if(Math.sqrt(dx*dx+dy*dy)<5){e.stopPropagation();hideSC();jumpToGuest(g.id);}
      });
      czInfo.addEventListener('mouseenter',e=>{if(_svgDrag)return;clearTimeout(window._scT);showSC(e,g,s,t,i);});
      czInfo.addEventListener('mouseleave',()=>{window._scT=setTimeout(()=>{if(!document.getElementById('seatCard').matches(':hover'))hideSC();},130);});
      czInfo.addEventListener('dblclick',e=>{e.stopPropagation();hideSC();pushUndo();removeSeat(t.id,i);});
      // 触控双击：取消落座（与 PC dblclick 等效）
      let _czLastTap=0;
      czInfo.addEventListener('touchend',function(e){
        e.stopPropagation();
        const now=Date.now();
        if(now-_czLastTap<400){_czLastTap=0;e.preventDefault();hideSC();pushUndo();removeSeat(t.id,i);}
        else{_czLastTap=now;}
      },{passive:false});
      czInfo.addEventListener('dragover',e=>{e.preventDefault();e.stopPropagation();czInfo.style.outline='2px solid #2FBB7A';});
      czInfo.addEventListener('dragleave',()=>{czInfo.style.outline='';});
      czInfo.addEventListener('drop',e=>{e.preventDefault();e.stopPropagation();czInfo.style.outline='';const k=e.dataTransfer.getData('slotKey');if(k)dropOnSeat(k,t.id,i);});
      grp.appendChild(czInfo);
      seatHitEls.add(czInfo);
    } else {
      const _ef=_ink?'#605A58':'#EBEBEB',_es=_ink?'#787068':'#D0D0D0',_en=_ink?'#888078':'#B0B0B0',_ep=_ink?'#A29088':'#A8AEA8';
      const emptyBg=mkEl('rect',{x:sx-rw/2,y:sy-rh/2,width:rw,height:rh,rx:rh/2,fill:_ef,stroke:_es,'stroke-width':'1'});
      grp.appendChild(emptyBg);
      grp.appendChild(mkTx(i+1,sx-rw/2+7,sy,{'font-size':'8','fill':_en}));
      grp.appendChild(mkTx('＋',sx+4,sy,{'font-size':'13','fill':_ep}));
      const ez=mkEl('rect',{x:sx-rw/2,y:sy-rh/2,width:rw,height:rh,rx:rh/2,fill:'transparent'});
      ez.style.cursor='pointer';
      ez.dataset.tid=String(t.id);ez.dataset.sidx=String(i);
      ez.addEventListener('mousedown',e=>{e.stopPropagation();});
      ez.addEventListener('click',e=>{e.stopPropagation();openQA(t.id,i);});
      ez.addEventListener('dragover',e=>{e.preventDefault();e.stopPropagation();emptyBg.setAttribute('stroke','#2FBB7A');emptyBg.setAttribute('fill','#e6f7ee');});
      ez.addEventListener('dragleave',()=>{emptyBg.setAttribute('stroke',_es);emptyBg.setAttribute('fill',_ef);});
      ez.addEventListener('drop',e=>{e.preventDefault();e.stopPropagation();emptyBg.setAttribute('stroke',_es);emptyBg.setAttribute('fill',_ef);const k=e.dataTransfer.getData('slotKey');if(k)dropOnSeat(k,t.id,i);});
      grp.appendChild(ez);
      seatHitEls.add(ez);
    }
  }

  // ── Table events ──
  // Use mousedown+mouseup distance to simulate click/dblclick
  // because render() after mouseup destroys the grp before native click fires
  let _mdPos=null, _mdTime=0;
  grp.addEventListener('mousedown',e=>{
    if(_spaceDown||e.button!==0)return;
    if(seatHitEls.has(e.target))return;
    e.stopPropagation(); e.preventDefault();
    _mdPos={x:e.clientX,y:e.clientY}; _mdTime=Date.now();
    const wp=clientToWorld(e.clientX,e.clientY);
    // If this table is part of multi-selection, start multi-drag
    if(_selIds.has(t.id)&&_selIds.size>1){
      _multiDrag={
        starts:Array.from(_selIds).map(id=>{const tt=tables.find(tt=>tt.id===id);return{id,x:tt.x,y:tt.y};}),
        ox:wp.x,oy:wp.y
      };
    } else {
      _selIds=new Set(); // clicking a non-selected table clears selection
      _tDrag={tid:t.id,ox:wp.x-t.x,oy:wp.y-t.y};
    }
  });
  grp.addEventListener('mouseup',e=>{
    if(e.button!==0||!_mdPos)return;
    if(seatHitEls.has(e.target))return;
    const dx=e.clientX-_mdPos.x, dy=e.clientY-_mdPos.y;
    const dist=Math.sqrt(dx*dx+dy*dy);
    const dt=Date.now()-_mdTime;
    if(dist<5 && dt<400){
      // treat as click: select table
      selectTable(t.id);
    }
    _mdPos=null;
  });
  grp.addEventListener('dblclick',e=>{
    if(seatHitEls.has(e.target))return;
    e.stopPropagation();
    // double-click on table disc = edit label
    editLabel(t);
  });
  grp.addEventListener('dragover',e=>{
    if(seatHitEls.has(e.target))return;
    e.preventDefault(); e.dataTransfer.dropEffect='move';
  });
  grp.addEventListener('drop',e=>{
    if(seatHitEls.has(e.target))return;
    e.preventDefault();
    const fam=e.dataTransfer.getData('familyDrop');
    if(fam){dropFamilyOnTable(parseInt(fam),t.id);return;}
    const k=e.dataTransfer.getData('slotKey'); if(k)dropOnTable(k,t.id);
  });
  parent.appendChild(grp);
}

// ══════════════════════════════════════════════════
// TABLE ACTIONS
// ══════════════════════════════════════════════════
function nextTableNum(){
  const used=new Set(tables.map(t=>t.num).filter(Boolean));
  for(let i=1;i<=999;i++){const s=String(i).padStart(3,'0');if(!used.has(s))return s;}
  return '';
}
function addTable(){
  pushUndo();tableCounter++;
  const cx=roomW*M/2+(Math.random()-.5)*roomW*M*.4;
  const cy=roomH*M/2+(Math.random()-.5)*roomH*M*.4;
  const num=nextTableNum();
  tables.push({id:tableCounter,x:cx,y:cy,seats:10,type:'shared',label:`第${tableCounter}桌`,size:DEFAULT_SIZE,num});
  selectTable(tableCounter);render();
}
function selectTable(id){selectedTableId=id;if(id)switchTab('config');render();}
function deleteSelectedTable(){
  if(!selectedTableId)return;pushUndo();
  guests.forEach(g=>(g.slots||[]).forEach(s=>{if(s.seatedTableId===selectedTableId){s.seatedTableId=null;s.seatedSeat=null;}}));
  tables=tables.filter(t=>t.id!==selectedTableId);selectedTableId=null;render();
}
function editLabel(t){const l=prompt('圆桌名称：',t.label);if(l!==null){t.label=l;render();}}
function removeSeat(tid,si){const f=findSlot(tid,si);if(f){f.s.seatedTableId=null;f.s.seatedSeat=null;render();}}

// ══════════════════════════════════════════════════
// TABLE CONFIG PANEL
// ══════════════════════════════════════════════════
// 春日预设桌名
const SPRING_NAMES=['春和景明（主）','惠风和畅','杏花疏影','燕草如碧','秦桑低绿','风帘翠幕','拂堤春晓','花光柳影','暗香盈袖','清溪几曲'];
function applyPresetName(n){
  const t=tables.find(t=>t.id===selectedTableId);if(!t)return;
  pushUndo();t.label=n;renderTableConfig();renderTablesOnly();renderTableList();
}
function onTNum(v){
  const t=tables.find(t=>t.id===selectedTableId);if(!t)return;
  t.num=v.replace(/[^0-9]/g,'').slice(0,3);
  const dup=t.num&&tables.some(tt=>tt.num===t.num&&tt.id!==t.id);
  document.getElementById('cfg-num-warn').style.display=dup?'':'none';
  renderTablesOnly();renderTableList();
}
function adjTNum(delta){
  const t=tables.find(t=>t.id===selectedTableId);if(!t)return;
  pushUndo();
  const cur=parseInt(t.num||'0',10);
  const next=Math.max(1,Math.min(999,cur+delta));
  t.num=String(next).padStart(3,'0');
  const el=document.getElementById('cfg-num');if(el)el.value=t.num;
  const dup=tables.some(tt=>tt.num===t.num&&tt.id!==t.id);
  const w=document.getElementById('cfg-num-warn');if(w)w.style.display=dup?'inline':'none';
  renderTablesOnly();renderTableList();
}

function renderTableConfig(){
  const panel=document.getElementById('tableConfigPanel');
  const t=tables.find(t=>t.id===selectedTableId);
  if(!t){panel.innerHTML='<p class="text-xs text-muted text-center pt-8">请先点击选择一张圆桌</p>';return;}
  const sz=t.size||DEFAULT_SIZE;
  const builtins=['groom','bride','shared'];
  const isCustom=!builtins.includes(t.type);
  const tBtn=(type,lbl)=>`<button onclick="setTT('${type}')" class="flex-1 py-1 text-xs rounded border transition-all ${t.type===type?'cfg-type-btn-on':'cfg-type-btn-off'}">${lbl}</button>`;
  const sBtn=(s,lbl)=>`<button onclick="setTS('${s}')" class="flex-1 py-1 text-xs rounded border transition-all ${sz===s?'cfg-type-btn-on':'cfg-type-btn-off'}">${lbl}</button>`;
  // 所属方：3个固定按钮 + 自定义分类下拉（同一行）
  const catOpts=customCategories.map(c=>`<option value="${c.id}"${t.type===c.id?' selected':''}>${eh(c.label)}</option>`).join('');
  const otherSel=`<select onchange="setTT(this.value)" style="border:1.5px solid ${isCustom?'#2FBB7A':'#DDE6DC'};background:${isCustom?'#2FBB7A':'#fff'};color:${isCustom?'#fff':'#5A7A6A'};border-radius:6px;font-size:11px;padding:2px 6px;outline:none;cursor:pointer;transition:all .15s;flex-shrink:0">
    <option value=""${isCustom?'':' selected'}>其他…</option>${catOpts}
  </select>`;
  // 桌号重复检查
  const numVal=t.num||'';
  const numDup=numVal&&tables.some(tt=>tt.num===numVal&&tt.id!==t.id);
  // 座位排序列表
  const baseOrder=Array.from({length:t.seats},(_,i)=>i);
  const ordArr=t.seatOrder?.length===t.seats?t.seatOrder:[...baseOrder];
  const rows=ordArr.map((seatIdx,pos)=>{
    const f=findSlot(t.id,seatIdx),name=f?slotDN(f.g,f.s):'—';
    return`<div class="flex items-center text-xs py-0.5 gap-2 rounded cursor-move hover:bg-surface select-none"
      draggable="true" ondragstart="cfgDragStart(event,${pos})" ondragover="cfgDragOver(event,${pos})"
      ondrop="cfgDrop(event,${pos},${t.id})" ondragend="cfgDragEnd()">
      <span class="text-muted text-[10px] w-3">⠿</span>
      <span class="text-muted w-5 text-right">${seatIdx+1}</span>
      <span class="flex-1 ${f?'text-ink':'text-muted'}">${eh(name)}</span>
      ${f?`<button onclick="event.stopPropagation();pushUndo();removeSeat(${t.id},${seatIdx})" class="text-red-400 hover:text-red-600 text-xs">移</button>`:''}
    </div>`;
  }).join('');
  const px=(t.x/M).toFixed(1),py=(t.y/M).toFixed(1);
  panel.innerHTML=`<div class="space-y-3">
    <!-- 桌号 + 名称（同行） -->
    <div class="flex items-end gap-2">
      <div style="flex:0 0 72px">
        <label class="fl">桌号</label>
        <div class="flex items-center gap-0.5">
          <button onclick="adjTNum(-1)" class="adj-num-btn" style="width:22px;height:28px;border-radius:4px;cursor:pointer;font-size:14px;flex-shrink:0;padding:0;line-height:1">−</button>
          <input id="cfg-num" type="text" value="${eh(numVal)}" maxlength="3" placeholder="001"
            class="fi text-center font-mono${numDup?' border-red-400':''}" style="padding:5px 2px;flex:1;min-width:0"
            oninput="onTNum(this.value)">
          <button onclick="adjTNum(+1)" class="adj-num-btn" style="width:22px;height:28px;border-radius:4px;cursor:pointer;font-size:14px;flex-shrink:0;padding:0;line-height:1">＋</button>
          <span id="cfg-num-warn" title="桌号重复" style="display:${numDup?'inline':'none'};color:#EF4444;font-size:14px;line-height:1;flex-shrink:0">⚠</span>
        </div>
      </div>
      <div class="flex-1 min-w-0">
        <label class="fl">桌子名称 <span style="font-weight:400;color:#8A9A8A;font-size:9px">/ 标注</span></label>
        <div class="flex gap-1">
          <input id="cfg-lbl" type="text" value="${eh(t.label)}" class="fi flex-1" oninput="onTLI(this.value)">
          <input id="cfg-anno" type="text" value="${eh(t.annotation||'')}" class="fi" style="width:72px;flex-shrink:0" placeholder="标注…" maxlength="6" oninput="onAnno(this.value)" title="标注（如：男方亲戚、VIP…）">
          <button onclick="toggleMainTable(${t.id})" class="btn-main-toggle-${t.isMain?'on':'off'} flex-shrink-0 text-[11px] rounded border" style="padding:5px 8px;white-space:nowrap;font-weight:600;cursor:pointer;transition:all .15s;${t.isMain?'background:#2FBB7A;color:#fff;border-color:#2FBB7A':'background:#F2F6F1;color:#5A7A6A;border-color:#DDE6DC'}" title="切换主桌标记">${t.isMain?'★ 主桌':'☆ 主桌'}</button>
        </div>
      </div>
    </div>
    <!-- 所属方 -->
    <div><label class="fl mb-1">所属方</label>
      <div class="flex gap-1.5 items-center">${tBtn('groom','男方')}${tBtn('bride','女方')}${tBtn('shared','共同')}${otherSel}</div>
    </div>
    <!-- 尺寸 / 座位 -->
    <div><label class="fl mb-1">圆桌尺寸</label><div class="flex gap-1.5">${sBtn('1.8','1.8m')}${sBtn('2.0','2.0m ★')}${sBtn('2.2','2.2m')}</div></div>
    <div><label class="fl">座位数 <strong class="sc-seated">${t.seats}</strong></label><input type="range" min="6" max="14" value="${t.seats}" oninput="updSeats(this.value)" class="w-full mt-1" style="accent-color:var(--sc-slider-c,#2FBB7A)" list="seats-ticks"></div>
    <div class="text-xs text-muted bg-surface rounded px-2 py-1.5">📍 (${px}m, ${py}m) · ⌀${sz}m</div>
    <div><label class="fl">就坐 <strong class="sc-seated">${countOcc(t)}/${t.seats}</strong> <span class="text-[10px] text-muted font-normal">（可拖动调整PDF排序）</span></label><div class="space-y-0.5 mt-1">${rows}</div></div>
    <button onclick="deleteSelectedTable()" class="btn btn-danger w-full text-xs">🗑 删除此桌</button>
  </div>`;
}
// ── Config panel seat-order drag ──
let _cfgDragFrom=null;
function cfgDragStart(e,pos){_cfgDragFrom=pos;e.dataTransfer.effectAllowed='move';}
function cfgDragOver(e,pos){e.preventDefault();e.dataTransfer.dropEffect='move';}
function cfgDrop(e,pos,tid){
  e.preventDefault();
  if(_cfgDragFrom===null||_cfgDragFrom===pos)return;
  const t=tables.find(t=>t.id===tid);if(!t)return;
  const baseOrder=Array.from({length:t.seats},(_,i)=>i);
  const arr=t.seatOrder?.length===t.seats?[...t.seatOrder]:[...baseOrder];
  const [moved]=arr.splice(_cfgDragFrom,1);arr.splice(pos,0,moved);
  t.seatOrder=arr;_cfgDragFrom=null;renderTableConfig();
}
function cfgDragEnd(){_cfgDragFrom=null;}
function onTLI(v){const t=tables.find(t=>t.id===selectedTableId);if(t){t.label=v;renderTablesOnly();updateStats();}}
function onAnno(v){const t=tables.find(t=>t.id===selectedTableId);if(t){t.annotation=v;renderTablesOnly();}}
function renderTableList(){
  const el=document.getElementById('tableListItems');
  const ct=document.getElementById('tableListCount');
  if(!el)return;
  ct.textContent=`${tables.length} 张`;
  if(!tables.length){el.innerHTML='<div class="text-[11px] text-muted text-center py-3">暂无圆桌</div>';return;}
  el.innerHTML=tables.map(t=>{
    const occ=countOcc(t), isSel=t.id===selectedTableId;
    const col=COL[t.type]||getSideColor(t.type);
    const sz=t.size||DEFAULT_SIZE;
    const typeLabel=LABEL[t.type]||(customCategories.find(c=>c.id===t.type)?.label)||t.type;
    const mainBadge=t.isMain?`<span class="main-table-badge" style="font-size:9px;font-weight:700;background:#FEF3C7;color:#92400E;padding:1px 4px;border-radius:3px;flex-shrink:0;margin-left:2px">主</span>`:'';
    return`<div class="flex items-center gap-2 px-3 py-1.5 cursor-pointer border-b border-line transition-all ${isSel?'bg-emerald-50':'hover:bg-surface'}"
      onclick="selectTable(${t.id})"
      ondragover="event.preventDefault();this.style.outline='2px solid #2FBB7A';this.style.outlineOffset='-2px'"
      ondragleave="this.style.outline=''"
      ondrop="this.style.outline='';wtnDropOnTable(event,${t.id})">
      <div class="w-2.5 h-2.5 rounded-full flex-shrink-0" style="background:${col}"></div>
      <div class="flex-1 min-w-0">
        <div class="text-xs font-semibold text-ink flex items-center gap-0.5" style="color:${isSel?'#2FBB7A':'inherit'}">
          <span class="truncate">${t.num?`<span style="font-family:monospace;color:#aaa;font-weight:400">${eh(t.num)}·</span>`:''}${eh(t.label)}</span>${mainBadge}
        </div>
        <div class="text-[10px] text-muted">${typeLabel} · ${sz}m · ${occ}/${t.seats}座</div>
      </div>
      <button onclick="event.stopPropagation();deleteTable(${t.id})" class="flex-shrink-0 text-[11px] text-muted hover:text-red-500 px-1 transition-colors" title="删除">✕</button>
    </div>`;
  }).join('');
}
function deleteTable(id){
  if(!confirm('确认删除此桌？桌上宾客将回到未落座状态。'))return;
  pushUndo();
  guests.forEach(g=>(g.slots||[]).forEach(s=>{if(s.seatedTableId===id){s.seatedTableId=null;s.seatedSeat=null;}}));
  tables=tables.filter(t=>t.id!==id);
  if(selectedTableId===id)selectedTableId=null;
  render();
}
function setTT(type){if(!type)return;pushUndo();const t=tables.find(t=>t.id===selectedTableId);if(t){t.type=type;render();}}
function setTS(s){pushUndo();const t=tables.find(t=>t.id===selectedTableId);if(t){t.size=s;render();}}
function updSeats(v){pushUndo();const t=tables.find(t=>t.id===selectedTableId);if(!t)return;const n=parseInt(v);for(let i=n;i<t.seats;i++){const f=findSlot(t.id,i);if(f){f.s.seatedTableId=null;f.s.seatedSeat=null;}}t.seats=n;render();}
function eh(s){return(s||'').replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/</g,'&lt;').replace(/>/g,'&gt;');}

// ══════════════════════════════════════════════════
// SEAT CARD
// ══════════════════════════════════════════════════
function showSC(e,g,s,tbl,seatIdx){
  const card=document.getElementById('seatCard');
  document.getElementById('scName').textContent=slotDN(g,s);
  // T4: 座位元信息行（座位号 + 所属方）
  const metaEl=document.getElementById('scMeta');
  const seatNo=seatIdx!=null?`📍 ${tbl?.label||''} · 第${seatIdx+1}座`:'';
  const sideCol=getSideColor(g.side);
  metaEl.innerHTML=`<span>${seatNo}</span><span style="color:${sideCol};font-weight:600">${getSideLabel(g.side)}</span>`;
  const no=document.getElementById('scNote');
  if(g.note){no.textContent='📝 '+g.note;no.style.display='block';}else no.style.display='none';
  document.getElementById('scStatusRow').innerHTML=[{k:'confirmed',l:'✓ 已确认'},{k:'pending',l:'⏳ 待定'},{k:'declined',l:'⚑ 标注'}]
    .map(st=>`<span class="scp ${g.status===st.k?'sp-'+st.k:''}" onclick="setSS(${g.id},'${st.k}')">${st.l}</span>`).join('');
  document.getElementById('scEditBtn').onclick=()=>{hideSC();openGuestModal(g.id);};
  card.style.display='block';
  const w=card.offsetWidth||200,h=card.offsetHeight||130;
  let left=e.clientX+18,top=e.clientY-h/2;
  if(left+w>window.innerWidth-10)left=e.clientX-w-18;
  if(top<8)top=8;if(top+h>window.innerHeight-8)top=window.innerHeight-h-8;
  card.style.left=left+'px';card.style.top=top+'px';
}
function hideSC(){document.getElementById('seatCard').style.display='none';}
function setSS(gid,status){const g=guests.find(g=>g.id===gid);if(g){pushUndo();g.status=status;hideSC();render();}}
document.getElementById('seatCard').addEventListener('mouseleave',()=>{window._scT=setTimeout(hideSC,80);});
document.getElementById('seatCard').addEventListener('mouseenter',()=>clearTimeout(window._scT));

// ══════════════════════════════════════════════════
// DRAG — sidebar → canvas
// ══════════════════════════════════════════════════
function startDrag(e,gid,subId){
  e.dataTransfer.setData('slotKey',`${gid}:${subId}`);e.dataTransfer.effectAllowed='move';
  const g=guests.find(g=>g.id===gid),s=(g?.slots||[]).find(s=>s.subId===subId);
  const gh=document.getElementById('dragGhost');gh.textContent=s?slotDN(g,s):g?.name||'';gh.style.display='block';
  e.dataTransfer.setDragImage(new Image(),0,0);document.addEventListener('dragover',movGhost);
}
function movGhost(e){const g=document.getElementById('dragGhost');g.style.left=(e.clientX+14)+'px';g.style.top=(e.clientY-10)+'px';}
function endDrag(){document.getElementById('dragGhost').style.display='none';document.removeEventListener('dragover',movGhost);}

// ── 宾客面板 HTML5 drop zone：拖回宾客栏取消落座 ──
// dataTransfer.types 全小写；用捕获阶段拦截，避免被子元素 stopPropagation 吃掉
(function(){
  const pr=document.getElementById('paneRight');if(!pr)return;
  pr.addEventListener('dragover',e=>{
    if(e.dataTransfer.types.includes('slotkey')&&!e.dataTransfer.types.includes('familydrop')){
      e.preventDefault();e.stopPropagation();e.dataTransfer.dropEffect='move';
      pr.style.outline='2px dashed #E87A6A';pr.style.outlineOffset='-3px';
    }
  },{capture:true});
  pr.addEventListener('dragleave',e=>{if(!pr.contains(e.relatedTarget))pr.style.outline='';},{capture:true});
  pr.addEventListener('drop',e=>{
    pr.style.outline='';
    const k=e.dataTransfer.getData('slotKey');if(!k)return;
    e.preventDefault();e.stopPropagation();
    const[gs,ss]=k.split(':');
    const guest=guests.find(g=>g.id===parseInt(gs));
    const slot=(guest?.slots||[]).find(s=>s.subId===parseInt(ss));
    if(slot&&slot.seatedTableId){pushUndo();slot.seatedTableId=null;slot.seatedSeat=null;render();}
  },{capture:true});
})();

// ── 家庭拖到画布桌子（PC）────────────────────────────────────────────────
function familyCanvasDragStart(e,gid){
  _guestDragId=gid;
  e.dataTransfer.setData('guestReorder',String(gid)); // 保留列表内排序兼容
  e.dataTransfer.setData('familyDrop',String(gid));
  e.dataTransfer.effectAllowed='move';
  const g=guests.find(g=>g.id===gid);
  const n=(g?.slots||[]).length;
  const gh=document.getElementById('dragGhost');
  gh.textContent=(g?g.name:'')+(n>1?' ('+n+'人)':'');gh.style.display='block';
  e.dataTransfer.setDragImage(new Image(),0,0);
  document.addEventListener('dragover',movGhost);
}
// 将一组家庭成员依次落座到指定桌
function dropFamilyOnTable(gid,tid){
  const guest=guests.find(g=>g.id===gid);if(!guest)return;
  const table=tables.find(t=>t.id===tid);if(!table)return;
  const slots=guest.slots||[];if(!slots.length)return;
  let seated=0;
  pushUndo();
  for(const slot of slots){
    let free=-1;
    for(let i=0;i<table.seats;i++){if(!findSlot(tid,i)){free=i;break;}}
    if(free===-1){if(!seated)alert(`${table.label} 已满，无法安排`);break;}
    if(slot.seatedTableId){slot.seatedTableId=null;slot.seatedSeat=null;}
    slot.seatedTableId=tid;slot.seatedSeat=free;seated++;
  }
  render();
}

// Drop on table disc = auto next free seat
function dropOnTable(key,tid){
  const [gs,ss]=key.split(':');const gid=parseInt(gs),subId=parseInt(ss);
  const guest=guests.find(g=>g.id===gid);if(!guest)return;
  const slot=(guest.slots||[]).find(s=>s.subId===subId);if(!slot)return;
  if(slot.seatedTableId){slot.seatedTableId=null;slot.seatedSeat=null;}
  const t=tables.find(t=>t.id===tid);if(!t)return;
  let free=-1;for(let i=0;i<t.seats;i++){if(!findSlot(tid,i)){free=i;break;}}
  if(free===-1){alert(`${t.label} 已满座`);return;}
  pushUndo();slot.seatedTableId=tid;slot.seatedSeat=free;render();
}
// Drop on specific seat — T9: 有人则弹换人对话框
function dropOnSeat(key,tid,seatIdx){
  const [gs,ss]=key.split(':');const gid=parseInt(gs),subId=parseInt(ss);
  const guest=guests.find(g=>g.id===gid);if(!guest)return;
  const slot=(guest.slots||[]).find(s=>s.subId===subId);if(!slot)return;
  const existing=findSlot(tid,seatIdx);
  if(existing&&!(existing.g.id===gid&&existing.s.subId===subId)){
    // T9: 目标座位有人，弹出选择框
    _pendingSwap={key,toTid:tid,toSeat:seatIdx};
    document.getElementById('swapFromName').textContent=slotDN(guest,slot);
    document.getElementById('swapToName').textContent=slotDN(existing.g,existing.s);
    const t=tables.find(t=>t.id===tid);
    document.getElementById('swapSeatInfo').textContent=(t?.label||'')+'·第'+(seatIdx+1)+'座';
    document.getElementById('swapModal').classList.add('open');
    return;
  }
  pushUndo();
  if(slot.seatedTableId){slot.seatedTableId=null;slot.seatedSeat=null;}
  slot.seatedTableId=tid;slot.seatedSeat=seatIdx;render();
}
// T9: 换人模式执行
function execSwap(mode){
  if(!_pendingSwap)return;
  const{key,toTid,toSeat}=_pendingSwap;
  const[gs,ss]=key.split(':');const gid=parseInt(gs),subId=parseInt(ss);
  const guest=guests.find(g=>g.id===gid);const slot=(guest?.slots||[]).find(s=>s.subId===subId);
  const existing=findSlot(toTid,toSeat);
  pushUndo();
  if(mode==='swap'&&existing&&slot){
    const oldTid=slot.seatedTableId,oldSeat=slot.seatedSeat;
    slot.seatedTableId=toTid;slot.seatedSeat=toSeat;
    existing.s.seatedTableId=oldTid;existing.s.seatedSeat=oldSeat;
  } else if(mode==='override'&&existing&&slot){
    existing.s.seatedTableId=null;existing.s.seatedSeat=null;
    if(slot.seatedTableId){slot.seatedTableId=null;slot.seatedSeat=null;}
    slot.seatedTableId=toTid;slot.seatedSeat=toSeat;
  }
  _pendingSwap=null;document.getElementById('swapModal').classList.remove('open');render();
}
function closeSwapModal(){_pendingSwap=null;document.getElementById('swapModal').classList.remove('open');}

// T10: 点击座位 → 宾客名单跳转到家庭
function jumpToGuest(gid){
  // 切换到宾客名单 tab
  switchTab('guests');
  const g=guests.find(g=>g.id===gid);if(!g)return;
  // 展开该分类
  if(sideCollapsed[g.side])sideCollapsed[g.side]=false;
  renderGuestList(filterText);
  // 等 DOM 更新后滚动
  setTimeout(()=>{
    const el=document.getElementById('guest-family-'+gid);
    if(el){el.scrollIntoView({block:'center',behavior:'smooth'});
      el.style.transition='background .3s';el.style.background='#dcf5e8';
      setTimeout(()=>{el.style.background='';},1200);
    }
  },80);
}

// ══════════════════════════════════════════════════
// QUICK ASSIGN
// ══════════════════════════════════════════════════
function openQA(tid,si){
  _qaTableId=tid;_qaSeatIdx=si;
  const t=tables.find(t=>t.id===tid);
  document.getElementById('qaInfo').textContent=`${t?.label||''} · 第 ${si+1} 座`;
  document.getElementById('qaSearch').value='';renderQaList('');
  document.getElementById('qaModal').classList.add('open');
}
function closeQA(){document.getElementById('qaModal').classList.remove('open');if(!_pendingQA){_qaTableId=null;_qaSeatIdx=null;}}
// T12: 同时搜索未落座和已落座者
function renderQaList(filter){
  const ul=document.getElementById('qaList');
  const unsArr=[],satArr=[];
  guests.forEach(g=>(g.slots||[]).forEach(s=>{
    const dn=slotDN(g,s);
    const match=!filter||dn.includes(filter)||g.name.includes(filter)||(g.name2||'').includes(filter)||(s.subName||'').includes(filter);
    if(!match)return;
    if(!s.seatedTableId)unsArr.push({g,s,dn});
    else satArr.push({g,s,dn});
  }));
  let html='';
  if(unsArr.length){
    html+='<div class="text-[9px] font-semibold text-muted px-1 mb-0.5">未落座</div>';
    html+=unsArr.map(({g,s,dn})=>`<div class="flex items-center gap-2 px-2 py-1 rounded cursor-pointer hover:bg-surface" onclick="qaAssign(${g.id},${s.subId})"><span class="inline-flex items-center gap-1 text-xs px-2 py-0.5 rounded-full" style="background:${gBg(g)};color:${gTc(g)}">${s.subType==='child'?'👶':s.subType==='elder'?'🧓':'👤'} ${eh(dn)}</span><span class="text-[10px] text-muted ml-auto">${getSideLabel(g.side)}</span></div>`).join('');
  }
  // T12: 已落座者也可移座
  if(satArr.length){
    html+=`<div class="text-[9px] font-semibold text-muted px-1 mt-1.5 mb-0.5">已落座（点击移来）</div>`;
    html+=satArr.map(({g,s,dn})=>{
      const tbl=tables.find(t=>t.id===s.seatedTableId);
      return`<div class="flex items-center gap-2 px-2 py-1 rounded cursor-pointer hover:bg-amber-50" onclick="qaAssignSeated(${g.id},${s.subId})">
        <span class="inline-flex items-center gap-1 text-xs px-2 py-0.5 rounded-full" style="background:${gBg(g)};color:${gTc(g)};opacity:.85">${s.subType==='child'?'👶':s.subType==='elder'?'🧓':'👤'} ${eh(dn)}</span>
        <span class="text-[9px] ml-auto whitespace-nowrap" style="color:#b07a20">${tbl?tbl.label:'已落座'} ↗</span>
      </div>`;
    }).join('');
  }
  if(!unsArr.length&&!satArr.length)html='<div class="text-xs text-muted text-center py-3">'+(filter?'无匹配结果':'所有宾客均已落座 ✓')+'</div>';
  ul.innerHTML=html;
}
function qaAssign(gid,subId){
  if(_qaTableId===null)return;
  const g=guests.find(g=>g.id===gid),s=(g?.slots||[]).find(s=>s.subId===subId);if(!s)return;
  pushUndo();s.seatedTableId=_qaTableId;s.seatedSeat=_qaSeatIdx;
  _qaTableId=null;_qaSeatIdx=null;closeQA();render();
}
// T12: 已入座者移座——检查目标座是否有人，有则弹换人框
function qaAssignSeated(gid,subId){
  if(_qaTableId===null)return;
  const g=guests.find(g=>g.id===gid),s=(g?.slots||[]).find(s=>s.subId===subId);if(!s)return;
  const existing=findSlot(_qaTableId,_qaSeatIdx);
  if(existing&&!(existing.g.id===gid&&existing.s.subId===subId)){
    _pendingSwap={key:`${gid}:${subId}`,toTid:_qaTableId,toSeat:_qaSeatIdx};
    document.getElementById('swapFromName').textContent=slotDN(g,s);
    document.getElementById('swapToName').textContent=slotDN(existing.g,existing.s);
    const t=tables.find(t=>t.id===_qaTableId);
    document.getElementById('swapSeatInfo').textContent=(t?.label||'')+'·第'+(_qaSeatIdx+1)+'座';
    closeQA();document.getElementById('swapModal').classList.add('open');return;
  }
  pushUndo();s.seatedTableId=_qaTableId;s.seatedSeat=_qaSeatIdx;
  _qaTableId=null;_qaSeatIdx=null;closeQA();render();
}
// T13: 打开新建宾客弹窗时预填入已输入的姓名
function openGuestModalFromQA(){
  _pendingQA=true;
  const prefill=(document.getElementById('qaSearch')?.value||'').trim();
  closeQA();openGuestModal(null);
  if(prefill){
    // 预填姓名（用户可修改），搜索词可能是家庭主名/配偶/孩子名
    const inp=document.getElementById('g-name');
    if(inp&&!inp.value)inp.value=prefill;
  }
}

// ══════════════════════════════════════════════════
// GUEST LIST
// ══════════════════════════════════════════════════
function filterGuests(v){filterText=v;renderGuestList(v);}
// CSV 工具栏展开（手机）
function toggleCsvToolbar(){
  document.getElementById('csvRow').classList.toggle('csv-open');
}
function toggleCsvMobileBar(){
  document.getElementById('csvMobileBar').classList.toggle('open');
}

// 手机紧凑宾客列表（以个人为单位，可触摸拖拽）
let _mobileSelGuestId=null;
let _mcLastTapKey=null,_mcLastTapTime=0;
// slotKey 传入用于双击取消落座
function mobileSelectGuest(gid,slotKey){
  const now=Date.now();
  if(slotKey&&slotKey===_mcLastTapKey&&now-_mcLastTapTime<400){
    // 双击：取消该槽位的落座
    _mcLastTapKey=null;_mcLastTapTime=0;
    const[gs,ss]=slotKey.split(':');
    const guest=guests.find(g=>g.id===parseInt(gs));
    const slot=(guest?.slots||[]).find(s=>s.subId===parseInt(ss));
    if(slot&&slot.seatedTableId){pushUndo();slot.seatedTableId=null;slot.seatedSeat=null;render();}
    return;
  }
  _mcLastTapKey=slotKey||null;_mcLastTapTime=now;
  _mobileSelGuestId=gid;
  const eb=document.getElementById('mobileEditBtn');
  if(eb)eb.style.display=gid!=null?'inline-flex':'none';
  // 更新选中高亮
  document.querySelectorAll('.mc-tag').forEach(el=>{
    el.style.outline=el.dataset.gid==gid?'2px solid #2FBB7A':'none';
  });
}
function mobileEditSelected(){
  if(_mobileSelGuestId!=null)openGuestModal(_mobileSelGuestId);
}
function renderGuestListCompact(filter,list){
  const all=guests.filter(g=>!filter||g.name.includes(filter)||(g.name2||'').includes(filter)||
    (g.slots||[]).some(s=>(slotDN(g,s)||'').includes(filter)));
  if(!all.length){list.innerHTML='<div class="text-xs text-muted text-center pt-6">暂无宾客，点击 ＋ 添加</div>';return;}
  let html='<div style="display:flex;flex-wrap:wrap;gap:4px;padding:6px 4px;align-content:flex-start">';
  all.forEach(g=>{
    const bg=gBg(g),tc=gTc(g);
    (g.slots||[]).forEach(s=>{
      const dn=slotDN(g,s);
      const seated=!!s.seatedTableId;
      const tbl=seated?tables.find(t=>t.id===s.seatedTableId):null;
      const slotKey=`${g.id}:${s.subId}`;
      const isSel=_mobileSelGuestId===g.id;
      html+=`<span class="mc-tag" data-gid="${g.id}" data-slotkey="${slotKey}"
        draggable="true" ondragstart="startDrag(event,${g.id},${s.subId})" ondragend="endDrag()"
        onclick="mobileSelectGuest(${g.id},'${slotKey}')"
        style="display:inline-flex;align-items:center;gap:3px;background:${bg};color:${tc};
          padding:2px 9px 2px 3px;border-radius:12px;font-size:11px;cursor:grab;
          border:1.5px solid ${tc}40;white-space:nowrap;user-select:none;touch-action:manipulation;
          opacity:1;outline:${isSel?'2px solid #2FBB7A':'none'};${seated?'text-decoration-line:line-through;text-decoration-color:'+tc+'88;':''}" >`;
      html+=`<span class="tc-handle" data-slotkey="${slotKey}" style="touch-action:none;cursor:grab;font-size:12px;opacity:.5;padding:2px 4px;flex-shrink:0;user-select:none;line-height:1">⠿</span>`;
      html+=`<span style="font-size:9px;opacity:.7">${s.subType==='child'?'👶':s.subType==='elder'?'🧓':'👤'}</span>`;
      html+=`<span>${eh(dn)}</span>`;
      if(seated&&tbl)html+=`<span style="font-size:9px;opacity:.55"> ·${eh(tbl.label)}</span>`;
      html+=`</span>`;
    });
  });
  html+='</div>';
  list.innerHTML=html;
}

// ══════════════════════════════════════════════════════════════════
// 触控拖拽系统 V4 — 移动激活模型 + 详细 console 回显
//
// 工作原理（无长按计时器）：
//   touchstart  → 记录起点 _tcPress={slotKey,sx,sy,fromHandle}
//   touchmove   → 移动超阈值 → tcEngageDrag() → 显示 ghost
//   touchend    → 若 ghost 显示过 → 执行落座；否则 → tap，onclick 正常触发
//
// 阈值：⠿ 手柄（touch-action:none）= 5px；mc-tag 整体 = 14px
// 来源：① mc-tag ⠿ 手柄  ② mc-tag 整体  ③ 画布座位 czDrag
// ══════════════════════════════════════════════════════════════════
let _tcPress=null; // 待激活状态 {slotKey,sx,sy,thresh,fromHandle}
// _tcDrag 已在顶部声明：{slotKey,ghost} → 激活状态

let _tcDebug=false; // Shift+D 开关；生产环境默认关闭
function _tcLog(...args){if(_tcDebug)console.log('[TC]',...args);}
// Shift+D 切换 debug 回显 + 设备/视口诊断信息
document.addEventListener('keydown',e=>{
  if(e.shiftKey&&e.key==='D'&&!e.target.matches('input,textarea,select')){
    _tcDebug=!_tcDebug;
    console.log('[TC] debug '+(_tcDebug?'ON ✅':'OFF ❌'));
    if(_tcDebug){
      const vw=window.innerWidth,vh=window.innerHeight;
      const cssVh=document.documentElement.clientHeight;
      const body=document.getElementById('appBody');
      const bodyH=body?body.getBoundingClientRect().height:null;
      const layoutMode=vw<=768?'compact(≤768)':'PC(>768)';
      console.log('[TC] ── 设备/视口诊断 ──────────────────────');
      console.log('[TC] window.innerWidth  =',vw,'px');
      console.log('[TC] window.innerHeight =',vh,'px');
      console.log('[TC] document.documentElement.clientHeight (≈100vh) =',cssVh,'px');
      console.log('[TC] 100vh - innerHeight 差值 =',cssVh-vh,'px  (正值=浏览器UI占用)');
      console.log('[TC] devicePixelRatio    =',window.devicePixelRatio);
      console.log('[TC] maxTouchPoints      =',navigator.maxTouchPoints);
      console.log('[TC] appBody 实际高度    =',bodyH,'px');
      console.log('[TC] 布局模式            =',layoutMode);
      console.log('[TC] touch-tablet class  =',document.body.classList.contains('touch-tablet'));
      console.log('[TC] 宾客列表模式        =',(_isTouchDevice||vw<=768)?'compact(mc-tag)':'PC(draggable)');
      console.log('[TC] ────────────────────────────────────────');
    }
  }
});

function tcGetGhost(){
  let g=document.getElementById('tcDragGhost');
  if(!g){
    g=document.createElement('div');g.id='tcDragGhost';
    g.style.cssText='position:fixed;z-index:10000;pointer-events:none;display:none;'+
      'background:#2FBB7A;color:#fff;padding:6px 16px;border-radius:22px;font-size:13px;'+
      'font-weight:600;box-shadow:0 4px 20px rgba(47,187,122,.55);white-space:nowrap;'+
      'user-select:none;letter-spacing:.3px';
    document.body.appendChild(g);
  }
  return g;
}

// 激活拖拽（touchmove 超阈值后调用）
function tcEngageDrag(slotKey,cx,cy){
  if(_tcDrag)return; // 防重复
  const[gs,ss]=slotKey.split(':');
  const g=guests.find(g=>g.id===parseInt(gs));
  const sl=(g?.slots||[]).find(sl=>sl.subId===parseInt(ss));
  const ghost=tcGetGhost();
  const label=g?slotDN(g,sl):'…';
  ghost.textContent='✦ '+label;
  ghost.style.left=(cx+16)+'px';ghost.style.top=(cy-32)+'px';
  ghost.style.display='block';
  _tcDrag={slotKey,ghost,ghostShown:true};
  mobileSelectGuest(parseInt(gs));
  _tcLog('🟢 激活拖拽 slotKey='+slotKey+' "'+label+'" at('+Math.round(cx)+','+Math.round(cy)+')');
}

// 查找落点目标（ghost 隐藏后 elementFromPoint 再恢复）
function tcFindTarget(cx,cy){
  const ghost=_tcDrag?.ghost;
  if(ghost)ghost.style.display='none';
  const el=document.elementFromPoint(cx,cy);
  if(ghost)ghost.style.display='block';
  // SVG 元素的 className 是 SVGAnimatedString，需用 baseVal 或 getAttribute
  const elCls=(typeof el.className==='string'?el.className:(el.getAttribute?.('class')||'')).trim();
  const elDesc=el?(el.tagName+(elCls?' .'+elCls.split(/\s+/).join('.'):'')+(el.getAttribute?.('data-tid')?'[tid='+el.getAttribute('data-tid')+']':'')):null;
  _tcLog('  落点 elementFromPoint →',elDesc||'null');
  if(!el)return{type:'none'};
  // 1. 宾客栏 → 取消落座
  if(el.closest('#paneRight')){_tcLog('  → 取消落座区(paneRight)');return{type:'unseat'};}
  // 2. 精确座位（SVG rect：data-tid + data-sidx 同时存在）
  let n=el;
  while(n&&n!==document.documentElement){
    const dTid=n.getAttribute?.('data-tid'),dSidx=n.getAttribute?.('data-sidx');
    if(dTid!=null&&dSidx!=null&&dTid!==''&&dSidx!==''){
      _tcLog('  → 精确座位 tid='+dTid+' seatIdx='+dSidx);
      return{type:'seat',tid:parseInt(dTid),seatIdx:parseInt(dSidx)};
    }
    n=n.parentElement;
  }
  // 3. 桌子 group（data-tid，无 data-sidx）
  const grpEl=el.closest?.('[data-tid]');
  if(grpEl){const v=grpEl.getAttribute('data-tid');if(v!=null){_tcLog('  → 桌子 tid='+v);return{type:'table',tid:parseInt(v)};}}
  // 4. 邻近感应：80px 屏幕半径
  const tg=document.getElementById('tablesGroup');
  if(!tg){_tcLog('  → tablesGroup 不存在');return{type:'none'};}
  try{
    const ctm=tg.getScreenCTM(),ratio=Math.sqrt(ctm.a*ctm.a+ctm.b*ctm.b)||1;
    const thresh=90/ratio;
    const svg=document.getElementById('canvas');
    const pt=svg.createSVGPoint();pt.x=cx;pt.y=cy;
    const sp=pt.matrixTransform(ctm.inverse());
    let best=null,bestD=Infinity,bestR=0;
    tables.forEach(t=>{
      const dx=t.x-sp.x,dy=t.y-sp.y,d=Math.sqrt(dx*dx+dy*dy),r=tSize(t).r;
      if(d<r+thresh&&d<bestD){bestD=d;best=t.id;bestR=Math.round(d-r);}
    });
    if(best!==null){_tcLog('  → 近似桌子 tid='+best+' 超边缘'+bestR+'px');return{type:'table',tid:best};}
  }catch(err){_tcLog('  近似感应异常:',err.message);}
  _tcLog('  → 未命中任何目标（松开位置在画布空白处或列表外）');
  return{type:'none'};
}

// 执行落座/换位/取消
function tcApplyDrop(slotKey,target){
  const[gs,ss]=slotKey.split(':');
  const guest=guests.find(g=>g.id===parseInt(gs));
  const slot=(guest?.slots||[]).find(s=>s.subId===parseInt(ss));
  _tcLog('🎯 落座执行 slotKey='+slotKey+' type='+target.type+(target.tid!=null?' tid='+target.tid:'')+(target.seatIdx!=null?' seatIdx='+target.seatIdx:''));
  if(!guest||!slot){_tcLog('  ❌ guest/slot 查找失败');return;}
  if(target.type==='seat'){
    if(slot.seatedTableId===target.tid&&slot.seatedSeat===target.seatIdx){_tcLog('  ⏭ 已在此座位，跳过');return;}
    dropOnSeat(slotKey,target.tid,target.seatIdx); // 有人则弹换人框
  } else if(target.type==='table'){
    if(slot.seatedTableId===target.tid){_tcLog('  ⏭ 已在此桌，跳过');return;}
    dropOnTable(slotKey,target.tid);
  } else if(target.type==='unseat'){
    if(slot.seatedTableId){pushUndo();slot.seatedTableId=null;slot.seatedSeat=null;render();_tcLog('  ✅ 取消落座成功');}
    else _tcLog('  ⏭ 未落座，无需取消');
  } else {
    _tcLog('  ⚠ 未命中有效目标，未执行任何操作');
  }
}

// ─────────────────────────────────────────────────────────────────
// STEP 1 — touchstart：记录起点（不激活拖拽，不阻止 onclick）
// ─────────────────────────────────────────────────────────────────
document.addEventListener('touchstart',function(e){
  if(_tcDrag)return; // 已在拖拽中
  const touch=e.touches[0];
  // ⠿ 手柄（touch-action:none，阈值 5px）
  const handle=e.target.closest?.('.tc-handle');
  if(handle&&handle.dataset.slotkey){
    e.preventDefault(); // ← 仅手柄处阻止默认（touch-action:none 也保证了这里）
    _tcPress={slotKey:handle.dataset.slotkey,sx:touch.clientX,sy:touch.clientY,thresh:5,fromHandle:true};
    _tcLog('▶ touchstart ⠿手柄 slotKey='+handle.dataset.slotkey+' 阈值5px');
    return;
  }
  // mc-tag 整体（阈值 14px，不阻止默认，允许列表滚动）
  const tag=e.target.closest?.('.mc-tag');
  if(tag&&tag.dataset.slotkey){
    _tcPress={slotKey:tag.dataset.slotkey,sx:touch.clientX,sy:touch.clientY,thresh:14,fromHandle:false};
    _tcLog('▶ touchstart mc-tag slotKey='+tag.dataset.slotkey+' 阈值14px');
    return;
  }
},{passive:false});

// ─────────────────────────────────────────────────────────────────
// STEP 2 — touchmove：超阈值 → 激活拖拽；激活后更新 ghost
// ─────────────────────────────────────────────────────────────────
document.addEventListener('touchmove',function(e){
  const touch=e.touches[0];
  // 待激活状态：检测移动量
  if(_tcPress&&!_tcDrag){
    const dx=touch.clientX-_tcPress.sx,dy=touch.clientY-_tcPress.sy;
    const dist=Math.sqrt(dx*dx+dy*dy);
    _tcLog('  移动中 dist='+dist.toFixed(1)+'px 阈值='+_tcPress.thresh+'px');
    if(dist>=_tcPress.thresh){
      const sk=_tcPress.slotKey;
      _tcPress=null;
      tcEngageDrag(sk,touch.clientX,touch.clientY);
    } else if(!_tcPress.fromHandle){
      return; // 未超阈值且不是手柄 → 不阻止滚动
    }
  }
  if(!_tcDrag)return;
  e.preventDefault(); // 阻止滚动/系统手势

  // 更新 ghost
  const ghost=_tcDrag.ghost;
  ghost.style.left=(touch.clientX+16)+'px';ghost.style.top=(touch.clientY-32)+'px';
  ghost.style.display='block';
  // 宾客栏高亮（暂隐 ghost 以检测底层元素）
  ghost.style.display='none';
  const ht=document.elementFromPoint(touch.clientX,touch.clientY);
  ghost.style.display='block';
  const pr=document.getElementById('paneRight');
  if(pr)pr.style.outline=ht?.closest('#paneRight')?'2px dashed #E87A6A':'';
},{passive:false});

// ─────────────────────────────────────────────────────────────────
// STEP 3 — touchend：若 ghost 显示 → 执行落座；否则 → tap（onclick）
// ─────────────────────────────────────────────────────────────────
document.addEventListener('touchend',function(e){
  _tcPress=null; // 清理待激活（短暂 tap 不触发拖拽，onclick 正常触发）
  if(!_tcDrag)return;
  const touch=e.changedTouches[0];
  const pr=document.getElementById('paneRight');if(pr)pr.style.outline='';
  _tcLog('🖐 touchend at('+Math.round(touch.clientX)+','+Math.round(touch.clientY)+') ghostShown='+_tcDrag.ghostShown);
  const {slotKey,ghost,ghostShown}=_tcDrag;
  _tcDrag=null; // 先清空，防止 tcFindTarget 里的 display:none→block 被后续逻辑干扰
  ghost.style.display='none'; // 确保 ghost 彻底消失
  if(ghostShown){
    const target=tcFindTarget(touch.clientX,touch.clientY);
    ghost.style.display='none'; // tcFindTarget 会恢复 display:block，再次强制隐藏
    tcApplyDrop(slotKey,target);
  } else {
    _tcLog('  ghost 从未显示（移动不足），按 tap 处理');
  }
},{passive:false});

document.addEventListener('touchcancel',function(){
  _tcPress=null;
  if(!_tcDrag)return;
  _tcDrag.ghost.style.display='none';
  const pr=document.getElementById('paneRight');if(pr)pr.style.outline='';
  _tcLog('❌ touchcancel');
  _tcDrag=null;
},{passive:false});

function renderGuestList(filter=''){
  const list=document.getElementById('guestList');
  // 触控设备（手机/平板）始终显示紧凑触控列表；宽屏 PC（非触控）显示分类列表
  if(_isTouchDevice||window.innerWidth<=768){renderGuestListCompact(filter,list);return;}
  // T2: 状态按钮（可循环点击）
  const sBadge=g=>{
    const sc={confirmed:'bg-emerald-100 text-emerald-700 border-emerald-400',pending:'bg-amber-100 text-amber-700 border-amber-400',declined:'bg-red-100 text-red-600 border-red-400'};
    const sl={confirmed:'✓ 已确认',pending:'⏳ 待定',declined:'⚑ 标注'};
    const st=g.status||'confirmed';
    return`<span class="text-[9px] px-1.5 py-0.5 rounded-full border cursor-pointer leading-none whitespace-nowrap ${sc[st]}" onclick="event.stopPropagation();cycleStatus(${g.id})" title="点击切换状态">${sl[st]}</span>`;
  };
  let html='';
  getAllSides().forEach(side=>{
    const color=getSideColor(side),bg=getSideBg(side),label=getSideLabel(side);
    const collapsed=!!sideCollapsed[side];
    const items=guests.filter(g=>g.side===side&&(!filter||g.name.includes(filter)||(g.name2||'').includes(filter)||(g.slots||[]).some(s=>(slotDN(g,s)||'').includes(filter))));
    const tp=items.reduce((s,g)=>s+gCount(g),0),sp=items.reduce((s,g)=>s+(g.slots||[]).filter(sl=>sl.seatedTableId).length,0);
    const rem=tp-sp; // N2: 余人数
    // 分类区块：展开时 flex:1（占剩余空间），收起时 flex:0 0 auto（仅标题高度）
    const sectionFlex=collapsed?'flex:0 0 auto;order:1':'flex:1 1 0;min-height:0;display:flex;flex-direction:column;order:0';
    html+=`<div style="${sectionFlex};border:1.5px solid ${color}60;border-radius:8px;margin-bottom:4px;overflow:hidden">`;
    html+=`<div class="side-cat-header flex items-center justify-between px-3 py-1.5 cursor-pointer select-none flex-shrink-0" style="background:${bg};border-left:3px solid ${color};padding-right:10px" onclick="toggleSideCollapse('${side}')" ondragover="if(event.dataTransfer.types.includes('guestreorder'))event.preventDefault()" ondrop="guestDropOnSide(event,'${side}')">`;
    html+=`<div class="flex items-center gap-1.5"><div class="w-2 h-2 rounded-full flex-shrink-0" style="background:${color};opacity:0.85"></div><span class="cat-label text-xs font-semibold" style="color:${color}">${label}</span></div>`;
    html+=`<div class="flex items-center gap-2">`;
    html+=`<span class="cat-stat text-[10px]" style="color:${color};opacity:0.85">${items.length}组·${tp}人·坐${sp}·<strong style="opacity:1">余${rem}人</strong></span>`;
    html+=`<span class="side-chevron" style="font-size:9px;color:${color};opacity:0.75;transform:rotate(${collapsed?'0':'90'}deg)">▶</span>`;
    html+=`</div></div>`;
    if(!collapsed){
      html+=`<div class="side-section-body" style="flex:1;overflow-y:auto;background:#fff">`;
      if(!items.length)html+=`<div class="text-[11px] text-muted text-center py-3">暂无宾客</div>`;
      else items.forEach(g=>{
        const slots=g.slots||[],allSat=slots.length>0&&slots.every(s=>s.seatedTableId);
        const bg2=gBg(g),tc=gTc(g),tt=[];
        if(g.type==='couple'||g.type==='couple_child')tt.push(`<span class="tag-chip" style="border-color:#9a7ab8;color:#6a4a98">夫妻</span>`);
        if(g.type==='single_child'||g.type==='couple_child')tt.push(`<span class="tag-chip" style="border-color:#e8a44a;color:#c87d20">×${g.children||1}孩</span>`);
        if(g.type==='elder')tt.push(`<span class="tag-chip" style="border-color:#2FBB7A;color:#239A65">老人</span>`);
        // T3: 拖动排序 drop zone；T7: family-sat
        html+=`<div class="${allSat?'family-sat':''}" ondragover="if(event.dataTransfer.types.includes('guestreorder'))event.preventDefault()" ondrop="guestDropOnRow(event,${g.id})">`;
        // T6: 悬停提示；T3: ⠿ 拖动把手；T2: "::" 标识
        html+=`<div id="guest-family-${g.id}" class="guest-row flex items-center px-2 py-1 gap-1.5 bg-white border-t" style="border-color:${color}25" title="可双击编辑" ondblclick="openGuestModal(${g.id})">`;
        // T3: 拖动把手（⠿）
        html+=`<span class="drag-handle text-muted select-none flex-shrink-0" draggable="true" ondragstart="familyCanvasDragStart(event,${g.id})" ondragend="guestDragEnd()" style="cursor:grab;font-size:12px;padding:0 2px;color:${color}80" title="拖动到桌子落座全家 / 拖动到分类换类">⠿</span>`;
        html+=`<div class="w-3 h-3 rounded-full flex-shrink-0" style="background:${bg2};border:1px solid ${tc}40"></div>`;
        html+=`<span class="text-xs font-medium flex-1 truncate text-ink">${eh(g.name)}${g.name2?' & '+eh(g.name2):''}</span>`;
        // T2: "::" 分隔 + 状态按钮
        html+=`<span class="text-muted text-[10px] select-none flex-shrink-0" style="letter-spacing:2px;color:#ccc"> ∷ </span>`;
        html+=`<div class="flex items-center gap-1 flex-shrink-0">${tt.join('')}${sBadge(g)}</div>`;
        html+=`</div>`;
        // 子席位项
        slots.forEach(s=>{
          const tbl=s.seatedTableId?tables.find(t=>t.id===s.seatedTableId):null,dn=slotDN(g,s);
          const isDrag=!s.seatedTableId;
          html+=`<div class="sub-item flex items-center gap-2 px-3 py-0.5 bg-white ${s.seatedTableId?'seated-sub':''}" style="border-top:1px solid ${color}15;cursor:grab" draggable="true" ondragstart="startDrag(event,${g.id},${s.subId})" ondragend="endDrag()" title="${s.seatedTableId?'拖动换座/调桌':'拖动到座位入座'}">`;
          html+=`<div class="w-3 flex-shrink-0"></div>`;
          html+=`<span class="sub-chip inline-flex items-center gap-1 text-[11px] px-2 py-0.5 rounded-full flex-shrink-0" style="background:${bg2};color:${tc}">${s.subType==='child'?'👶':s.subType==='elder'?'🧓':'👤'} ${eh(dn)}</span>`;
          html+=`<span class="sub-loc ml-auto text-[9px] whitespace-nowrap" style="color:${s.seatedTableId?getSideColor(g.side):'#bbb'}">${s.seatedTableId?(tbl?tbl.label:'已落座'):(isDrag?'拖 →':'')}</span>`;
          html+=`</div>`;
        });
        html+=`</div>`;
      });
      html+=`</div>`; // side-section-body
    }
    html+=`</div>`; // section
  });
  html+=`<div id="addCatDropZone" style="flex:0 0 auto;order:2;padding:4px 6px;border-radius:8px;transition:background .15s" ondragover="addCatDragOver(event)" ondragleave="addCatDragLeave()" ondrop="addCatDrop(event)"><button onclick="addCustomCategory()" class="btn btn-ghost btn-sm w-full text-[10px]" id="addCatBtn" style="border-style:dashed;color:#5A7A6A">＋ 添加分类</button></div>`;
  if(!guests.length)html='<div class="text-xs text-muted text-center pt-6" style="flex:0 0 auto">暂无宾客，点击 ＋ 添加</div>';
  list.innerHTML=html;
}

// ══════════════════════════════════════════════════
// STATS
// ══════════════════════════════════════════════════
function updateStats(){
  let tot=0,sat=0,unsat=0,child=0,elder=0,confirmed=0,pending=0,declined=0;
  const ts=tables.reduce((a,t)=>a+t.seats,0);
  guests.forEach(g=>{
    const slots=g.slots||[];
    const st=g.status||'confirmed';
    slots.forEach(s=>{
      tot++;
      if(s.subType==='child')child++;
      if(s.subType==='elder')elder++;
      if(!!s.seatedTableId)sat++;else unsat++;
      if(st==='confirmed')confirmed++;else if(st==='pending')pending++;else declined++;
    });
  });
  const c=(dot,lbl,val,sub='')=>`<span class="flex items-center gap-1 whitespace-nowrap">${dot?`<span class="w-1.5 h-1.5 rounded-full inline-block" style="background:${dot}"></span>`:''}<span>${lbl}</span><strong class="text-ink">${val}</strong>${sub?`<span>${sub}</span>`:''}</span>`;
  document.getElementById('statsFooter').innerHTML=
    `<div class="flex flex-wrap gap-x-3 gap-y-0.5">${c('','🍽',tables.length,'桌')} <span class="opacity-40">|</span> ${c('','总座位',ts)} ${c('','已坐',sat)} <span class="opacity-40">|</span> ${c('','未落座',`<span class="font-bold" style="color:#F5A623">${unsat}</span>`)}</div>`+
    `<div class="flex flex-wrap gap-x-3 gap-y-0.5 border-t border-line pt-1">${c('#2FBB7A','✓ 已确认',confirmed,'人')} ${c('#F5A623','⏳ 待定',pending,'人')} ${c('#E53935','⚑ 标注',declined,'人')} <span class="opacity-40">|</span> ${c('','👶',child)} ${c('','🧓',elder)}</div>`;
}

// ══════════════════════════════════════════════════
// GUEST MODAL — color picker from PAL_ALL + PAL_SPL
// ══════════════════════════════════════════════════
function buildSwatches(sel){
  const wMain=document.getElementById('colorSwatches');
  const wSpl=document.getElementById('specialSwatches');
  wMain.innerHTML=PAL_ALL.map(c=>`<div class="swatch ${c.bg===sel?'sel':''}" style="background:${c.bg}" onclick="selColor('${c.bg}')"><span class="swtip">${c.name}</span></div>`).join('');
  wSpl.innerHTML='<span class="text-[9px] text-muted mr-1 self-center">特殊：</span>'+PAL_SPL.map(c=>`<div class="swatch ${c.bg===sel?'sel':''}" style="background:${c.bg}" onclick="selColor('${c.bg}')"><span class="swtip">${c.name}</span></div>`).join('');
}
function selColor(bg){document.getElementById('g-color').value=bg;document.querySelectorAll('.swatch').forEach(s=>s.classList.toggle('sel',s.style.background===bg));}
function randColor(){const c=PAL_ALL[Math.floor(Math.random()*PAL_ALL.length)];selColor(c.bg);buildSwatches(c.bg);}
function selStatus(s){document.getElementById('g-status').value=s;document.querySelectorAll('.status-pill').forEach(el=>{el.classList.remove('sp-confirmed','sp-pending','sp-declined');if(el.dataset.s===s)el.classList.add('sp-'+s);});}

function openGuestModal(gid){
  editingGuestId=gid;
  updateGuestSideSelect(); // T4: 同步自定义分类到下拉
  document.getElementById('gModalTitle').textContent=gid?'编辑宾客':'添加宾客';
  const del=document.getElementById('delGuestBtn');
  if(gid){
    const g=guests.find(g=>g.id===gid);
    document.getElementById('g-name').value=g.name;document.getElementById('g-name2').value=g.name2||'';
    const spChk=document.getElementById('g-showPartner');if(spChk)spChk.checked=(g.showPartner!==false);
    document.getElementById('g-side').value=g.side;document.getElementById('g-type').value=g.type;
    document.getElementById('g-children').value=g.children||1;document.getElementById('g-childnames').value=(g.childNames||[]).join(',');
    document.getElementById('g-note').value=g.note||'';document.getElementById('g-color').value=g.color||'';
    selStatus(g.status||'confirmed');buildSwatches(g.color||'');del.style.display='inline-flex';
  } else {
    document.getElementById('g-name').value='';document.getElementById('g-name2').value='';
    document.getElementById('g-side').value='groom';document.getElementById('g-type').value='single';
    document.getElementById('g-children').value=1;document.getElementById('g-childnames').value='';
    document.getElementById('g-note').value='';
    const nc=PAL_BG[guests.length%PAL_BG.length];
    document.getElementById('g-color').value=nc;selStatus('confirmed');buildSwatches(nc);del.style.display='none';
  }
  updateGTF();document.getElementById('guestModal').classList.add('open');
}
function updateGTF(){const t=document.getElementById('g-type').value;document.getElementById('g-n2row').classList.toggle('hidden',t!=='couple'&&t!=='couple_child');document.getElementById('g-crow').classList.toggle('hidden',t!=='couple_child'&&t!=='single_child');document.getElementById('g-cnrow').classList.toggle('hidden',t!=='couple_child'&&t!=='single_child');}
function closeGuestModal(){document.getElementById('guestModal').classList.remove('open');_pendingQA=false;}
function saveGuest(){
  const name=document.getElementById('g-name').value.trim();if(!name){alert('请输入姓名');return;}
  const name2=document.getElementById('g-name2').value.trim(),side=document.getElementById('g-side').value,type=document.getElementById('g-type').value;
  const children=parseInt(document.getElementById('g-children').value)||1;
  const childNames=document.getElementById('g-childnames').value.split(',').map(s=>s.trim()).filter(Boolean);
  const status=document.getElementById('g-status').value||'confirmed',note=document.getElementById('g-note').value.trim();
  const color=document.getElementById('g-color').value||PAL_BG[guestCounter%PAL_BG.length];
  const showPartner=document.getElementById('g-showPartner')?.checked!==false;
  pushUndo();
  if(editingGuestId){
    const g=guests.find(g=>g.id===editingGuestId),old=g.slots||[];
    Object.assign(g,{name,name2,side,type,children,childNames,status,note,color,showPartner});rebuildSlots(g,old);
  } else {
    guestCounter++;
    const g={id:guestCounter,name,name2,side,type,children,childNames,status,note,color,showPartner,slots:[]};
    g.slots=buildSlots(g);guests.push(g);
    if(_pendingQA&&_qaTableId!==null&&_qaSeatIdx!==null){
      const first=g.slots.find(s=>!s.seatedTableId);
      if(first&&!findSlot(_qaTableId,_qaSeatIdx)){first.seatedTableId=_qaTableId;first.seatedSeat=_qaSeatIdx;}
      _qaTableId=null;_qaSeatIdx=null;_pendingQA=false;
    }
  }
  closeGuestModal();render();
}
function deleteGuest(){if(!editingGuestId||!confirm('确认删除？'))return;pushUndo();guests=guests.filter(g=>g.id!==editingGuestId);closeGuestModal();render();}

// ══════════════════════════════════════════════════
// REFRESH CHECK
// ══════════════════════════════════════════════════
function refreshCheck(){
  const issues=[];
  guests.forEach(g=>(g.slots||[]).forEach(s=>{if(!s.seatedTableId)return;const t=tables.find(t=>t.id===s.seatedTableId);if(!t)issues.push({type:'nt',name:slotDN(g,s)});else if(s.seatedSeat>=t.seats)issues.push({type:'ov',name:slotDN(g,s),tbl:t.label});}));
  tables.forEach(t=>{for(let i=0;i<t.seats;i++){const ns=[];guests.forEach(g=>(g.slots||[]).forEach(s=>{if(s.seatedTableId===t.id&&s.seatedSeat===i)ns.push(slotDN(g,s));}));if(ns.length>1)issues.push({type:'cf',tbl:t.label,seat:i+1,names:ns});}});
  const uns=[];guests.forEach(g=>(g.slots||[]).forEach(s=>{if(!s.seatedTableId)uns.push({name:slotDN(g,s),side:g.side,g});}));
  let html='';
  if(!issues.length&&!uns.length)html='<div class="text-center py-6 text-primary font-semibold">✅ 所有座位数据正常</div>';
  if(issues.length){html+=`<div class="font-semibold text-red-600 mb-1.5">⚠️ ${issues.length} 个数据问题</div>`;issues.forEach(iss=>{if(iss.type==='nt')html+=`<div class="text-xs text-red-600 bg-red-50 rounded px-2 py-1 mb-1">「${eh(iss.name)}」关联已删除的桌子</div>`;if(iss.type==='ov')html+=`<div class="text-xs text-red-600 bg-red-50 rounded px-2 py-1 mb-1">「${eh(iss.name)}」超出 ${eh(iss.tbl)} 座位范围</div>`;if(iss.type==='cf')html+=`<div class="text-xs text-red-600 bg-red-50 rounded px-2 py-1 mb-1">${eh(iss.tbl)} 第${iss.seat}座冲突：${iss.names.map(eh).join('、')}</div>`;});}
  if(uns.length){html+=`<div class="font-semibold text-amber-600 mt-2 mb-1.5">⏳ ${uns.length} 人未落座</div>`;const byG={};uns.forEach(u=>{(byG[u.side]||(byG[u.side]=[])).push(u);});['groom','bride','shared'].forEach(side=>{if(!byG[side])return;html+=`<div class="flex flex-wrap gap-1 mb-1.5 items-center"><span class="text-xs font-medium" style="color:${COL[side]}">${LABEL[side]}：</span>${byG[side].map(u=>`<span class="text-[10px] px-2 py-0.5 rounded-full" style="background:${gBg(u.g)};color:${gTc(u.g)}">${eh(u.name)}</span>`).join('')}</div>`;});}
  document.getElementById('refreshContent').innerHTML=html;
  document.getElementById('refreshModal').classList.add('open');
}
function closeRefreshModal(){document.getElementById('refreshModal').classList.remove('open');}
function clearOrphanedSeats(){pushUndo();guests.forEach(g=>(g.slots||[]).forEach(s=>{if(!s.seatedTableId)return;const t=tables.find(t=>t.id===s.seatedTableId);if(!t||s.seatedSeat>=t.seats){s.seatedTableId=null;s.seatedSeat=null;}}));closeRefreshModal();render();setTimeout(refreshCheck,80);}

// ══════════════════════════════════════════════════
// CSV / JSON / PDF
// ══════════════════════════════════════════════════
function triggerCsvImport(){document.getElementById('csvInput').click();}
function triggerListImport(){document.getElementById('listInput').click();}

// 简易名单导入：每行一条，逗号后为配偶姓名，首行自动识别标题行
function importList(input){
  const file=input.files[0];if(!file)return;
  const r=new FileReader();
  r.onload=ev=>{
    const raw=ev.target.result;
    const lines=raw.split(/\r?\n/).map(l=>l.trim()).filter(Boolean);
    if(!lines.length)return;
    // 首行标题检测：若含常见表头关键字则跳过
    let start=0;
    if(/^(姓名|名字|宾客|人名|name|guest)/i.test(lines[0]))start=1;
    pushUndo();
    let added=0;
    for(let i=start;i<lines.length;i++){
      const parts=lines[i].split(',').map(s=>s.trim());
      const name=parts[0];if(!name)continue;
      const name2=parts[1]||'';
      const type=name2?'couple':'single';
      const color=PAL_BG[guestCounter%PAL_BG.length];
      guestCounter++;
      const g={id:guestCounter,name,name2,side:'groom',type,children:1,childNames:[],status:'confirmed',note:'',color,showPartner:true,slots:[]};
      g.slots=buildSlots(g);guests.push(g);added++;
    }
    render();
    if(added)console.log('[List] 导入',added,'条，从第'+(start+1)+'行开始');
  };
  r.readAsText(file,'UTF-8');input.value='';
}
function importCsv(input){
  const file=input.files[0];if(!file)return;
  const r=new FileReader();
  r.onload=e=>{
    const lines=e.target.result.split('\n').filter(l=>l.trim());if(!lines.length)return;
    const hdr=lines[0].split(',').map(h=>h.trim().replace(/"/g,'').toLowerCase());
    const idx=keys=>hdr.findIndex(h=>keys.some(k=>h.includes(k)));
    const ni=idx(['姓名','name']),si=idx(['所属','side']),ti=idx(['人员','type']),ci=idx(['小孩','child']),noi=idx(['备注','note']),sti=idx(['状态','status']),n2i=idx(['配偶','name2']);
    pushUndo();
    for(let i=1;i<lines.length;i++){
      const cols=lines[i].split(',').map(c=>c.trim().replace(/"/g,''));if(!cols[ni])continue;
      const name=cols[ni],sr=cols[si]||'',side=sr.includes('女')?'bride':sr.includes('共')?'shared':'groom';
      const tr=(cols[ti]||'').toLowerCase();let type='single';
      if(tr.includes('夫妻')&&tr.includes('小孩'))type='couple_child';else if(tr.includes('夫妻'))type='couple';else if(tr.includes('小孩'))type='single_child';else if(tr.includes('老人'))type='elder';
      const children=parseInt(cols[ci])||1,note=cols[noi]||'',sraw=(cols[sti]||'').toLowerCase(),status=sraw.includes('确')?'confirmed':sraw.includes('拒')?'declined':'confirmed';
      const name2=n2i>=0?(cols[n2i]||''):'',color=PAL_BG[guestCounter%PAL_BG.length];
      guestCounter++;const g={id:guestCounter,name,name2,side,type,children,childNames:[],status,note,color,slots:[]};g.slots=buildSlots(g);guests.push(g);
    }
    render();
  };
  r.readAsText(file,'UTF-8');input.value='';
}
function exportCsv(){
  const tm={single:'单人',elder:'老人',couple:'夫妻',couple_child:'夫妻带小孩',single_child:'1人带小孩'},sm={groom:'男方',bride:'女方',shared:'共同'};
  const rows=[['姓名','配偶','所属方','人员类型','小孩人数','邀请状态','总人数','备注','桌位']];
  guests.forEach(g=>{const fs=g.slots?.find(s=>s.seatedTableId),tbl=fs?tables.find(t=>t.id===fs.seatedTableId):null;rows.push([g.name,g.name2||'',sm[g.side]||'',tm[g.type]||g.type,(g.type==='couple_child'||g.type==='single_child')?g.children:'',STATUS_LABEL[g.status]||'已确认',gCount(g),g.note||'',tbl?tbl.label:'未落座']);});
  dl('wedding-guests.csv','\uFEFF'+rows.map(r=>r.join(',')).join('\n'),'text/csv');
}
// N3: 导出CSV样板（已含一组示例数据）
function exportCsvTemplate(){
  const rows=[
    ['姓名','配偶','所属方','人员类型','小孩人数','邀请状态','总人数','备注','桌位'],
    ['张三','李梅','男方','夫妻','0','已确认','2','朋友·无辣','—'],
    ['王老爷','','男方','老人','0','已确认','1','爷爷·需轮椅','—'],
    ['赵小花','周亮','女方','夫妻带小孩','1','已确认','3','同学·小孩约8岁','—'],
    ['刘芳','','共同','单人','0','待定','1','','—'],
  ];
  dl('guests-template.csv','\uFEFF'+rows.map(r=>r.map(c=>`"${c}"`).join(',')).join('\n'),'text/csv');
}

function getState(){
  return{
    tables,guests,tableCounter,guestCounter,roomW,roomH,projectName,
    customCategories,
    bgState:{...bgState,src:null}, // don't serialize large base64 in default — use separate if needed
    version:8,
    savedAt:new Date().toISOString(),
    colorConfig:COLOR_CFG
  };
}
function openHelpAndMark(){
  document.getElementById('helpModal').classList.add('open');
  const q=document.getElementById('logoQ');
  if(q&&q.style.display!=='none'){q.style.display='none';const lb=document.getElementById('helpLogoBtn');if(lb)lb.title='使用说明 (♦)';}
}
function openHelp(){
  document.getElementById('helpModal').classList.add('open');
  const btn=document.getElementById('helpBtn');
  if(btn&&btn.textContent.trim()==='?'){
    btn.textContent='♦';
    btn.style.border='1.5px solid #DDE6DC';
  }
}
function editProjectName(){
  const cur=projectName||'婚宴座位图';
  const v=prompt('项目名称：',cur);
  if(v!==null&&v.trim()){
    projectName=v.trim();
    const el=document.getElementById('projectTitle');
    if(el)el.textContent=projectName;
  }
}
function saveSnapshot(){
  const name=prompt('保存名称（可留空）：',projectName??'婚宴座位')??'';
  const now=new Date();
  const ts=`${now.getFullYear()}${String(now.getMonth()+1).padStart(2,'0')}${String(now.getDate()).padStart(2,'0')}-${String(now.getHours()).padStart(2,'0')}${String(now.getMinutes()).padStart(2,'0')}`;
  const safeName=name.trim().replace(/[\\/:*?"<>|]/g,'').slice(0,40);
  const filename=`wedding-seating-${ts}${safeName?'-'+safeName:''}.json`;
  const state={...getState(),bgState:{...bgState},savedAt:new Date().toISOString(),saveName:name};
  const json=JSON.stringify(state,null,2);
  // Download locally
  dl(filename,json,'application/json');
  // Also push to server
  fetch(authUrl('save.php'),{method:'POST',headers:{'Content-Type':'application/json'},body:json})
    .then(r=>r.ok?r.json():Promise.reject('HTTP '+r.status))
    .then(j=>console.log('Server backup saved:',j.filename))
    .catch(e=>console.warn('Server save failed (file still downloaded):',e));
}
function triggerJsonLoad(){document.getElementById('jsonInput').click();}
function loadJsonFile(input){
  const f=input.files[0];if(!f)return;
  const r=new FileReader();
  r.onload=e=>{
    try{
      pushUndo();const s=JSON.parse(e.target.result);applyState(s);
    }catch(err){alert('JSON 格式错误：'+err.message);}
  };
  r.readAsText(f);input.value='';
}
function applyState(s){
  tables=s.tables||[];guests=s.guests||[];tableCounter=s.tableCounter||0;guestCounter=s.guestCounter||0;
  customCategories=s.customCategories||[];
  if(s.projectName){projectName=s.projectName;const el=document.getElementById('projectTitle');if(el)el.textContent=projectName;}
  roomW=s.roomW||20;roomH=s.roomH||20;
  if(s.bgState){bgState={...bgState,...s.bgState};if(bgState.src){document.getElementById('bgImage').setAttribute('href',bgState.src);}}
  document.getElementById('roomWInput').value=roomW;document.getElementById('roomHInput').value=roomH;
  guests.forEach(g=>{if(!g.color)g.color=PAL_BG[g.id%PAL_BG.length];if(!g.slots?.length)g.slots=buildSlots(g);if(!g.size)g.size=DEFAULT_SIZE;});
  tables.forEach(t=>{if(!t.size)t.size=DEFAULT_SIZE;});
  initSideCollapsed();
  selectedTableId=null;updateRoomSize();render();setTimeout(zoomReset,80);
}

// ══════════════════════════════════════════════════
// SERVER — save.php / list.php / load.php → /data/ directory
// ══════════════════════════════════════════════════
// ── Dropdown toggle ──
function toggleDropdown(id){
  const el=document.getElementById(id);
  const open=el.style.display==='none';
  // close all others
  document.querySelectorAll('.dropdown-menu').forEach(m=>m.style.display='none');
  if(open){
    el.style.display='block';
    if(id==='loadServerDrop')populateLoadDrop();
  }
}
document.addEventListener('click',e=>{
  if(!e.target.closest('.relative'))document.querySelectorAll('.dropdown-menu').forEach(m=>m.style.display='none');
});

// ── Load server dropdown ──
async function populateLoadDrop(){
  const el=document.getElementById('loadServerDrop');
  const dirLabel=AUTH_CODE?(VIEW_ONLY&&VIEW_PREFIX?`data/${VIEW_PREFIX}…/`:`data/${AUTH_CODE}/`):'—';
  // 目录标签（顶部醒目）
  el.innerHTML=`<div class="dm-item dm-dir-label" style="padding:5px 10px">
    <span style="font-size:10px">📂 <code style="font-size:10px">${dirLabel}</code></span>
  </div>`;
  if(!AUTH_CODE){
    el.innerHTML+='<div class="dm-item"><span class="dm-sub" style="color:#e74c3c">未登录场次，无法访问服务器</span></div>'; return;
  }
  el.innerHTML+='<div class="dm-item" style="opacity:.6"><span class="dm-sub">加载中…</span></div>';
  try{
    // 并行拉取：存档位 + 最近备份
    const [rSlots,rBackups]=await Promise.all([
      fetch(authUrl('list.php?mode=slots')).then(r=>r.json()),
      fetch(authUrl('list.php?n=3')).then(r=>r.json()),
    ]);
    el.innerHTML=`<div class="dm-item dm-dir-label" style="padding:5px 10px">
      <span style="font-size:10px">📂 <code style="font-size:10px">${dirLabel}</code></span>
    </div>`;
    // ── 存档位 ──
    el.innerHTML+=`<div class="dm-item"><span class="dm-name" style="font-size:10px;color:#5A7A6A">存档位（手动）</span></div>`;
    if(rSlots.ok){
      (rSlots.slots||[]).forEach(s=>{
        const filled=!s.empty;
        const d=filled?new Date(s.savedAt):null;
        const ds=d?`${d.getMonth()+1}/${d.getDate()} ${d.getHours()}:${String(d.getMinutes()).padStart(2,'0')}`:'空';
        const fname=`wedding-seating-slot-${s.slot}.json`;
        el.innerHTML+=`<div class="dm-slot ${filled?'':'empty'}" style="gap:6px">
          <div style="flex:1;min-width:0;${filled?'cursor:pointer':''}" onclick="${filled?`loadServerBackup('${fname}');document.getElementById('loadServerDrop').style.display='none'`:'void 0'}">
            <div class="dm-slot-name">位置 ${s.slot}${filled&&s.projectName?' · '+s.projectName:''}</div>
            <div class="dm-slot-sub">${ds}${filled?' · '+s.tables+'桌 '+s.guests+'组':''}</div>
          </div>
          ${filled?`<button onclick="event.stopPropagation();deleteServerBackup('${fname}').then(()=>populateLoadDrop())" style="font-size:10px;color:#aaa;border:none;background:none;cursor:pointer;padding:0 4px;flex-shrink:0" title="清除此存档位">✕</button>`:''}
          <span style="font-size:10px;color:${filled?'#2FBB7A':'#aaa'};flex-shrink:0">${filled?'载入 →':'空'}</span>
        </div>`;
      });
    }else{
      el.innerHTML+=`<div class="dm-item"><span class="dm-sub" style="color:#e74c3c">${rSlots.error||'获取失败'}</span></div>`;
    }
    // ── 最近备份 ──
    el.innerHTML+=`<div class="dm-sep"></div><div class="dm-item"><span class="dm-name" style="font-size:10px;color:#5A7A6A">最近备份（自动）</span></div>`;
    if(!rBackups.ok){
      const msg=rBackups.error==='code not registered'?'场次未在服务器注册':'服务器拒绝访问';
      el.innerHTML+=`<div class="dm-item"><span class="dm-sub" style="color:#e74c3c">${msg}</span></div>`;
    }else if(!rBackups.backups?.length){
      el.innerHTML+='<div class="dm-item"><span class="dm-sub">暂无自动备份</span></div>';
    }else{
      rBackups.backups.forEach(item=>{
        const d=new Date(typeof item.savedAt==='number'?item.savedAt*1000:item.savedAt);
        const ds=`${d.getMonth()+1}/${d.getDate()} ${d.getHours()}:${String(d.getMinutes()).padStart(2,'0')}`;
        const title=item.filename.replace('wedding-seating-backup-','').replace('.json','');
        el.innerHTML+=`<div class="dm-slot" style="gap:6px">
          <div style="flex:1;min-width:0;cursor:pointer" onclick="loadServerBackup('${item.filename}');document.getElementById('loadServerDrop').style.display='none'">
            <div class="dm-slot-name">${ds}</div>
            <div class="dm-slot-sub">${item.tables}桌 · ${item.guests}组</div>
          </div>
          <button onclick="event.stopPropagation();deleteServerBackup('${item.filename}')" style="font-size:10px;color:#aaa;border:none;background:none;cursor:pointer;padding:0 4px;flex-shrink:0" title="删除此备份">🗑</button>
          <span style="font-size:10px;color:#2FBB7A;flex-shrink:0">载入 →</span>
        </div>`;
      });
    }
  }catch(e){
    el.innerHTML+=`<div class="dm-item"><span class="dm-sub" style="color:#e74c3c">连接失败: ${e.message}</span></div>`;
  }
}
async function deleteServerBackup(filename){
  if(!confirm(`确认删除服务器备份？\n${filename}`))return;
  try{
    const res=await fetch(authUrl(`delete.php?filename=${encodeURIComponent(filename)}`));
    const j=await res.json();
    if(j.ok){populateLoadDrop();}
    else alert('删除失败: '+(j.error||'unknown'));
  }catch(e){alert('请求失败: '+e.message);}
}

// ── 服务器存档位（5个固定位，存于 data/{auth}/wedding-seating-slot-N.json）──
async function populateSaveSlots(){
  const el=document.getElementById('saveServerDrop');
  const dirLabel=AUTH_CODE?(VIEW_ONLY&&VIEW_PREFIX?`data/${VIEW_PREFIX}…/`:`data/${AUTH_CODE}/`):'—';
  el.innerHTML=`
    <div class="dm-item dm-dir-label" style="padding:5px 10px">
      <span style="font-size:10px">📂 <code style="font-size:10px">${dirLabel}</code></span>
    </div>
    <div class="dm-item" style="background:#fff8ec;border-bottom:1px solid #ffe0a0;padding:5px 10px">
      <span style="font-size:9.5px;color:#b45309">⚠ 勿上传隐私数据，建议用「⬇ 下载JSON」</span>
    </div>`;
  if(!AUTH_CODE){
    el.innerHTML+='<div class="dm-item"><span class="dm-sub" style="color:#e74c3c">未登录场次，无法存档</span></div>'; return;
  }
  el.innerHTML+='<div class="dm-item" style="opacity:.6;pointer-events:none"><span class="dm-sub">加载中…</span></div>';
  try{
    const res=await fetch(authUrl('list.php?mode=slots'));
    const j=await res.json();
    if(!j.ok) throw new Error(j.error||'error');
    el.innerHTML=el.innerHTML.replace(/<div class="dm-item"[^>]*>.*?加载中.*?<\/div>/,'');
    el.innerHTML+='<div class="dm-sep"></div>';
    (j.slots||[]).forEach(s=>{
      const filled=!s.empty;
      const d=filled?new Date(s.savedAt):null;
      const ds=d?`${d.getMonth()+1}/${d.getDate()} ${d.getHours()}:${String(d.getMinutes()).padStart(2,'0')}`:'空';
      const name=s.projectName||'';
      el.innerHTML+=`<div class="dm-slot ${filled?'':'empty'}" style="gap:6px">
        <div style="flex:1;min-width:0" onclick="saveToServerSlot(${s.slot})">
          <div class="dm-slot-name">位置 ${s.slot}${filled&&name?' · '+name:''}</div>
          <div class="dm-slot-sub">${ds}${filled?' · '+s.tables+'桌 '+s.guests+'组':''}</div>
        </div>
        ${filled?`<button onclick="event.stopPropagation();clearServerSlot(${s.slot})" style="font-size:10px;color:#aaa;border:none;background:none;cursor:pointer;padding:0 4px;flex-shrink:0" title="清除此存档位">✕</button>`:''}
        <span style="font-size:10px;color:#2FBB7A;flex-shrink:0">${filled?'覆盖':'保存'} →</span>
      </div>`;
    });
  }catch(e){
    el.innerHTML+=`<div class="dm-item"><span class="dm-sub" style="color:#e74c3c">连接失败: ${e.message}</span></div>`;
  }
}
async function saveToServerSlot(n){
  if(!AUTH_CODE){alert('未登录场次，无法存档');return;}
  const state={...getState(),bgState:{...bgState,src:null},projectName,savedAt:new Date().toISOString()};
  try{
    const res=await fetch(authUrl(`save.php?slot=${n}`),{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(state)});
    const j=await res.json();
    if(!j.ok&&j.error) throw new Error(j.error);
    document.getElementById('saveServerDrop').style.display='none';
    alert(`✓ 已保存到服务器存档位 ${n}`);
  }catch(e){alert('保存失败: '+e.message);}
}
async function clearServerSlot(n){
  if(!confirm(`清除服务器存档位 ${n}？`))return;
  try{
    const res=await fetch(authUrl(`delete.php?filename=${encodeURIComponent('wedding-seating-slot-'+n+'.json')}`));
    const j=await res.json();
    if(!j.ok) throw new Error(j.error||'error');
    populateSaveSlots();
  }catch(e){alert('清除失败: '+e.message);}
}
async function saveJsonServerDirect(){
  // 静默推送一份时间戳备份（不写存档位）
  const state={...getState(),bgState:{...bgState,src:null},projectName};
  try{
    const res=await fetch(authUrl('save.php'),{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(state)});
    if(res.ok){const j=await res.json();return j;}
  }catch(e){console.warn('Server save:',e);}
}
async function saveJsonServer(){await saveJsonServerDirect();}

// openRecentsModal removed (replaced by dropdown)
function closeRecentsModal(){document.getElementById('recentsModal').classList.remove('open');}

async function loadServerBackup(filename){
  if(!confirm(`确认载入备份？\n${filename}\n\n当前未保存的状态将丢失。`))return;
  try{
    const res=await fetch(authUrl(`load.php?filename=${encodeURIComponent(filename)}`));
    if(!res.ok) throw new Error('HTTP '+res.status);
    const state=await res.json();
    pushUndo();applyState(state);closeRecentsModal();
  }catch(e){
    alert('载入失败: '+e.message);
  }
}

function exportPdf(){
  // Snapshot current viewport, then fit room to canvas
  const ov={...vp};
  // Set viewport to show exactly the room, no padding
  vp.scale=1; vp.x=0; vp.y=0; applyViewport();
  setTimeout(()=>{
    const rw=roomW*M, rh=roomH*M;
    const clone=canvasEl.cloneNode(true);
    clone.setAttribute('width',rw); clone.setAttribute('height',rh);
    clone.setAttribute('viewBox',`0 0 ${rw} ${rh}`);
    // Remove selBox from clone
    const sb=clone.querySelector('#selBox');if(sb)sb.remove();
    const svgStr=new XMLSerializer().serializeToString(clone);
    vp=ov; applyViewport();

    // Build PDF table rows — use seatOrder, group family as 全家
    const tableRows=tables.map(t=>{
      const baseOrder=Array.from({length:t.seats},(_,i)=>i);
      const ordArr=t.seatOrder?.length===t.seats?t.seatOrder:baseOrder;
      // Collect names in order; group same-family slots
      const seen=new Set();
      const names=[];
      ordArr.forEach(si=>{
        const f=findSlot(t.id,si);if(!f)return;
        const {g,s}=f;
        if(seen.has(g.id)){return;} // already added this family
        const cnt=(g.slots||[]).filter(sl=>sl.seatedTableId===t.id).length;
        if(cnt>1&&(g.type==='couple'||g.type==='couple_child'||g.type==='single_child')){
          names.push(g.name.slice(0,6)+'全家');seen.add(g.id);
        } else {
          names.push(slotDN(g,s).slice(0,6));seen.add(g.id);
        }
      });
      const lbl=t.label.slice(0,6).padEnd(0);
      const cnt=ordArr.filter(si=>findSlot(t.id,si)).length;
      return`<tr><td style="font-weight:600">${lbl}</td><td style="text-align:center">${cnt}</td><td>${names.join('、')||'—'}</td></tr>`;
    }).join('');

    const isDark=document.body.classList.contains('dark-mode');
    const pw=window.open('','_blank');
    pw.document.write(`<!DOCTYPE html>
<html><head><title>Seat Card</title><meta charset="UTF-8">
<link href="https://fonts.loli.net/css2?family=Noto+Sans+SC:wght@400;600&family=Noto+Serif+SC:wght@600&display=swap" rel="stylesheet">
<style>
*{margin:0;padding:0;box-sizing:border-box}
${isDark?`
html,body{width:297mm;height:210mm;overflow:hidden;background:#1A1815;font-family:'Noto Sans SC',sans-serif;margin:0;padding:0;color:#E0D8CC}
.page{width:297mm;height:210mm;display:grid;grid-template-rows:1fr;grid-template-columns:1fr 68mm;gap:0;padding:0}
.svg-wrap{grid-column:1;grid-row:1;overflow:hidden;background:#1A1815}
.svg-wrap svg{width:100%;height:100%;display:block}
.table-wrap{grid-column:2;grid-row:1;overflow:hidden;border-left:1px solid #3A3530;padding:10mm 2mm 10mm 3mm}
.tbl-title{font-family:'Noto Serif SC',serif;font-size:9pt;color:#3DD68C;letter-spacing:2px;margin-bottom:2mm;padding-bottom:1mm;border-bottom:1px solid #3A3530}
.tbl-sub{font-size:6pt;color:#908070;margin-bottom:2mm}
table{width:100%;border-collapse:collapse;font-size:6pt}
th{background:#2FBB7A;color:#0A1A10;padding:1.5pt 2pt;text-align:left}
td{padding:1.5pt 2pt;border-bottom:1px solid #3A3530;vertical-align:top;line-height:1.4;color:#D0C8BC}
tr:nth-child(even) td{background:#222018}
`:`
html,body{width:297mm;height:210mm;overflow:hidden;background:#FBF7E6;font-family:'Noto Sans SC',sans-serif;margin:0;padding:0}
.page{width:297mm;height:210mm;display:grid;grid-template-rows:1fr;grid-template-columns:1fr 68mm;gap:0;padding:0}
.svg-wrap{grid-column:1;grid-row:1;overflow:hidden;background:#FBF7E6}
.svg-wrap svg{width:100%;height:100%;display:block}
.table-wrap{grid-column:2;grid-row:1;overflow:hidden;border-left:1px solid #DDE6DC;padding:10mm 2mm 10mm 3mm}
.tbl-title{font-family:'Noto Serif SC',serif;font-size:9pt;color:#2FBB7A;letter-spacing:2px;margin-bottom:2mm;padding-bottom:1mm;border-bottom:1px solid #DDE6DC}
.tbl-sub{font-size:6pt;color:#5A7A6A;margin-bottom:2mm}
table{width:100%;border-collapse:collapse;font-size:6pt}
th{background:#1F3B2F;color:#fff;padding:1.5pt 2pt;text-align:left}
td{padding:1.5pt 2pt;border-bottom:1px solid #DDE6DC;vertical-align:top;line-height:1.4}
tr:nth-child(even) td{background:#F2F6F1}
`}
@media print{
  @page{size:A4 landscape;margin:0}
  html,body{width:297mm;height:210mm}
}
</style>
</head>
<body>
<div class="page">
  <div class="svg-wrap">${svgStr}</div>
  <div class="table-wrap">
    <div class="tbl-title">♦ ${projectName||'座位卡'}</div>
    <div class="tbl-sub">${new Date().toLocaleDateString('zh-CN')} · ${roomW}×${roomH}m · ${tables.length}桌</div>
    <table>
      <thead><tr><th>桌名</th><th>人</th><th>就坐宾客</th></tr></thead>
      <tbody>${tableRows}</tbody>
    </table>
  </div>
</div>
<script>window.onload=()=>{window.print();}<\/script>
</body></html>`);
    pw.document.close();
  },200);
}
function dl(fn,content,type){const a=document.createElement('a');a.href=URL.createObjectURL(new Blob([content],{type}));a.download=fn;a.click();}
function switchTab(name){const isG=name==='guests';document.getElementById('panel-guests').style.display=isG?'flex':'none';document.getElementById('panel-config').style.display=isG?'none':'flex';document.getElementById('tab-guests-btn').classList.toggle('active',isG);document.getElementById('tab-config-btn').classList.toggle('active',!isG);}

// ══ RESIZE PANES ══
(()=>{
  const handle=document.getElementById('resizeHandle');
  const L=document.getElementById('paneLeft');
  const R=document.getElementById('paneRight');
  let dragging=false,startX=0,startW=0;
  handle.addEventListener('mousedown',e=>{
    dragging=true;startX=e.clientX;startW=L.offsetWidth;
    document.body.style.cursor='col-resize';document.body.style.userSelect='none';
  });
  window.addEventListener('mousemove',e=>{
    if(!dragging)return;
    const total=L.parentElement.offsetWidth-5;
    const nw=Math.min(total-320,Math.max(500,startW+(e.clientX-startX)));
    L.style.flex=`0 0 ${nw}px`;R.style.flex=`1 1 0`;
  });
  window.addEventListener('mouseup',()=>{
    if(dragging){dragging=false;document.body.style.cursor='';document.body.style.userSelect='';}
  });
})();

// ══ MOBILE VERTICAL RESIZE: paneLeft ↕ paneRight (touch) ══
(function(){
  const handle=document.getElementById('resizeHandle');
  const L=document.getElementById('paneLeft');
  if(!handle||!L)return;
  let tdrag=false,tStartY=0,tStartH=0;
  handle.addEventListener('touchstart',e=>{
    if(!_isPhonePortrait())return;
    e.preventDefault();tdrag=true;
    tStartY=e.touches[0].clientY;tStartH=L.offsetHeight;
    handle.style.background='#2FBB7A';
  },{passive:false});
  document.addEventListener('touchmove',e=>{
    if(!tdrag)return;
    e.preventDefault();
    const dy=e.touches[0].clientY-tStartY;
    const newH=Math.min(window.innerHeight*0.75,Math.max(80,tStartH+dy));
    L.style.flex=`0 0 ${newH}px`;
  },{passive:false});
  const endT=()=>{if(tdrag){tdrag=false;handle.style.background='';}};
  document.addEventListener('touchend',endT);document.addEventListener('touchcancel',endT);
})();

// ══ MOBILE DARK TOGGLE: 移到 hdrLeft 右端，节省第二行空间 ══
(function(){
  const db=document.getElementById('darkBtn');
  const hl=document.getElementById('hdrLeft');
  const ha=document.getElementById('hdrActions');
  if(!db||!hl||!ha)return;
  function repos(){
    if(_isPhonePortrait()){
      if(db.parentElement!==hl){hl.appendChild(db);db.style.marginLeft='auto';}
    } else {
      if(db.parentElement!==ha){ha.appendChild(db);db.style.marginLeft='';}
    }
  }
  repos();
  window.addEventListener('resize',()=>repos());
})();

// ══ VERTICAL RESIZE: tableListPanel ↕ tableConfigPanel ══
(()=>{
  const lp=document.getElementById('tableListPanel');
  if(!lp)return;
  // inject resize handle between list and config
  const vHandle=document.createElement('div');
  vHandle.id='vResizeHandle';
  vHandle.style.cssText='height:5px;cursor:row-resize;background:#DDE6DC;flex-shrink:0;transition:background .15s';
  vHandle.onmouseenter=()=>vHandle.style.background='#2FBB7A';
  vHandle.onmouseleave=()=>vHandle.style.background=document.body.classList.contains('dark-mode')?'#3A3530':'#DDE6DC';
  lp.insertAdjacentElement('afterend',vHandle);
  let drag=false,startY=0,startH=0;
  vHandle.addEventListener('mousedown',e=>{drag=true;startY=e.clientY;startH=lp.offsetHeight;document.body.style.cursor='row-resize';document.body.style.userSelect='none';});
  window.addEventListener('mousemove',e=>{if(!drag)return;const nh=Math.min(500,Math.max(60,startH+(e.clientY-startY)));lp.style.maxHeight=nh+'px';});
  window.addEventListener('mouseup',()=>{if(drag){drag=false;document.body.style.cursor='';document.body.style.userSelect='';}});
})();

let _darkMode=false;
function toggleDark(){
  _darkMode=!_darkMode;
  document.getElementById('appBody').classList.toggle('dark-mode',_darkMode);
  document.body.classList.toggle('dark-mode',_darkMode);
  const _db=document.getElementById('darkBtn');
  if(_db){_db.textContent=_darkMode?'☀':'🌙';_db.title=_darkMode?'切换至春日模式':'切换至喜夜模式';}
  document.getElementById('canvas').style.background=_darkMode?'#1e1e1e':'#FBF7E6';
  localStorage.setItem('sc-dark',_darkMode?'1':'');
  renderRoomLabels();
  renderTablesOnly();
  wtnRenderTabs(); // 桌名模板 tab 颜色跟随暗夜
}
// 恢复暗夜偏好
(function(){
  if(localStorage.getItem('sc-dark')==='1'){
    _darkMode=true;
    document.getElementById('appBody').classList.add('dark-mode');
    document.body.classList.add('dark-mode');
    const b=document.getElementById('darkBtn');if(b){b.textContent='☀';b.title='切换至春日模式';}
    const c=document.getElementById('canvas');if(c)c.style.background='#1e1e1e';
  }
})();
// ══════════════════════════════════════════════════
// 主桌切换
// ══════════════════════════════════════════════════
function toggleMainTable(id){
  pushUndo();
  const t=tables.find(t=>t.id===id);
  if(t){t.isMain=!t.isMain;renderTableList();renderTableConfig();}
}

// ══════════════════════════════════════════════════
// WTN 桌名快速工具栏
// ══════════════════════════════════════════════════
// 模板分类（排除"普通"，用于标注）
const WTN_TMPL_CATS=Object.keys(WTN_DATA).filter(c=>c!=='普通');
const WTN_ANNO_CAT='普通';
let wtnCat=WTN_TMPL_CATS[0]||'';
let _wtnOpen=false;
let _wtnSuffix='';

// 去掉雅文词尾的"席"/"桌"，保留核心语素
function wtnStripSuffix(w){return(w.endsWith('席')||w.endsWith('桌'))?w.slice(0,-1):w;}

function wtnToggle(){
  _wtnOpen=!_wtnOpen;
  const body=document.getElementById('wtn-body');
  const icon=document.getElementById('wtn-chevron');
  if(body)body.style.display=_wtnOpen?'block':'none';
  if(icon)icon.style.transform=_wtnOpen?'rotate(90deg)':'rotate(0deg)';
}

function wtnInitToolbar(){
  if(!WTN_TMPL_CATS.length)return;
  wtnCat=WTN_TMPL_CATS[0];
  wtnRenderTabs();
  wtnRender();
  wtnRenderAnno();
  wtnRenderNumBtns();
}

function wtnRenderTabs(){
  const el=document.getElementById('wtn-tabs');if(!el)return;
  const dk=document.body.classList.contains('dark-mode');
  el.innerHTML=WTN_TMPL_CATS.map(c=>`<button onclick="wtnSetCat('${c}')"
    style="font-size:10px;padding:2px 8px;border:none;border-radius:4px;cursor:pointer;font-family:inherit;white-space:nowrap;flex-shrink:0;transition:all .12s;
    ${c===wtnCat?'background:#2FBB7A;color:#fff;font-weight:600':(dk?'background:#302E2B;color:#E0D8D0':'background:transparent;color:#5A7A6A')}">${c}</button>`).join('');
}

function wtnRender(){
  const rawWords=WTN_DATA[wtnCat]||[];
  // 雅文分类去掉词尾席/桌，其他保留原名
  const words=wtnCat==='雅文'?rawWords.map(wtnStripSuffix):rawWords;
  const el=document.getElementById('wtn-chips');if(!el)return;
  el.innerHTML=words.map((w,i)=>{
    const raw=rawWords[i];
    const full=w+_wtnSuffix;
    return`<span class="wtn-chip-word" draggable="true"
      data-wtntype="label" data-wtnval="${eh(full)}"
      ondragstart="wtnDragFromEl(event)"
      style="font-size:10px;background:#fff;border:1px solid #DDE6DC;border-radius:10px;padding:1px 8px;cursor:grab;white-space:nowrap;color:#1F3B2F;line-height:1.9;user-select:none;flex-shrink:0"
      title="拖拽到列表中的桌子">${eh(w)}${_wtnSuffix?`<em style="color:#5A7A6A;font-style:normal;font-size:9px">·${eh(_wtnSuffix)}</em>`:''}</span>`;
  }).join('');
  wtnRenderTabs();
}

function wtnRenderAnno(){
  const words=(WTN_DATA[WTN_ANNO_CAT]||[]);
  const el=document.getElementById('wtn-anno-chips');if(!el)return;
  el.innerHTML=words.map(w=>`<span class="wtn-chip-anno" draggable="true"
    data-wtntype="anno" data-wtnval="${eh(w)}"
    ondragstart="wtnDragFromEl(event)"
    style="font-size:10px;background:#EAF5EE;border:1px solid #A8D8BC;border-radius:10px;padding:1px 8px;cursor:grab;white-space:nowrap;color:#1A5C38;line-height:1.9;user-select:none;flex-shrink:0"
    title="拖拽到桌子设置标注">${eh(w)}</span>`).join('');
}

// ── 桌号快填按钮（wtn-num-btns，可选）──
function wtnRenderNumBtns(){
  const el=document.getElementById('wtn-num-btns');if(!el)return;
  const dk=document.body.classList.contains('dark-mode');
  el.innerHTML=Array.from({length:Math.min(tables.length||12,20)},(_,i)=>
    `<button onclick="wtnFillNum(${i+1})"
      style="font-size:9px;padding:1px 6px;border-radius:4px;border:1.5px solid ${dk?'#4A4540':'#DDE6DC'};
      background:${dk?'#2A2623':'#F2F6F1'};color:${dk?'#E0D8D0':'#5A7A6A'};cursor:pointer;
      transition:all .12s;line-height:1.6">${i+1}</button>`
  ).join('');
}

function wtnSetCat(c){wtnCat=c;wtnRender();}

function wtnSetSfx(s){
  _wtnSuffix=s;
  // 更新按钮状态
  ['','桌','席'].forEach((v,i)=>{
    const btn=document.getElementById('sfx-'+i);if(!btn)return;
    const on=v===s;
    btn.style.background=on?'#2FBB7A':'#F2F6F1';
    btn.style.borderColor=on?'#2FBB7A':'#DDE6DC';
    btn.style.color=on?'#fff':'#5A7A6A';
  });
  wtnRender();
}

function wtnDragFromEl(e){
  const type=e.currentTarget.dataset.wtntype;
  const val=e.currentTarget.dataset.wtnval;
  e.dataTransfer.setData('wtn-type',type);
  e.dataTransfer.setData('wtn-val',val);
  e.dataTransfer.effectAllowed='copy';
}

function wtnDropOnTable(e,tid){
  e.preventDefault();e.stopPropagation();
  const type=e.dataTransfer.getData('wtn-type');
  const val=e.dataTransfer.getData('wtn-val');
  if(!type||!val)return;
  const t=tables.find(t=>t.id===tid);if(!t)return;
  pushUndo();
  if(type==='label'){t.label=val;}
  else if(type==='anno'){t.annotation=val;}
  renderTablesOnly();renderTableList();
  if(selectedTableId===tid)renderTableConfig();
}
function wtnDropOnConfigPanel(e){
  e.preventDefault();e.stopPropagation();
  if(!selectedTableId)return;
  const type=e.dataTransfer.getData('wtn-type');
  const val=e.dataTransfer.getData('wtn-val');
  if(!type||!val)return;
  const t=tables.find(t=>t.id===selectedTableId);if(!t)return;
  pushUndo();
  if(type==='label'){
    t.label=val;
    const inp=document.getElementById('cfg-lbl');if(inp)inp.value=val;
  }else if(type==='anno'){
    t.annotation=val;
    const inp=document.getElementById('cfg-anno');if(inp)inp.value=val;
  }
  renderTablesOnly();renderTableList();renderTableConfig();
}

// ── Touch-drag shim：WTN chip 拖动（iOS Safari 不支持 HTML5 DnD）──
(function(){
  let _td=null;
  const gh=()=>document.getElementById('dragGhost');
  document.addEventListener('touchstart',function(e){
    const chip=e.target.closest('[data-wtntype]');
    if(!chip)return;
    e.preventDefault();
    const t=e.touches[0];
    _td={type:chip.dataset.wtntype,val:chip.dataset.wtnval};
    const g=gh();g.textContent=_td.val;
    g.style.left=(t.clientX+14)+'px';g.style.top=(t.clientY-10)+'px';g.style.display='block';
  },{passive:false});
  document.addEventListener('touchmove',function(e){
    if(!_td)return;
    e.preventDefault();
    const t=e.touches[0];
    const g=gh();g.style.left=(t.clientX+14)+'px';g.style.top=(t.clientY-10)+'px';
    g.style.display='none';
    const el=document.elementFromPoint(t.clientX,t.clientY);
    g.style.display='block';
    document.querySelectorAll('._tdHL').forEach(x=>{x.classList.remove('_tdHL');x.style.outline='';});
    if(el){
      const row=el.closest('[ondrop*="wtnDropOnTable"]');
      const pnl=el.closest('#tableConfigPanel');
      if(row){row.style.outline='2px solid #2FBB7A';row.classList.add('_tdHL');}
      else if(pnl){pnl.style.outline='2px dashed #2FBB7A';pnl.classList.add('_tdHL');}
    }
  },{passive:false});
  document.addEventListener('touchend',function(e){
    if(!_td)return;
    const t=e.changedTouches[0];
    gh().style.display='none';
    document.querySelectorAll('._tdHL').forEach(x=>{x.classList.remove('_tdHL');x.style.outline='';});
    const el=document.elementFromPoint(t.clientX,t.clientY);
    if(el){
      const row=el.closest('[ondrop*="wtnDropOnTable"]');
      const pnl=el.closest('#tableConfigPanel');
      const{type,val}=_td;
      if(row){
        const m=(row.getAttribute('onclick')||'').match(/selectTable\((\d+)\)/);
        if(m)_wtnTD(type,val,parseInt(m[1]));
      }else if(pnl){_wtnTDPanel(type,val);}
    }
    _td=null;
  },{passive:true});
  window._wtnTD=function(type,val,tid){
    const t=tables.find(t=>t.id===tid);if(!t)return;
    pushUndo();
    if(type==='label')t.label=val;else if(type==='anno')t.annotation=val;
    renderTablesOnly();renderTableList();
    if(selectedTableId===tid)renderTableConfig();
  };
  window._wtnTDPanel=function(type,val){
    if(!selectedTableId)return;
    const t=tables.find(t=>t.id===selectedTableId);if(!t)return;
    pushUndo();
    if(type==='label'){t.label=val;const i=document.getElementById('cfg-lbl');if(i)i.value=val;}
    else if(type==='anno'){t.annotation=val;const i=document.getElementById('cfg-anno');if(i)i.value=val;}
    renderTablesOnly();renderTableList();renderTableConfig();
  };
})();

function wtnApplyAll(){
  const rawWords=WTN_DATA[wtnCat]||[];
  const words=wtnCat==='雅文'?rawWords.map(wtnStripSuffix):rawWords;
  if(!words.length)return;
  if(!confirm(`将「${wtnCat}」的 ${Math.min(words.length,tables.length)} 个名称依次应用到全部 ${tables.length} 张桌子？`))return;
  pushUndo();
  tables.forEach((t,i)=>{if(i<words.length)t.label=words[i]+_wtnSuffix;});
  renderTablesOnly();renderTableList();updateStats();
}

// 画布显示选项（按钮版）
function toggleDispOpt(key){
  document.getElementById('disp-'+key+'-btn')?.classList.toggle('disp-opt-active');
  updDispOpts();
}
function updDispOpts(){
  tableDispOpts.showNum         = document.getElementById('disp-num-btn')  ?.classList.contains('disp-opt-active')??false;
  tableDispOpts.showLabel       = document.getElementById('disp-label-btn')?.classList.contains('disp-opt-active')??true;
  tableDispOpts.showAnnotation  = document.getElementById('disp-anno-btn') ?.classList.contains('disp-opt-active')??false;
  renderTablesOnly();
}


// ── versionBadge 悬浮分享框 ──
let _badgeMode='edit';
function toggleBadgeTip(e){
  const tip=document.getElementById('badgeTip');if(!tip)return;
  const opening=!tip.classList.contains('open');
  tip.classList.toggle('open');
  if(opening){
    const trig=e?.currentTarget||document.getElementById('shareBtn')||document.getElementById('versionBadgeWrap');
    if(trig){
      const r=trig.getBoundingClientRect();
      tip.style.top=(r.bottom+6)+'px';
      tip.style.left=Math.min(r.left,window.innerWidth-240)+'px';
    }
    badgeSetMode(VIEW_ONLY?'view':'edit');
  }
}
function badgeSetMode(mode){
  _badgeMode=mode;
  const te=document.getElementById('tipTabEdit');
  const tv=document.getElementById('tipTabView');
  const url=document.getElementById('tipActiveUrl');
  const desc=document.getElementById('tipDesc');
  if(te)te.classList.toggle('tip-tab-active',mode==='edit');
  if(tv)tv.classList.toggle('tip-tab-active',mode==='view');
  const base=location.origin+location.pathname;
  if(url)url.value=mode==='edit'?base+'?Auth='+AUTH_CODE:base+'?Auth='+AUTH_CODE.slice(0,7);
  if(desc)desc.textContent=mode==='edit'
    ?'编辑链接：拥有全部权限，可修改桌位、宾客及保存数据'
    :'查看链接：仅浏览，不可编辑或删除，适合分享给宾客';
}
document.addEventListener('click',e=>{
  if(!e.target.closest('#versionBadgeWrap')&&!e.target.closest('#shareBtn')&&!e.target.closest('#badgeTip')){
    const t=document.getElementById('badgeTip');
    if(t)t.classList.remove('open');
  }
});
async function _badgeCopy(url,label){
  try{await navigator.clipboard.writeText(url);_badgeMsg(label+' 已复制 ✓');}
  catch{_badgeMsg(url);}
}
function _badgeMsg(m){const el=document.getElementById('badgeTipMsg');if(el){el.textContent=m;setTimeout(()=>{el.textContent='';},2500);}}
async function badgeCopyCode(){_badgeCopy(AUTH_CODE,'授权码');}
async function badgeCopyActive(){
  const url=document.getElementById('tipActiveUrl');if(!url)return;
  _badgeCopy(url.value,_badgeMode==='edit'?'编辑链接':'查看链接');
}

// ══ 视口高度适配：用 window.innerHeight 替代 100vh，解决 iOS/iPadOS Chrome 底栏遮挡 ══
(function(){
  function fitViewport(){
    const body=document.getElementById('appBody');if(!body)return;
    const h=window.innerHeight;
    body.style.height=h+'px';
    body.style.maxHeight=h+'px';
    body.style.minHeight=Math.min(h,600)+'px';
  }
  fitViewport();
  window.addEventListener('resize',fitViewport);
  window.addEventListener('orientationchange',()=>setTimeout(fitViewport,300));
})();

// ══ INIT ══
updateRoomSize();render();setTimeout(zoomReset,100);
setTimeout(wtnInitToolbar,50);
// 触屏检测：JS 判断优先于 CSS 媒体查询，防止 Chrome DevTools 模拟漏判
// 上下分割布局条件：宽度 <768px 且 高宽比 >1.5（竖屏手机）；iPad/平板横屏保持左右布局
// 切回左右布局时，清除移动端拖拽留下的内联 flex 样式（否则 pane 宽度/高度会残留）
let _wasCompactLayout=false;
function _isPhonePortrait(){return window.innerWidth<768&&window.innerHeight/window.innerWidth>1.5;}
function updateTouchTabletClass(){
  const isCompact=_isPhonePortrait();
  const wasCompact=_wasCompactLayout;
  _wasCompactLayout=isCompact;
  document.body.classList.remove('touch-tablet'); // 不再使用 touch-tablet 强制分割
  // 从上下布局切回左右布局时，重置内联 flex（还原 HTML 原始比例）
  if(wasCompact&&!isCompact){
    const L=document.getElementById('paneLeft');
    const R=document.getElementById('paneRight');
    if(L)L.style.flex='';
    if(R)R.style.flex='';
  }
  // ── Shift+D 调试：布局切换回显 ──
  if(_tcDebug&&wasCompact!==isCompact){
    const lbl=isCompact?'phone-portrait(上下)':'PC/tablet(左右)';
    console.log('[LAYOUT]',wasCompact?'上下':'左右','→',lbl,'| w='+window.innerWidth+' h='+window.innerHeight+' ratio='+(window.innerHeight/window.innerWidth).toFixed(2));
  }
}
function applyHintMode(force){
  if(force)_isTouchDevice=true;
  const prevW=window.innerWidth;
  updateTouchTabletClass();
  const mobileHint=_isTouchDevice||window.innerWidth<=768;
  document.querySelectorAll('.hint-desktop').forEach(el=>{el.style.display=mobileHint?'none':'inline';});
  document.querySelectorAll('.hint-mobile').forEach(el=>{el.style.display=mobileHint?'inline':'none';});
  // ── Shift+D 调试：操作模式回显 ──
  if(_tcDebug){
    const listMode=(_isTouchDevice||prevW<=768)?'compact(触控mc-tag)':'PC(draggable)';
    console.log('[MODE] 宾客列表='+listMode+' | hint='+( mobileHint?'mobile':'desktop'));
  }
  if(document.getElementById('guestList'))renderGuestList(filterText);
}
applyHintMode();
// 首次真实 touchstart：仅更新提示文字+布局 class，不重渲染列表（避免销毁拖拽中的 DOM）
document.addEventListener('touchstart',function _firstTouch(){
  if(!_isTouchDevice){
    _isTouchDevice=true;
    updateTouchTabletClass();
    // 只更新 hint 元素显示，不调用 renderGuestList（防止拖拽中 DOM 被销毁）
    document.querySelectorAll('.hint-desktop').forEach(el=>{el.style.display='none';});
    document.querySelectorAll('.hint-mobile').forEach(el=>{el.style.display='inline';});
  }
},{once:true,passive:true});
// 屏幕旋转 / 窗口大小变化时重新检测（宽屏切换回 PC 后恢复分类列表）
window.addEventListener('orientationchange',()=>setTimeout(applyHintMode,400));
window.addEventListener('resize',()=>{clearTimeout(window._hintRT);window._hintRT=setTimeout(applyHintMode,300);});

// ══════════════════════════════════════════════════
// 首次进入确认 + 引导 overlay
// ══════════════════════════════════════════════════
function showFirstRun(){
  const modal=document.getElementById('firstRunModal');
  if(!modal)return;
  const m=AUTH_CODE.match(/^(\d{2})(\d{2})(\d{2})([A-Za-z]{1,2})([A-Z0-9]{2})$/);
  if(m){
    const yy=m[1],mo=parseInt(m[2]),dd=parseInt(m[3]),letter=m[4],ck=m[5];
    document.getElementById('frTitle').value=mo+'月'+dd+'日晚宴';
    document.getElementById('frInfo').innerHTML=
      '<div><b>📅 日期：</b>20'+yy+'年'+mo+'月'+dd+'日</div>'+
      '<div><b>🔑 场次：</b>'+letter+'&nbsp;&nbsp;<b>✓ 校验：</b>'+ck+'</div>'+
      '<div><b>🔒 授权码：</b>'+AUTH_CODE+'</div>';
  } else {
    document.getElementById('frTitle').value='婚宴座位图';
    document.getElementById('frInfo').innerHTML='<div><b>🔒 授权码：</b>'+AUTH_CODE+'</div>';
  }
  modal.style.display='flex';
}
function confirmFirstRun(){
  const modal=document.getElementById('firstRunModal');
  const inp=document.getElementById('frTitle');
  const name=(inp?.value||'').trim();
  if(name){
    projectName=name;
    const el=document.getElementById('projectTitle');
    if(el)el.textContent=name;
  }
  if(modal)modal.style.display='none';
  localStorage.setItem('sc-fr-'+AUTH_CODE,'1');
  setTimeout(showFirstGuide,400);
}
function showFirstGuide(){
  if(!AUTH_CODE||VIEW_ONLY)return;
  const gkey='sc-guide-'+AUTH_CODE;
  if(localStorage.getItem(gkey))return;
  localStorage.setItem(gkey,'1');
  const ov=document.createElement('div');
  ov.id='firstGuide';
  ov.style.cssText='position:fixed;inset:0;z-index:9995;pointer-events:none;animation:guideFadeIn .4s ease';
  ov.innerHTML=
    '<div style="position:absolute;inset:0;background:rgba(0,0,0,.5)"></div>'+
    '<div id="guideCard" style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);background:rgba(20,18,16,.92);color:#F0E8E0;border-radius:14px;padding:20px 26px;font-size:13px;text-align:center;line-height:2;min-width:230px;border:1px solid rgba(255,255,255,.12);animation:guidePulse 1.2s ease infinite">'+
      '<div style="font-size:15px;font-weight:600;margin-bottom:10px;letter-spacing:.5px">开始使用 SeatCard 🎉</div>'+
      '<div style="text-align:left;line-height:2.2">'+
        '➕ <b>新建桌子</b> — 底部工具栏左侧<br>'+
        '☁ <b>保存到服务器</b> — 右上角按钮<br>'+
        '⬇ <b>下载 JSON 备份</b> — 右上角按钮<br>'+
        '❓ <b>帮助说明</b> — 左上角 Logo'+
      '</div>'+
      '<div style="font-size:10px;opacity:.5;margin-top:12px">3 秒后自动消失</div>'+
    '</div>';
  document.body.appendChild(ov);
  let count=0;
  const blink=setInterval(()=>{
    count++;
    const c=document.getElementById('guideCard');
    if(c)c.style.animationPlayState=count%2===0?'running':'paused';
    if(count>=6)clearInterval(blink);
  },400);
  setTimeout(()=>{
    clearInterval(blink);
    ov.style.transition='opacity .6s';ov.style.opacity='0';
    setTimeout(()=>ov.remove(),700);
  },3000);
}
// 首次进入检测（页面加载后延迟判断）
if(AUTH_CODE&&!VIEW_ONLY){
  setTimeout(()=>{
    if(tables.length===0&&guests.length===0){
      if(!localStorage.getItem('sc-fr-'+AUTH_CODE))showFirstRun();
      else setTimeout(showFirstGuide,200);
    }
  },250);
}
</script>

<!-- badgeTip 移到 body 末端，避免被 versionBadgeWrap display:none 影响 -->
<div id="badgeTip">
  <?php if($AUTH_CODE): ?>
  <div class="tip-note" style="margin-bottom:6px;font-size:9px;color:#5A7A6A">📌 复制链接或添加到收藏夹，可直接访问无需重新输入授权码</div>
  <div class="tip-code" onclick="badgeCopyCode()" title="点击复制授权码" style="cursor:pointer"><?= htmlspecialchars($AUTH_CODE,ENT_QUOTES) ?></div>
  <div style="display:flex;gap:4px;align-items:center;margin:8px 0 6px">
    <?php if(!$VIEW_ONLY): ?>
    <button id="tipTabEdit" class="tip-tab tip-tab-active" onclick="badgeSetMode('edit')">编辑</button>
    <button id="tipTabView" class="tip-tab" onclick="badgeSetMode('view')">查看</button>
    <?php else: ?>
    <button class="tip-tab tip-tab-active" style="pointer-events:none">查看</button>
    <?php endif; ?>
    <div style="flex:1"></div>
    <button class="tip-copy-btn" onclick="badgeCopyActive()">复制</button>
  </div>
  <input class="tip-url" id="tipActiveUrl" readonly onclick="this.select()">
  <div id="tipDesc" class="tip-note"></div>
  <?php endif; ?>
  <div id="badgeTipMsg"></div>
</div>
</body>
</html>