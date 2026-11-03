<?php
declare(strict_types=1);

use Portix\Navigation\BreadcrumbTrail;
use Portix\Seo\PageMetadata;
use Portix\View\Template;

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Ce test doit être exécuté en ligne de commande.\n");
    exit(1);
}

$root = dirname(__DIR__);
require $root . '/bootstrap/autoload.php';
chdir($root);

try {
    $metadata = new PageMetadata('Univers Guitare', '', 'https://www.universguitare.be');
    $metadata->define(
        'La forme du corps',
        '',
        'la-forme-du-corps.html',
        'article'
    );
    $trail = new BreadcrumbTrail('Accueil');
    $trail->add('Articles', 'articles.html');
    $trail->add('Anatomie', 'articles-categorie-anatomie.html');
    $trail->add('Le corps', 'articles-categorie-le-corps.html');
    $template = new Template('./var/templates/shared/breadcrumb.tpl');
    $trail->apply($template, $metadata, '/P3', false, 'Fil d’Ariane');

    $rows = $template->rows('breadcrumbitem');
    breadcrumbAssertSame(5, count($rows), 'nombre de niveaux');
    breadcrumbAssertSame('Accueil', $rows[0]['name'] ?? '', 'racine Accueil');
    breadcrumbAssertSame('/P3/', $rows[0]['url'] ?? '', 'Accueil respecte le sous-dossier');
    breadcrumbAssertSame('Articles', $rows[1]['name'] ?? '', 'niveau Articles');
    breadcrumbAssertSame('Anatomie', $rows[2]['name'] ?? '', 'catégorie parente');
    breadcrumbAssertSame('Le corps', $rows[3]['name'] ?? '', 'catégorie courante');
    breadcrumbAssertSame('La forme du corps', $rows[4]['name'] ?? '', 'page courante');
    breadcrumbAssertSame('1', $rows[4]['current'] ?? '', 'aria-current sur la page courante');

    $html = $template->render();
    breadcrumbAssertTrue(str_contains($html, 'aria-label="Fil d’Ariane"'), 'libellé accessible');
    breadcrumbAssertTrue(str_contains($html, 'aria-current="page"'), 'page courante accessible');
    breadcrumbAssertTrue(str_contains($html, 'href="/P3/"'), 'lien Accueil rendu');
    breadcrumbAssertTrue(str_contains($html, 'articles-categorie-le-corps.html'), 'lien catégorie rendu');

    $jsonLd = $metadata->jsonLd();
    breadcrumbAssertTrue(str_contains($jsonLd, 'BreadcrumbList'), 'JSON-LD BreadcrumbList');
    breadcrumbAssertTrue(str_contains($jsonLd, 'https://www.universguitare.be/'), 'racine canonique JSON-LD');
    breadcrumbAssertTrue(str_contains($jsonLd, 'La forme du corps'), 'page courante JSON-LD');

    $listingMetadata = new PageMetadata('Univers Guitare', '', 'https://www.universguitare.be');
    $listingMetadata->define('Actualités - Page 2', '', 'news-page-2.html');
    $listingTrail = new BreadcrumbTrail('Accueil');
    $listingTrail->add('Actualités', 'news.html');
    $listingTemplate = new Template('./var/templates/shared/breadcrumb.tpl');
    $listingTrail->apply($listingTemplate, $listingMetadata, '/P3', false, 'Fil d’Ariane');
    $listingRows = $listingTemplate->rows('breadcrumbitem');
    breadcrumbAssertSame(3, count($listingRows), 'niveau de pagination distinct');
    breadcrumbAssertSame('Actualités', $listingRows[1]['name'] ?? '', 'racine Actualités conservée');
    breadcrumbAssertSame('Actualités - Page 2', $listingRows[2]['name'] ?? '', 'page paginée courante');
    breadcrumbAssertSame('news-page-2.html', $listingRows[2]['url'] ?? '', 'pagination comme page courante');

    $frontMetadata = new PageMetadata('Univers Guitare', '', 'https://www.universguitare.be');
    $frontMetadata->define('Bienvenue', '', '/');
    $frontTrail = new BreadcrumbTrail('Accueil');
    $frontTemplate = new Template('./var/templates/shared/breadcrumb.tpl');
    $frontTrail->apply($frontTemplate, $frontMetadata, '/P3', true, 'Fil d’Ariane');
    breadcrumbAssertSame([], $frontTemplate->rows('breadcrumbitem'), 'pas de fil d’Ariane sur l’accueil');
    breadcrumbAssertTrue(!str_contains($frontMetadata->jsonLd(), 'BreadcrumbList'), 'pas de BreadcrumbList sur l’accueil');

    echo "Fil d’Ariane central : OK\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'Test du fil d’Ariane : ÉCHEC - ' . $error->getMessage() . "\n");
    exit(1);
}

function breadcrumbAssertSame(mixed $expected, mixed $actual, string $label): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($label . ' : attendu ' . var_export($expected, true)
            . ', reçu ' . var_export($actual, true));
    }
}

function breadcrumbAssertTrue(bool $condition, string $label): void
{
    if (!$condition) {
        throw new RuntimeException($label);
    }
}
