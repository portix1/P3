<?php
declare(strict_types=1);

use Portix\Infrastructure\Database;
use Portix\Infrastructure\Migrator;
use Portix\Module\ModuleInstaller;
use Portix\Repository\RepositoryRegistry;
use PortixModule\Articles\ArticleRepository;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$root = dirname(__DIR__);
require $root . '/bootstrap/autoload.php';
require $root . '/var/modules/articles/src/ArticleRepository.php';

try {
    $database = new Database(':memory:');
    (new Migrator($database))->migrate();
    $repositories = new RepositoryRegistry($database);
    (new ModuleInstaller($database, $repositories->modules()))
        ->installDirectory($root . '/var/modules/articles');
    $articles = new ArticleRepository($database->connection());
    $body = '<p>Un article illustré.</p><img src="test.webp" alt="Schéma" />';
    $summary = 'Diapason, tension et confort : comprendre les différences entre les guitares.';
    $now = '2026-10-08 12:00:00';
    $id = $articles->createArticle(
        null, 'Le diapason', 'le-diapason', $body, 'published', 'admin', $now, $now,
        '', '', $summary
    );
    if ($articles->find($id)['summary'] !== $summary) {
        throw new RuntimeException('La description n’est pas enregistrée à la création.');
    }

    // An older caller that does not pass a description must preserve it.
    $articles->updateArticle($id, null, 'Le diapason', 'le-diapason', $body, 'published', $now, $now);
    $saved = $articles->find($id);
    if ($saved['summary'] !== $summary || $saved['body'] !== $body) {
        throw new RuntimeException('Une sauvegarde ancienne efface la description ou altère les illustrations.');
    }

    $replacement = 'Une description différente, avec des caractères accentués : é, è, à.';
    $articles->updateArticle(
        $id, null, 'Le diapason', 'le-diapason', $body, 'published', $now, $now,
        '', '', $replacement
    );
    if ($articles->find($id)['summary'] !== $replacement) {
        throw new RuntimeException('La modification de la description n’est pas enregistrée.');
    }

    // An explicitly empty field restores automatic descriptions.
    $articles->updateArticle(
        $id, null, 'Le diapason', 'le-diapason', $body, 'published', $now, $now,
        '', '', ''
    );
    $saved = $articles->find($id);
    if ($saved['summary'] !== '' || $saved['body'] !== $body || $saved['slug'] !== 'le-diapason') {
        throw new RuntimeException('Le retour à la description automatique modifie l’article.');
    }
    echo "Descriptions d’articles : création, conservation, modification et retour automatique OK\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'Test descriptions : ÉCHEC - ' . $error->getMessage() . "\n");
    exit(1);
}
