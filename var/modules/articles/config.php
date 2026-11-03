<?php
declare(strict_types=1);

require_once './var/modules/articles/src/ArticleRepository.php';
require_once './var/modules/articles/src/ArticleOrganizer.php';
require_once './var/modules/articles/src/ArticleAdminController.php';
require_once './var/modules/articles/src/CategoryMetadataSchema.php';
require_once './var/modules/articles/src/ReadingTocBatch.php';
require_once './var/modules/articles/src/NumberedSeriesBatch.php';
require_once './includes/class/html_sanitizer.class.php';

\PortixModule\Articles\CategoryMetadataSchema::ensure($database, dirname(__DIR__, 3));

if ($user->isAdmin()) {
    try {
        $tocBatch = \PortixModule\Articles\ReadingTocBatch::applyOnce($database, dirname(__DIR__, 3));
        if ($tocBatch !== null) {
            $template->addRow('tocbatchnotice', ['message'=>sprintf($translator->text('articles_toc_batch_done'), $tocBatch['enabled'])]);
        }
    } catch (\Throwable $error) {
        error_log((string)$error);
        $template->addRow('tocbatchnotice', ['message'=>$translator->text('articles_toc_batch_failed')]);
    }
}

if ($user->isAdmin()) {
    try {
        $seriesBatch = \PortixModule\Articles\NumberedSeriesBatch::applyOnce($database, dirname(__DIR__, 3));
        if ($seriesBatch !== null) {
            $template->addRow('tocbatchnotice', ['message'=>sprintf($translator->text('articles_series_batch_done'), $seriesBatch['created'], $seriesBatch['linked'], $seriesBatch['skipped'])]);
        }
    } catch (\Throwable $error) {
        error_log((string)$error);
        $template->addRow('tocbatchnotice', ['message'=>$translator->text('articles_series_batch_failed')]);
    }
}

$articleRepository = new \PortixModule\Articles\ArticleRepository($database->connection());
$reservedArticleSlugs = [
    'accueil', 'admin', 'install', 'index', 'disconnect', 'login', 'personnal', 'forgot-password', 'reset-password', 'usrmgr',
];
foreach ($modules as $currentModule) {
    $reservedArticleSlugs[] = strtolower(trim((string) $currentModule['prefix']));
}
(new \PortixModule\Articles\ArticleAdminController(
    $articleRepository,
    dirname(__DIR__, 3),
    $translator,
    array_values(array_unique($reservedArticleSlugs)),
    $repositories->contentSafety()
))->handle($template, $argument, $user);
