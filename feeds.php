<?php

if (!extension_loaded("yaml")) {
    die("错误：未安装或未启用PHP YAML扩展。");
}

$feeds_data = yaml_parse_file("feeds.yaml");
if ($feeds_data === false) {
    die("错误：无法解析 feeds.yaml 文件。");
}
if (!is_array($feeds_data) || empty($feeds_data)) {
    die("错误：feeds.yaml 文件为空或格式不正确。");
}
$postLimit = 5; //解析文章篇数
$feeda = [];
$feedn = 0;
$source_url_to_channel_map = [];
$failed_feeds = [];
$invalid_urls = [];

echo "\033[32m开始并行抓取订阅源...\n\033[m";

$mh = curl_multi_init();
$curlHandles = [];

foreach ($feeds_data as $category => $urls_in_category) {
    if (!is_array($urls_in_category)) {
        echo "\n\033[33mWarning: Skipping category '{$category}' as it does not contain a list of URLs.\n\033[m";
        continue;
    }
    foreach ($urls_in_category as $url) {
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            echo "\n\033[33mWarning: Invalid URL found and skipped: {$url}\n\033[m";
            $invalid_urls[] = $url;
            continue;
        }
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_USERAGENT => "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/102.0.0.0 Safari/537.36",
            CURLOPT_REFERER => "https://www.bing.com/",
            CURLOPT_TIMEOUT => 10, //单位为秒
            CURLOPT_ENCODING => "",
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_FAILONERROR => true,
        ]);
        curl_multi_add_handle($mh, $ch);
        $curlHandles[(string) $url] = $ch;
    }
}

$active = null;
do {
    $status = curl_multi_exec($mh, $active);
    if ($active) {
        curl_multi_select($mh);
    }
} while ($active && $status === CURLM_OK);


echo "处理结果中...\n";
foreach ($curlHandles as $source_url => $ch) {
    $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $response = curl_multi_getcontent($ch);

    if (curl_errno($ch) || $httpcode != 200) {
        echo "\033[31m无法加载的订阅源： " .
            $source_url .
            " (HTTP: " .
            $httpcode .
            ")\n\033[m";
        $failed_feeds[] = $source_url;
        curl_multi_remove_handle($mh, $ch);
        curl_close($ch);
        continue;
    }

    echo "\033[32m获取成功的订阅源: " . $source_url . "\n\033[m";

    $response = str_ireplace(
        ["media:thumbnail", "<media:group>", "</media:group>"],
        ["thumbnail", "", ""],
        $response
    );
    $feed = simplexml_load_string($response);

    if ($feed) {
        $feedType = strtolower($feed->getName());
        $count = 0;
        $channel_name = null;

        if ($feedType === "rss") {
            $channel_name = (string) $feed->channel->title;
        } elseif ($feedType === "feed") {
            $channel_name = (string) $feed->title;
        }

        if ($channel_name) {
            $source_url_to_channel_map[$source_url] = $channel_name;
        }

        if ($feedType === "rss") {
            foreach ($feed->channel->item as $item) {
                if ($count >= $postLimit) {
                    break;
                }
                $description = (string) $item->description;
                $articleLink = (string) $item->link;
                $image = null;
                if (
                    isset($item->thumbnail) &&
                    isset($item->thumbnail->attributes()["url"])
                ) {
                    $image = (string) $item->thumbnail->attributes()["url"];
                } elseif (!empty($description)) {
                    preg_match(
                        '/<img.+src=[\'"](?P<src>.+?)[\'"].*>/i',
                        $description,
                        $imagematch
                    );
                    if ($imagematch && isset($imagematch["src"])) {
                        $image = $imagematch["src"];
                    }
                } elseif (
                    isset($feed->channel->image) &&
                    isset($feed->channel->image->url)
                ) {
                    $image = (string) $feed->channel->image->url;
                }
                if ($image) {
                    if (parse_url($image, PHP_URL_SCHEME) === null) {
                        $image =
                            rtrim($articleLink, "/") . "/" . ltrim($image, "/");
                    }
                    $image = str_replace("http://", "https://", $image);
                }
                $audiourl = null;
                if (
                    isset($item->enclosure) &&
                    strpos($item->enclosure["type"], "audio/") !== false
                ) {
                    $audiourl = (string) $item->enclosure["url"];
                }

                $feeda[$feedn]["link"] = $articleLink;
                $feeda[$feedn]["title"] = (string) $item->title;
                if (empty($feeda[$feedn]["title"])) {
                    $feeda[$feedn]["title"] = substr(
                        strip_tags($description),
                        0,
                        140
                    );
                }
                $feeda[$feedn]["ch"] = $channel_name; // 使用获取到的频道名
                $feeda[$feedn]["date"] = strtotime((string) $item->pubDate);
                $feeda[$feedn]["image"] = $image;
                $feeda[$feedn]["audio"] = (string) $audiourl;
                $feeda[$feedn]["category"] = null;
                $count++;
                $feedn++;
            }
        } elseif ($feedType === "feed") {
            foreach ($feed->entry as $entry) {
                if ($count >= $postLimit) {
                    break;
                }
                $content = (string) $entry->content;
                $articleLink = (string) $entry->link["href"];
                $image = null;
                if (
                    isset($entry->{'thumbnail'}) &&
                    isset($entry->{'thumbnail'}->attributes()["url"])
                ) {
                    $image = (string) $entry->{'thumbnail'}->attributes()["url"];
                } elseif (!empty($content)) {
                    preg_match(
                        '/<img.+src=[\'"](?P<src>.+?)[\'"].*>/i',
                        $content,
                        $imagematch
                    );
                    if ($imagematch && isset($imagematch["src"])) {
                        $image = $imagematch["src"];
                    }
                } elseif (isset($feed->image) && isset($feed->image->url)) {
                    $image = (string) $feed->image->url;
                }
                if ($image) {
                    if (parse_url($image, PHP_URL_SCHEME) === null) {
                        $image =
                            rtrim($articleLink, "/") . "/" . ltrim($image, "/");
                    }
                    $image = str_replace("http://", "https://", $image);
                }
                $audiourl = null;
                if (
                    isset($entry->link) &&
                    $entry->link["rel"] == "enclosure" &&
                    strpos($entry->link["type"], "audio/") !== false
                ) {
                    $audiourl = (string) $entry->link["href"];
                }

                $feeda[$feedn]["link"] = $articleLink;
                $feeda[$feedn]["title"] = (string) $entry->title;
                $feeda[$feedn]["ch"] = $channel_name; 
                $feeda[$feedn]["date"] = strtotime(
                    (string) ($entry->published ?? $entry->updated)
                );
                $feeda[$feedn]["image"] = $image;
                $feeda[$feedn]["audio"] = (string) $audiourl;
                $feeda[$feedn]["category"] = null;
                $count++;
                $feedn++;
            }
        }
    } else {
        echo "\033[31m无法解析的订阅源: " . $source_url . "\n\033[m";
        $failed_feeds[] = $source_url;
    }
    curl_multi_remove_handle($mh, $ch);
    curl_close($ch);
}
curl_multi_close($mh);
echo "\033[32m所有订阅源处理完成.\n\033[m";

foreach ($feeda as &$post) {
    foreach ($feeds_data as $category => $urls) {
        if (in_array($post["link"], $urls)) {
            $post["category"] = $category;
            break;
        }
    }
}

usort($feeda, fn($a, $b) => $b["date"] <=> $a["date"]);

$detailed_channels_by_category = [];
foreach ($feeds_data as $category => $urls) {
    $detailed_channels_by_category[$category] = [];
    foreach ($urls as $url) {
        if (isset($source_url_to_channel_map[$url])) {
            $channel_name = $source_url_to_channel_map[$url];
            if (
                !in_array(
                    $channel_name,
                    $detailed_channels_by_category[$category]
                )
            ) {
                $detailed_channels_by_category[$category][] = $channel_name;
            }
        }
    }
}

$outhtml = "";
$index = 0;
$today_timestamp = strtotime('today midnight'); 
foreach ($feeda as $post) {
    $isaudio = !empty($post["audio"]) ? 1 : 0;
    $channelIdentifier = htmlspecialchars($post["ch"]);
    $is_today = ($post['date'] >= $today_timestamp);   
    $today_class = $is_today ? ' today' : ''; 
    $outhtml .= '<div class="post' . $today_class . '" data-channel="' . $channelIdentifier . '" data-category="' . htmlspecialchars($post['category']) . '" data-ts="' . $post['date'] . '" data-audio="' . $isaudio . '">';
    if (!empty($post["image"])) {
        $outhtml .= '<div class="leftpan"><img src="' . htmlspecialchars($post['image']) . '" loading="lazy"/></div>';
    } else {
        $domain = parse_url($post["link"], PHP_URL_HOST);
        if ($domain) {
            $outhtml .= '<div class="leftpan"><img src="https://toolb.cn/favicon/' . urlencode($domain) . '" loading="lazy"/></div>';
        } else {
            $outhtml .= '<div class="leftpan"><img src="/yyghub/img/loading.gif" loading="lazy"/></div>';
        }
    }
    $outhtml .= '<div class="rightpan"><div class="feedname"><span class="channel">' . htmlspecialchars($post['ch']) . '</span> &bull; <span class="date">' . date('Y-n-j', $post['date']) . '</span></div>
<h2><a href="' . htmlspecialchars($post['link']) . '" target="_blank">' . htmlspecialchars($post['title']) . '</a></h2>';
    if (!empty($post["audio"])) {
        $outhtml .= '<div class="audio"><button data-aid="' . $index . '">Play</button><audio src="' . htmlspecialchars($post['audio']) . '" preload="metadata" aid="' . $index . '"  controls></audio></div>';
        $index++;
    }
    $outhtml .= "</div></div>";
}

if (!is_dir("public")) {
    mkdir("public", 0755, true);
}
file_put_contents("public/channels.json", json_encode($detailed_channels_by_category, JSON_UNESCAPED_UNICODE));
file_put_contents("public/feed.json", json_encode($feeda, JSON_UNESCAPED_UNICODE));

$template = file_get_contents("default.html");
if ($template) {
    $html = str_replace("<!-- posts here -->", $outhtml, $template);
    file_put_contents("public/index.html", $html);
    echo "\033[32m侧边栏主题html生成成功\n\033[m";
} else {
    echo "\033[31m错误：无法找到 default.html 模板文件。\n\033[m";
}
$template = file_get_contents("left.html");
if ($template) {
    $html = str_replace("<!-- posts here -->", $outhtml, $template);
    file_put_contents("public/lindex.html", $html);
    echo "\033[32m顶栏主题html生成成功\n\033[m";
} else {
    echo "\033[31m错误：无法找到 left.html 模板文件。\n\033[m";
}

echo "\n\n--- 失败订阅源统计 ---\n";
echo "无法加载的订阅源数量: " . count($failed_feeds) . "\n";
if (!empty($failed_feeds)) {
    echo "失败的订阅源链接:\n";
    foreach ($failed_feeds as $url) {
        echo "- " . $url . "\n";
    }
}
echo "无效的URL数量: " . count($invalid_urls) . "\n";
if (!empty($invalid_urls)) {
    echo "无效的URL链接:\n";
    foreach ($invalid_urls as $url) {
        echo "- " . $url . "\n";
    }
}
echo "\n\n--- 生成完毕 ---\n";
echo "文章数量个数: " . count($feeda) . "\n";
echo "类别个数: " . count($feeds_data) . "\n";
echo "成功处理的订阅源数量: " . (count($curlHandles) - count($failed_feeds)) . "\n";
echo "总订阅源数量: " . count($curlHandles) . "\n";
echo "\033[32mHTML和JSON文件生成成功.\n\033[m";
