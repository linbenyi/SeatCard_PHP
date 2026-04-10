# SeatCard V0.24 · 婚宴圆桌座位编排系统

> 专为中文婚宴场景设计的轻量座位管理工具。  
> 单 PHP 文件部署，无数据库，无外部 CDN 依赖，开箱即用。  
> Lightweight wedding seating planner built for Chinese banquet workflows. Single PHP file, no database, no external CDN.

![PHP](https://img.shields.io/badge/PHP-7.4%2B-777BB4?logo=php&logoColor=white)
![License](https://img.shields.io/badge/license-MIT-2FBB7A)
![Version](https://img.shields.io/badge/version-V0.24-blue)

---

## ✨ 为什么选择 SeatCard

传统婚宴排座方式通常依赖 Excel 表格或手写纸条，反复修改费时费力，现场核对容易出错。SeatCard 把整个流程压缩到一个可视化页面：

| 传统方式 | SeatCard |
|----------|----------|
| Excel 手动排列，无法直观看到桌子关系 | SVG 画布实时渲染，拖拽即落座 |
| 反复口头确认座位，容易遗漏 | 颜色标注 + 状态管理，一目了然 |
| 最后打印一张大表，现场翻查 | 一键导出 A4 PDF，每桌清单同页 |
| 多人协作靠传文件 | 授权码链接分享，只读/可编辑分开 |
| 手动汇总宾客名单 | 直接粘贴名单文本或导入 CSV，自动建档 |

---

## 🚀 核心功能

### 🗺 可视化圆桌画布

- SVG 渲染，**拖拽移动桌子**，滚轮缩放，空格+拖动平移
- 框选多桌后整组拖动
- 桌子间距实时标注（红色 = 不足 1.8m，自动预警）
- 底部工具栏独立控制「桌号 / 桌名 / 标注」显示
- 放置背景平面图（JPG/PNG），按比例叠加参考

### 👥 宾客管理 · 高效录入

- **📋 导入名单**（新）：纯文本，每行一人，逗号后写配偶名，自动识别标题行跳过
- **📥 导入 CSV**：批量导入带所属方、状态、备注的完整名单
- 莫兰迪配色系统（30 色 + 6 种特殊喜庆色），自动轮换分配
- 宾客分类：男方 / 女方 / 共同 / 自定义（最多扩展至 6 方）
- 状态追踪：已确认 / 待定 / 标注（可循环切换）
- 支持配偶、随行小孩、老人等类型关联

### 🪑 拖拽落座 · 减少重复操作

- 从宾客名单**拖到桌子**（自动分配空位）
- 拖到**指定座位胶囊**精确落座
- 已落座者拖出画布即取消
- 点击空座位（＋）弹出快速搜索指派
- **双击座位胶囊**取消落座（鼠标 & 触控均有效）
- Ctrl+Z 撤销，最近 40 步

### 📱 多端适配

- **PC**：全功能鼠标拖拽，分类列表
- **iPad / 平板**：自动切换纵向分割布局，触控 V4 拖拽系统（移动激活，无误触）
- **手机**：上下分割，紧凑标签列表，双指捏合缩放画布
- 春日（暖色）/ 喜夜（暗色）主题一键切换

### 📄 PDF 导出

- A4 横版单页：**左侧平面图**（场地撑满）+ **右侧按桌清单**
- 同桌家庭自动合并（「张伟全家」）
- 桌位清单顺序可在设置中拖排

### 💾 多重存储保障

| 方式 | 含底图 | 说明 |
|------|--------|------|
| ⬇ 下载 JSON | ✅ | 完整备份，推荐首选 |
| ☁ 服务器存档 | ❌ | 5 个固定存档位，可命名 |
| 📤 导出 CSV | — | 宾客名单导出，便于共享 |

---

## 🇨🇳 国内环境友好

- **Tailwind CSS 本地化**：`tw.js` 随项目文件部署，不依赖 jsDelivr / unpkg 等国外 CDN
- **字体走国内节点**：Noto Serif/Sans SC 经 `fonts.loli.net` 加载，速度稳定
- **无第三方服务**：无 Google Analytics、无外部 API 调用，完全离线可用
- **全中文界面**：操作提示、状态标签、错误信息均为中文

---

## 📂 文件结构

```
SeatCard/
├── index.php          # 主入口：授权门 + 完整前端应用（SPA）
├── admin.php          # 管理后台（生成/删除场次、部署说明）
├── dashboard.php      # 总览看板（日历视图、场次状态）
├── auth.php           # 授权码 API
├── save.php           # 保存备份到服务器
├── load.php           # 从服务器加载备份
├── list.php           # 列出服务器备份
├── delete.php         # 删除备份文件
├── tw.js              # Tailwind 本地缓存（解决国内屏蔽）
├── guests-template.csv  # CSV 导入模板
├── CHANGELOG.md       # 版本日志
└── data/
    ├── auth.example.json  # 授权码格式示例
    └── {CODE}/            # 每场授权码独立目录（首次保存自动创建）
```

> `data/` 目录的真实数据（auth.json、备份 JSON）已由 `.gitignore` 排除，不会上传至 GitHub。

---

## ⚡ 快速部署

### 环境要求

- PHP **7.4+**（推荐 8.1+）
- `data/` 目录对 Web 用户可写（首次保存时自动创建）

### 步骤

```bash
# 1. 克隆项目
git clone https://github.com/linbenyi/SeatCard_PHP.git

# 2. 上传到服务器（或直接用宝塔/Nginx面板上传）
scp -r SeatCard_PHP/ user@server:/var/www/html/seatcard/

# 3. 创建 data 目录并授权
mkdir -p /var/www/html/seatcard/data
chmod 755 /var/www/html/seatcard/data
chown www-data:www-data /var/www/html/seatcard/data

# 4. 初始化授权码记录
cp data/auth.example.json data/auth.json

# 5. 浏览器访问
https://yoursite.com/seatcard/
```

### 宝塔面板一键部署

1. 新建站点 → PHP 7.4+
2. 上传所有文件到网站根目录
3. 在「文件」里新建 `data/` 文件夹，权限设为 755
4. 将 `auth.example.json` 复制为 `auth.json`
5. 访问 `/admin.php` 生成第一个授权码

### 修改后台密码

| 文件 | 常量 | 默认值 |
|------|------|--------|
| `admin.php` | `ADMIN_PASS` | `admin888` |
| `dashboard.php` | `DASH_PASS` | `superSC2026` |

### Nginx 配置（禁止直接访问 data 目录）

```nginx
location /seatcard/data/ { deny all; }
```

---

## 🔑 授权码体系

每场婚礼使用一个独立授权码，格式 `YYMMDDXcc`（9位）：

```
260419 A N3
└─日期 └场次 └校验位（CRC32，防枚举）
```

- 同一日期支持 A–Z 共 26 场；扩展格式 `YYMMDDaXcc` 支持 702 场/日
- 分享**只读链接**：去掉末尾 2 位校验位即为查看码（`260419A` → 只能看，不能改）
- 未注册的授权码进入时显示橙色警告，已归档/删除的授权码无法登录

---

## 🗂 后台管理

### `admin.php` — 场次管理

- 生成新场次授权码（选日期/场次字母，自动写入 auth.json）
- 查看所有场次列表（备份数、创建时间、备注）
- 一键进入编辑页 / 删除场次
- 部署配置说明（权限、文件结构、安全提示）

### `dashboard.php` — 总览看板

- 年度日历视图，‹/› 切换年份
- 场次卡片：桌数、宾客数、状态标注
- 一键复制编辑/查看链接
- 状态管理：有效 → 归档 → 删除

---

## 🔒 安全说明

- 服务器上的 JSON 文件为**明文存储**，请勿上传身份证号等隐私数据
- 建议使用 **HTTPS** 部署
- `admin.php` 使用 PHP Session 保护，**务必修改默认密码**
- `data/` 目录建议通过 Nginx/Apache 规则禁止直接浏览

---

## 🛠 技术栈

| 层 | 技术 |
|----|------|
| 后端 | PHP 7.4+，无框架，无数据库，文件存储 |
| 前端 | 原生 JavaScript + SVG，无构建工具 |
| CSS | Tailwind Play CDN（本地 `tw.js`，无外部依赖）|
| 字体 | Noto Serif/Sans SC（fonts.loli.net 国内节点）|
| PDF | 浏览器原生打印，SVG 矢量输出 |
| 校验 | CRC32 → 28字符表双字符映射 |

---

## 📋 版本日志

详见 [CHANGELOG.md](./CHANGELOG.md)

| 版本 | 说明 |
|------|------|
| **V0.24** | iPad/平板触控适配；导入名单功能；双击取消落座（触控）；布局自适应优化 |
| V0.23 | 触控拖拽 V4 系统；双指缩放；自定义分类；Ctrl+Z 40步 |
| V0.19 | dashboard 看板；702场/日扩展授权码；CRC32 校验 |
| V0.16 | Tailwind 本地化；多用户授权码隔离架构 |

---

## 📄 License

MIT License · 欢迎 Fork 和二次开发

---

*SeatCard · 适合婚庆公司、酒店宴会厅、个人婚礼策划使用*  
*目前界面以中文为主，国际化版本待规划*
