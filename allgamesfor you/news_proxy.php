<?php
header('Content-Type: application/json');

// Enable error logging to a local file for debugging
ini_set('log_errors', 1);
ini_set('error_log', 'php_error.log');

/**
 * news_proxy.php
 * Aggregates gaming news from multiple RSS feeds (CharlieIntel, GameSpot).
 */

$feeds = [
    'CharlieIntel' => 'https://www.charlieintel.com/feed/',
    'GameSpot' => 'https://www.gamespot.com/feeds/news/',
    'IGN' => 'https://www.ign.com/rss/articles/all',
    'Eurogamer' => 'https://www.eurogamer.net/feed/news'
];

$allNews = [];

foreach ($feeds as $sourceName => $url) {
    try {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36');

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200 && $response) {
            $xml = new SimpleXMLElement($response);
            foreach ($xml->channel->item as $item) {
                $namespaces = $item->getNamespaces(true);
                $media = $item->children($namespaces['media'] ?? '');

                // Extract image URL
                $imageUrl = '';
                if (isset($item->enclosure) && isset($item->enclosure['url'])) {
                    $imageUrl = (string) $item->enclosure['url'];
                } elseif (isset($media->thumbnail) && isset($media->thumbnail->attributes()->url)) {
                    $imageUrl = (string) $media->thumbnail->attributes()->url;
                } elseif (isset($media->content) && isset($media->content->attributes()->url)) {
                    $imageUrl = (string) $media->content->attributes()->url;
                }

                // Extract and truncate description
                $description = strip_tags((string) $item->description);
                if (strlen($description) > 250) {
                    $description = substr($description, 0, 247) . '...';
                }

                $allNews[] = [
                    'title' => (string) $item->title,
                    'url' => (string) $item->link,
                    'image' => $imageUrl,
                    'description' => $description,
                    'source' => $sourceName,
                    'published' => (string) $item->pubDate,
                    'timestamp' => strtotime((string) $item->pubDate)
                ];
            }
        } else {
            error_log("Failed to fetch $sourceName (HTTP $httpCode) or empty response.");
        }
    } catch (Exception $e) {
        error_log("Error fetching or parsing $sourceName: " . $e->getMessage());
    }
}

// Sort by timestamp descending
usort($allNews, function ($a, $b) {
    return $b['timestamp'] - $a['timestamp'];
});

// Remove the 'timestamp' key before final output
$finalNews = array_map(function ($item) {
    unset($item['timestamp']);
    return $item;
}, $allNews);

// Take top 24 items
$finalNews = array_slice($finalNews, 0, 24);

echo json_encode($finalNews);
?>