<?php
require_once './var/modules/news/feedcreator.class.php';

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = isset($_SERVER['HTTP_HOST']) ? (string) $_SERVER['HTTP_HOST'] : 'localhost';
$script = isset($_SERVER['SCRIPT_NAME']) ? (string) $_SERVER['SCRIPT_NAME'] : '/index.php';
$basePath = str_replace('\\', '/', dirname($script));
$basePath = $basePath === '/' || $basePath === '.' ? '' : rtrim($basePath, '/');
$siteUrl = $scheme . '://' . $host . $basePath;

$rss = new UniversalFeedCreator();
$rss->title = (string) $CONFIG['title'];
$rss->description = 'Informations à propos de ' . (string) $CONFIG['title'];
$rss->link = $siteUrl . '/news.html';
$rss->syndicationURL = $siteUrl . '/news-rss.html';
$rss->language = isset($CONFIG['lang']) ? (string) $CONFIG['lang'] : 'fr';

foreach ($repositories->news()->latestPublished(100) as $data) {
    $item = new FeedItem();
    $item->title = $data['title'];
    $item->link = $siteUrl . '/' . rawurlencode((string) $data['slug']) . '.html';
    $item->description = strip_tags((string) $data['intro']);
    $item->date = $data['published_at'];
    $rss->addItem($item);
}

if (!headers_sent()) {
    header('Content-Type: application/rss+xml; charset=UTF-8');
}
echo $rss->createFeed('RSS2.0');
exit;
