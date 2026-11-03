<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Ce test doit être exécuté en ligne de commande.\n");
    exit(1);
}

$root = dirname(__DIR__);
$checks = [
    'var/modules/articles/templates/admin-form.tpl' => [
        'data-unsaved-warning="1"', 'data-preview-submit="1"', '{{ articles_preview_action }}',
        'name="author_name"', 'name="featured_image"',
    ],
    'var/admin/templates/news-form.tpl' => [
        'data-unsaved-warning="1"', 'data-preview-submit="1"', '{{ news_preview_action }}',
    ],
    'var/modules/articles/templates/admin-list.tpl' => [
        'Admin-articles-preview-{{ articlerow.id }}.html', 'articlerow.public_url == ""', '{{ ui_view }}',
    ],
    'var/admin/templates/news-list.tpl' => [
        'Admin-news-preview-{{ newsrow.id }}.html', 'newsrow.public_url == ""', '{{ ui_view }}',
    ],
    'var/editor/portix-ui.js' => [
        "form[data-unsaved-warning]", 'beforeunload', 'data-preview-submit',
        'fitSubmenuToViewport', 'submenu-opens-left', 'submenu-align-right',
    ],
    'var/templates/univers-guitare/style.css' => [
        'width: max-content', 'min-width: 0', 'white-space: normal',
        'submenu-opens-left', 'submenu-align-right',
    ],
    'var/modules/articles/index.php' => [
        "SessionPreviewStore())->get('article'", "'TechArticle'", 'publicOutline(', 'article-toc',
    ],
    'var/modules/articles/src/ArticleAdminController.php' => [
        'optimiseReferencedFilenames',
    ],
    'src/Controller/Admin/NewsAdminController.php' => [
        'optimiseReferencedFilenames',
    ],
    'var/modules/news/index.php' => [
        "SessionPreviewStore())->get('news'", "'NewsArticle'", 'noIndexNoFollow()',
    ],
    'src/Preview/SessionPreviewStore.php' => [
        'portix_content_previews', 'private const TTL = 1800', 'bin2hex(random_bytes(16))',
    ],
    'includes/class/image_storage.class.php' => [
        "function_exists('imagewebp')", "return function_exists('imagewebp') && imagewebp",
        'optimiseReferencedFilenames', 'figcaption', 'filenameSlug',
    ],
    'var/admin/style.css' => [
        'repeat(4, minmax(5.4rem, auto))', '.article-row-actions { grid-template-columns: 1fr; }',
    ],
    'var/editor/portix-editor.js' => [
        'createTableTools', 'createFormulaTools', 'createMarkdownTools',
        'markdownToHtml', "tableModule.insertTable", "dangerouslyPasteHTML",
        "quill.insertEmbed(index, 'formula'", "data-row",
    ],
    'var/editor/portix-ui.js' => [
        ".portix-formula[data-latex]", 'window.katex.render', 'cdn.jsdelivr.net/npm/katex@0.18.9',
    ],
    'var/admin/index.tpl' => [
        'data-editor-table=', 'data-editor-formula=', 'data-editor-markdown=',
    ],
    'includes/class/html_sanitizer.class.php' => [
        "'table', 'caption', 'thead', 'tbody', 'tfoot', 'tr', 'th', 'td'",
        '$class === \'portix-formula\'', '$node->setAttribute(\'class\', \'portix-table\')',
    ],
];

try {
    foreach ($checks as $relative => $needles) {
        $content = file_get_contents($root . '/' . $relative);
        if ($content === false) {
            throw new RuntimeException('Fichier introuvable : ' . $relative);
        }
        foreach ($needles as $needle) {
            if (!str_contains($content, $needle)) {
                throw new RuntimeException($relative . ' : élément attendu absent : ' . $needle);
            }
        }
    }
    $descriptor = (string) file_get_contents($root . '/var/modules/articles/database/articles/desc.csv');
    foreach (['author_name; text; null;', 'featured_image; text; null;'] as $needle) {
        if (!str_contains($descriptor, $needle)) {
            throw new RuntimeException('Schéma Articles incomplet : ' . $needle);
        }
    }
    $request = (string) file_get_contents($root . '/src/Http/Request.php');
    if (!str_contains($request, '-preview-[a-f0-9]{32}')) {
        throw new RuntimeException('Route de prévisualisation News absente.');
    }
    echo "Aperçus privés, éditeur structuré, garde anti-perte, SEO images et WebP : OK\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'Workflow éditorial 0.7.04 : ÉCHEC - ' . $error->getMessage() . "\n");
    exit(1);
}
