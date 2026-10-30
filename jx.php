<?php
/**
 * YoduPlayer 接口实现（由 Action.php 引入，不可直接访问）。
 *
 * 路由：/yoduapi?server=netease|tencent&type=playlist|song|url|pic|lrc|name|artist&id=xxx&auth=xxx
 *
 * 鉴权：
 *  - playlist / song / name / artist：auth 必须等于插件里保存的密钥
 *  - url / pic / lrc：auth 为 hmac_sha1(server + type + id, 密钥)，由服务端签发给播放器
 *
 * @package YoduPlayer
 */

if (!defined('YODU_BOOT')) {
    header('HTTP/1.1 403 Forbidden');
    exit;
}

// PHP 的 notice / deprecation 不能混进 JSON 响应（PHP 8.2+ 的 utf8_encode 就会触发）
@ini_set('display_errors', '0');
@ini_set('html_errors', '0');
error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE & ~E_WARNING & ~E_USER_DEPRECATED);
ob_start();
$server = isset($_GET['server']) ? strtolower(trim((string) $_GET['server'])) : 'netease';
// Meting 支持这些源；歌单里可能混入别的源，白名单要能容纳
$allowedServers = array('netease', 'tencent', 'xiami', 'kugou', 'baidu', 'kuwo');
if (!in_array($server, $allowedServers, true)) {
    $server = 'netease';
}

$type = isset($_GET['type']) ? strtolower(trim((string) $_GET['type'])) : '';
$id = isset($_GET['id']) ? trim((string) $_GET['id']) : '';

$allowedTypes = array('playlist', 'song', 'name', 'artist', 'url', 'pic', 'lrc');
if (!in_array($type, $allowedTypes, true) || !preg_match('/^[A-Za-z0-9_\-]{1,64}$/', $id)) {
    yodu_fail(400, 'bad request');
}

$auth = isset($_GET['auth']) ? (string) $_GET['auth'] : '';
if (in_array($type, array('url', 'pic', 'lrc'), true)) {
    $expected = hash_hmac('sha1', $server . $type . $id, YODU_SECRET);
    if (!hash_equals($expected, $auth)) {
        yodu_fail(403, 'forbidden');
    }
} elseif (!hash_equals(YODU_SECRET, $auth)) {
    yodu_fail(403, 'forbidden');
}

header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store, max-age=0');

include __DIR__ . '/src/Meting.php';

try {
    $api = new Metowolf\Meting($server);
    $api->format(true);

    switch ($type) {
        case 'playlist':
            yodu_playlist($api, $server, $id);
            break;

        case 'song':
            yodu_song($api, $server, $id);
            break;

        case 'name':
        case 'artist':
            yodu_text_field($api, $type, $id);
            break;

        case 'lrc':
            yodu_lyric($api, $id);
            break;

        case 'url':
            yodu_url($api, $id);
            break;

        case 'pic':
            yodu_pic($api, $id);
            break;

        default:
            yodu_fail(400, 'bad request');
    }
} catch (Exception $e) {
    yodu_fail(502, 'upstream error');
}

// ----------------------------------------------------------------------

/** 丢掉此前所有输出缓冲，保证响应体只有 JSON/文本 */
function yodu_clean_output()
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
}

/** 从 Meting 的原始响应里取一句人话，用于前端提示 */
function yodu_upstream_message($raw)
{
    $data = json_decode((string) $raw, true);
    if (is_array($data)) {
        foreach (array('message', 'msg', 'error') as $key) {
            if (!empty($data[$key]) && is_string($data[$key])) {
                return $data[$key];
            }
        }
    }
    return '';
}

function yodu_fail($code, $message)
{
    yodu_clean_output();
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(array('error' => $message), JSON_UNESCAPED_UNICODE);
    exit;
}

function yodu_json($data)
{
    yodu_clean_output();
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function yodu_text($text)
{
    yodu_clean_output();
    header('Content-Type: text/plain; charset=utf-8');
    echo $text;
    exit;
}

/** 给单曲生成带签名的接口地址 */
function yodu_signed_url($server, $type, $id)
{
    return YODU_API_URI . '?server=' . rawurlencode($server)
        . '&type=' . $type
        . '&id=' . rawurlencode($id)
        . '&auth=' . hash_hmac('sha1', $server . $type . $id, YODU_SECRET);
}

/** 把 Meting 的 playlist/song 结果整理成最小字段，缓存里只存引用不存绝对地址 */
function yodu_refs($songs, $fallbackServer)
{
    $refs = array();
    foreach ($songs as $song) {
        if (!is_array($song) || count($refs) >= 500) {
            continue;
        }
        $refs[] = array(
            'title' => isset($song['name']) ? (string) $song['name'] : '',
            'artist' => (isset($song['artist']) && is_array($song['artist'])) ? implode('/', $song['artist']) : '',
            's' => isset($song['source']) && $song['source'] !== '' ? (string) $song['source'] : $fallbackServer,
            'u' => isset($song['url_id']) ? (string) $song['url_id'] : '',
            'p' => isset($song['pic_id']) ? (string) $song['pic_id'] : '',
        );
    }
    return $refs;
}

/** 把缓存里的引用补上当前域名下的签名地址 */
function yodu_decorate($refs)
{
    $out = array();
    foreach ($refs as $ref) {
        if (!is_array($ref) || !isset($ref['u'])) {
            continue;
        }
        $out[] = array(
            'title' => isset($ref['title']) ? (string) $ref['title'] : '',
            'artist' => isset($ref['artist']) ? (string) $ref['artist'] : '',
            'mp3' => $ref['u'] === '' ? '' : yodu_signed_url($ref['s'], 'url', $ref['u']),
            'cover' => empty($ref['p']) ? '' : yodu_signed_url($ref['s'], 'pic', $ref['p']),
            'lrc' => $ref['u'] === '' ? '' : yodu_signed_url($ref['s'], 'lrc', $ref['u']),
        );
    }
    return $out;
}

function yodu_cache_path($server, $id)
{
    return YODU_CACHE_DIR . '/' . hash('sha256', $server . '|' . $id) . '.json';
}

function yodu_cache_read($file)
{
    if (!is_file($file)) {
        return null;
    }
    if (time() - (int) filemtime($file) >= YODU_CACHE_TTL) {
        return null;
    }
    $raw = @file_get_contents($file);
    if ($raw === false || $raw === '') {
        return null;
    }
    $data = json_decode($raw, true);
    return is_array($data) ? $data : null;
}

function yodu_cache_write($file, $data)
{
    $dir = dirname($file);
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    $json = json_encode($data, JSON_UNESCAPED_UNICODE);
    if ($json === false) {
        return;
    }
    $tmp = $file . '.' . getmypid() . '.tmp';
    if (@file_put_contents($tmp, $json, LOCK_EX) !== false) {
        if (!@rename($tmp, $file)) {
            @unlink($tmp);
        }
    }
}

/** 歌单取不到时，把上游的原因原样告诉前端 */
function yodu_playlist_unavailable($api)
{
    $message = yodu_upstream_message(isset($api->raw) ? $api->raw : '');
    if ($message === '') {
        $message = '歌单暂时无法获取（可能为空、被设为私密，或被平台限制）';
    }
    yodu_json(array('error' => 'playlist unavailable', 'message' => $message));
}

function yodu_playlist($api, $server, $id)
{
    $cacheFile = yodu_cache_path($server, $id);
    $refs = yodu_cache_read($cacheFile);

    if ($refs === null) {
        $raw = $api->playlist($id);
        if (!is_string($raw) || trim($raw) === '' || trim($raw) === '[]') {
            yodu_playlist_unavailable($api);
        }
        $decoded = json_decode($raw, true);
        if (!is_array($decoded) || empty($decoded)) {
            yodu_playlist_unavailable($api);
        }
        $refs = yodu_refs($decoded, $server);
        if (empty($refs)) {
            yodu_playlist_unavailable($api);
        }
        yodu_cache_write($cacheFile, $refs);
    }

    yodu_json(yodu_decorate($refs));
}

function yodu_song($api, $server, $id)
{
    $raw = $api->song($id);
    if (!is_string($raw) || trim($raw) === '' || trim($raw) === '[]') {
        yodu_fail(404, 'song not found');
    }
    $decoded = json_decode($raw, true);
    if (!is_array($decoded) || empty($decoded)) {
        yodu_fail(404, 'song not found');
    }
    yodu_json(yodu_decorate(yodu_refs($decoded, $server)));
}

function yodu_text_field($api, $field, $id)
{
    $raw = $api->song($id);
    if (!is_string($raw) || trim($raw) === '') {
        yodu_fail(404, 'song not found');
    }
    $decoded = json_decode($raw, true);
    if (!is_array($decoded) || empty($decoded)) {
        yodu_fail(404, 'song not found');
    }
    $song = $decoded[0];
    if ($field === 'name') {
        yodu_text(isset($song['name']) ? (string) $song['name'] : '');
    }
    $artist = (isset($song['artist']) && is_array($song['artist'])) ? implode('/', $song['artist']) : '';
    yodu_text($artist);
}

function yodu_lyric($api, $id)
{
    $raw = $api->lyric($id);
    if (!is_string($raw) || trim($raw) === '') {
        yodu_fail(404, 'lyric not found');
    }
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        yodu_fail(404, 'lyric not found');
    }

    $lyric = isset($data['lyric']) ? (string) $data['lyric'] : '';
    $tlyric = isset($data['tlyric']) ? (string) $data['tlyric'] : '';

    if ($lyric === '') {
        yodu_text('[00:00.00]这似乎是一首纯音乐呢，请尽情欣赏它吧！');
    }
    if ($tlyric === '') {
        yodu_text($lyric);
    }

    // 把翻译按时间轴并到原文行尾
    $map = array();
    foreach (explode("\n", $tlyric) as $line) {
        if ($line === '' || strpos($line, ']') === false) {
            continue;
        }
        $parts = explode(']', $line, 2);
        $text = trim(preg_replace('/\s\s+/', ' ', $parts[1]));
        if ($text !== '') {
            $map[$parts[0]] = $text;
        }
    }
    $merged = array();
    foreach (explode("\n", $lyric) as $line) {
        if ($line === '' || strpos($line, ']') === false) {
            continue;
        }
        $key = substr($line, 0, strpos($line, ']'));
        if (isset($map[$key]) && $map[$key] !== '//') {
            $line .= ' (' . $map[$key] . ')';
            unset($map[$key]);
        }
        $merged[] = $line;
    }
    yodu_text(implode("\n", $merged));
}

function yodu_url($api, $id)
{
    $raw = $api->url($id, 320);
    if (!is_string($raw) || trim($raw) === '') {
        yodu_fail(404, 'url not found');
    }
    $data = json_decode($raw, true);
    $url = is_array($data) && isset($data['url']) ? (string) $data['url'] : '';
    if ($url === '' || !preg_match('#^https?://#i', $url)) {
        yodu_fail(404, 'url not found');
    }
    header('Location: ' . $url, true, 302);
    exit;
}

function yodu_pic($api, $id)
{
    $raw = $api->pic($id, 90);
    if (!is_string($raw) || trim($raw) === '') {
        yodu_fail(404, 'pic not found');
    }
    $data = json_decode($raw, true);
    $url = is_array($data) && isset($data['url']) ? (string) $data['url'] : '';
    if ($url === '' || !preg_match('#^https?://#i', $url)) {
        yodu_fail(404, 'pic not found');
    }
    header('Location: ' . $url, true, 302);
    exit;
}
