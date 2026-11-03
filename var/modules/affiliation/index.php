<?php
declare(strict_types=1);

if (($route ?? '') !== 'articles' || ($argument[1] ?? '') !== 'page') {
    return;
}

$slug = implode('-', array_slice($argument, 2));
if ($slug === '') {
    return;
}

try {
    $pdo = $database->connection();
    $statement = $pdo->prepare(
        "SELECT id FROM articles WHERE slug = :slug AND status = 'published' LIMIT 1"
    );
    $statement->execute(['slug' => strtolower($slug)]);
    $articleId = $statement->fetchColumn();
    if ($articleId === false) {
        return;
    }

    require_once __DIR__ . '/src/AffiliationRenderer.php';
    $html = \PortixModule\Affiliation\AffiliationRenderer::forArticle($pdo, (int) $articleId);
    if ($html !== '') {
        $template->set('affiliation_public_html', \Portix\View\Html::trusted($html));
        $template->setSlot('after_main', __DIR__ . '/templates/after-main.tpl');
    }
} catch (Throwable $error) {
    error_log('[Portix3 affiliation] ' . $error->getMessage());
}