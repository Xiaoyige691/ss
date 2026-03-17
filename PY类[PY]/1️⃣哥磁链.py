# -*- coding: utf-8 -*-
import json, re, sys, requests, hashlib
from urllib.parse import quote
from concurrent.futures import ThreadPoolExecutor, as_completed
sys.path.append('..')
from base.spider import Spider

class Spider(Spider):
    def init(self, extend=""):
        # 保持你的反代地址
        self.host = "https://down.nigx.cn/t.me"
        self.channel_id = "xiaoyige_ss" 
        self.session = requests.Session()
        self.session.headers = {
            'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36'
        }
        self.mag_pattern = re.compile(r'magnet:\?xt=urn:btih:[a-zA-Z0-9]{32,40}', re.I)
        self.hash_pattern = re.compile(r'\b([a-fA-F0-9]{40})\b|\b([a-zA-Z2-7]{32})\b')
        self.cache = {}

    def getName(self):
        return "小一哥磁力"

    def homeContent(self, filter):
        return {'class': [{"type_name": "磁力更新", "type_id": self.channel_id}]}

    def _parse_tg_html(self, html, search_key=None):
        """核心解析逻辑：取前三行，并根据搜索词过滤"""
        vod_list = []
        blocks = html.split('js-widget_message')[1:]
        
        for block in blocks:
            # 1. 磁力提取与去重
            mags = self.mag_pattern.findall(block)
            if not mags:
                plain_text = re.sub(r'<[^>]+>', '', block)
                hashes = self.hash_pattern.findall(plain_text)
                for h_tuple in hashes:
                    h = h_tuple[0] if h_tuple[0] else h_tuple[1]
                    mags.append(f"magnet:?xt=urn:btih:{h}")
            
            if not mags: continue
            unique_mags = list(dict.fromkeys(mags)) # 保持顺序去重

            # 2. 取名逻辑：提取前三行内容
            text_match = re.search(r'js-message_text[^>]*?>(.*?)</div>', block, re.S)
            full_title = ""
            if text_match:
                # 清洗 HTML 标签
                raw_text = re.sub(r'<br\s*/?>', '\n', text_match.group(1)) # 确保换行符被正确处理
                text = re.sub(r'<[^>]+>', '', raw_text).strip()
                # 按行分割，提取前 3 行有效内容
                lines = [line.strip() for line in text.split('\n') if line.strip()]
                top_lines = lines[:3]
                
                # 检查搜索词是否在前三行中（如果处于搜索模式）
                if search_key:
                    search_content = " ".join(top_lines).lower()
                    if search_key.lower() not in search_content:
                        continue # 不包含关键词，忽略该条目
                
                full_title = " / ".join(top_lines)
            
            if not full_title:
                full_title = "磁力资源"

            # 3. 封面图
            pic_match = re.search(r'background-image:url\([\'"]?(.*?)[\'"]?\)', block)
            pic = pic_match.group(1) if pic_match else "https://api.xinac.net/icon/?url=magnet"
            
            # 4. 封装播放列表
            play_urls = [f"磁力源{i+1}${m}" for i, m in enumerate(unique_mags)]
            
            vod_list.append({
                "vod_name": full_title,
                "vod_id": json.dumps({"n": full_title, "p": pic, "u": "#".join(play_urls)}),
                "vod_pic": pic,
                "vod_remarks": f"磁链x{len(play_urls)}"
            })
        return vod_list

    def categoryContent(self, tid, pg, filter, extend):
        pg = int(pg)
        url = f"{self.host}/s/{tid}"
        if pg > 1:
            cache_key = f"{tid}_{pg}"
            if cache_key in self.cache:
                url += f"?before={self.cache[cache_key]}"
            else:
                return {'list': []}
        
        try:
            res = self.session.get(url, timeout=15)
            html = res.text
            if match := re.search(r'before=(\d+)', html):
                self.cache[f"{tid}_{pg+1}"] = match.group(1)
            results = self._parse_tg_html(html)
            return {'list': results[::-1], 'page': pg, 'pagecount': pg + 1}
        except:
            return {'list': [], 'page': pg, 'pagecount': pg}

    def detailContent(self, ids):
        data = json.loads(ids[0])
        return {'list': [{
            "vod_name": data['n'],
            "vod_pic": data['p'],
            "vod_play_from": "磁力下载",
            "vod_play_url": data['u'],
            "vod_content": data['n']
        }]}

    def searchContent(self, key, quick, pg="1"):
        # 搜索时将关键词传入解析函数进行二次精准过滤
        results = []
        try:
            url = f"{self.host}/s/{self.channel_id}?q={quote(key)}"
            res = self.session.get(url, timeout=15)
            if res.status_code == 200:
                results = self._parse_tg_html(res.text, search_key=key)
        except:
            pass
        return {'list': results}

    def playerContent(self, flag, id, vipFlags):
        return {
            'parse': 0, 'play': 1, 'url': id,
            'header': {'User-Agent': 'Mozilla/5.0'}
        }