# SeatCard V0.24 · 婚宴圆桌座位编排系统

> 轻量级婚礼座位管理工具。单 PHP 入口，无数据库，多用户授权码隔离，可视化圆桌布局，支持 PDF 导出。

---

## 目录

- [功能概览](#功能概览)
- [文件结构](#文件结构)
- [快速部署](#快速部署)
- [授权码体系](#授权码体系)
- [后台管理](#后台管理)
- [API 参考](#api-参考)
- [存储说明](#存储说明)
- [安全说明](#安全说明)
- [版本历史](#版本历史)
- [技术栈](#技术栈)

---

## 功能概览

### 主应用（`index.php`）

| 功能 | 说明 |
|------|------|
| 🔑 授权码隔离 | 每场婚礼独立授权码，数据目录完全互不干扰 |
| 🗺 可视化画布 | SVG 圆桌布局，鼠标拖拽移动，滚轮缩放，空格+拖动平移，单击/框选多桌 |
| 🪑 桌位设置 | 设置桌名、桌号、标注、座位数（6–14）、圆桌尺寸（1.8/2.0/2.2m）、所属方 |
| 👥 宾客管理 | 莫兰迪色分类标注、宾客状态（确认/待定）、配偶/小孩关联 |
| 📄 PDF 导出 | A4 横版：左侧平面图 + 右侧桌位清单，SVG 矢量品质 |
| 💾 多种存储 | 本地 JSON 下载 + 服务器 5 存档位 + 服务器时间戳备份 |
| 📥 JSON 上载 | 从本地 JSON 文件还原完整状态 |
| 🔄 CSV 导入 | 批量导入宾客名单 |
| ↩ 撤销 | Ctrl+Z，最近 40 步 |
| 🌙 喜夜模式 | 一键切换春日（暖色）/ 喜夜（暗色）主题，即时跟随画布渲染 |
| 🔗 分享链接 | 头部版本徽章弹窗，复制编辑链接 / 查看链接 |

### 画布显示控制

底部工具栏可独立切换「桌号」「桌名」「标注」三项的显示，支持任意组合。喜夜模式下桌名显示为白色，桌号/标注为淡色。

---

## 文件结构

```
SeatCard/
├── index.php          # 主入口：授权门 + 完整前端应用（SPA）
├── auth.php           # 授权码管理 API（生成 / 列表 / 删除）
├── save.php           # 保存 JSON 备份到服务器
├── load.php           # 从服务器加载 JSON 文件
├── list.php           # 列出服务器备份（含存档位状态）
├── delete.php         # 删除服务器备份文件
├── admin.php          # 操作后台（生成/删除场次、备份注入）
├── dashboard.php      # 总览看板（日历视图、场次状态管理）
├── tw.js              # Tailwind Play CDN 本地缓存（解决国内屏蔽）
└── data/
    ├── auth.json      # 所有已注册授权码记录（唯一凭据源）
    └── {CODE}/        # 每场授权码独立目录，首次保存时自动创建
        ├── wedding-seating-backup-YYYYMMDD-HHmmss.json
        └── wedding-seating-slot-{1-5}.json
```

> `data/` 目录不会预建，由首次保存操作自动创建。

---

## 快速部署

### 环境要求

- PHP **7.4+**（推荐 8.1+）
- `data/` 目录对 Web 用户可写

### 步骤

```bash
# 1. 上传全部文件
scp -r SeatCard/ user@server:/var/www/html/seatcard/

# 2. 创建 data 目录并授权
mkdir -p /var/www/html/seatcard/data
chmod 755 /var/www/html/seatcard/data
chown www-data:www-data /var/www/html/seatcard/data

# 3. 访问授权门
https://yoursite.com/seatcard/
```

### Nginx 配置参考

```nginx
location /seatcard/ {
    index index.php;
}
location ~ \.php$ {
    fastcgi_pass unix:/run/php/php8.1-fpm.sock;
    include fastcgi_params;
    fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
}
# 禁止直接浏览 data 目录
location /seatcard/data/ { deny all; }
```

### 修改后台密码

打开对应文件，修改第一行的 `define`：

| 文件 | 常量 | 默认值 | 说明 |
|------|------|--------|------|
| `admin.php` | `ADMIN_PASS` | `admin888` | 操作后台密码 |
| `dashboard.php` | `DASH_PASS` | `superSC2026` | 总览看板密码 |

---

## 授权码体系

### 格式说明

系统支持两种授权码长度，可混合使用：

#### 标准格式（9 位）— `YYMMDDXcc`

| 字段 | 长度 | 说明 |
|------|------|------|
| `YYMMDD` | 6 位 | 年月日，如 `260401` = 2026-04-01 |
| `X` | 1 位 | 场次字母，**A–Z**（26 场/日）|
| `cc` | 2 位 | CRC32 校验位，映射到 28 字符表 |

#### 扩展格式（10 位）— `YYMMDDaXcc`

| 字段 | 长度 | 说明 |
|------|------|------|
| `YYMMDD` | 6 位 | 年月日 |
| `aX` | 2 位 | 双字母场次：**小写前缀**（a–z）+ 大写字母（A–Z），如 `aA`、`aB`、`zZ` |
| `cc` | 2 位 | CRC32 校验位 |

**总容量：** A–Z（26）+ aA–zZ（26×26 = 676）= **702 场/日**

#### 字符表

```
346789ACDEFGHJKLMNPQRSTUVWXY
```
共 28 个字符，去除了易混淆字符（`0 1 2 5 B I O Z`）。

#### 校验算法

```
CRC32(YYMMDD + 场次标识) → 无符号整数 → intdiv(h,28)%28 和 h%28 → 各取字符表一位
```

任意一位变化，CRC32 结果完全不可预测，有效抵抗枚举猜测。

### 仅查看链接

去掉末尾 2 位校验位即为查看前缀：

| 授权码 | 类型 | 查看前缀（7/8 位）|
|--------|------|-----------------|
| `260401ATW` | 标准 9 位 | `260401A` |
| `260401aATW` | 扩展 10 位 | `260401aA` |

```
编辑链接：index.php?Auth=260401ATW
查看链接：index.php?Auth=260401A
```

查看模式：只读，不可编辑桌位或删除备份。

### `auth.json` 结构

```json
[
  {
    "code":      "260401ATW",
    "date":      "260401",
    "createdAt": "2026-04-01T10:00:00+08:00",
    "note":      "张三&李四婚礼 · 下午场",
    "status":    "active"
  }
]
```

`status` 取值：`active`（有效）/ `archived`（已归档）/ `deleted`（已删除，记录保留）

---

## 后台管理

### `admin.php` — 操作后台

**密码：** `admin888`（在文件顶部修改）

| 功能 | 说明 |
|------|------|
| 生成场次编号 | 指定年/月/日/场次字母，写入 `auth.json`（不预建目录）|
| 查看所有场次 | 列表显示所有已注册授权码、备注、备份数量、创建时间 |
| 进入场次 | 直接跳转到对应编辑页面 |
| 删除场次 | 从 `auth.json` 移除记录，删除对应 `data/{CODE}/` 目录 |
| 备份注入 | 将根目录的旧版备份文件迁移到指定场次目录 |

---

### `dashboard.php` — 总览看板

**密码：** `superSC2026`（独立于 admin，在文件顶部修改）

| 功能 | 说明 |
|------|------|
| 日历视图 | 按年展示，‹ / › 切换年份 |
| 月级折叠 | 整月无场次 → 一行折叠显示 |
| 周级折叠 | 整周无场次 → 一行折叠显示 |
| 场次卡片 | 显示场次编号（默认隐藏校验位）、桌数、宾客数、状态 |
| 数量标注 | 桌数 < 1 或宾客 < 5 时灰色显示（尚未正式使用）|
| 编号切换 | 👁 点击显示 / 隐藏完整授权码 |
| 复制链接 | 一键复制编辑链接或查看链接 |
| 状态管理 | 有效 → 归档；归档 → 恢复 / 删除（删除仅清除文件，记录保留）|
| 年度统计 | 头部显示当年总场次、有效/归档/删除数量 |

---

## API 参考

### `auth.php` — 授权码管理

| 请求 | 说明 |
|------|------|
| `GET ?action=generate&date=YYMMDD` | 自动分配下一个场次字母，写入 `auth.json` |
| `GET ?action=list&date=YYMMDD` | 返回指定日期的授权码列表 |
| `GET ?action=all` | 返回 `auth.json` 全部记录 |
| `GET ?action=delete&code=CODE` | 从 `auth.json` 删除指定授权码记录 |

### `save.php` — 保存备份

```
POST /save.php?auth=CODE
Content-Type: application/json
Body: 完整状态 JSON

可选参数：
  &slot=1-5   → 写入固定存档位（覆盖）
  （无 slot） → 写入时间戳备份文件
```

### `list.php` — 列出备份

```
GET /list.php?auth=CODE&n=10         → 最近 n 个时间戳备份摘要
GET /list.php?auth=CODE&mode=slots   → 5 个固定存档位状态
```

返回字段：`filename`、`savedAt`、`tables`（桌数）、`guests`（宾客数）

### `load.php` — 加载文件

```
GET /load.php?auth=CODE&filename=wedding-seating-backup-20260401-120000.json
```

### `delete.php` — 删除备份文件

```
GET /delete.php?auth=CODE&filename=xxx.json
仅允许 .json 扩展名，禁止路径穿越
```

---

## 存储说明

| 存储方式 | 背景图 | 安全性 | 持久性 | 推荐度 |
|----------|--------|--------|--------|--------|
| ⬇ 下载 JSON 到本地 | ✅ 含底图 | ✅ 本地保管 | ✅ 永久 | ⭐⭐⭐ |
| ☁ 服务器固定存档位（1–5）| ❌ 不含底图 | ⚠ 不加密 | ⚠ 随服务器 | ⭐⭐ |
| 服务器时间戳备份 | ❌ 不含底图 | ⚠ 不加密 | ⚠ 随服务器 | ⭐ |

> **建议**：编辑完成后务必「⬇ 下载 JSON」到本地，服务器备份仅作为临时协作用途。

---

## 安全说明

- **校验位用途**：快速验证格式合法性，不是访问控制手段。
- **auth.json 为准**：未注册的授权码（即使格式合法）进入时显示橙色警告。
- **状态校验**：`archived` / `deleted` 状态的授权码无法登录，也无法通过 `save.php` 写入数据。
- **数据不加密**：服务器上的 JSON 文件为明文，**请勿在公共服务器上存储真实宾客隐私数据**。
- **目录隔离**：每个授权码只能访问自己的 `data/{CODE}/` 目录，API 层严格校验路径。
- **管理页保护**：`admin.php` 和 `dashboard.php` 使用 PHP Session 保护，建议将密码改为强密码。
- **data/ 禁止浏览**：建议通过 Nginx/Apache 规则禁止直接访问 `data/` 目录。

---

## 版本历史

| 版本 | 日期 | 主要变更 |
|------|------|----------|
| V0.23 | 2026-04-07 | 移除 `h-screen`（`100vh`），改用 `fitViewport` 函数（`window.innerHeight`）动态设置 body 高度，解决 iOS/iPadOS Chrome 浏览器双工具栏遮挡底部问题；移除旧 `applyTabletCap` IIFE；`Shift+D` 开启 debug 时同步输出设备/视口诊断（innerWidth/innerHeight/100vh差值/devicePixelRatio/maxTouchPoints/appBody高度/布局模式）；mc-tag 颜色方案重设计：30色缩减为16色，分浅色组（黑字 `#2a2a2a`）×8 与深色组（白字 `#ffffff`）×8，色相分布更均匀，相邻色差异明显 |
| V0.22d | 2026-04-03 | 修复拖拽 ghost 不消失（`tcFindTarget` 内部恢复 `display:block` 问题）；修复 SVG `className.split` 崩溃（SVGAnimatedString 兼容处理）；修复 PC 已落座宾客无法拖出（`pointer-events:none` 改为 `auto`）；上下分割布局断点统一为 768px（CSS media query + JS resize + dark button 全部同步）；`_tcDebug` 默认关闭，`Shift+D` 开启调试输出 |
| V0.22 | 2026-04-03 | 触控拖拽系统完全重写（`tcDragStart/tcFindTarget/tcApplyDrop`），统一覆盖：宾客名单⠿手柄拖拽、画布落座宾客拖拽、拖到空位/有人位（弹换人框）/任意桌/宾客栏取消落座；6px 移动阈值防止误触；事件委托替代内联 handlers；iPad/大屏触控设备高度上限（≤760px），防止菜单被遮挡 |
| V0.21f | 2026-04-03 | 修复 `wtnRenderNumBtns is not defined` 崩溃（补全函数定义）；宾客列表切换改为纯宽度判断（≤768px 紧凑/触控，>768px PC 分类+拖拽），不再受 `_isTouchDevice` 永久标志干扰，从手机模式切回 PC 后分类立即恢复；`applyHintMode` 改为双向感知（宽屏切回 PC 自动更新提示）；移除重复 resize 监听 |
| V0.21e | 2026-04-03 | 手机喜夜按钮移至标题行右端；上下分割竖向拖动恢复（touch 事件）；画布提示可点击触发再次自检；页面最小高度 800px |
| V0.21d | 2026-04-03 | 修复 `_isTouchDevice` TDZ 崩溃（声明移至脚本顶部）；`#badgeTip` 跟随喜夜模式（`document.body` 同步添加 dark-mode 类）；badgeTip 补充文字说明 |
| V0.21c | 2026-04-03 | 手机 mc-tag 双击取消落座；拖拽感应半径改为屏幕固定 80px（缩放不影响）；宾客列表改用全局 `_isTouchDevice` 标志（一旦检测到触屏立即持久切换，解决 Chrome DevTools 不刷新无法切换问题）；画布提示首次 touchstart 时强制切换 |
| V0.21b | 2026-04-02 | 修复手机触摸拖拽（proximity 检测改用 tablesGroup.getScreenCTM，正确处理 pan/zoom）；PC 画布座位拖出改为「拖离画布区即取消落座」；宾客名单面板新增 HTML5 drop zone（拖回名单栏取消落座，橙虚线提示）|
| V0.21a | 2026-04-02 | 头部菜单重构（图标+文字按钮、双行布局、非手机第二行靠右）；手机模式显示版本标题、隐藏授权码；CSV 工具移至状态栏开关；分享按钮🔗常驻左侧；首次使用引导覆盖层（3秒闪烁渐隐）；新建空白场次弹窗确认；每5次操作自动保存；喜夜模式文字亮度提升20%；桌名模板面板跟随喜夜模式；手机宾客名单改为按个人槽位展示，支持触摸拖拽到桌子；宾客选中后搜索栏出现✏编辑按钮；画布操作提示基于触屏检测动态切换 |
| V0.19 | 2026-04-01 | 新增 `dashboard.php` 总览看板（日历/周月折叠/场次状态管理）；场次扩展至双字母格式（aA–zZ，702场/日）；校验算法改为 CRC32（抗枚举）；auth.json 增加 status 字段；生成授权码不再预建目录（懒创建）；授权门/看板暗夜模式全面修复；画布喜夜模式即时跟随；喜夜文字色方案调整 |
| V0.18 | 2026-03-30 | versionBadge 悬浮窗重设计（position:fixed 修复层级、编辑/查看 Tab、权限说明文字）；春日/喜夜模式命名；4字桌名分行+字间距；座位卡偏移查找表（12/13/14座）；底部桌号/桌名/标注改为按钮组 |
| V0.17 | 2026-03-30 | versionBadge 补全（完整 URL 展示与复制、点击授权码直接复制）；全系统移除旧 6 位格式兼容 |
| V0.16f | 2026-03-27 | 隐私警告；Header 服务器警示；帮助文档更新；存档位 UI 整理 |
| V0.16e | 2026-03-27 | 服务器存档列表添加删除按钮、清空本地存档、目录标签 |
| V0.16d | 2026-03-27 | 授权码升级为 9 位 YYMMDDXcc；`delete.php` 新增 |
| V0.16c | 2026-03-27 | `auth.php` 初版；授权门重构为「按日期生成 + 直接输入」双 Tab |
| V0.16 | 2026-03-27 | Tailwind CDN 改本地 `tw.js`；多用户授权码隔离架构 |
| V0.15 | 2026-03 | 初始多用户版，6 位授权码，服务器存档位 |

---

## 技术栈

| 层 | 技术 |
|----|------|
| 后端 | PHP 7.4+，无框架，无数据库，文件存储 |
| 前端 | 原生 JavaScript + SVG，无构建工具 |
| CSS 框架 | Tailwind Play CDN（本地 `tw.js` 缓存，解决国内屏蔽）|
| 字体 | Noto Serif SC / Noto Sans SC（via fonts.loli.net）|
| PDF 导出 | 浏览器原生打印，SVG 矢量输出 |
| 校验算法 | CRC32 → 无符号 → 28 字符表双字符映射 |

---

*SeatCard · Beta · 仅供内部使用 · 请勿在公共服务器存储真实隐私数据*
