<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(403); exit(1); }
require dirname(__DIR__) . '/bootstrap/autoload.php';
require dirname(__DIR__) . '/var/modules/articles/src/ArticleAutosaveStore.php';

use PortixModule\Articles\ArticleAutosaveStore;

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$store = new ArticleAutosaveStore($pdo);

$fields = [
    'title' => 'Un brouillon à récupérer',
    'slug' => 'brouillon',
    'category_id' => '2',
    'content' => '<h2>Version non publiée</h2>',
    'summary' => 'Description',
    'author_name' => '',
    'featured_image' => '',
    'toc_enabled' => '1',
    'related' => ['3', '4'],
    'related_positions' => ['3' => '1', '4' => '2'],
];
if ($store->load('redacteur', 12) !== null) {
    throw new RuntimeException('Aucun brouillon avant sauvegarde');
}
$store->save('redacteur', 12, $fields, '2026-11-13 12:00:00');
$found = $store->load('redacteur', 12);
if ($found === null || $found['fields'] !== $fields
    || $found['base_updated_at'] !== '2026-11-13 12:00:00') {
    throw new RuntimeException('Brouillon non récupéré fidèlement');
}
if ($store->load('autre-redacteur', 12) !== null || $store->load('redacteur', 13) !== null) {
    throw new RuntimeException('Séparation des utilisateurs et des articles');
}
$updated = $fields;
$updated['content'] = '<p>Nouvelle version</p>';
$store->save('redacteur', 12, $updated, '2026-11-13 12:00:00');
if ($store->load('redacteur', 12)['fields']['content'] !== $updated['content']) {
    throw new RuntimeException('Dernier brouillon non conservé');
}
try {
    $store->save('redacteur', 12, ['content' => str_repeat('a', 1048577)], '');
    throw new RuntimeException('Brouillon trop gros accepté');
} catch (RuntimeException $error) {
    if ($error->getMessage() !== 'articles_autosave_too_large') { throw $error; }
}
$store->delete('redacteur', 12);
if ($store->load('redacteur', 12) !== null) {
    throw new RuntimeException('Brouillon non supprimé après validation manuelle');
}
$store->save('redacteur', 0, $fields, '');
if ($store->load('redacteur', 0) === null) {
    throw new RuntimeException('Brouillon de nouvel article non conservé');
}
echo "Sauvegarde automatique : stockage privé, récupération et limites OK\n";
