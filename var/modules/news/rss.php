<?php
require_once './var/modules/news/feedcreator.class.php';

$siteUrl = \Portix\Seo\PublicUrl::resolve((string) ($CONFIG['site_url'] ?? ''), $request);

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
