<?php
/**
 * YoduPlayer 接口入口：把配置整理成常量后交给 jx.php 处理。
 *
 * @package YoduPlayer
 */
class YoduPlayer_Action extends Typecho_Widget implements Widget_Interface_Do
{
    public function execute()
    {
    }

    public function action()
    {
        $options = Helper::options();
        // 配置还没写入时（激活中断等）不能让接口 500，退回空配置
        try {
            $config = $options->plugin('YoduPlayer');
        } catch (Exception $e) {
            $config = null;
        }

        $data = array();
        if (is_object($config)) {
            if (method_exists($config, 'toArray')) {
                $data = $config->toArray();
            } else {
                $data = get_object_vars($config);
            }
        } elseif (is_array($config)) {
            $data = $config;
        }

        $secret = isset($data['auth']) ? (string) $data['auth'] : '';
        if ($secret === '') {
            // 老配置没有 auth 时给一个与站点绑定的稳定值
            $secret = substr(hash_hmac('sha1', 'yoduplayer', (string) $options->siteUrl), 0, 32);
        }

        $rewrite = $options->rewrite == 0 ? 'index.php/' : '';
        $apiUri = rtrim((string) $options->siteUrl, '/') . '/' . $rewrite . 'yoduapi';

        define('YODU_API_URI', $apiUri);
        define('YODU_SECRET', $secret);
        define('YODU_CACHE_DIR', __DIR__ . '/cache/playlist');
        define('YODU_CACHE_TTL', 86400);
        define('YODU_BOOT', true);

        require __DIR__ . '/jx.php';
    }
}
