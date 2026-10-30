# YoduPlayer 2.9.4 · 安装、配置与排错

> 一款清爽的 BGM 播放器，支持 instantclick 页面切换不中断播放。
> 作者：OsAtNb　版本：2.9.4　协议：BSD-3-Clause（内置 Meting 为 MIT）

---

## 0. 当前状态与紧急修复

如果站点出现：

```
call_user_func_array(): Argument #1 ($callback) must be a valid callback,
class "YoduPlayer_Plugin" not found
```

含义是：**数据库里插件还是「已启用」，但 `usr/plugins/YoduPlayer/` 目录已经不在了**。Typecho 渲染页面时执行 `Widget_Archive->header` 钩子，找不到 `YoduPlayer_Plugin` 类就抛异常。

### 修复步骤

1. 宝塔 → 文件 → 进入 `/www/wwwroot/mnbt.221328d3/usr/plugins/`
2. 上传 `YoduPlayer-2.7.5-upload.zip`，**就在 plugins 目录里解压**
3. 确认最终路径是：

   ```
   /www/wwwroot/mnbt.221328d3/usr/plugins/YoduPlayer/Plugin.php
   ```

4. 刷新前台，错误立即消失；歌单、颜色等配置都保存在数据库里，不需要重设

### 两个必踩的坑

| 现象 | 原因 | 处理 |
|---|---|---|
| `usr/plugins/YoduPlayer/YoduPlayer/Plugin.php` | 压缩包根目录已经是 `YoduPlayer/`，又手动套了一层 | 把内层文件提到 `YoduPlayer/` 下 |
| `usr/plugins/YoduPlayer-2.7.5/` | 面板按压缩包名解压出了带版本号的目录 | 改名为 `YoduPlayer` |

### 验证

- 浏览器打开 `https://你的域名/usr/plugins/YoduPlayer/css/player.css` → 能下载（200）即目录正确
- 直接打开 `Plugin.php` 会报 `Interface "Typecho_Plugin_Interface" not found`，**这是正常现象**（没走 Typecho 引导），不用管

> 如果确实不想再用这个插件：先在后台「插件」里**禁用**，再删目录。直接删目录就会留下现在这种「启用了但文件没了」的僵尸状态。

---

## 1. 插件概览

| 项目 | 说明 |
|---|---|
| 名称 | YoduPlayer |
| 描述 | 一款清爽的 BGM 播放器，支持 instantclick 页面切换不中断播放。 |
| 作者 | OsAtNb |
| 版本 | 2.9.4 |
| 上游 | [jrotty/YoduPlayer](https://github.com/jrotty/YoduPlayer)（原版 2.6.5），本仓库 [XES8/YoduPlayer](https://github.com/XES8/YoduPlayer) |
| 依赖 | Typecho 1.1 / 1.2，PHP 7.2+（实测 8.3 可用） |

### 主要能力

- **外观全可调**：主色、副色、渐变角度与流速、色相循环、面板底色与透明度、毛玻璃模糊、圆角、阴影、文字色、封面转速、位置与手机端布局
- **播放体验**：列表循环 / 随机 / 顺序播放 / 单曲循环（面板与后台都能切）、可拖动进度条、音量条、键盘快捷键、系统媒体控制（Media Session）
- **跨页续播**：整页跳转后自动接着上次的歌曲与进度（浏览器拦截自动播放时提示点一下）
- **歌词窗**：有歌词时标题/歌手区域变为三行歌词，当前句高亮居中、上下渐隐，逐帧插值滚动（不是 CSS 过渡）
- **PJAX / instantclick 不中断**：换页只重挂 UI、不重建 audio；主题整页刷新时用「跨页续播」兜底
- **贴边隐藏**：停在屏幕右边缘的透明长方体把手，点一下直接出面板，无光晕、无渐变
- **安全加固**：接口参数白名单、缓存文件名哈希化、缓存原子写、接口鉴权、错误输出不污染 JSON

---

## 2. 安装

1. 解压得到 `YoduPlayer/` 目录（**名字不能改**）
2. 上传到 `usr/plugins/`
3. 后台 → 控制台 → 插件 → YoduPlayer → **启用**
4. 进入「设置」，按需配置外观与歌单
5. 前台 `Ctrl+Shift+R` 硬刷新

> 插件目录名必须是 `YoduPlayer`：`pluginUrl`、`/yoduapi` 路由都依赖它。

---

## 3. 升级

1. **备份**旧的 `usr/plugins/YoduPlayer`（挪到 `usr/plugins` 之外）
2. 后台 → 插件 → YoduPlayer → **停用**（会清掉 `cache/playlist` 里的旧歌单缓存）
3. 覆盖文件（保持目录名 `YoduPlayer`）
4. 后台 → **启用**
5. 前台 `Ctrl+Shift+R` 硬刷新

配置保存在数据库（`typecho_options` 的 `plugin:YoduPlayer`），覆盖文件不会丢歌单；但**停用再启用**时，Typecho 会用表单默认值重新写一次配置，所以升级前建议先把歌单 id 记下来。

---

## 4. 后台设置一览

### 外观

| 设置项 | 作用 | 默认 |
|---|---|---|
| 主题模式 | 跟随系统 / 浅色 / 深色 / 完全自定义 | 跟随系统 |
| 主色 / 副色 | 渐变两端 | `#5b8cff` / `#ff6fa5` |
| 渐变角度 | 0–360 度 | 135 |
| 渐变流动速度 | 秒；0 = 静止 | 9 |
| 色相循环 | 强调色绕色环缓慢转动 | 关闭 |
| 色相循环一圈 | 秒 | 24 |
| 面板底色 | 十六进制 | `#ffffff` |
| 面板不透明度 | 0–100 | 72 |
| 毛玻璃模糊 | px | 14 |
| 圆角 | px | 18 |
| 阴影强度 | 0–100 | 60 |
| 文字颜色 | 留空自动取色 | 自动 |
| 封面旋转一圈 | 秒；0 = 不转 | 9 |
| 距离顶部间距 | px | 65 |
| 手机端是否显示 | 隐藏 / 显示 | 隐藏 |
| 手机端布局 | 紧凑（底部抽屉）/ 完整 | 紧凑 |
| 贴边自动隐藏 | 收起为透明长方体把手，点击出面板 | 关闭 |

> 「手机端布局」只有在「手机端是否显示 = 显示」时才有效果；两条都只在 ≤767px 生效。

### 播放

| 设置项 | 作用 | 默认 |
|---|---|---|
| 播放模式 | 列表循环 / 随机播放 / 顺序播放（播完停止）/ 单曲循环 | 列表循环 |
| 初始音量 | 0–100 | 68 |
| 记住播放进度 | 存 localStorage | 开启 |
| 系统媒体控制 | 锁屏 / 耳机线控 | 开启 |
| 键盘快捷键 | 空格、←/→、↑/↓、M、L、Esc | 开启 |
| 显示范围 | 全站 / 仅首页 | 全站 |
| 列表默认展开 | 点击才展开 / 自动展开 | 点击才展开 |
| 跨页自动续播 | 换页后自动接着放 | 开启 |

### 歌单

| 设置项 | 说明 |
|---|---|
| 歌曲源 | 网易云音乐 / QQ 音乐 |
| 歌单 id | 填了就忽略「歌曲列表」。网易云 `music.163.com/#/playlist?id=7694448425` → 填 `7694448425` |
| 歌曲列表 | 支持标准 JSON 数组，或旧写法 `{title:"",artist:"",mp3:"",cover:""},` |
| 追加 CSS | 拼在外观变量之后，可直接覆盖 `.bgmplayer` 样式 |

---

## 5. 接口

```
/yoduapi?server=netease|tencent&type=playlist|song|url|pic|lrc|name|artist&id=...&auth=...
```

- `playlist` / `song` / `name` / `artist`：`auth` 必须等于插件里保存的密钥
- `url` / `pic` / `lrc`：`auth` 为 `hmac_sha1(server + type + id, 密钥)`，由服务端签发给播放器
- 歌单缓存 24 小时，文件名是 `sha256(server|id)`，缓存里只存歌曲引用，**换域名/换 https 后签名地址会自动按新域名重建**
- 缓存目录会自动写入 `.htaccess`（Apache 拒绝直接访问）；Nginx 建议自行加：

  ```nginx
  location ^~ /usr/plugins/YoduPlayer/cache/ { deny all; }
  ```

---

## 6. 常见问题（都是实际踩过的）

| 现象 | 原因 | 处理 |
|---|---|---|
| `Cannot redeclare YoduPlayer_Plugin::config()` | 内部方法名与 Typecho 要求的 `config()` 重名 | 已修（改为 `settings()`） |
| 点激活报 `Server Error`(500) | Typecho 1.2 表单元素构造函数是 `($name, ?array $options, $value, $label, $description)`，旧代码少传了 `$options` | 已修（全部改 5 参数） |
| `插件YoduPlayer的配置信息没有找到` | 插件已写入「已启用」但配置行没写入（激活中断） | 已修（取配置时捕获异常、退回默认值）；恢复文件后**禁用→启用**可补齐 |
| 歌单列表空 / 显示「加载失败」 | 接口返回的原因现在会显示在列表里，例如「歌单涉嫌违规，审核中」 | 平台限制，换歌单；可用 `3778678` 验证播放器是否正常 |
| 响应里混入 `Deprecated: utf8_encode()` | PHP 8.2+ 弃用告警污染 JSON | 已修（接口关错误显示 + 输出缓冲；Meting 内替换为 `mb_convert_encoding`） |
| 列表盖住播放器面板 | 2.7.4 的定位判断写成了「窗口窄 **或** 紧凑布局」 | 已修（看实际 CSS 是否 `position: fixed`） |
| 收起后原位置还有一块光 | 面板背景光晕常亮 | 已修（只在展开时淡入） |
| 边缘那圈彩色光斑 | 按钮外圈 `.yd-ring` 常亮 | 已修（贴边模式关闭光晕） |
| 手机上看不到播放器 | 「手机端是否显示」默认是隐藏 | 改成「显示」 |
| 换页音乐停了 | 主题没开 PJAX，整页刷新 | 开启「跨页自动续播」；要完全无缝需在主题里启用 PJAX/instantclick |
| `class "YoduPlayer_Plugin" not found` | 插件目录被删/改名，但数据库仍标记已启用 | 恢复 `usr/plugins/YoduPlayer/` 目录（见第 0 节） |

---

## 7. 目录结构

```
YoduPlayer/
├── Plugin.php          # 插件入口：配置表单、前台输出、主题变量
├── Action.php          # /yoduapi 路由入口
├── jx.php              # 接口实现：鉴权、缓存、Meting 调用
├── src/
│   └── Meting.php      # Meting 1.5.11（MIT，已修 utf8_encode）
├── css/
│   └── player.css      # 全部样式，颜色走 CSS 变量
├── js/
│   └── player.js       # 播放逻辑、渲染、续播、贴边
├── images/             # 默认封面与提示音
├── cache/
│   ├── .htaccess       # 拒绝直接访问缓存
│   └── playlist/       # 歌单缓存（需可写）
├── README.md
└── CHANGELOG.md
```

静态资源版本号用文件修改时间（`?v=filemtime`），升级后浏览器不会继续用旧 CSS/JS。

---

## 8. 更新记录（2.7.0 → 2.9.4）

- **2.7.0** 外观重做：CSS 变量、玻璃拟态、深浅色；播放体验（续播、媒体键、进度条、快捷键）；接口加固
- **2.7.1** 修 Typecho 1.2 表单元素构造参数导致的激活 500；取色器脚本只在配置页注入
- **2.7.2** 配置缺失时不再抛异常（整站 500 的根因）
- **2.7.3** 修 `utf8_encode` 弃用告警污染 JSON；歌单失败时回传平台原因
- **2.7.4** 歌单列表贴到面板下方；收起不再残留光晕
- **2.7.5** 修定位判断（紧凑布局也生效）、列表默认展开开关、贴边自动隐藏、滚动条内缩、按钮光晕交互显示、跨页继续播放、插件信息改为 XES8
- **2.7.6** 歌词显示（标题与时长之间）
- **2.7.7** 歌词超长改横向滚动
- **2.7.8** 歌词改为多行竖向滚动 + 上下渐隐
- **2.7.9** 歌词滚动改 rAF 逐帧插值
- **2.8.0** 重画播放模式图标；修点模式按钮面板误关；窄面板音量条不再溢出
- **2.8.1** 随机图标换 shuffle；辅助图标统一亮度；鼠标点击不再留聚焦圈
- **2.8.2** 压掉主题自带的按钮聚焦圈
- **2.8.3** 循环图标改圆箭头
- **2.8.4** 去掉模式/静音激活高亮，三个辅助图标颜色统一
- **2.9.0** 新增**顺序播放**模式；四种模式图标改为回环样式
- **2.9.1** 图标放大到 20px；修 PJAX 切页时音乐“暂停一下”（`booted` 守卫 + 补 `pjax:success/complete`）
- **2.9.2** 回环箭头收小
- **2.9.3** 回环改为「开口 + 线头折箭头」结构
- **2.9.4** 随机图标改为曲线交叉箭头

详见插件目录下的 `CHANGELOG.md`。

---

## 9. 回滚

1. 后台 → 插件 → YoduPlayer → 停用
2. 用备份目录覆盖 `usr/plugins/YoduPlayer`
3. 启用 → 重新填写歌单 id → 前台硬刷新

数据库里的配置不会因为换文件而损坏，只有「停用→启用」会让 Typecho 用表单默认值重写一次配置。
