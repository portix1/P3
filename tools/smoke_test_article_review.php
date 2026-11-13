<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(403); exit(1); }

require dirname(__DIR__) . '/bootstrap/autoload.php';
require dirname(__DIR__) . '/var/modules/articles/src/ArticleReviewPolicy.php';

use PortixModule\Articles\ArticleReviewPolicy as Review;
use Portix\Domain\ArticlePermissions as Rights;

function allowTransition(string $from, string $to, bool $admin, bool $edit, bool $publish, string $note = ''): ?string
{
    return Review::transition($from, $to, $admin, $edit, $publish, $note);
}
function refuseTransition(string $from, string $to, bool $admin, bool $edit, bool $publish, string $note = ''): void
{
    try {
        allowTransition($from, $to, $admin, $edit, $publish, $note);
    } catch (RuntimeException $error) {
        return;
    }
    throw new RuntimeException('Transition interdite autorisée : ' . $from . ' → ' . $to);
}
function reviewAssert(bool $valid, string $reason): void
{
    if (!$valid) { throw new RuntimeException($reason); }
}

require dirname(__DIR__) . '/var/modules/articles/src/ArticleListFilter.php';

$sampleArticles = [
    ['title'=>'Manche en érable', 'category_name'=>'Fabrication', 'slug'=>'manche-erable', 'status'=>'draft'],
    ['title'=>'Chevalet', 'category_name'=>'Réglages', 'slug'=>'chevalet', 'status'=>'pending'],
    ['title'=>'Finition', 'category_name'=>'Atelier', 'slug'=>'finition', 'status'=>'published'],
];
$filterClass = \PortixModule\Articles\ArticleListFilter::class;
reviewAssert(count($filterClass::apply($sampleArticles, 'pending', '')) === 1, 'Filtre relecture');
reviewAssert(count($filterClass::apply($sampleArticles, 'draft', 'ÉRABLE')) === 1, 'Recherche UTF-8 insensible à la casse');
reviewAssert(count($filterClass::apply($sampleArticles, '', 'atelier')) === 1, 'Recherche par catégorie');
reviewAssert(count($filterClass::apply($sampleArticles, '', 'chevalet')) === 1, 'Recherche par adresse');
reviewAssert(count($filterClass::apply($sampleArticles, 'published', 'sans résultat')) === 0, 'Aucun résultat');
reviewAssert(count($filterClass::apply($sampleArticles, 'invalide', '')) === 3, 'État inconnu ignoré');

require dirname(__DIR__) . '/var/modules/articles/src/ArticleListPagination.php';
$pager = \PortixModule\Articles\ArticleListPagination::class;
$many = array_map(static fn (int $i): array => ['id' => $i], range(1, 53));
$firstPage = $pager::paginate($many, 1);
reviewAssert(count($firstPage['rows']) === 25 && $firstPage['from'] === 1 && $firstPage['to'] === 25,
    'Première page de 25 articles');
$lastPage = $pager::paginate($many, 1000);
reviewAssert(count($lastPage['rows']) === 3 && $lastPage['page'] === 3
    && $lastPage['from'] === 51 && $lastPage['to'] === 53, 'Dernière page et numéro hors bornes');
$emptyPage = $pager::paginate([], 5);
reviewAssert($emptyPage['page'] === 1 && $emptyPage['from'] === 0 && $emptyPage['to'] === 0,
    'Pagination vide sans décalage');
$link = $pager::pageUrl(2, 'pending', 'érable & acajou');
reviewAssert(str_contains($link, 'state=pending') && str_contains($link, 'q=%C3%A9rable%20%26%20acajou'),
    'Filtres conservés avec encodage des liens');
reviewAssert(count($pager::paginate($filterClass::apply($sampleArticles, 'pending', ''), 9)['rows']) === 1,
    'Les filtres sont appliqués avant la pagination');

$default = Rights::DEFAULTS;
reviewAssert(!$default['publish'] && !$default['edit_published'], 'New rédacteur starts with mandatory review');
$direct = Rights::withPublicationChoice($default, 'direct');
reviewAssert($direct['publish'] && $direct['edit_published'], 'Direct publishing grants editing of published articles');
$review = Rights::withPublicationChoice($direct, 'review');
reviewAssert(!$review['publish'] && !$review['edit_published'], 'Mandatory review revokes both rights together');
try {
    Rights::withPublicationChoice($default, 'forged');
    throw new RuntimeException('Invalid publication radio accepted');
} catch (RuntimeException $exception) {
    reviewAssert($exception->getMessage() === 'users_permissions_invalid', 'Invalid radio rejected');
}
reviewAssert(allowTransition('draft', 'pending', false, true, false) === null, 'Le rédacteur peut demander la relecture');
refuseTransition('draft', 'published', false, true, false);
refuseTransition('pending', 'published', false, true, false);
refuseTransition('pending', 'draft', false, true, false);
refuseTransition('published', 'draft', false, true, false);
refuseTransition('draft', 'pending', false, false, false);
refuseTransition('pending', 'pending', false, true, false);
refuseTransition('draft', 'garbage', false, true, true);
reviewAssert(allowTransition('draft', 'published', false, true, true) === null, 'Publication directe autorisée');
reviewAssert(allowTransition('published', 'draft', false, true, true) === null, 'L’auteur de confiance peut dépublier');
refuseTransition('pending', 'published', false, true, true);
reviewAssert(allowTransition('pending', 'published', true, true, true) === null, 'Administrateur approuve');
refuseTransition('pending', 'draft', true, true, true);
reviewAssert(allowTransition('pending', 'draft', true, true, true, '  Corriger les cotes.  ') === 'Corriger les cotes.',
    'Le retour exige une explication');
refuseTransition('pending', 'draft', true, true, true, str_repeat('a', 2001));
refuseTransition('draft', 'draft', false, true, false);
refuseTransition('published', 'pending', false, true, true);

$db = new Portix\Infrastructure\Database(':memory:');
(new Portix\Infrastructure\Migrator($db))->migrate();
$pdo = $db->connection();
$columns = array_column($pdo->query('PRAGMA table_info(users)')->fetchAll(PDO::FETCH_ASSOC), 'name');
reviewAssert(in_array('article_permissions', $columns, true), 'Permissions conservées lors de la migration');
$version = (int) $pdo->query('SELECT MAX(version) FROM schema_migrations')->fetchColumn();
reviewAssert($version === 9, 'La base neuve est en version 9');
(new Portix\Infrastructure\Migrator($db))->migrate();
reviewAssert((int) $pdo->query('SELECT MAX(version) FROM schema_migrations')->fetchColumn() === 9,
    'Migration sans effet à la seconde ouverture');
$oldDb = new Portix\Infrastructure\Database(':memory:');
$oldPdo = $oldDb->connection();
$oldPdo->exec('CREATE TABLE schema_migrations (version INTEGER PRIMARY KEY, applied_at TEXT NOT NULL)');
$oldPdo->exec("INSERT INTO schema_migrations VALUES (8, '2026-11-12')");
$oldPdo->exec('CREATE TABLE articles (id INTEGER PRIMARY KEY, title TEXT NOT NULL, status TEXT NOT NULL)');
$oldPdo->exec("INSERT INTO articles (id,title,status) VALUES (42,'Contenu préservé','draft')");
(new Portix\Infrastructure\Migrator($oldDb))->migrate();
$names = array_column($oldPdo->query('PRAGMA table_info(articles)')->fetchAll(PDO::FETCH_ASSOC), 'name');
reviewAssert(in_array('review_note', $names, true), 'Migration depuis 8 ajoute le commentaire sans reconstruire la table');
reviewAssert($oldPdo->query('SELECT title FROM articles WHERE id = 42')->fetchColumn() === 'Contenu préservé',
    'La migration conserve les données des articles');
reviewAssert((int) $oldPdo->query('PRAGMA user_version')->fetchColumn() === 9,
    'Migration existante vers version 9');
echo "Relecture : permissions, transitions et migration 9 vérifiées.\n";
