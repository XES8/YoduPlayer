/*!
 * YoduPlayer 2.7.0 — 播放器逻辑
 * 只依赖 window.YODUPLAYER（由 Plugin.php 输出），不依赖 jQuery。
 * 状态存在 window.__YODU_STATE，instantclick 换页后只重挂 UI，不打断播放。
 */
(function () {
  'use strict';

  var CFG = window.YODUPLAYER;
  if (!CFG || typeof CFG !== 'object') {
    return;
  }

  var KEY = 'yoduplayer:v1';
  var MAX_SKIP = 3;

  var ICON = {
    play: '<svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M6.3 3.4A1 1 0 004.8 4.3v11.4a1 1 0 001.5.9l9.1-5.7a1 1 0 000-1.8L6.3 3.4z"/></svg>',
    pause: '<svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><rect x="5" y="3.5" width="3.4" height="13" rx="1"/><rect x="11.6" y="3.5" width="3.4" height="13" rx="1"/></svg>',
    prev: '<svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M8.4 14.8A1 1 0 0110 14v-2.8l5.4 3.6A1 1 0 0017 14V6a1 1 0 00-1.6-.8L10 8.8V6a1 1 0 00-1.6-.8l-6 4a1 1 0 000 1.6l6 4z"/></svg>',
    next: '<svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M4.6 5.2A1 1 0 003 6v8a1 1 0 001.6.8L10 11.2V14a1 1 0 001.6.8l6-4a1 1 0 000-1.6l-6-4A1 1 0 0010 6v2.8L4.6 5.2z"/></svg>',
    list: '<svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M3 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1z" clip-rule="evenodd"/></svg>',
    sequence: '<svg class="yd-ico-line" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19.9 10.4V13.6A4.6 4.6 0 0 1 15.3 18.2H8.7A4.6 4.6 0 0 1 4.1 13.6V10.4A4.6 4.6 0 0 1 8.7 5.8H14.6"/><path d="M14.6 3.9 16.8 5.8 14.6 7.7"/></svg>',
    order: '<svg class="yd-ico-line" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19.9 10.4V13.6A4.6 4.6 0 0 1 15.3 18.2H8.9"/><path d="M8.9 16.3 6.7 18.2 8.9 20.1"/><path d="M4.1 13.6V10.4A4.6 4.6 0 0 1 8.7 5.8H14.6"/><path d="M14.6 3.9 16.8 5.8 14.6 7.7"/></svg>',
    random: '<svg class="yd-ico-line" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 4l3 3l-3 3"/><path d="M18 20l3-3l-3-3"/><path d="M3 7h3a5 5 0 0 1 5 5a5 5 0 0 0 5 5h5"/><path d="M3 17h3a5 5 0 0 0 5-5a5 5 0 0 1 5-5h5"/></svg>',
    single: '<svg class="yd-ico-line" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19.9 10.4V13.6A4.6 4.6 0 0 1 15.3 18.2H8.7A4.6 4.6 0 0 1 4.1 13.6V10.4A4.6 4.6 0 0 1 8.7 5.8H14.6"/><path d="M14.6 3.9 16.8 5.8 14.6 7.7"/><text x="12" y="14.8" text-anchor="middle" font-size="8.4" font-weight="700" fill="currentColor" stroke="none">1</text></svg>',
    mute: '<svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M9 3.5L5.5 6H3a1 1 0 00-1 1v6a1 1 0 001 1h2.5L9 16.5a1 1 0 001.5-.9V4.4A1 1 0 009 3.5zM13.3 7.3a1 1 0 00-1.4 1.4L13 10l-1.1 1.3a1 1 0 001.4 1.4L14.5 12l1.2 1.7a1 1 0 001.4-1.4L16 11l1.1-1.3a1 1 0 00-1.4-1.4L14.5 10l-1.2-1.7z"/></svg>',
    vol: '<svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M9 3.5L5.5 6H3a1 1 0 00-1 1v6a1 1 0 001 1h2.5L9 16.5a1 1 0 001.5-.9V4.4A1 1 0 009 3.5z"/><path d="M12.5 6.5a1 1 0 011.4 0 5 5 0 010 7 1 1 0 11-1.4-1.4 3 3 0 000-4.2 1 1 0 010-1.4z"/></svg>'
  };

  var S = window.__YODU_STATE = window.__YODU_STATE || {
    audio: null,
    list: null,
    state: 'idle',
    index: 0,
    loop: CFG.loop || 'order',
    volume: null,
    muted: false,
    pendingSeek: null,
    errCount: 0,
    skipStreak: 0,
    root: null,
    el: {},
    docBound: false,
    lastSave: 0,
    errorMessage: '',
    gestureArmed: false,
    lyrics: [],
    lyricIndex: -1,
    lyricCache: {},
    currentLrc: '',
    lyricY: null,
    lyricTargetY: 0,
    lyricRaf: 0,
    booted: false
  };

  // ------------------------------------------------------------------
  // 小工具
  // ------------------------------------------------------------------

  function $(root, sel) {
    return root ? root.querySelector(sel) : null;
  }

  function fmt(seconds) {
    if (!isFinite(seconds) || seconds < 0) {
      return '0:00';
    }
    var m = Math.floor(seconds / 60);
    var s = Math.floor(seconds % 60);
    return m + ':' + (s < 10 ? '0' : '') + s;
  }

  function clamp(value, min, max) {
    return Math.max(min, Math.min(max, value));
  }

  function status(text, isError) {
    var el = S.el.status;
    if (!el) {
      return;
    }
    el.textContent = text || '';
    el.className = 'yd-status' + (text ? ' show' : '') + (isError ? ' error' : '');
    if (S.root && S.root.classList.contains('bgmon')) {
      window.requestAnimationFrame(positionList);
    }
  }

  function loadStore() {
    if (!CFG.remember) {
      return null;
    }
    try {
      var raw = window.localStorage.getItem(KEY);
      return raw ? JSON.parse(raw) : null;
    } catch (e) {
      return null;
    }
  }

  function saveStore(force) {
    if (!CFG.remember || !S.audio) {
      return;
    }
    var now = Date.now();
    if (!force && now - S.lastSave < 4000) {
      return;
    }
    S.lastSave = now;
    try {
      window.localStorage.setItem(KEY, JSON.stringify({
        i: S.index,
        t: S.audio.currentTime || 0,
        v: S.volume,
        l: S.loop,
        m: S.muted,
        p: !S.audio.paused
      }));
    } catch (e) { /* 隐私模式等场景忽略 */ }
  }

  // ------------------------------------------------------------------
  // 音频
  // ------------------------------------------------------------------

  function audio() {
    if (!S.audio) {
      var a = new Audio();
      a.preload = 'metadata';
      a.addEventListener('timeupdate', onTime);
      a.addEventListener('loadedmetadata', onMeta);
      a.addEventListener('ended', onEnded);
      a.addEventListener('error', onError);
      a.addEventListener('play', function () { playingUI(true); saveStore(true); });
      a.addEventListener('playing', function () { S.skipStreak = 0; saveStore(true); });
      a.addEventListener('pause', function () { playingUI(false); saveStore(true); });
      S.audio = a;
    }
    return S.audio;
  }

  function onMeta() {
    var a = S.audio;
    if (S.pendingSeek !== null && isFinite(a.duration)) {
      a.currentTime = clamp(S.pendingSeek, 0, Math.max(0, a.duration - 1));
      S.pendingSeek = null;
    }
    syncUI();
  }

  function onTime() {
    var a = S.audio;
    var d = a.duration;
    if (!S.el.bar || !isFinite(d) || d <= 0) {
      return;
    }
    var pct = clamp(a.currentTime / d * 100, 0, 100);
    S.el.fill.style.width = pct + '%';
    S.el.thumb.style.left = pct + '%';
    S.el.time.textContent = fmt(a.currentTime);
    S.el.bar.setAttribute('aria-valuenow', String(Math.round(pct)));
    S.el.bar.setAttribute('aria-valuetext', Math.round(pct) + '%');
    updateLyric(a.currentTime);
    saveStore(false);
  }

  function onEnded() {
    if (S.loop === 'single') {
      S.audio.currentTime = 0;
      S.audio.play().catch(function () {});
      return;
    }
    step(1, false);
  }

  function onError() {
    S.skipStreak = (S.skipStreak || 0) + 1;
    if (S.skipStreak <= MAX_SKIP) {
      status('这首播放失败，自动跳下一首', true);
      step(1, true);
    } else {
      status('播放列表里有多首无法播放，请检查音源', true);
    }
  }

  // ------------------------------------------------------------------
  // 播放控制
  // ------------------------------------------------------------------

  function load(index, autoplay) {
    var list = S.list || [];
    var song = list[index];
    if (!song) {
      return;
    }
    var a = audio();
    S.index = index;
    a.src = song.mp3;
    a.load();
    S.pendingSeek = null;
    paintMeta(song);
    markList();
    status('', false);
    loadLyrics(song);
    if (autoplay) {
      a.play().then(function () {
        playingUI(true);
      }).catch(function () {
        playingUI(false);
      });
    }
    saveStore(true);
  }

  function toggle() {
    var a = audio();
    if (!a.src) {
      load(S.index, true);
      return;
    }
    if (a.paused) {
      a.play().then(function () { playingUI(true); }).catch(function () {
        status('浏览器拦截了自动播放，请再点一次', true);
      });
    } else {
      a.pause();
    }
  }

  function step(delta, manual) {
    var list = S.list || [];
    if (!list.length) {
      return;
    }
    var next;
    if (S.loop === 'sequence') {
      var seq = S.index + delta;
      if (seq < 0) {
        seq = 0;
      }
      if (seq >= list.length) {
        // 顺序播放：放到最后一首就停下，不再回头
        S.audio.pause();
        S.audio.currentTime = 0;
        S.lyricIndex = -1;
        return;
      }
      load(seq, true);
      return;
    }
    if (S.loop === 'random') {
      next = Math.floor(Math.random() * list.length);
      if (list.length > 1 && next === S.index) {
        next = (next + 1) % list.length;
      }
    } else if (S.loop === 'single' && !manual) {
      next = S.index;
    } else {
      next = (S.index + delta + list.length) % list.length;
    }
    load(next, true);
  }

  function cycleLoop() {
    var order = ['order', 'random', 'sequence', 'single'];
    var i = order.indexOf(S.loop);
    S.loop = order[(i + 1) % order.length];
    paintLoop();
    saveStore(true);
  }

  function setVolume(value, remember) {
    var a = audio();
    var v = clamp(Number(value), 0, 100);
    a.volume = v / 100;
    S.volume = v;
    if (v > 0) {
      S.muted = false;
    }
    paintVolume();
    if (remember) {
      saveStore(true);
    }
  }

  function toggleMute() {
    S.muted = !S.muted;
    S.audio.muted = S.muted;
    paintVolume();
    saveStore(true);
  }

  // ------------------------------------------------------------------
  // 界面
  // ------------------------------------------------------------------

  function paintMeta(song) {
    if (!song) {
      return;
    }
    if (S.el.title) {
      S.el.title.textContent = song.title || '未命名';
    }
    if (S.el.artist) {
      S.el.artist.textContent = song.artist || '';
    }
    if (S.el.cover) {
      S.el.cover.removeAttribute('data-fallback');
      S.el.cover.src = song.cover || CFG.fallbackCover;
    }
    if (S.el.glow) {
      S.el.glow.style.backgroundImage = song.cover ? 'url("' + song.cover + '")' : 'none';
    }
    if (CFG.mediaSession && 'mediaSession' in navigator && 'MediaMetadata' in window) {
      try {
        navigator.mediaSession.metadata = new window.MediaMetadata({
          title: song.title || '',
          artist: song.artist || '',
          artwork: song.cover ? [{ src: song.cover, sizes: '512x512' }] : []
        });
      } catch (e) { /* 老浏览器忽略 */ }
    }
  }

  /** 解析 LRC：[mm:ss.xx]歌词 */
  function parseLrc(text) {
    var out = [];
    var lines = String(text || '').split(/\r?\n/);
    var re = /\[(\d{1,3}):(\d{1,2})(?:[.:](\d{1,3}))?\]/g;
    for (var i = 0; i < lines.length; i++) {
      var line = lines[i];
      var times = [];
      var m;
      re.lastIndex = 0;
      while ((m = re.exec(line)) !== null) {
        var min = parseInt(m[1], 10) || 0;
        var sec = parseInt(m[2], 10) || 0;
        var frac = m[3] ? parseInt(m[3], 10) : 0;
        var fracSec = m[3] ? (String(m[3]).length === 3 ? frac / 1000 : frac / 100) : 0;
        times.push(min * 60 + sec + fracSec);
      }
      if (!times.length) {
        continue;
      }
      var content = line.replace(re, '').trim();
      if (content === '') {
        continue;
      }
      // 过滤作词/作曲/编曲这类制作信息
      if (/^(作词|作曲|编曲|词|曲|制作人|制作|出品人|出品|监制|策划|企划|统筹|宣发|营销|发行|版权|混音|录音|音频|母带|吉他|贝斯|鼓|打击乐|键盘|钢琴|弦乐|和声|编写|封面|设计|摄影|编辑|审核|OP|SP)\s*[:：]/i.test(content)) {
        continue;
      }
      for (var j = 0; j < times.length; j++) {
        out.push({ t: times[j], s: content });
      }
    }
    out.sort(function (a, b) { return a.t - b.t; });
    return out;
  }

  /** 拉取并缓存歌词 */
  function loadLyrics(song) {
    S.lyrics = [];
    S.lyricIndex = -1;
    S.currentLrc = song && song.lrc ? song.lrc : '';
    renderLyricLines();
    if (!CFG.lyric || !S.currentLrc) {
      return;
    }
    var url = S.currentLrc;
    if (S.lyricCache[url]) {
      S.lyrics = S.lyricCache[url];
      renderLyricLines();
      return;
    }
    window.fetch(url, { credentials: 'same-origin' }).then(function (res) {
      if (!res.ok) {
        throw new Error('HTTP ' + res.status);
      }
      return res.text();
    }).then(function (text) {
      var parsed = parseLrc(text);
      S.lyricCache[url] = parsed;
      if (S.currentLrc === url) {
        S.lyrics = parsed;
        renderLyricLines();
      }
    }).catch(function () {
      S.lyricCache[url] = [];
    });
  }

  /** 把歌词铺成竖排，由 CSS 负责上下滚动与两端渐隐 */
  function renderLyricLines() {
    var inner = S.el.lyricInner;
    var head = S.root ? S.root.querySelector('.yd-head') : null;
    if (!inner) {
      return;
    }
    inner.innerHTML = '';
    inner.style.transform = '';
    S.lyricIndex = -1;
    S.lyricY = null;

    if (!S.lyrics.length) {
      if (head) {
        head.classList.remove('has-lyric');
      }
      return;
    }

    var frag = document.createDocumentFragment();
    for (var i = 0; i < S.lyrics.length; i++) {
      var line = document.createElement('div');
      line.className = 'yd-lyric-line';
      line.textContent = S.lyrics[i].s;
      frag.appendChild(line);
    }
    inner.appendChild(frag);
    if (head) {
      head.classList.add('has-lyric');
    }
    updateLyric(S.audio ? S.audio.currentTime : 0, true);
  }

  /** 按播放进度把当前行滚到中间 */
  function updateLyric(time, force) {
    var inner = S.el.lyricInner;
    var wrap = S.el.lyric;
    if (!inner || !wrap || !S.lyrics.length) {
      return;
    }
    var idx = S.lyricIndex;
    while (idx + 1 < S.lyrics.length && S.lyrics[idx + 1].t <= time + 0.15) {
      idx++;
    }
    while (idx >= 0 && S.lyrics[idx].t > time + 0.15) {
      idx--;
    }
    if (idx === S.lyricIndex && !force) {
      return;
    }
    S.lyricIndex = idx;

    var nodes = inner.children;
    var active = idx >= 0 ? idx : 0;
    for (var i = 0; i < nodes.length; i++) {
      nodes[i].className = 'yd-lyric-line' + (i === active ? ' active' : '');
    }

    var lineH = nodes.length ? nodes[0].offsetHeight : 16;
    var viewH = wrap.clientHeight || 48;
    S.lyricTargetY = viewH / 2 - lineH / 2 - Math.max(0, idx) * lineH;
    ensureLyricLoop();
  }

  /** 用 rAF 插值追赶目标位置，滑起来是连续的而不是一跳一跳 */
  function ensureLyricLoop() {
    if (S.lyricRaf) {
      return;
    }

    var prefersReduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var step = function () {
      var inner = S.el.lyricInner;
      if (!inner) {
        S.lyricRaf = 0;
        return;
      }
      if (S.lyricY === null) {
        S.lyricY = S.lyricTargetY;
      }
      var diff = S.lyricTargetY - S.lyricY;
      if (Math.abs(diff) < 0.3) {
        S.lyricY = S.lyricTargetY;
        inner.style.transform = 'translateY(' + S.lyricY.toFixed(2) + 'px)';
        S.lyricRaf = 0;
        return;
      }
      S.lyricY += diff * 0.18;
      inner.style.transform = 'translateY(' + S.lyricY.toFixed(2) + 'px)';
      S.lyricRaf = window.requestAnimationFrame(step);
    };

    if (prefersReduce) {
      step();
      S.lyricY = S.lyricTargetY;
      return;
    }
    S.lyricRaf = window.requestAnimationFrame(step);
  }
  function playingUI(playing) {
    if (!S.root) {
      return;
    }
    S.root.classList.toggle('is-playing', !!playing);
    if (S.el.play) {
      S.el.play.innerHTML = playing ? ICON.pause : ICON.play;
      S.el.play.setAttribute('aria-label', playing ? '暂停' : '播放');
    }
    if (S.el.toggle) {
      S.el.toggle.classList.toggle('is-playing', !!playing);
    }
  }

  function paintLoop() {
    if (!S.el.mode) {
      return;
    }
    S.el.mode.innerHTML = ICON[S.loop] || ICON.order;
    var labels = {
      order: '列表循环',
      random: '随机播放',
      sequence: '顺序播放',
      single: '单曲循环'
    };
    var label = labels[S.loop] || labels.order;
    S.el.mode.setAttribute('aria-label', '播放模式：' + label);
    S.el.mode.setAttribute('title', label);
    // 不再用颜色区分模式，图标本身已经能表达，保持和右侧按钮一致
    S.el.mode.classList.remove('on');
  }

  function paintVolume() {
    if (!S.el.vol) {
      return;
    }
    S.el.vol.value = String(S.volume === null ? 68 : S.volume);
    if (S.el.mute) {
      S.el.mute.innerHTML = S.muted ? ICON.mute : ICON.vol;
      S.el.mute.setAttribute('aria-label', S.muted ? '取消静音' : '静音');
      S.el.mute.classList.remove('on');
    }
  }

  function syncUI() {
    if (!S.root) {
      return;
    }
    playingUI(S.audio && !S.audio.paused);
    paintLoop();
    paintVolume();
    var song = (S.list || [])[S.index];
    if (song) {
      paintMeta(song);
    }
  }

  function markList() {
    var ul = S.el.list;
    if (!ul) {
      return;
    }
    Array.prototype.forEach.call(ul.children, function (li) {
      var idx = Number(li.getAttribute('data-i'));
      var on = idx === S.index;
      li.classList.toggle('yd-playing', on);
      li.setAttribute('aria-selected', on ? 'true' : 'false');
    });
    var active = ul.querySelector('.yd-playing');
    if (active && !ul.classList.contains('yhidden')) {
      var top = active.offsetTop;
      if (top < ul.scrollTop || top > ul.scrollTop + ul.clientHeight - 40) {
        ul.scrollTop = Math.max(0, top - 40);
      }
    }
  }

  /** 让歌单列表贴在面板下方，并根据剩余视口高度限制它的高度 */
  function positionList() {
    var ul = S.el.list;
    var panel = S.el.panel;
    if (!ul || !panel || !S.root || ul.classList.contains('yhidden')) {
      return;
    }
    // 只有当 CSS 真的把列表变成「固定底部抽屉」时才跳过；
    // 不能按「配置是紧凑布局」判断——紧凑布局在宽屏下并不会生效
    var fixedByCss = false;
    try {
      fixedByCss = window.getComputedStyle(ul).position === 'fixed';
    } catch (e) {
      fixedByCss = false;
    }
    if (fixedByCss) {
      ul.style.maxHeight = '';
      S.root.style.removeProperty('--yd-list-offset');
      return;
    }
    var offset = Math.round(panel.offsetHeight) + 8;
    S.root.style.setProperty('--yd-list-offset', offset + 'px');
    var rootTop = S.root.getBoundingClientRect().top;
    var viewportH = window.innerHeight || document.documentElement.clientHeight || 0;
    var available = viewportH - (rootTop + 58 + offset) - 16;
    // 严格不超过视口：空间不够就矮一点，绝不溢出屏幕
    var maxHeight = Math.max(0, Math.min(420, available));
    ul.style.maxHeight = maxHeight + 'px';
  }

  function renderList() {
    var ul = S.el.list;
    if (!ul) {
      return;
    }
    ul.innerHTML = '';

    if (S.state === 'loading') {
      ul.appendChild(tip('正在加载播放列表…'));
      return;
    }
    if (S.state === 'error') {
      var retry = tip((S.errorMessage ? S.errorMessage + ' · ' : '') + '点此重试');
      retry.addEventListener('click', function () { loadList(true); });
      ul.appendChild(retry);
      return;
    }
    if (!S.list || !S.list.length) {
      ul.appendChild(tip('播放列表是空的'));
      return;
    }

    var frag = document.createDocumentFragment();
    S.list.forEach(function (song, i) {
      var li = document.createElement('li');
      li.className = 'yd-lib' + (i === S.index ? ' yd-playing' : '');
      li.setAttribute('data-i', String(i));
      li.setAttribute('role', 'option');
      li.setAttribute('aria-selected', i === S.index ? 'true' : 'false');

      var num = document.createElement('span');
      num.className = 'anum';
      num.textContent = (i + 1 < 10 ? '0' : '') + (i + 1);

      var title = document.createElement('span');
      title.className = 'yd-song';
      title.textContent = song.title || '未命名';

      li.appendChild(num);
      li.appendChild(title);

      if (song.artist) {
        var artist = document.createElement('span');
        artist.className = 'artist';
        artist.textContent = song.artist;
        li.appendChild(artist);
      }
      frag.appendChild(li);
    });
    ul.appendChild(frag);

    if (!ul.classList.contains('yhidden')) {
      window.requestAnimationFrame(positionList);
    }
  }

  function tip(text) {
    var li = document.createElement('li');
    li.className = 'yd-list-tip';
    li.textContent = text;
    return li;
  }

  // ------------------------------------------------------------------
  // 数据
  // ------------------------------------------------------------------

  function loadList(force) {
    if (!force && S.state === 'ready') {
      return Promise.resolve(S.list);
    }

    if (!CFG.api) {
      S.list = (CFG.list || []).slice();
      S.state = 'ready';
      renderList();
      return Promise.resolve(S.list);
    }

    S.state = 'loading';
    renderList();
    status('', false);

    var url = CFG.api
      + '?server=' + encodeURIComponent(CFG.source || 'netease')
      + '&type=playlist&id=' + encodeURIComponent(CFG.playlist || '')
      + '&auth=' + encodeURIComponent(CFG.auth || '');

    var timer = null;
    var controller = ('AbortController' in window) ? new AbortController() : null;
    if (controller) {
      timer = window.setTimeout(function () { controller.abort(); }, 15000);
    }

    return window.fetch(url, {
      credentials: 'same-origin',
      signal: controller ? controller.signal : undefined
    }).then(function (res) {
      if (res.status === 403) {
        throw new Error('接口鉴权失败，请到后台重新保存一次插件设置');
      }
      if (!res.ok) {
        throw new Error('接口返回 HTTP ' + res.status);
      }
      return res.json();
    }).then(function (data) {
      if (timer) { window.clearTimeout(timer); }
      if (data && data.error) {
        throw new Error(data.message || '歌单暂时无法获取');
      }
      if (!Array.isArray(data) || !data.length) {
        throw new Error('歌单是空的');
      }
      S.list = data;
      S.state = 'ready';
      S.errorMessage = '';
      renderList();
      return S.list;
    }).catch(function (err) {
      if (timer) { window.clearTimeout(timer); }
      S.state = 'error';
      S.errorMessage = (err && err.message) ? err.message : '加载失败';
      status(S.errorMessage, true);
      renderList();
      return null;
    });
  }

  // ------------------------------------------------------------------
  // 挂载与事件
  // ------------------------------------------------------------------

  function mount() {
    var root = document.getElementById('bgmplayer');
    if (!root) {
      return;
    }
    var same = S.root === root;
    S.root = root;

    S.el = {
      root: root,
      panel: $(root, '#bgmpanel'),
      toggle: $(root, '#ydtoggle'),
      cover: $(root, '#ydfm'),
      glow: $(root, '#yodubg'),
      title: $(root, '#ydtitle'),
      artist: $(root, '#ydartist'),
      time: $(root, '#ytime'),
      bar: $(root, '#ydprogress'),
      fill: $(root, '#jindu'),
      thumb: $(root, '.yd-bar-thumb'),
      play: $(root, '#ydplay'),
      prev: $(root, '#ydprev'),
      next: $(root, '#ydnext'),
      mode: $(root, '#ydmode'),
      listBtn: $(root, '#ydlist'),
      list: $(root, '#playlist'),
      vol: $(root, '#ydvol'),
      mute: $(root, '#ydmute'),
      status: $(root, '#ydstatus'),
      lyric: $(root, '#ydlyric'),
      lyricInner: $(root, '#ydlyricinner')
    };

    if (!S.el.play) {
      return;
    }

    if (S.el.play.getAttribute('data-bound') !== '1') {
      S.el.play.setAttribute('data-bound', '1');
      bindControls();
    }

    if (S.el.listBtn.getAttribute('data-bound') !== '1') {
      S.el.listBtn.setAttribute('data-bound', '1');
      bindList();
    }

    if (S.el.vol.getAttribute('data-bound') !== '1') {
      S.el.vol.setAttribute('data-bound', '1');
      bindVolume();
    }

    if (S.el.cover && S.el.cover.getAttribute('data-bound') !== '1') {
      S.el.cover.setAttribute('data-bound', '1');
      S.el.cover.addEventListener('error', function () {
        if (S.el.cover.getAttribute('data-fallback') === '1') {
          return;
        }
        S.el.cover.setAttribute('data-fallback', '1');
        S.el.cover.src = CFG.fallbackCover;
      });
    }

    if (!same) {
      renderList();
      syncUI();
      if (S.lyrics && S.lyrics.length) {
        renderLyricLines();
      }
    }
  }

  function bindControls() {
    S.el.play.innerHTML = ICON.play;
    S.el.play.addEventListener('click', toggle);

    if (S.el.toggle) {
      S.el.toggle.addEventListener('click', function (e) {
        e.stopPropagation();
        setOpen(!S.root.classList.contains('bgmon'));
      });
    }

    if (S.el.cover) {
      S.el.cover.addEventListener('click', function (e) {
        e.preventDefault();
        toggle();
      });
    }

    if (S.el.prev) {
      S.el.prev.innerHTML = ICON.prev;
      S.el.prev.addEventListener('click', function () { step(-1, true); });
    }
    if (S.el.next) {
      S.el.next.innerHTML = ICON.next;
      S.el.next.addEventListener('click', function () { step(1, true); });
    }
    if (S.el.mode) {
      S.el.mode.addEventListener('click', function (e) {
        e.stopPropagation();
        cycleLoop();
      });
    }

    var bar = S.el.bar;
    if (bar) {
      bar.addEventListener('pointerdown', function (e) {
        if (e.button !== undefined && e.button !== 0) {
          return;
        }
        S.root.classList.add('is-seeking');
        if (bar.setPointerCapture) {
          bar.setPointerCapture(e.pointerId);
        }
        seekTo(e);
        e.preventDefault();
      });
      bar.addEventListener('pointermove', function (e) {
        if (S.root.classList.contains('is-seeking')) {
          seekTo(e);
          e.preventDefault();
        }
      });
      var stop = function (e) {
        S.root.classList.remove('is-seeking');
        if (bar.releasePointerCapture && e.pointerId !== undefined) {
          try { bar.releasePointerCapture(e.pointerId); } catch (err) { /* noop */ }
        }
        saveStore(true);
      };
      bar.addEventListener('pointerup', stop);
      bar.addEventListener('pointercancel', stop);
      bar.addEventListener('keydown', function (e) {
        if (e.key === 'ArrowLeft' || e.key === 'ArrowRight') {
          e.preventDefault();
          jump((e.key === 'ArrowLeft' ? -1 : 1) * 5);
        }
      });
    }

    if (S.el.time) {
      S.el.time.textContent = '0:00';
    }
  }

  function bindList() {
    S.el.listBtn.innerHTML = ICON.list;
    S.el.listBtn.addEventListener('click', function (e) {
      e.stopPropagation();
      var ul = S.el.list;
      if (!ul) {
        return;
      }
      var hidden = ul.classList.toggle('yhidden');
      S.el.listBtn.setAttribute('aria-expanded', hidden ? 'false' : 'true');
      if (!hidden) {
        setOpen(true);
        markList();
        positionList();
      }
    });

    if (S.el.list) {
      S.el.list.addEventListener('click', function (e) {
        e.stopPropagation();
        var li = e.target.closest ? e.target.closest('li[data-i]') : null;
        if (!li) {
          return;
        }
        var idx = Number(li.getAttribute('data-i'));
        if (isFinite(idx)) {
          load(idx, true);
        }
      });
    }
  }

  function bindVolume() {
    if (S.el.mute) {
      S.el.mute.innerHTML = ICON.vol;
      S.el.mute.addEventListener('click', toggleMute);
    }
    S.el.vol.addEventListener('input', function () {
      setVolume(this.value, true);
    });
    S.el.vol.addEventListener('change', function () {
      setVolume(this.value, true);
    });
  }

  function setOpen(open) {
    if (!S.root) {
      return;
    }
    S.root.classList.toggle('bgmon', open);
    if (S.el.toggle) {
      S.el.toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    }

    // 后台选了「自动展开」：面板一开就把歌单铺在下面
    if (open && CFG.listOpen && S.el.list && S.el.list.classList.contains('yhidden')) {
      S.el.list.classList.remove('yhidden');
      if (S.el.listBtn) {
        S.el.listBtn.setAttribute('aria-expanded', 'true');
      }
      window.requestAnimationFrame(positionList);
    }
    if (!open && S.el.list && !S.el.list.classList.contains('yhidden')) {
      S.el.list.classList.add('yhidden');
      if (S.el.listBtn) {
        S.el.listBtn.setAttribute('aria-expanded', 'false');
      }
    }
  }

  function seekTo(e) {
    var a = S.audio;
    var bar = S.el.bar;
    if (!a || !bar || !isFinite(a.duration) || a.duration <= 0) {
      return;
    }
    var rect = bar.getBoundingClientRect();
    var x = (e.clientX !== undefined ? e.clientX : 0) - rect.left;
    var ratio = clamp(x / rect.width, 0, 1);
    a.currentTime = ratio * a.duration;
    S.el.fill.style.width = (ratio * 100) + '%';
    S.el.thumb.style.left = (ratio * 100) + '%';
    S.el.time.textContent = fmt(a.currentTime);
  }

  function jump(seconds) {
    var a = S.audio;
    if (!a || !isFinite(a.duration)) {
      return;
    }
    a.currentTime = clamp(a.currentTime + seconds, 0, a.duration);
  }

  /** 浏览器拦截自动播放时，等用户第一次点击/按键再恢复 */
  function armGestureResume() {
    if (S.gestureArmed) {
      return;
    }
    S.gestureArmed = true;
    var handler = function (e) {
      if (S.root && e.target && S.root.contains(e.target)) {
        return; // 播放器自己的按钮交给它自己处理
      }
      document.removeEventListener('pointerdown', handler, true);
      document.removeEventListener('keydown', handler, true);
      S.gestureArmed = false;
      if (S.audio && S.audio.paused) {
        S.audio.play().then(function () {
          playingUI(true);
          status('', false);
        }).catch(function () {});
      }
    };
    document.addEventListener('pointerdown', handler, true);
    document.addEventListener('keydown', handler, true);
  }

  /** 新页面加载后，接着上次的歌曲与进度播放 */
  function resumeAfterReload() {
    var a = audio();
    if (!a || !a.src) {
      return;
    }
    var attempt = a.play();
    if (attempt && typeof attempt.then === 'function') {
      attempt.then(function () {
        playingUI(true);
        status('', false);
      }).catch(function () {
        armGestureResume();
        status('已恢复上次进度，点一下继续播放', false);
      });
    }
  }

  function bindDocument() {
    if (S.docBound) {
      return;
    }
    S.docBound = true;

    document.addEventListener('click', function (e) {
      if (!S.root) {
        return;
      }
      // 点击处理里会重写按钮 innerHTML，事件目标可能已脱离 DOM，
      // 所以用 composedPath 判断是否点在播放器内部
      var path = typeof e.composedPath === 'function' ? e.composedPath() : [];
      if (path.indexOf(S.root) !== -1 || (e.target && S.root.contains(e.target))) {
        return;
      }
      setOpen(false);
    });

    document.addEventListener('keydown', function (e) {
      if (!CFG.shortcuts || !S.root || !S.root.classList.contains('bgmon')) {
        return;
      }
      var tag = (e.target && e.target.tagName || '').toLowerCase();
      if (tag === 'input' || tag === 'textarea' || tag === 'select' || (e.target && e.target.isContentEditable)) {
        return;
      }
      if (e.key === 'Escape') {
        setOpen(false);
      } else if (e.key === ' ') {
        e.preventDefault();
        toggle();
      } else if (e.key === 'ArrowLeft') {
        e.preventDefault();
        jump(-5);
      } else if (e.key === 'ArrowRight') {
        e.preventDefault();
        jump(5);
      } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        setVolume((S.volume === null ? 68 : S.volume) + 5, true);
      } else if (e.key === 'ArrowDown') {
        e.preventDefault();
        setVolume((S.volume === null ? 68 : S.volume) - 5, true);
      } else if (e.key === 'm' || e.key === 'M') {
        toggleMute();
      } else if (e.key === 'l' || e.key === 'L') {
        if (S.el.listBtn) {
          S.el.listBtn.click();
        }
      }
    });

    document.addEventListener('visibilitychange', function () {
      if (document.visibilityState === 'hidden') {
        saveStore(true);
      }
    });


    window.addEventListener('resize', function () {
      positionList();
      if (S.audio) {
        updateLyric(S.audio.currentTime, true);
      }
    });
    window.addEventListener('pagehide', function () { saveStore(true); });
    window.addEventListener('beforeunload', function () { saveStore(true); });
  }

  function bindMediaSession() {
    if (!CFG.mediaSession || !('mediaSession' in navigator)) {
      return;
    }
    var ms = navigator.mediaSession;
    var handlers = {
      play: function () { S.audio.play().catch(function () {}); },
      pause: function () { S.audio.pause(); },
      previoustrack: function () { step(-1, true); },
      nexttrack: function () { step(1, true); },
      seekbackward: function (d) { jump(-(d.seekOffset || 10)); },
      seekforward: function (d) { jump(d.seekOffset || 10); }
    };
    Object.keys(handlers).forEach(function (name) {
      try {
        ms.setActionHandler(name, handlers[name]);
      } catch (e) { /* 不支持的 action 忽略 */ }
    });
    try {
      ms.setActionHandler('seekto', function (d) {
        if (typeof d.seekTime === 'number' && S.audio) {
          S.audio.currentTime = d.seekTime;
        }
      });
    } catch (e) { /* noop */ }
  }

  // ------------------------------------------------------------------
  // 启动
  // ------------------------------------------------------------------

  function boot() {
    // PJAX / 主题重复执行脚本时，这里不能再走一遍初始化，
    // 否则会把 audio.src 重置，导致音乐“暂停一下”
    if (S.booted) {
      mount();
      if (S.list && S.list.length) {
        renderList();
      }
      syncUI();
      if (S.lyrics && S.lyrics.length) {
        renderLyricLines();
      }
      return;
    }
    S.booted = true;

    var saved = loadStore();
    if (saved) {
      if (saved.l === 'order' || saved.l === 'random' || saved.l === 'sequence' || saved.l === 'single') {
        S.loop = saved.l;
      }
      if (typeof saved.v === 'number') {
        S.volume = saved.v;
      }
      S.muted = !!saved.m;
    }
    if (S.volume === null) {
      S.volume = CFG.volume === undefined ? 68 : CFG.volume;
    }

    var a = audio();
    a.volume = clamp(S.volume, 0, 100) / 100;
    a.muted = S.muted;

    bindDocument();
    bindMediaSession();
    mount();

    loadList().then(function (list) {
      if (!list || !list.length) {
        if (!S.errorMessage) {
          status('没有可播放的曲目', true);
        }
        return;
      }
      var index = 0;
      if (saved && typeof saved.i === 'number' && saved.i >= 0 && saved.i < list.length) {
        index = saved.i;
      }
      load(index, false);
      if (saved && typeof saved.t === 'number' && saved.t > 2) {
        S.pendingSeek = saved.t;
      }
      if (saved && saved.p === true && CFG.resume) {
        resumeAfterReload();
      }
    });
  }

  function onNavigate() {
    mount();
    if (S.list && S.state === 'ready') {
      renderList();
      syncUI();
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }

  document.addEventListener('instantclick:newpage', onNavigate);
  document.addEventListener('pjax:end', onNavigate);
  document.addEventListener('pjax:success', onNavigate);
  document.addEventListener('pjax:complete', onNavigate);
  document.addEventListener('turbo:load', onNavigate);
  if (window.InstantClick && typeof window.InstantClick.on === 'function') {
    window.InstantClick.on('change', onNavigate);
  }
})();
