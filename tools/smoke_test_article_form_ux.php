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
    'data-preview-submit="1"', 'articles_optional_metadata', 'name="save_continue"', 'articles_is_edit == "1"'];
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
foreach (["isset(\$_POST['save_continue'])", "'articles_is_edit'", "'articles_saved_continue'", "'Location: Admin-articles-edit-'"] as $expected) {
    if (!str_contains($controller, $expected)) {
        throw new RuntimeException('Sauvegarde avec poursuite absente : ' . $expected);
    }
}
if (!str_contains($controller, "!\$isNew && \$id !== null && isset(\$_POST['save_continue'])")) {
    throw new RuntimeException('Enregistrer et continuer doit être limité aux articles existants.');
}
echo "Formulaire rédaction : champs, ordre, aperçu et syntaxe du template OK\n";
