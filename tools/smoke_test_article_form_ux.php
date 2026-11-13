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
    'data-preview-submit="1"', 'articles_optional_metadata'];
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
echo "Formulaire rédaction : champs, ordre, aperçu et syntaxe du template OK\n";
