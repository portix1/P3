<?php
require_once './includes/class/html_sanitizer.class.php';

if (isset($argument[1]) && $argument[1] === 'rss') {
    include './var/modules/news/rss.php';
}

$template->setSlot('main', './var/modules/news/templates/index.tpl');
$template->set('news_listing', '1');
$template->set('content_preview', '0');

if (isset($argument[1], $argument[2]) && $argument[1] === 'preview') {
    $canPreview = isset($user) && is_object($user)
        && method_exists($user, 'isAdmin') && method_exists($user, 'isRedactor')
        && ($user->isAdmin() || $user->isRedactor());
    $preview = $canPreview
        ? (new \Portix\Preview\SessionPreviewStore())->get('news', (string) $argument[2])
        : null;
    if ($preview === null) {
        http_response_code(404);
        $pageMetadata->noIndexNoFollow();
        $template->setSlot('main', './var/templates/modern/not-found.tpl');
        return;
    }
    header('Cache-Control: private, no-store, max-age=0');
    $pageMetadata->define(
        (string) $preview['title'],
        (string) $preview['intro'],
        '',
        'article',
        (string) ($preview['published_at'] ?? ''),
        (string) ($preview['updated_at'] ?? ''),
        '',
        \Portix\Seo\PageMetadata::firstImageFromHtml((string) $preview['intro'] . (string) $preview['body']),
        'NewsArticle'
    );
    $pageMetadata->noIndexNoFollow();
    $template->addRow('shownews', [
        'title' => (string) $preview['title'],
        'date' => (string) ($preview['published_at'] ?? ''),
        'show_date' => !empty($preview['is_pinned']) ? '0' : '1',
        'intro' => \Portix\View\Html::trusted(PortixHtmlSanitizer::clean((string) $preview['intro'])),
        'content' => \Portix\View\Html::trusted(PortixHtmlSanitizer::clean((string) $preview['body'])),
    ]);
    $template->set('news_listing', '0');
    $template->set('content_preview', '1');
    return;
}

$breadcrumbTrail->add($translator->text('ui_news'), 'news.html');

if (isset($argument[1], $argument[2]) && $argument[1] === 'slug') {
    $newsItem = $repositories->news()->findPublishedBySlug((string) $argument[2]);
    if ($newsItem === null) {
        http_response_code(404);
        $pageMetadata->noIndex();
        $template->setSlot('main', './var/templates/modern/not-found.tpl');
        return;
    }
    $newsCanonical = $request->isFrontPage()
        ? '/'
        : (string) $newsItem['slug'] . '.html';
    $pageMetadata->define(
        (string) $newsItem['title'],
        (string) $newsItem['intro'],
        $newsCanonical,
        'article',
        (string) ($newsItem['published_at'] ?? ''),
        (string) ($newsItem['updated_at'] ?? ''),
        '',
        \Portix\Seo\PageMetadata::firstImageFromHtml((string) $newsItem['intro'] . (string) $newsItem['body']),
        'NewsArticle'
    );
    $template->addRow('shownews', array(
        'title' => (string) $newsItem['title'],
        'date' => (string) $newsItem['published_at'],
        'show_date' => !empty($newsItem['is_pinned']) ? '0' : '1',
        'intro' => \Portix\View\Html::trusted(PortixHtmlSanitizer::clean((string) $newsItem['intro'])),
        'content' => \Portix\View\Html::trusted(PortixHtmlSanitizer::clean((string) $newsItem['body'])),
    ));
    $template->set('news_listing', '0');
    return;
}

$page = isset($argument[1], $argument[2]) && $argument[1] === 'page'
    ? max(1, (int) $argument[2])
    : 1;
$newsCanonical = $request->isFrontPage() && $page === 1
    ? '/'
    : ($page === 1 ? 'news.html' : 'news-page-' . $page . '.html');
$newsPageTitle = $page === 1
    ? $translator->text('ui_news')
    : $translator->text('ui_news') . ' - ' . $translator->text('ui_page') . ' ' . $page;
$pageMetadata->define($newsPageTitle, '', $newsCanonical);
$newsCount = $repositories->news()->countPublishedUnpinned();
$pageCount = max(1, (int) ceil($newsCount / 10));
if ($page > $pageCount) {
    http_response_code(404);
    $template->setSlot('main', './var/templates/modern/not-found.tpl');
    return;
}

if ($page === 1) {
    foreach ($repositories->news()->pinnedPublished() as $item) {
        $template->addRow('viewnews', array(
            'title' => (string) $item['title'],
            'slug' => (string) $item['slug'],
            'date' => '',
            'show_date' => '0',
            'intro' => \Portix\View\Html::trusted(PortixHtmlSanitizer::clean((string) $item['intro'])),
        ));
    }
}

foreach ($repositories->news()->latestPublished(10, ($page - 1) * 10) as $item) {
    $template->addRow('viewnews', array(
        'title' => (string) $item['title'],
        'slug' => (string) $item['slug'],
        'date' => (string) $item['published_at'],
        'show_date' => '1',
        'intro' => \Portix\View\Html::trusted(PortixHtmlSanitizer::clean((string) $item['intro'])),
    ));
}

for ($number = 1; $number <= $pageCount; $number++) {
    $template->addRow('page', array(
        'page' => (string) $number,
        'current' => $number === $page ? 'is-current' : '',
    ));
}
?>
