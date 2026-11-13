<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(403); exit(1); }

$root = dirname(__DIR__);
require $root . '/bootstrap/autoload.php';

$templatePath = $root . '/var/modules/articles/templates/admin-form.tpl';
$source = (string) file_get_contents($templatePath);
(new \Portix\View\Template($templatePath))->validate();

$required = ['name="title"', 'name="slug"', 'name="status"', 'name="category_id"',
    'name="content"', 'name="summary"', 'name="author_name"', 'name="featured_image"',
    'name="reading_settings_present"', 'name="toc_enabled"', 'data-unsaved-warning="1"',
    'data-preview-submit="1"', 'articles_optional_metadata', 'data-article-autosave', 'name="base_updated_at"', 'article-autosave-snapshot'];
foreach ($required as $item) {
    if (!str_contains($source, $item)) { throw new RuntimeException('Champ de rédaction manquant : ' . $item); }
}
$bodyPos = strpos($source, 'name="content"');
$summaryPos = strpos($source, 'name="summary"');
$authorPos = strpos($source, 'name="author_name"');
if ($bodyPos === false || $summaryPos === false || $authorPos === false
    || !($bodyPos < $summaryPos && $summaryPos < $authorPos)) {
    throw new RuntimeException('Ordre du formulaire rédaction non conforme.');
}
if (preg_match('/\{%\s*if\s+[^%]*(?:&&|\|\|)[^%]*%\}/', $source) === 1) {
    throw new RuntimeException('Condition de template non prise en charge.');
}
$controller = (string) file_get_contents($root . '/var/modules/articles/src/ArticleAdminController.php');
foreach (["'articles_autosave_url'", "'articles_autosave_snapshot'",
    'autosaveArticle(', "'base_updated_at'", "autosaves()->delete("] as $expected) {
    if (!str_contains($controller, $expected)) {
        throw new RuntimeException('Sauvegarde automatique : élément absent : ' . $expected);
    }
}
$layout = (string) file_get_contents($root . '/var/admin/index.tpl');
if (!str_contains($layout, 'var/modules/articles/article-autosave.js')) {
    throw new RuntimeException('Script de sauvegarde automatique non chargé.');
}
echo "Formulaire rédaction : champs, ordre, aperçu et syntaxe du template OK\n";
