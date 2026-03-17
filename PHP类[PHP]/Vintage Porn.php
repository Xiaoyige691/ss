<?php
require_once __DIR__ . '/lib/spider.php';

class Spider extends BaseSpider {
    
    private $host;
    private $customHeaders;
    
    public function getName() {
        return "复古片";
    }

    public function init($extend = "") {
        $this->host = "https://vintagepornfun.com";
        $this->customHeaders = array(
            "User-Agent" => "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36",
            "Referer" => $this->host,
            "Origin" => $this->host,
            "Connection" => "keep-alive"
        );
    }

    private function _fetch($url, $headers = null) {
        $reqHeaders = $headers ? $headers : $this->customHeaders;
        $html = $this->fetch($url, array('headers' => $reqHeaders));
        return $html;
    }

    private function _generateRandomString($length = 10) {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }
        return $randomString;
    }

    private function _resolve_myvidplay($url) {
        try {
            $embed = str_replace("/d/", "/e/", $url);
            if (strpos($embed, 'd000d.com') !== false) {
                $embed = str_replace('d000d.com', 'myvidplay.com', $embed);
            }
            if (strpos($embed, 'doood.com') !== false) {
                $embed = str_replace('doood.com', 'myvidplay.com', $embed);
            }
            
            $parsedUrl = parse_url($embed);
            $host = $parsedUrl['scheme'] . '://' . $parsedUrl['host'];
            
            $hReq = array(
                "User-Agent" => $this->customHeaders['User-Agent'],
                "Referer" => $this->host
            );
            
            $r = $this->fetch($embed, array('headers' => $hReq));
            if (!$r) {
                return array('parse' => 1, 'url' => $url, 'header' => $hReq);
            }
            
            $m = array();
            preg_match('/\/pass_md5\/[^\'"]+/', $r, $m);
            if (empty($m)) {
                return array('parse' => 1, 'url' => $url, 'header' => $hReq);
            }
            
            $hReq["Referer"] = $embed;
            $prefix = trim($this->fetch($host . $m[0], array('headers' => $hReq)));
            
            if (substr($prefix, 0, 4) !== "http") {
                return array('parse' => 1, 'url' => $url, 'header' => $hReq);
            }
            
            $parts = explode("/", $m[0]);
            $token = end($parts);
            $rnd = $this->_generateRandomString(10);
            
            $finalHeaders = array(
                'User-Agent' => $this->customHeaders['User-Agent'],
                'Referer' => $host . '/',
                'Connection' => 'keep-alive'
            );
            
            return array(
                'parse' => 0,
                'url' => $prefix . $rnd . '?token=' . $token,
                'header' => $finalHeaders
            );
        } catch (Exception $e) {
            return array('parse' => 1, 'url' => $url, 'header' => $this->customHeaders);
        }
    }

    public function homeContent($filter) {
        $classes = array(
            array("type_name" => "最新更新", "type_id" => "latest"),
            array("type_name" => "70年代", "type_id" => "70s-porn"),
            array("type_name" => "80年代", "type_id" => "80s-porn"),
            array("type_name" => "亚洲经典", "type_id" => "asian-vintage-porn"),
            array("type_name" => "欧洲经典", "type_id" => "euro-porn-movies"),
            array("type_name" => "日本经典", "type_id" => "japanese-vintage-porn"),
            array("type_name" => "法国经典", "type_id" => "french-vintage-porn"),
            array("type_name" => "德国经典", "type_id" => "german-vintage-porn"),
            array("type_name" => "意大利经典", "type_id" => "italian-vintage-porn"),
            array("type_name" => "经典影片", "type_id" => "classic-porn-movies")
        );
        
        $sortConf = array(
            "key" => "order",
            "name" => "排序",
            "value" => array(
                array("n" => "默认", "v" => ""),
                array("n" => "最新", "v" => "date"),
                array("n" => "随机", "v" => "rand"),
                array("n" => "标题", "v" => "title"),
                array("n" => "热度", "v" => "comment_count")
            )
        );
        
        $tagConf = array(
            "key" => "tag",
            "name" => "标签",
            "value" => array(
                array("n" => "全部", "v" => ""),
                array("n" => "70年代", "v" => "70s-porn"),
                array("n" => "80年代", "v" => "80s-porn"),
                array("n" => "90年代", "v" => "90s-porn"),
                array("n" => "肛交", "v" => "anal-sex"),
                array("n" => "亚洲", "v" => "asian"),
                array("n" => "大胸", "v" => "big-boobs"),
                array("n" => "金发", "v" => "blonde"),
                array("n" => "经典", "v" => "classic"),
                array("n" => "喜剧", "v" => "comedy"),
                array("n" => "绿帽", "v" => "cuckold"),
                array("n" => "黑人", "v" => "ebony"),
                array("n" => "欧洲", "v" => "european"),
                array("n" => "法国", "v" => "french"),
                array("n" => "德国", "v" => "german"),
                array("n" => "群交", "v" => "group-sex"),
                array("n" => "多毛", "v" => "hairy-porn"),
                array("n" => "跨种族", "v" => "interracial"),
                array("n" => "意大利", "v" => "italian"),
                array("n" => "女同", "v" => "lesbian"),
                array("n" => "熟女", "v" => "milf"),
                array("n" => "乱交", "v" => "orgy"),
                array("n" => "户外", "v" => "public-sex"),
                array("n" => "复古", "v" => "retro"),
                array("n" => "少女", "v" => "teen-sex"),
                array("n" => "3P", "v" => "threesome"),
                array("n" => "老片", "v" => "vintage-porn"),
                array("n" => "偷窥", "v" => "voyeur")
            )
        );
        
        $filters = array();
        foreach ($classes as $item) {
            $filters[$item['type_id']] = array($sortConf, $tagConf);
        }
        
        return array("class" => $classes, "filters" => $filters);
    }

    public function homeVideoContent() {
        return array("list" => array());
    }

    public function categoryContent($tid, $pg = 1, $filter = array(), $extend = array()) {
        if ($tid == "latest") {
            $url = ($pg == 1) ? $this->host : $this->host . "/page/" . $pg . "/";
        } else {
            $base = $this->host . "/category/" . $tid;
            $url = ($pg == 1) ? $base . "/" : $base . "/page/" . $pg . "/";
        }
        
        $queryParts = array();
        if (isset($extend['order']) && $extend['order']) {
            $queryParts[] = "orderby=" . $extend['order'];
        }
        if (isset($extend['tag']) && $extend['tag']) {
            $queryParts[] = "tag=" . $extend['tag'];
        }
        
        if (!empty($queryParts)) {
            $sep = strpos($url, '?') !== false ? '&' : '?';
            $url .= $sep . implode('&', $queryParts);
        }

        return $this->_get_list($url, intval($pg));
    }

    private function _get_list($url, $page = 1) {
        $videos = array();
        $html = $this->_fetch($url);
        if ($html) {
            $articles = array();
            preg_match_all('/<article[^>]*>(.*?)<\/article>/s', $html, $articles);
            if (isset($articles[1])) {
                foreach ($articles[1] as $item) {
                    $aMatch = array();
                    if (!preg_match('/<a[^>]*href=["\']([^"\']+)["\']/', $item, $aMatch)) {
                        continue;
                    }
                    $href = $aMatch[1];
                    
                    $pic = "";
                    $imgMatch = array();
                    if (preg_match('/<img[^>]*data-src=["\']([^"\']+)["\']/', $item, $imgMatch)) {
                        $pic = $imgMatch[1];
                    } elseif (preg_match('/<img[^>]*src=["\']([^"\']+)["\']/', $item, $imgMatch)) {
                        $pic = $imgMatch[1];
                    }
                    
                    if ($pic && substr($pic, 0, 4) !== "http") {
                        $pic = $this->host . $pic;
                    }
                    
                    $name = "";
                    $headMatch = array();
                    if (preg_match('/class="entry-header"[^>]*>(.*?)<\/div>/s', $item, $headMatch)) {
                        $name = strip_tags($headMatch[1]);
                    } else {
                        $titleMatch = array();
                        if (preg_match('/title=["\']([^"\']+)["\']/', $item, $titleMatch)) {
                            $name = $titleMatch[1];
                        }
                    }
                    $name = trim($name);
                    
                    $remarks = "";
                    $remMatch = array();
                    if (preg_match('/class="rating-bar"[^>]*>(.*?)<\/div>/s', $item, $remMatch)) {
                        $remarks = trim(strip_tags($remMatch[1]));
                    }
                    
                    $videos[] = array(
                        "vod_id" => $href,
                        "vod_name" => $name ? $name : "",
                        "vod_pic" => $pic,
                        "vod_remarks" => $remarks
                    );
                }
            }
        }
        
        $pagecount = (!empty($videos) ? $page + 1 : $page);
        return array(
            "list" => $videos,
            "page" => $page,
            "pagecount" => $pagecount,
            "limit" => 20,
            "total" => 999
        );
    }

    public function detailContent($ids) {
        $html = $this->_fetch($ids[0]);
        if (!$html) {
            return array("list" => array());
        }
        
        $metaImg = "";
        $metaMatch = array();
        if (preg_match('/<meta[^>]*property="og:image"[^>]*content=["\']([^"\']+)["\']/', $html, $metaMatch)) {
            $metaImg = $metaMatch[1];
        }
        
        $metaDesc = "";
        if (preg_match('/<meta[^>]*property="og:description"[^>]*content=["\']([^"\']+)["\']/', $html, $metaMatch)) {
            $metaDesc = $metaMatch[1];
        }
        
        $name = "";
        $h1Match = array();
        if (preg_match('/<h1[^>]*>(.*?)<\/h1>/s', $html, $h1Match)) {
            $name = trim(strip_tags($h1Match[1]));
        }
        
        $playUrl = "";
        $m = array();
        if (preg_match('/src=["\'](https?:\/\/(?:[^"\']*(?:d000d|doood|myvidplay)\.[a-z]+)\/e\/[a-zA-Z0-9]+)/i', $html, $m)) {
            $playUrl = $m[1];
        } else {
            $iframeMatch = array();
            if (preg_match('/<iframe[^>]*src=["\']([^"\']*\/e\/[^"\']+)["\']/', $html, $iframeMatch)) {
                $playUrl = $iframeMatch[1];
            }
        }

        $vodPlayUrl = $playUrl ? 'HD$' . $playUrl : '无资源$#';
        $result = array(
            "list" => array(
                array(
                    "vod_id" => $ids[0],
                    "vod_name" => $name,
                    "vod_pic" => $metaImg,
                    "vod_content" => $metaDesc,
                    "vod_play_from" => "文艺复兴",
                    "vod_play_url" => $vodPlayUrl
                )
            )
        );
        return $result;
    }

    public function searchContent($key, $quick = false, $pg = 1) {
        return $this->_get_list($this->host . "/page/" . $pg . "/?s=" . urlencode($key), intval($pg));
    }

    public function playerContent($flag, $id, $vipFlags = array()) {
        if ($flag == 'myvidplay' || strpos($id, 'myvidplay') !== false || strpos($id, 'd000d') !== false || strpos($id, 'doood') !== false) {
            return $this->_resolve_myvidplay($id);
        }
        return array("parse" => 1, "url" => $id, "header" => $this->customHeaders);
    }
}

(new Spider())->run();
