<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Ce test doit être exécuté en ligne de commande.\n");
    exit(1);
}

$root = dirname(__DIR__);

try {
    $source = $root . '/src/Installation/default-content.json';
    if (!is_file($source)) {
        throw new RuntimeException('Contenu documentaire initial absent.');
    }

    $content = json_decode(
        (string) file_get_contents($source),
        true,
        512,
        JSON_THROW_ON_ERROR
    );

    $categories = $content['categories'] ?? [];
    $news = $content['news'] ?? [];
    $articles = [];

    foreach (($content['articles'] ?? []) as $article) {
        $slug = (string) ($article['slug'] ?? '');
        if ($slug === '') {
            throw new RuntimeException('Article documentaire sans slug.');
        }
        $articles[$slug] = (string) ($article['body'] ?? '');
    }

    if (count($categories) !== 3) {
        throw new RuntimeException('Le contenu initial doit distribuer 3 catégories.');
    }
    if (count($articles) !== 8) {
        throw new RuntimeException('Le contenu initial doit distribuer 8 articles.');
    }
    if (count($news) !== 2) {
        throw new RuntimeException('Le contenu initial doit distribuer 2 actualités.');
    }

    documentationRequires($articles, 'presentation', [
        '<h2>P3</h2>',
        'PHP 8.2',
        'SQLite',
        'Actualités',
        'Articles',
        'Recherche',
        'Affiliation',
    ]);

    documentationRequires($articles, 'installation', [
        'Installation initiale et documentation',
        'src/Installation/default-content.json',
        'Versions des modules',
        '&lt;Version&gt;',
        'MODULE-VERSION',
        'Intégration continue',
        'PHP 8.2',
        '8.4',
    ]);

    documentationRequires($articles, 'administration-de-portix-3', [
        'Gestion des conversations depuis la liste',
        'Corbeille et conservation',
        '30 jours',
        'deux colonnes',
        'Actualités épinglées',
        'Le module Recherche',
        'L’administration du module Affiliation',
        'Confirmations de suppression en affiliation',
        'Métadonnées propres à l’accueil',
        'Version des modules',
        'Thèmes versionnés et installation ZIP',
        'conflits de personnalisation',
        'restauration refuse',
    ]);

    documentationRequires($articles, 'guide-du-redacteur-portix-3', [
        'palette image et collage en cellule',
        'sans scinder le tableau',
        'Éditeur visuel Snow', 'Images et légendes attachées', 'Déplacer par glisser-déposer',
        'Palette du tableau', 'Ligne horizontale',
        'Épingler une actualité',
        'Coller un tableau dans l’éditeur',
        'Aperçu',
    ]);

    documentationRequires($articles, 'guide-du-redacteur-portix-3', [
        'Insérer une vidéo',
        'YouTube Shorts',
        'Vimeo',
        'Dailymotion',
        'youtube-nocookie.com',
    ]);

    documentationRequires($articles, 'portix-3-structure-et-fonctionnement', [
        'Versions de modules',
        'Affiliation 1.1.2',
        'Nettoyage des tests SQLite',
        'News 1.0.1',
        'Search 1.0.3',
        'Contrat formel de thème',
        'Manifeste et paquet de thème',
        'theme.json',
        'ThemePackageManager',
        'validate_theme.php --all',
    ]);

    documentationRequires($articles, 'developper-un-module', [
        'Déclarer la version du module',
        '&lt;Version&gt;1.0.0&lt;/Version&gt;',
        'MODULE-VERSION',
        'Templates stricts',
    ]);

    documentationRequires($articles, 'diagnostics-de-portix-3', [
        'Administration → Diagnostics',
        'Tests rapides',
        'Suite complète',
        'Copier le rapport',
        'CI PHP 8.2–8.4',
    ]);

    documentationRequires($articles, 'developper-un-module-avec-l-aide-d-une-ia', [
        'Travailler depuis une baseline connue',
        'Vérifier les fichiers générés',
        'smoke_test_documentation.php',
        'CI PHP 8.2–8.4',
    ]);

    echo "Documentation en BDD / contenu initial : OK\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'Test documentation : ÉCHEC - ' . $error->getMessage() . "\n");
    exit(1);
}

/**
 * @param array<string,string> $articles
 * @param list<string> $markers
 */
function documentationRequires(array $articles, string $slug, array $markers): void
{
    if (!isset($articles[$slug])) {
        throw new RuntimeException('Guide absent : ' . $slug);
    }
    foreach ($markers as $marker) {
        if (!str_contains($articles[$slug], $marker)) {
            throw new RuntimeException('Guide ' . $slug . ' incomplet : ' . $marker);
        }
    }
}
