<?php
/**
 * 一款清爽的 BGM 播放器，支持 instantclick 页面切换不中断播放。
 *
 * @package YoduPlayer
 * @author OsAtNb
 * @version 2.9.4
 * @link https://github.com/XES8/YoduPlayer
 */
class YoduPlayer_Plugin implements Typecho_Plugin_Interface
{
    const VERSION = '2.9.4';

    /** 插件激活 */
    public static function activate()
    {
        Typecho_Plugin::factory('Widget_Archive')->header = array(__CLASS__, 'header');
        Typecho_Plugin::factory('Widget_Archive')->footer = array(__CLASS__, 'footer');
        Helper::addRoute('yoduapi', '/yoduapi', 'YoduPlayer_Action', 'action');
        self::ensureCacheDir();
    }

    /** 插件禁用 */
    public static function deactivate()
    {
        Helper::removeRoute('yoduapi');
        self::deleteFile();
    }

    // ==================================================================
    // 后台设置
    // ==================================================================

    public static function config(Typecho_Widget_Helper_Form $form)
    {
        // ---------------- 外观 ----------------
        $form->addInput(new Typecho_Widget_Helper_Form_Element_Select(
            'theme',
            array('auto' => '跟随系统', 'light' => '浅色', 'dark' => '深色', 'custom' => '完全自定义'),
            'auto',
            '外观 · 主题模式',
            '「跟随系统」读取访客系统的深色模式；「完全自定义」时面板底色与文字色按下面填写直接生效。'
        ));

        $form->addInput(new Typecho_Widget_Helper_Form_Element_Text(
            'primary', null, '#5b8cff', '外观 · 主色',
            '六位十六进制，例如 #5b8cff。用于渐变、播放按钮与进度条。'
        ));

        $form->addInput(new Typecho_Widget_Helper_Form_Element_Text(
            'secondary', null, '#ff6fa5', '外观 · 副色',
            '渐变里的第二个颜色，例如 #ff6fa5。主色与副色之间生成流动渐变。'
        ));

        $form->addInput(new Typecho_Widget_Helper_Form_Element_Text(
            'angle', null, '135', '外观 · 渐变角度（0–360）',
            '45 斜向右上，135 斜向右下，90 从上到下。'
        ));

        $form->addInput(new Typecho_Widget_Helper_Form_Element_Text(
            'flowSpeed', null, '9', '外观 · 渐变流动速度（秒）',
            '数字越大越慢；填 0 关闭流动，颜色变为静止渐变。'
        ));

        $form->addInput(new Typecho_Widget_Helper_Form_Element_Radio(
            'hueCycle',
            array('0' => '关闭', '1' => '开启'),
            '0',
            '外观 · 色相循环',
            '开启后强调色会绕色环缓慢转动，整块 UI 的颜色一直在变。'
        ));

        $form->addInput(new Typecho_Widget_Helper_Form_Element_Text(
            'hueSpeed', null, '24', '外观 · 色相循环一圈（秒）',
            '仅在色相循环开启时生效，建议 18–40。'
        ));

        $form->addInput(new Typecho_Widget_Helper_Form_Element_Text(
            'panelBg', null, '#ffffff', '外观 · 面板底色',
            '十六进制颜色。浅色主题一般 #ffffff，深色主题可填 #151822。'
        ));

        $form->addInput(new Typecho_Widget_Helper_Form_Element_Text(
            'panelOpacity', null, '72', '外观 · 面板不透明度（0–100）',
            '0 完全透明（只剩模糊与描边），100 完全不透明。60–85 比较好看。'
        ));

        $form->addInput(new Typecho_Widget_Helper_Form_Element_Text(
            'blur', null, '14', '外观 · 毛玻璃模糊（px）',
            '面板背后的背景模糊半径，0 为关闭；移动端建议不超过 16。'
        ));

        $form->addInput(new Typecho_Widget_Helper_Form_Element_Text(
            'radius', null, '18', '外观 · 圆角（px）',
            '面板与列表的圆角，0–40。'
        ));

        $form->addInput(new Typecho_Widget_Helper_Form_Element_Text(
            'shadow', null, '60', '外观 · 阴影强度（0–100）',
            '0 无阴影，100 最重。'
        ));

        $form->addInput(new Typecho_Widget_Helper_Form_Element_Text(
            'textColor', null, '', '外观 · 文字颜色',
            '留空按底色自动取色（深底配浅字 / 浅底配深字）；填十六进制可强制指定。'
        ));

        $form->addInput(new Typecho_Widget_Helper_Form_Element_Text(
            'coverSpeed', null, '9', '外观 · 封面旋转一圈（秒）',
            '播放时封面旋转速度，越大越慢；0 表示不旋转。'
        ));

        $topOptions = array();
        for ($n = 0; $n <= 200; $n += 5) {
            $topOptions[(string) $n] = $n . ' px';
        }
        $form->addInput(new Typecho_Widget_Helper_Form_Element_Select(
            'top', $topOptions, '65', '外观 · 距离顶部间距',
            '播放器按钮距离页面顶部的距离，默认 65px。'
        ));

        $form->addInput(new Typecho_Widget_Helper_Form_Element_Radio(
            'sxj',
            array('0' => '隐藏', '1' => '显示'),
            '0',
            '外观 · 手机端是否显示',
            '窄屏（<768px）下是否显示播放器。选「显示」时按下面的布局呈现。'
        ));

        $form->addInput(new Typecho_Widget_Helper_Form_Element_Select(
            'mobileLayout',
            array('compact' => '紧凑（只留按钮，点开是面板）', 'full' => '完整（和桌面一致）'),
            'compact',
            '外观 · 手机端布局',
            '紧凑模式不占内容区，点按钮才展开面板。'
        ));

        $form->addInput(new Typecho_Widget_Helper_Form_Element_Radio(
            'edgeHide',
            array('0' => '关闭', '1' => '开启'),
            '0',
            '外观 · 贴边自动隐藏',
            '开启后播放器只贴着屏幕右边缘露出一小条，鼠标移到右边缘（或点击它）才滑出来；触屏设备不生效。'
        ));

        // ---------------- 播放行为 ----------------
        $form->addInput(new Typecho_Widget_Helper_Form_Element_Select(
            'loopMode',
            array('order' => '列表循环', 'random' => '随机播放', 'sequence' => '顺序播放（播完停止）', 'single' => '单曲循环'),
            'order',
            '播放 · 播放模式',
            '面板上的模式按钮可在三种模式间切换，这里只是初始值。'
        ));

        $form->addInput(new Typecho_Widget_Helper_Form_Element_Text(
            'volume', null, '68', '播放 · 初始音量（0–100）',
            '访客第一次打开时的音量，之后会记住访客自己调过的值。'
        ));

        $form->addInput(new Typecho_Widget_Helper_Form_Element_Radio(
            'remember',
            array('1' => '记住', '0' => '不记住'),
            '1',
            '播放 · 记住播放进度',
            '把当前歌曲、播放位置、音量存在访客浏览器里，刷新或翻页后接着播。'
        ));

        $form->addInput(new Typecho_Widget_Helper_Form_Element_Radio(
            'mediaSession',
            array('1' => '开启', '0' => '关闭'),
            '1',
            '播放 · 系统媒体控制',
            '锁屏 / 通知栏 / 耳机线控显示歌名并可切歌（需浏览器支持）。'
        ));

        $form->addInput(new Typecho_Widget_Helper_Form_Element_Radio(
            'shortcuts',
            array('1' => '开启', '0' => '关闭'),
            '1',
            '播放 · 键盘快捷键',
            '面板展开时：空格播放/暂停，←/→ 前后 5 秒，↑/↓ 音量，M 静音，Esc 收起。输入框内不生效。'
        ));

        $form->addInput(new Typecho_Widget_Helper_Form_Element_Select(
            'showOn',
            array('all' => '全站', 'index' => '仅首页'),
            'all',
            '播放 · 显示范围',
            '只在首页显示可以避免文章页抢焦点，也能少一次接口请求。'
        ));

        $form->addInput(new Typecho_Widget_Helper_Form_Element_Radio(
            'listOpen',
            array('0' => '点击才展开', '1' => '自动展开'),
            '0',
            '播放 · 列表默认展开',
            '选「自动展开」时，打开播放器面板就会在面板下方直接显示歌单，不用再点列表按钮。'
        ));

        $form->addInput(new Typecho_Widget_Helper_Form_Element_Radio(
            'resume',
            array('1' => '开启', '0' => '关闭'),
            '1',
            '播放 · 跨页自动续播',
            '换到本站其他页面时，自动接着上次的歌曲与进度播放；浏览器若拦截自动播放，会提示点一下继续。'
        ));

        $form->addInput(new Typecho_Widget_Helper_Form_Element_Radio(
            'lyric',
            array('1' => '显示', '0' => '不显示'),
            '1',
            '播放 · 显示歌词',
            '在标题与时长之间显示当前这句歌词（来自歌曲源），没有歌词或获取失败时自动隐藏。'
        ));

        // ---------------- 歌单 ----------------
        $form->addInput(new Typecho_Widget_Helper_Form_Element_Radio(
            'getype',
            array('netease' => '网易云音乐', 'tencent' => 'QQ 音乐'),
            'netease',
            '歌单 · 歌曲源',
            '选好后在下面填对应平台的歌单 id。'
        ));

        $form->addInput(new Typecho_Widget_Helper_Form_Element_Text(
            'gedan', null, '', '歌单 · 歌单 id',
            '填了歌单 id 就忽略「歌曲列表」。网易云 music.163.com/#/playlist?id=7694448425 → 填 7694448425。'
        ));

        $form->addInput(new Typecho_Widget_Helper_Form_Element_Textarea(
            'musicList', null, '', '歌单 · 歌曲列表',
            '两种写法都支持：① 标准 JSON 数组；② 旧写法 {title:"歌名",artist:"歌手",mp3:"音频",cover:"封面"}, 每首用英文逗号分隔。'
        ));

        $form->addInput(new Typecho_Widget_Helper_Form_Element_Textarea(
            'sok', null, '', '歌单 · 追加 CSS',
            '会拼在外观变量之后，可直接覆盖 .bgmplayer 的任意样式。'
        ));

        $authText = new Typecho_Widget_Helper_Form_Element_Text('auth', null, self::defaultAuth(), '', '');
        $authText->setAttribute('class', 'hidden');
        $authText->setAttribute('style', 'display:none');
        $form->addInput($authText);

        // 只在「配置页的 GET 请求」里注入取色器脚本：
        // 激活（?activate=）和保存配置（POST）时不能有任何输出，否则会顶掉跳转
        if (
            isset($_SERVER['REQUEST_METHOD'], $_GET['config'])
            && strtoupper($_SERVER['REQUEST_METHOD']) === 'GET'
            && is_string($_GET['config'])
        ) {
            self::adminEnhance();
        }
    }

    public static function personalConfig(Typecho_Widget_Helper_Form $form)
    {
    }

    /**
     * 把后台里的文本框升级成取色器 / 滑杆。
     * 纯渐进增强：找不到目标元素就静默跳过，不影响手工输入。
     */
    private static function adminEnhance()
    {
        echo <<<'HTML'
<script>
(function () {
  function ready(fn) {
    if (document.readyState !== 'loading') { fn(); }
    else { document.addEventListener('DOMContentLoaded', fn); }
  }
  function field(name) {
    return document.querySelector(
      'input[name="' + name + '"],input[name$="[' + name + ']"],' +
      'textarea[name="' + name + '"],textarea[name$="[' + name + ']"]'
    );
  }
  ready(function () {
    var colors = ['primary', 'secondary', 'panelBg', 'textColor'];
    colors.forEach(function (name) {
      var input = field(name);
      if (!input || input.getAttribute('data-yd') === '1') { return; }
      input.setAttribute('data-yd', '1');
      var picker = document.createElement('input');
      picker.type = 'color';
      picker.value = /^#[0-9a-fA-F]{6}$/.test(input.value) ? input.value : '#5b8cff';
      picker.style.cssText = 'width:38px;height:26px;margin-left:8px;padding:0;border:1px solid #ccd;border-radius:4px;background:none;vertical-align:middle;cursor:pointer';
      picker.addEventListener('input', function () { input.value = picker.value; });
      input.addEventListener('input', function () {
        if (/^#[0-9a-fA-F]{6}$/.test(input.value)) { picker.value = input.value; }
      });
      input.parentNode.insertBefore(picker, input.nextSibling);
    });

    var ranges = {
      angle: [0, 360, 1], flowSpeed: [0, 60, 1], hueSpeed: [4, 120, 1],
      panelOpacity: [0, 100, 1], blur: [0, 40, 1], radius: [0, 40, 1],
      shadow: [0, 100, 1], coverSpeed: [0, 60, 1], volume: [0, 100, 1]
    };
    Object.keys(ranges).forEach(function (name) {
      var input = field(name);
      if (!input || input.getAttribute('data-yd') === '1') { return; }
      var cfg = ranges[name];
      input.setAttribute('data-yd', '1');
      var slider = document.createElement('input');
      slider.type = 'range';
      slider.min = cfg[0];
      slider.max = cfg[1];
      slider.step = cfg[2];
      slider.value = parseInt(input.value, 10) || cfg[0];
      slider.style.cssText = 'width:180px;margin-left:10px;vertical-align:middle';
      var out = document.createElement('span');
      out.textContent = slider.value;
      out.style.cssText = 'margin-left:8px;color:#889;font-size:12px';
      slider.addEventListener('input', function () {
        input.value = slider.value;
        out.textContent = slider.value;
      });
      input.addEventListener('input', function () {
        if (/^-?\d+$/.test(input.value)) {
          slider.value = input.value;
          out.textContent = input.value;
        }
      });
      input.parentNode.insertBefore(slider, input.nextSibling);
      input.parentNode.insertBefore(out, slider.nextSibling);
    });
  });
})();
</script>
HTML;
    }

    // ==================================================================
    // 前台输出
    // ==================================================================

    public static function header()
    {
        if (!self::shouldShow()) {
            return;
        }
        $base = Helper::options()->pluginUrl . '/YoduPlayer';
        echo '<link rel="stylesheet" id="yd-css" href="' . $base . '/css/player.css?v=' . self::assetVersion(__DIR__ . '/css/player.css') . '">' . "\n";
        echo '<style id="yd-vars">' . self::inlineStyle() . '</style>' . "\n";

        $extra = trim(self::get('sok'));
        if ($extra !== '') {
            $extra = str_ireplace('</style', '', $extra);
            echo '<style id="yd-extra-css">' . $extra . '</style>' . "\n";
        }
    }

    public static function footer()
    {
        if (!self::shouldShow()) {
            return;
        }

        $base = Helper::options()->pluginUrl . '/YoduPlayer';
        $cfg = self::settings();
        $songs = self::parseMusicList($cfg['musicList']);

        if (empty($songs) && (string) $cfg['gedan'] === '') {
            $songs = array(array(
                'title' => '未设置歌曲',
                'artist' => '',
                'mp3' => $base . '/images/tixing.mp3',
                'cover' => $base . '/images/loading.svg',
            ));
        }

        $firstCover = !empty($songs) ? $songs[0]['cover'] : $base . '/images/loading.svg';
        $rewrite = Helper::options()->rewrite == 0 ? 'index.php/' : '';
        $apiUrl = rtrim(Helper::options()->siteUrl, '/') . '/' . $rewrite . 'yoduapi';

        $data = array(
            'api' => (string) $cfg['gedan'] !== '' ? $apiUrl : '',
            'auth' => (string) $cfg['auth'],
            'source' => $cfg['getype'] === 'tencent' ? 'tencent' : 'netease',
            'playlist' => (string) $cfg['gedan'],
            'list' => $songs,
            'loop' => in_array($cfg['loopMode'], array('order', 'random', 'sequence', 'single'), true) ? $cfg['loopMode'] : 'order',
            'volume' => self::clamp($cfg['volume'], 0, 100, 68),
            'remember' => $cfg['remember'] === '1',
            'mediaSession' => $cfg['mediaSession'] === '1',
            'shortcuts' => $cfg['shortcuts'] === '1',
            'listOpen' => $cfg['listOpen'] === '1',
            'edgeHide' => $cfg['edgeHide'] === '1',
            'resume' => $cfg['resume'] === '1',
            'lyric' => $cfg['lyric'] === '1',
            'pluginUrl' => $base,
            'fallbackCover' => $base . '/images/loading.svg',
            'mobile' => $cfg['mobileLayout'] === 'compact',
        );

        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

        $html = <<<'HTML'
<div id="bgmplayer" class="bgmplayer{{HUE}}" data-yd-theme="{{THEME}}" data-yd-mobile="{{MOBILE}}" data-yd-hide="{{HIDE}}" data-yd-edge="{{EDGE}}">
<button type="button" class="bgmbuttom" id="ydtoggle" aria-label="打开音乐播放器" aria-expanded="false" aria-controls="bgmpanel">
<svg class="ydicon" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M18 3a1 1 0 00-1.196-.98l-10 2A1 1 0 006 5v9.114A4.369 4.369 0 005 14c-1.657 0-3 .895-3 2s1.343 2 3 2 3-.895 3-2V7.82l8-1.6v5.894A4.37 4.37 0 0015 12c-1.657 0-3 .895-3 2s1.343 2 3 2 3-.895 3-2V3z"/></svg>
<span class="yd-ring" aria-hidden="true"></span>
</button>
<section id="bgmpanel" class="bgmpanel" aria-label="音乐播放器">
<div class="yd-head">
<button type="button" class="yd-cover" id="ydcover" aria-label="播放或暂停"><img id="ydfm" src="{{COVER}}" alt="" width="56" height="56"></button>
<div class="yd-meta"><span class="yd-title" id="ydtitle">未开始播放</span><span class="yd-artist" id="ydartist"></span></div>
<div class="yd-lyric" id="ydlyric" aria-hidden="true"><div class="yd-lyric-inner" id="ydlyricinner"></div></div>
<span class="yd-time" id="ytime">0:00</span>
</div>
<div class="yd-bar" id="ydprogress" role="slider" tabindex="0" aria-label="播放进度" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0" aria-valuetext="0%"><div class="yd-bar-fill" id="jindu"></div><span class="yd-bar-thumb" aria-hidden="true"></span></div>
<div class="yd-foot">
<div class="yd-btns">
<button type="button" id="ydprev" class="yd-btn" aria-label="上一首"></button>
<button type="button" id="ydplay" class="yd-btn yd-btn-main" aria-label="播放"></button>
<button type="button" id="ydnext" class="yd-btn" aria-label="下一首"></button>
<button type="button" id="ydmode" class="yd-btn yd-btn-aux" aria-label="播放模式"></button>
<button type="button" id="ydlist" class="yd-btn yd-btn-aux" aria-label="播放列表" aria-expanded="false" aria-controls="playlist"></button>
</div>
<div class="yd-volume"><button type="button" id="ydmute" class="yd-btn yd-btn-aux" aria-label="静音"></button><input type="range" id="ydvol" class="yd-vol" min="0" max="100" step="1" value="{{VOLUME}}" aria-label="音量"></div>
</div>
<div class="yd-status" id="ydstatus" role="status" aria-live="polite"></div>
</section>
<ul id="playlist" class="yd-list yhidden" aria-label="播放列表"></ul>
<div class="yd-glow" aria-hidden="true"><div class="yd-glow-img" id="yodubg"></div></div>
</div>
HTML;

        $html = strtr($html, array(
            '{{HUE}}' => self::get('hueCycle') === '1' ? ' yd-hue' : '',
            '{{THEME}}' => in_array($cfg['theme'], array('auto', 'light', 'dark', 'custom'), true) ? $cfg['theme'] : 'auto',
            '{{MOBILE}}' => $cfg['mobileLayout'] === 'compact' ? 'compact' : 'full',
            '{{HIDE}}' => $cfg['sxj'] === '0' ? '1' : '0',
            '{{EDGE}}' => $cfg['edgeHide'] === '1' ? '1' : '0',
            '{{COVER}}' => htmlspecialchars($firstCover, ENT_QUOTES, 'UTF-8'),
            '{{VOLUME}}' => (string) self::clamp($cfg['volume'], 0, 100, 68),
        ));

        echo $html;
        echo '<script data-no-instant>window.YODUPLAYER=' . $json . ';</script>' . "\n";
        echo '<script data-no-instant src="' . $base . '/js/player.js?v=' . self::assetVersion(__DIR__ . '/js/player.js') . '"></script>' . "\n";
    }

    // ==================================================================
    // 配置与主题
    // ==================================================================

    private static function defaults()
    {
        return array(
            'theme' => 'auto',
            'primary' => '#5b8cff',
            'secondary' => '#ff6fa5',
            'angle' => '135',
            'flowSpeed' => '9',
            'hueCycle' => '0',
            'hueSpeed' => '24',
            'panelBg' => '#ffffff',
            'panelOpacity' => '72',
            'blur' => '14',
            'radius' => '18',
            'shadow' => '60',
            'textColor' => '',
            'coverSpeed' => '9',
            'top' => '65',
            'sxj' => '0',
            'mobileLayout' => 'compact',
            'loopMode' => '',
            'random' => '0',
            'volume' => '68',
            'remember' => '1',
            'mediaSession' => '1',
            'shortcuts' => '1',
            'showOn' => 'all',
            'listOpen' => '0',
            'edgeHide' => '0',
            'resume' => '1',
            'lyric' => '1',
            'getype' => 'netease',
            'gedan' => '',
            'musicList' => '',
            'sok' => '',
            'auth' => '',
        );
    }

    /** 合并后的配置（老版本缺字段时补默认值） */
    private static function settings()
    {
        static $merged = null;
        if ($merged !== null) {
            return $merged;
        }

        $data = array();
        $opts = null;

        // 插件可能已在「启用」列表里、但配置还没写入（例如激活过程被中断）。
        // 这种情况下 Typecho 的 Options::plugin() 会抛异常，绝不能因此让整站 500，
        // 退回默认值即可，等用户重新保存一次设置就会补上。
        try {
            $opts = Helper::options()->plugin('YoduPlayer');
        } catch (Exception $e) {
            $opts = null;
        }

        if (is_object($opts)) {
            if (method_exists($opts, 'toArray')) {
                $data = $opts->toArray();
            } else {
                $data = get_object_vars($opts);
            }
        } elseif (is_array($opts)) {
            $data = $opts;
        }

        $merged = array_merge(self::defaults(), $data);

        if ((string) $merged['loopMode'] === '') {
            $merged['loopMode'] = (string) $merged['random'] === '1' ? 'random' : 'order';
        }
        if ((string) $merged['auth'] === '') {
            $merged['auth'] = self::defaultAuth();
        }

        return $merged;
    }

    private static function get($key)
    {
        $cfg = self::settings();
        return isset($cfg[$key]) ? (string) $cfg[$key] : '';
    }

    private static function defaultAuth()
    {
        return substr(hash_hmac('sha1', 'yoduplayer', (string) Helper::options()->siteUrl), 0, 32);
    }

    private static function shouldShow()
    {
        // 不要把播放器 HTML 输出到 RSS / Atom 里
        $uri = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '';
        if ($uri !== '' && preg_match('#/feed(/|$|\.)#i', $uri)) {
            return false;
        }
        if (self::get('showOn') !== 'index') {
            return true;
        }
        try {
            $archive = Typecho_Widget::widget('Widget_Archive');
            return $archive instanceof Typecho_Widget && $archive->is('index');
        } catch (Exception $e) {
            return true;
        }
    }

    private static function clamp($value, $min, $max, $fallback)
    {
        if (!is_numeric($value)) {
            return (int) $fallback;
        }
        $value = (int) $value;
        if ($value < $min) {
            return $min;
        }
        if ($value > $max) {
            return $max;
        }
        return $value;
    }

    /** 归一化十六进制颜色，非法时回退 */
    private static function hex($key, $fallback)
    {
        $value = strtolower(trim(self::get($key)));
        if (preg_match('/^#([0-9a-f])([0-9a-f])([0-9a-f])$/', $value, $m)) {
            return '#' . $m[1] . $m[1] . $m[2] . $m[2] . $m[3] . $m[3];
        }
        if (preg_match('/^#?([0-9a-f]{6})$/', $value, $m)) {
            return '#' . $m[1];
        }
        return $fallback;
    }

    private static function rgb($hex)
    {
        $hex = ltrim($hex, '#');
        return hexdec(substr($hex, 0, 2)) . ',' . hexdec(substr($hex, 2, 2)) . ',' . hexdec(substr($hex, 4, 2));
    }

    /** 按底色亮度给出可读的文字颜色 */
    private static function contrastText($hex)
    {
        $hex = ltrim($hex, '#');
        $r = hexdec(substr($hex, 0, 2)) / 255;
        $g = hexdec(substr($hex, 2, 2)) / 255;
        $b = hexdec(substr($hex, 4, 2)) / 255;
        $lum = 0.2126 * $r + 0.7152 * $g + 0.0722 * $b;
        return $lum > 0.55 ? '#161b26' : '#f2f5ff';
    }

    /** 一套完整的 CSS 变量值 */
    private static function palette($mode)
    {
        $cfg = self::settings();
        $primary = self::hex('primary', '#5b8cff');
        $secondary = self::hex('secondary', '#ff6fa5');
        $panelHex = self::hex('panelBg', '#ffffff');
        $override = trim(self::get('textColor')) === '' ? '' : self::hex('textColor', '');

        $bg = ($mode === 'dark' && $panelHex === '#ffffff') ? '#151822' : $panelHex;
        $text = $override !== '' ? $override : self::contrastText($bg);
        $isDark = self::contrastText($bg) === '#f2f5ff';

        return array(
            'primary' => $primary,
            'primaryRgb' => self::rgb($primary),
            'secondary' => $secondary,
            'secondaryRgb' => self::rgb($secondary),
            'angle' => self::clamp($cfg['angle'], 0, 360, 135),
            'flow' => self::clamp($cfg['flowSpeed'], 0, 120, 9),
            'hueSpeed' => self::clamp($cfg['hueSpeed'], 4, 180, 24),
            'radius' => self::clamp($cfg['radius'], 0, 48, 18),
            'blur' => self::clamp($cfg['blur'], 0, 40, 14),
            'shadow' => self::clamp($cfg['shadow'], 0, 100, 60) / 100,
            'cover' => self::clamp($cfg['coverSpeed'], 0, 120, 9),
            'top' => self::clamp($cfg['top'], 0, 400, 65),
            'alpha' => self::clamp($cfg['panelOpacity'], 0, 100, 72) / 100,
            'bg' => $bg,
            'bgRgb' => self::rgb($bg),
            'text' => $text,
            'muted' => $isDark ? 'rgba(242,245,255,.62)' : 'rgba(22,27,38,.58)',
            'line' => $isDark ? 'rgba(242,245,255,.16)' : 'rgba(22,27,38,.10)',
            'shadowRgb' => $isDark ? '0,0,0' : '15,23,42',
        );
    }

    private static function varsBlock($p, $selector = '#bgmplayer')
    {
        return sprintf(
            '%1$s{--yd-primary:%2$s;--yd-primary-rgb:%3$s;--yd-secondary:%4$s;--yd-secondary-rgb:%5$s;'
            . '--yd-angle:%6$ddeg;--yd-flow:%7$ds;--yd-hue-speed:%8$ds;--yd-radius:%9$dpx;'
            . '--yd-blur:%10$dpx;--yd-shadow:%11$.2f;--yd-cover-speed:%12$ds;--yd-top:%13$dpx;'
            . '--yd-bg:%14$s;--yd-bg-rgb:%20$s;--yd-bg-alpha:%15$.2f;--yd-text:%16$s;--yd-muted:%17$s;'
            . '--yd-line:%18$s;--yd-shadow-rgb:%19$s;}',
            $selector,
            $p['primary'],
            $p['primaryRgb'],
            $p['secondary'],
            $p['secondaryRgb'],
            $p['angle'],
            $p['flow'],
            $p['hueSpeed'],
            $p['radius'],
            $p['blur'],
            $p['shadow'],
            $p['cover'],
            $p['top'],
            $p['bg'],
            $p['alpha'],
            $p['text'],
            $p['muted'],
            $p['line'],
            $p['shadowRgb'],
            $p['bgRgb']
        );
    }

    private static function inlineStyle()
    {
        $theme = self::get('theme');
        $css = self::varsBlock(self::palette($theme === 'dark' ? 'dark' : 'light'));
        if ($theme === 'auto') {
            $css .= '@media (prefers-color-scheme:dark){' . self::varsBlock(self::palette('dark')) . '}';
        }
        return $css;
    }

    private static function assetVersion($file)
    {
        return is_file($file) ? (string) filemtime($file) : self::VERSION;
    }

    // ==================================================================
    // 歌单解析
    // ==================================================================

    /** 兼容标准 JSON 与旧版 {title:"",artist:"",mp3:"",cover:""}, 写法 */
    private static function parseMusicList($raw)
    {
        $raw = trim((string) $raw);
        if ($raw === '') {
            return array();
        }

        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            return self::normalizeSongs($decoded);
        }

        $text = trim($raw, " \t\r\n,");
        $text = preg_replace('/([{,]\s*)([A-Za-z_][A-Za-z0-9_]*)\s*:/', '$1"$2":', '[' . $text . ']');
        $text = preg_replace('/,\s*([\]}])/', '$1', (string) $text);
        $decoded = json_decode((string) $text, true);
        if (is_array($decoded)) {
            return self::normalizeSongs($decoded);
        }

        return array();
    }

    private static function normalizeSongs($items)
    {
        $songs = array();
        foreach ($items as $item) {
            if (count($songs) >= 300) {
                break;
            }
            if (!is_array($item)) {
                continue;
            }
            $mp3 = isset($item['mp3']) ? self::safeUrl($item['mp3']) : '';
            if ($mp3 === '') {
                continue;
            }
            $title = isset($item['title']) ? self::plain($item['title']) : '';
            $songs[] = array(
                'title' => $title !== '' ? $title : '未命名',
                'artist' => isset($item['artist']) ? self::plain($item['artist']) : '',
                'mp3' => $mp3,
                'cover' => isset($item['cover']) ? self::safeUrl($item['cover']) : '',
                'lrc' => isset($item['lrc']) ? self::safeUrl($item['lrc']) : '',
            );
        }
        return $songs;
    }

    private static function plain($value)
    {
        return is_scalar($value) ? trim(strip_tags((string) $value)) : '';
    }

    /** 只放行 http(s)、协议相对与站内相对地址 */
    private static function safeUrl($value)
    {
        if (!is_scalar($value)) {
            return '';
        }
        $url = trim((string) $value);
        if ($url === '') {
            return '';
        }
        if (preg_match('#^(https?:)?//[^\s"\']+$#i', $url)) {
            return $url;
        }
        if (preg_match('#^/[^\s"\']*$#', $url)) {
            return $url;
        }
        if (preg_match('#^[\w\-./]+\.[a-z0-9]{2,4}([?#][^\s"\']*)?$#i', $url)) {
            return 'https://' . $url;
        }
        return '';
    }

    // ==================================================================
    // 缓存目录
    // ==================================================================

    public static function ensureCacheDir()
    {
        $dir = __DIR__ . '/cache/playlist';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        $htaccess = dirname($dir) . '/.htaccess';
        if (!is_file($htaccess)) {
            @file_put_contents(
                $htaccess,
                "<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n"
                . "<IfModule !mod_authz_core.c>\nOrder deny,allow\nDeny from all\n</IfModule>\n"
            );
        }
        if (!is_file($dir . '/index.html')) {
            @file_put_contents($dir . '/index.html', '');
        }
    }

    public static function deleteFile()
    {
        $dir = __DIR__ . '/cache/playlist';
        if (!is_dir($dir)) {
            return;
        }
        foreach ((array) glob($dir . '/*.json') as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }
    }
}
