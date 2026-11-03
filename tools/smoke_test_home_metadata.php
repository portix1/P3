<?php
declare(strict_types=1);

use Portix\Configuration\SiteConfiguration;
use Portix\Seo\PageMetadata;
use Portix\Support\Translator;
use Portix\View\Template;

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$root = dirname(__DIR__);
chdir($root);
require $root . '/bootstrap/autoload.php';
require $root . '/includes/class/security.class.php';

function homeCheck(bool $condition, string $message): void
{
    if (!$condition) { throw new RuntimeException($message); }
}

try {
    $metadata = new PageMetadata('Univers Guitare', 'Description générale.', 'https://www.universguitare.be');
    $metadata->define('Bienvenue sur Univers Guitare.', 'Introduction de bienvenue.', '/', 'article', null, null, '', '', 'NewsArticle');
    $metadata->configureHomePage('Comprendre la guitare électrique', 'Des articles illustrés sur la construction et le fonctionnement de la guitare électrique.');
    homeCheck($metadata->documentTitle() === 'Comprendre la guitare électrique — Univers Guitare', 'titre indépendant');
    homeCheck($metadata->description() === 'Des articles illustrés sur la construction et le fonctionnement de la guitare électrique.', 'description indépendante');
    homeCheck($metadata->canonicalUrl() === 'https://www.universguitare.be/', 'canonique de l’accueil');
    $graph = json_decode($metadata->jsonLd(), true, 512, JSON_THROW_ON_ERROR)['@graph'];
    $websites = array_values(array_filter($graph, static fn (array $n): bool => $n['@type'] === 'WebSite'));
    $news = array_values(array_filter($graph, static fn (array $n): bool => $n['@type'] === 'NewsArticle'));
    homeCheck(count($websites) === 1 && $websites[0]['name'] === 'Univers Guitare' && $websites[0]['url'] === 'https://www.universguitare.be/', 'nom de site WebSite');
    homeCheck($news[0]['headline'] === 'Bienvenue sur Univers Guitare.', 'titre éditorial conservé dans NewsArticle');

    $fallback = new PageMetadata('Test', 'Description générale.', 'https://example.test/P3');
    $fallback->define('Bienvenue', 'Introduction existante.', '/');
    $fallback->configureHomePage();
    homeCheck($fallback->documentTitle() === 'Bienvenue — Test' && $fallback->description() === 'Introduction existante.', 'champs vides conservent les métadonnées actuelles');
    homeCheck($fallback->canonicalUrl() === 'https://example.test/P3/', 'installation en sous-dossier');
    $internal = new PageMetadata('Test', 'Description générale.', 'https://example.test');
    $internal->define('Un article', 'Description article.', 'un-article.html', 'article');
    homeCheck(!str_contains($internal->jsonLd(), 'WebSite'), 'pas de WebSite sur un article intérieur');
    homeCheck($internal->documentTitle() === 'Un article — Test', 'titre intérieur conservé');
    $sameName = new PageMetadata('Test', '', 'https://example.test');
    $sameName->configureHomePage('Test');
    homeCheck($sameName->documentTitle() === 'Test', 'pas de répétition du nom du site');

    // Exercise the existing general configuration form, including writes and errors.
    $tmp = sys_get_temp_dir() . '/p3-home-config-' . bin2hex(random_bytes(6));
    mkdir($tmp . '/var/sys', 0770, true);
    $configuration = SiteConfiguration::load($tmp);
    $configuration->update(['title' => 'Test', 'site_url' => 'https://example.test', 'defaultpage' => 'news']);
    $_LANG = [];
    require $root . '/var/lang/fr.php';
    $translator = new Translator($_LANG);
    $_SESSION['csrf_token'] = 'home-test-token';
    $basePost = ['langage' => 'fr', 'templ' => 'modern', 'defpage' => 'news.html', 'conftitle' => 'Test', 'site_url' => 'https://example.test', 'site_description' => 'Description générale.', 'csrf_token' => 'home-test-token'];
    foreach (['valid', 'missing', 'too-long-title', 'too-long-description', 'csrf', 'clear'] as $case) {
        $CONFIG = $configuration->all();
        $template = new Template($root . '/var/admin/templates/config.tpl');
        foreach ($_LANG as $key => $value) { $template->set($key, $value); }
        $template->set('csrf_token', 'home-test-token');
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = $basePost;
        if ($case === 'valid') { $_POST += ['seo_home_title' => '<b>Un accueil clair</b>', 'seo_home_description' => 'Une description spécifique.']; }
        if ($case === 'too-long-title') { $_POST['seo_home_title'] = str_repeat('é', 181); }
        if ($case === 'too-long-description') { $_POST['seo_home_description'] = str_repeat('é', 171); }
        if ($case === 'csrf') { $_POST['csrf_token'] = 'wrong'; $_POST['seo_home_title'] = 'Modification interdite'; }
        if ($case === 'clear') { $_POST += ['seo_home_title' => '', 'seo_home_description' => '']; }
        $before = file_get_contents($tmp . '/var/sys/config.json');
        require $root . '/includes/config.php';
        $saved = SiteConfiguration::load($tmp);
        $html = $template->render();
        homeCheck(str_contains($html, 'name="seo_home_title"') && str_contains($html, 'name="seo_home_description"'), 'champs du formulaire');
        if ($case === 'valid' || $case === 'missing') {
            homeCheck($saved->get('seo_home_title') === 'Un accueil clair' && $saved->get('seo_home_description') === 'Une description spécifique.', 'enregistrement et conservation sans nouveau champ');
        } elseif ($case === 'clear') {
            homeCheck($saved->get('seo_home_title') === '' && $saved->get('seo_home_description') === '', 'effacement explicite');
        } else {
            homeCheck(file_get_contents($tmp . '/var/sys/config.json') === $before, 'refus de l’écriture invalide');
            if ($case === 'too-long-title') { homeCheck(str_contains($html, str_repeat('é', 181)), 'brouillon conservé après erreur'); }
        }
    }
    unlink($tmp . '/var/sys/config.json'); rmdir($tmp . '/var/sys'); rmdir($tmp . '/var'); rmdir($tmp);
    echo "Accueil SEO : réglages, conservation, validation, CSRF, canonique et WebSite OK\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'Test accueil SEO : ÉCHEC - ' . $error->getMessage() . "\n");
    exit(1);
}
