<?php
declare(strict_types=1);

use Portix\Storage\DownloadStorage;

$template->setSlot('main', 'var/modules/download/templates/index.tpl');
$action = (string) ($argument[1] ?? 'list');
$categories = $repositories->downloads()->categories();
$publicCategoryCount = 0;

foreach ($categories as $category) {
    if ((int) $category['published_count'] < 1) {
        continue;
    }
    $publicCategoryCount++;
    $template->addRow('downloadcategory', [
        'name' => (string) $category['name'],
        'slug' => (string) $category['slug'],
        'description' => (string) $category['description'],
        'count' => (string) $category['published_count'],
    ]);
}

if ($action !== 'cat') {
    $pageMetadata->define($translator->text('download'), $translator->text('downloads_public_intro'), 'dwl.html');
    if ($publicCategoryCount === 0) {
        $pageMetadata->noIndex();
    }
    $template->set('downloads_listing', '1');
    return;
}

$slug = implode('-', array_slice($argument, 2));
$category = $repositories->downloads()->findCategoryBySlug($slug);
if ($category === null) {
    http_response_code(404);
    $pageMetadata->noIndex();
    $template->setSlot('main', './var/templates/modern/not-found.tpl');
    return;
}
$pageMetadata->define(
    (string) $category['name'],
    (string) $category['description'],
    'dwl-cat-' . (string) $category['slug'] . '.html'
);
$template->set('downloads_listing', '0');
$template->set('downloads_category_title', (string) $category['name']);
$template->set('downloads_category_description', (string) $category['description']);
$files = $repositories->downloads()->publishedForCategory((int) $category['id']);
$storage = new DownloadStorage(dirname(__DIR__, 3) . '/var/private/downloads');
$visibleFiles = 0;
foreach ($files as $file) {
    try {
        $path = $storage->path((string) $file['stored_name']);
        if (!is_file($path)) {
            continue;
        }
        $size = (int) $file['size_bytes'];
        if ($size < 1) {
            $size = (int) filesize($path);
        }
        $checksum = (string) $file['sha256'];
        if ($checksum === '') {
            $checksum = (string) hash_file('sha256', $path);
        }
        $template->addRow('downloadfile', [
            'id' => (string) $file['id'],
            'title' => (string) $file['title'],
            'description' => (string) $file['description'],
            'original_name' => (string) $file['original_name'],
            'mime_type' => (string) $file['mime_type'],
            'size' => downloadFormatBytes($size),
            'checksum' => $checksum,
            'counter' => (string) $file['download_count'],
        ]);
        $visibleFiles++;
    } catch (Throwable) {
    }
}
$template->set('downloads_has_files', $visibleFiles === 0 ? '0' : '1');
if ($visibleFiles === 0) {
    $pageMetadata->noIndex();
}

function downloadFormatBytes(int $bytes): string
{
    $units = ['o', 'Kio', 'Mio', 'Gio'];
    $value = max(0, $bytes);
    $unit = 0;
    while ($value >= 1024 && $unit < count($units) - 1) {
        $value /= 1024;
        $unit++;
    }
    return number_format((float) $value, $unit === 0 ? 0 : 1, ',', ' ') . ' ' . $units[$unit];
}
