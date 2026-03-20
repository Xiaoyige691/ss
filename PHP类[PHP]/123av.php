<?php
/**
 * 123AV.FUN T4 源（TVBox/影视壳兼容版）
 */
error_reporting(0);
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

define('HOST', 'https://123av.fun');
define('UA',   'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36');

$CLASSES = [
    ['type_id' => '1', 'type_name' => '最新推荐'],
    ['type_id' => '2', 'type_name' => '最多播放'],
    ['type_id' => '3', 'type_name' => '最新发布'],
    ['type_id' => '4', 'type_name' => '最多收藏'],
    ['type_id' => '5', 'type_name' => '最多评论'],
    ['type_id' => '6', 'type_name' => '本周短片'],
    ['type_id' => '7', 'type_name' => '本月短片'],
    ['type_id' => '8', 'type_name' => '本周长片'],
    ['type_id' => '9', 'type_name' => '长视频'],
];

$CAT_MAP = [
    '1' => '',
    '2' => 'view-count/sort-desc',
    '3' => 'publish-time/sort-desc',
    '4' => 'favorite-count/sort-desc',
    '5' => 'comment-count/sort-desc',
    '6' => 'list/short/week',
    '7' => 'list/short/month',
    '8' => 'list/long/week',
    '9' => 'explore',
];

// ── HTTP ─────────────────────────────────────────────────────
function http_get($url) {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_HTTPHEADER     => [
            'User-Agent: ' . UA,
            'Accept: text/html,*/*;q=0.9',
            'Accept-Language: zh-CN,zh;q=0.9',
            'Referer: ' . HOST . '/',
        ],
    ]);
    $body = curl_exec($ch);
    curl_close($ch);
    return $body ?: '';
}

// ── 图片代理 ─────────────────────────────────────────────────
function fix_pic($url) {
    if (!$url) return '';
    $self = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http')
          . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['SCRIPT_NAME'];
    return $self . '?ac=img&url=' . urlencode($url);
}

function do_img($url) {
    if (!$url) { http_response_code(400); exit; }
    $host = parse_url($url, PHP_URL_HOST);
    if (!$host || (strpos($host, '123av.fun') === false && strpos($host, 'static.123av.fun') === false)) {
        http_response_code(403); exit;
    }
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_HTTPHEADER     => ['User-Agent: ' . UA, 'Referer: ' . HOST . '/'],
    ]);
    $body = curl_exec($ch);
    $info = curl_getinfo($ch);
    curl_close($ch);
    if (!$body || $info['http_code'] !== 200) { http_response_code(404); exit; }
    header('Content-Type: ' . ($info['content_type'] ?: 'image/jpeg'));
    header('Cache-Control: public, max-age=86400');
    echo $body;
    exit;
}

// ── 构建分类 URL ─────────────────────────────────────────────
function build_url($t, $pg) {
    global $CAT_MAP;
    $pg   = max(1, (int)$pg);
    $path = isset($CAT_MAP[$t]) ? $CAT_MAP[$t] : $t;
    if ($path === '') {
        return $pg > 1 ? HOST . '/page-' . $pg : HOST . '/';
    }
    return $pg > 1
        ? HOST . '/' . ltrim($path, '/') . '/page-' . $pg
        : HOST . '/' . ltrim($path, '/');
}

// ── 解析列表 ─────────────────────────────────────────────────
function parse_list($html) {
    $list = [];
    if (!$html) return $list;

    $parts = explode('href="/detail/', $html);
    $seen  = [];

    for ($i = 1; $i < count($parts); $i++) {
        $chunk = $parts[$i];

        $vid = '/detail/' . strstr($chunk, '"', true);
        if (!$vid || isset($seen[$vid])) continue;
        $seen[$vid] = true;

        $block = substr($chunk, 0, 1500);

        // 封面
        $pic = '';
        if (preg_match('#<img[^>]+src="(https://static\.123av\.fun/[^"]+)"#i', $block, $pm))
            $pic = $pm[1];
        if (!$pic) continue;

        // 标题
        $name = '';
        if (preg_match('#<img[^>]+alt="([^"]+)"#i', $block, $nm))
            $name = html_entity_decode(trim($nm[1]), ENT_QUOTES, 'UTF-8');
        if (!$name || trim($name) === '') $name = basename($vid);

        // 时长
        $dur = '';
        if (preg_match('#video-date[^>]*>\s*([\d:]+)\s*<#i', $block, $dm))
            $dur = trim($dm[1]);

        $list[] = [
            'vod_id'      => $vid,
            'vod_name'    => $name,
            'vod_pic'     => fix_pic($pic),
            'vod_remarks' => $dur,
        ];
    }

    return $list;
}

// ── 首页 ─────────────────────────────────────────────────────
function do_home() {
    global $CLASSES;
    $html = http_get(HOST . '/');
    return [
        'class'   => $CLASSES,
        'filters' => new stdClass(),
        'list'    => parse_list($html),
    ];
}

// ── 分类列表 ─────────────────────────────────────────────────
function do_category($t, $pg) {
    $pg   = max(1, (int)$pg);
    $url  = build_url($t, $pg);
    $html = http_get($url);
    return [
        'page'      => $pg,
        'pagecount' => $pg + 1,
        'limit'     => 30,
        'total'     => 9999,
        'list'      => parse_list($html),
    ];
}

// ── 搜索 ─────────────────────────────────────────────────────
function do_search($wd, $pg) {
    $pg   = max(1, (int)$pg);
    $url  = HOST . '/search?q=' . urlencode($wd) . ($pg > 1 ? '&page=' . $pg : '');
    $html = http_get($url);
    return [
        'page'      => $pg,
        'pagecount' => $pg + 1,
        'limit'     => 30,
        'list'      => parse_list($html),
    ];
}

// ── 详情 ─────────────────────────────────────────────────────
function do_detail($ids) {
    $results = [];
    foreach ($ids as $vid) {
        $vid  = trim($vid);
        if (!$vid) continue;

        $url  = HOST . $vid;
        $html = http_get($url);
        if (!$html) continue;

        // 标题
        $title = '';
        if (preg_match('#<meta property="og:title"[^>]+content="([^"]+)"#i', $html, $m))
            $title = html_entity_decode($m[1], ENT_QUOTES, 'UTF-8');
        if (!$title && preg_match('#<title>([^<]+)</title>#i', $html, $m))
            $title = html_entity_decode(trim($m[1]), ENT_QUOTES, 'UTF-8');

        // 封面
        $pic = '';
        if (preg_match('#"thumbnailUrl":"([^"]+)"#', $html, $m))
            $pic = $m[1];
        if (!$pic && preg_match('#<meta property="og:image"[^>]+content="([^"]+)"#i', $html, $m))
            $pic = $m[1];

        // 简介
        $desc = '';
        if (preg_match('#<meta name="description"[^>]+content="([^"]+)"#i', $html, $m))
            $desc = html_entity_decode($m[1], ENT_QUOTES, 'UTF-8');

        // 日期
        $date = '';
        if (preg_match('#"uploadDate":"(\d{4}-\d{2}-\d{2})#', $html, $m))
            $date = $m[1];

        // 播放地址（直接存 m3u8，此类 CDN 不带 token）
        $m3u8 = '';
        if (preg_match('#"contentUrl":"([^"]+\.m3u8[^"]*)"#', $html, $m))
            $m3u8 = $m[1];
        if (!$m3u8 && preg_match('#data-src="([^"]+\.m3u8[^"]*)"#', $html, $m))
            $m3u8 = $m[1];

        $twitter = '';
        if (preg_match('#data-twitter="(https://video\.twimg\.com[^"]+\.m3u8[^"]*)"#', $html, $m))
            $twitter = $m[1];

        $play_parts = [];
        if ($m3u8)    $play_parts[] = '主线$' . $m3u8;
        if ($twitter) $play_parts[] = 'Twitter$' . $twitter;
        if (empty($play_parts)) continue;

        $results[] = [
            'vod_id'        => $vid,
            'vod_name'      => $title ?: $vid,
            'vod_pic'       => fix_pic($pic),
            'vod_content'   => $desc,
            'vod_remarks'   => $date,
            'vod_play_from' => '123AV',
            'vod_play_url'  => implode('#', $play_parts),
        ];
    }
    return ['list' => $results];
}

// ── 路由 ─────────────────────────────────────────────────────
$ac = $_GET['ac'] ?? '';
$pg = $_GET['pg'] ?? '1';

// 图片代理
if ($ac === 'img') do_img($_GET['url'] ?? '');

// 播放解析
if (isset($_GET['play'])) {
    echo json_encode([
        'parse'  => 0,
        'url'    => $_GET['play'],
        'header' => ['User-Agent' => UA, 'Referer' => HOST . '/'],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// 搜索
if (isset($_GET['wd'])) {
    echo json_encode(do_search($_GET['wd'], $pg), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// 首页
if (!$ac) {
    echo json_encode(do_home(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// 影视壳路由：ac=detail&t=分类ID 或 ac=detail&ids=视频ID
if ($ac === 'detail') {
    if (isset($_GET['t'])) {
        echo json_encode(do_category($_GET['t'], $pg), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    } elseif (isset($_GET['ids'])) {
        echo json_encode(do_detail(explode(',', $_GET['ids'])), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    } elseif (isset($_GET['id'])) {
        echo json_encode(do_detail([$_GET['id']]), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
    exit;
}

// TVBox 标准路由
if ($ac === 'videolist' || $ac === 'list') {
    echo json_encode(do_category($_GET['t'] ?? '1', $pg), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// 兜底
echo json_encode(do_home(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
