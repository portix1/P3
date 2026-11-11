<?php
declare(strict_types=1);

use Portix\Domain\AnonymousUser;
use Portix\Http\Request;
use Portix\Infrastructure\Database;
use Portix\Infrastructure\Migrator;
use Portix\Repository\RepositoryRegistry;
use Portix\Support\Slugger;

/** Vérifie les URLs éditoriales et l'ordre du menu sans toucher à la base du site. */
if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Ce test doit être exécuté en ligne de commande.\n");
    exit(1);
}

$root = dirname(__DIR__);
require $root . '/bootstrap/autoload.php';
require $root . '/includes/class/html_sanitizer.class.php';

$temporaryRoot = rtrim(sys_get_temp_dir(), '/\\') . DIRECTORY_SEPARATOR
    . 'portix2-routing-smoke-' . getmypid() . '-' . bin2hex(random_bytes(4));
$originalServer = $_SERVER;
$database = null;
$status = 0;

try {
    $_SERVER['SCRIPT_NAME'] = '/P3/index.php';

    $_SERVER['REQUEST_URI'] = '/P3/';
    assertSameValue(true, Request::fromGlobals()->isFrontPage(), 'racine en sous-dossier reconnue comme accueil');
    assertSameValue('', Request::fromGlobals()->routeName(), 'racine en sous-dossier sans faux nom de route');
    assertSameValue(['news'], Request::fromGlobals()->routeArguments('news', ['news', 'contact']), 'racine résout explicitement la page par défaut');
    assertSameValue(
        ['bienvenue-sur-univers-guitare'],
        Request::fromGlobals()->routeArguments('bienvenue-sur-univers-guitare.html', ['news', 'contact', 'articles']),
        'racine accepte une news individuelle comme page d’accueil'
    );

    $_SERVER['REQUEST_URI'] = '/P3/index.php';
    assertSameValue(true, Request::fromGlobals()->isFrontPage(), 'index.php en sous-dossier reconnu comme accueil');
    assertSameValue('', Request::fromGlobals()->routeName(), 'index.php en sous-dossier sans faux nom de route');

    $_SERVER['REQUEST_URI'] = '/P3/accueil.html';
    assertSameValue(['news'], Request::fromGlobals()->routeArguments('news', ['news', 'contact']), 'alias accueil');
    assertSameValue(
        ['presentation-portix-3'],
        Request::fromGlobals()->routeArguments(
            'presentation-portix-3.html',
            ['news', 'contact', 'articles']
        ),
        'article utilisé comme page d’accueil'
    );
    assertSameValue(
        'presentation-portix-3',
        Request::normalizeHomePageTarget(
            'http://localhost/P3/presentation-portix-3.html'
        ),
        'normalisation d’une adresse complète de page d’accueil'
    );
    assertSameValue(null, Request::normalizeHomePageTarget('accueil.html'), 'boucle d’accueil refusée');
    assertSameValue(null, Request::normalizeHomePageTarget('javascript:alert(1)'), 'protocole dangereux refusé');

    $_SERVER['REQUEST_URI'] = '/P3/conges-annuels.html';
    assertSameValue(['conges-annuels'], Request::fromGlobals()->routeArguments('news', ['news', 'contact']), 'slug public');

    $_SERVER['REQUEST_URI'] = '/P3/news-page-2.html';
    assertSameValue(['news', 'page', '2'], Request::fromGlobals()->routeArguments('news', ['news', 'contact']), 'pagination des actualités');

    $_SERVER['REQUEST_URI'] = '/P3/Admin-news-edit-12.html';
    assertSameValue(['Admin', 'news', 'edit', '12'], Request::fromGlobals()->routeArguments('news', ['news', 'contact']), 'édition en administration');

    $_SERVER['REQUEST_URI'] = '/P3/Admin-insights-export.html?days=30';
    assertSameValue(
        ['Admin', 'insights', 'export'],
        Request::fromGlobals()->routeArguments('news', ['news', 'contact', 'insights']),
        'export des statistiques agrégées'
    );

    $_SERVER['REQUEST_URI'] = '/P3/news-edit-12.html';
    assertSameValue(['news-edit-12'], Request::fromGlobals()->routeArguments('news', ['news', 'contact']), 'ancienne édition publique retirée');

    $_SERVER['REQUEST_URI'] = '/P3/login.html';
    assertSameValue(['login'], Request::fromGlobals()->routeArguments('news', ['news', 'contact']), 'route de connexion dédiée');

    $_SERVER['REQUEST_URI'] = '/P3/forgot-password.html';
    assertSameValue(['forgot-password'], Request::fromGlobals()->routeArguments('news', ['news', 'contact']), 'route mot de passe oublié');

    $_SERVER['REQUEST_URI'] = '/P3/reset-password.html?token=abc';
    assertSameValue(['reset-password'], Request::fromGlobals()->routeArguments('news', ['news', 'contact']), 'route de réinitialisation');

    $_SERVER['REQUEST_URI'] = '/P3/contact-marc.html';
    assertSameValue(['contact', 'marc'], Request::fromGlobals()->routeArguments('news', ['news', 'contact']), 'route de module');

    $_SERVER['REQUEST_URI'] = '/P3/installer-portix.html';
    assertSameValue(
        ['installer-portix'],
        Request::fromGlobals()->routeArguments('news', ['news', 'contact', 'articles']),
        'URL publique courte d’un article modulaire'
    );

    $_SERVER['REQUEST_URI'] = '/P3/Admin-articles-category-edit-3.html';
    assertSameValue(
        ['Admin', 'articles', 'category', 'edit', '3'],
        Request::fromGlobals()->routeArguments('news', ['news', 'contact', 'articles']),
        'édition d’une catégorie d’articles'
    );

    $_SERVER['REQUEST_URI'] = '/P3/Admin-articles-menu.html';
    assertSameValue(
        ['Admin', 'articles', 'menu'],
        Request::fromGlobals()->routeArguments('news', ['news', 'contact', 'articles']),
        'configuration du menu Articles'
    );
    $_SERVER['REQUEST_URI'] = '/P3/Admin-articles-order.html';
    assertSameValue(
        ['Admin', 'articles', 'order'],
        Request::fromGlobals()->routeArguments('news', ['news', 'contact', 'articles']),
        'organisation éditoriale des articles'
    );
    $_SERVER['REQUEST_URI'] = '/P3/Admin-users-edit-marc-test.html';
    assertSameValue(
        ['Admin', 'users', 'edit', 'marc', 'test'],
        Request::fromGlobals()->routeArguments('news', ['news', 'contact']),
        'édition d’un utilisateur au pseudonyme composé'
    );
    assertSameValue('conges-annuels', Slugger::fromText('Congés annuels !'), 'normalisation du slug');
    assertSameValue(
        'l-ete-a-liege',
        Slugger::fromText('L’été à Liège'),
        'normalisation déterministe des accents et apostrophes'
    );
    assertSameValue(
        'coeur-ca-strasse',
        Slugger::fromText('Cœur, ça, Straße'),
        'normalisation des ligatures européennes'
    );

    $anonymous = new AnonymousUser();
    assertSameValue(false, $anonymous->isAdmin(), 'utilisateur anonyme administrateur');
    assertSameValue(false, $anonymous->isRedactor(), 'utilisateur anonyme rédacteur');

    foreach (['addnews.tpl', 'edit_news.tpl', 'comment.tpl'] as $removedTemplate) {
        assertSameValue(false, is_file($root . '/var/modules/news/templates/' . $removedTemplate), 'écran public retiré');
    }
    $publicNewsTemplate = (string) file_get_contents($root . '/var/modules/news/templates/index.tpl');
    assertSameValue(false, str_contains($publicNewsTemplate, 'author'), 'auteur public retiré');
    assertSameValue(false, str_contains($publicNewsTemplate, 'news-edit'), 'édition publique retirée');
    $administrationTemplate = (string) file_get_contents($root . '/var/admin/templates/news-list.tpl');
    assertSameValue(true, str_contains($administrationTemplate, 'created_by'), 'auteur interne conservé');
    $loginTemplate = (string) file_get_contents($root . '/var/modules/UserZone/templates/users.tpl');
    assertSameValue(
        true,
        str_contains($loginTemplate, 'href="forgot-password.html"'),
        'lien mot de passe oublié sur la connexion'
    );
    $articlesTemplate = (string) file_get_contents($root . '/var/modules/articles/templates/admin-list.tpl');
    assertSameValue(true, str_contains($articlesTemplate, 'article-row-actions'), 'colonnes des actions Articles');
    assertSameValue(true, str_contains($articlesTemplate, 'status_action_class'), 'couleur de l’action de publication');
    foreach (['NewsScroll', 'meta', 'count', 'logmgr', 'agenda', 'group'] as $retiredModule) {
        assertSameValue(
            false,
            is_file($root . '/var/modules/' . $retiredModule . '/desc.xml'),
            'ancien module retiré : ' . $retiredModule
        );
    }
    $adminLayoutSource = (string) file_get_contents($root . '/var/admin/index.tpl');
    $dashboardSource = (string) file_get_contents($root . '/var/admin/templates/dashboard.tpl');
    $navigationSource = (string) file_get_contents($root . '/src/Navigation/AdminNavigation.php');
    assertSameValue(
        true,
        str_contains($navigationSource, 'Admin-diagnostics.html')
            && str_contains($dashboardSource, 'Admin-management.html')
            && str_contains($adminLayoutSource, 'ux_nav_management'),
        'accès Diagnostics présent dans la navigation administrateur'
    );

    $moduleManagerTemplate = (string) file_get_contents($root . '/var/admin/templates/modmgr.tpl');
    assertSameValue(
        false,
        str_contains($moduleManagerTemplate, 'href="Admin-{{ checkmod.prefix }}.html"'),
        'pas de lien de configuration en doublon depuis le gestionnaire de modules'
    );
    assertSameValue(
        false,
        is_file($root . '/var/templates/modern/adminmenu.tpl'),
        'ancien menu d’administration public retiré'
    );
    $administrationLayout = (string) file_get_contents($root . '/var/admin/index.tpl');
    $dashboardTemplate = (string) file_get_contents($root . '/var/admin/templates/dashboard.tpl');
    assertSameValue(
        true,
        str_contains($administrationLayout, 'class="admin-sidebar"'),
        'colonne latérale d’administration persistante'
    );
    assertSameValue(
        true,
        str_contains($navigationSource, 'Admin-users.html')
            && str_contains($dashboardTemplate, 'Admin-writing.html')
            && str_contains($dashboardTemplate, 'Admin-configuration.html'),
        'navigation administrative centralisée sur le tableau de bord'
    );
    assertSameValue(
        true,
        is_file($root . '/var/modules/UserZone/AUTH')
            && str_contains(
                (string) file_get_contents($root . '/var/modules/UserZone/desc.xml'),
                '<Name>Authentification interne</Name>'
            ),
        'UserZone conservé uniquement comme service interne'
    );
    assertSameValue(
        false,
        is_file($root . '/var/modules/UserZone/config.php'),
        'ancien écran Utilisateurs retiré du module d’authentification'
    );
    $userZoneEntry = (string) file_get_contents($root . '/var/modules/UserZone/index.php');
    assertSameValue(
        true,
        str_contains($userZoneEntry, "\$template->set('login_message', '');"),
        'message de connexion initialisé pour le rendu strict de login.html'
    );
    assertSameValue(
        true,
        str_contains($userZoneEntry, '$authRoute = isset($argument[0])')
            && !str_contains($userZoneEntry, '$route = isset($argument[0])'),
        'UserZone ne doit pas écraser la route publique du moteur'
    );
    foreach (['config.tpl', 'newuser.tpl', 'saveuser.tpl', 'user_mgr.tpl', 'newuser.css'] as $legacyUserTemplate) {
        assertSameValue(
            false,
            is_file($root . '/var/modules/UserZone/templates/' . $legacyUserTemplate),
            'ancien template UserZone retiré : ' . $legacyUserTemplate
        );
    }
    foreach ([
        'var/modules/UserZone/templates/img',
        'var/modules/contact/templates/admin.tpl',
        'var/modules/download/img',
        'var/modules/download/templates/admin.tpl',
        'var/modules/download/templates/category.tpl',
        'var/modules/download/templates/edit_cat.tpl',
        'var/modules/download/templates/file_edit.tpl',
        'var/modules/download/templates/files.tpl',
        'var/modules/download/templates/new_cat.tpl',
        'var/modules/download/templates/upload.tpl',
        'var/modules/news/comment.png',
        'var/modules/news/more.png',
    ] as $legacyModuleArtifact) {
        assertSameValue(
            false,
            file_exists($root . '/' . $legacyModuleArtifact),
            'ancien fichier ou dossier de module retiré : ' . $legacyModuleArtifact
        );
    }
    $userAdminController = (string) file_get_contents(
        $root . '/src/Controller/Admin/UserAdminController.php'
    );
    assertSameValue(
        true,
        str_contains($userAdminController, 'if (!$actor->isAdmin())'),
        'administration des utilisateurs réservée aux administrateurs'
    );
    $userListTemplate = (string) file_get_contents($root . '/var/admin/templates/users-list.tpl');
    assertSameValue(
        true,
        str_contains($userListTemplate, 'data-confirm-delete'),
        'confirmation avant suppression d’un utilisateur'
    );
    assertSameValue(
        true,
        str_contains($userListTemplate, 'href="Admin-users.html?role=admin"')
            && str_contains($userListTemplate, 'href="Admin-users.html?role=redactor"'),
        'filtres cliquables des utilisateurs'
    );
    assertSameValue(
        true,
        str_contains($userListTemplate, '{{ users_manage_account }}'),
        'action explicite pour administrer un compte'
    );
    $publicThemeDirectories = glob($root . '/var/templates/*', GLOB_ONLYDIR) ?: [];
    $publicThemeCount = 0;
    foreach ($publicThemeDirectories as $publicThemeDirectory) {
        $themeName = basename($publicThemeDirectory);
        $themeIndex = $publicThemeDirectory . '/index.tpl';
        if (!is_file($themeIndex)) {
            continue;
        }
        $publicTemplate = (string) file_get_contents($themeIndex);
        if (in_array($themeName, ['univers-guitare', 'univers-guitare2'], true)) {
            assertSameValue(false, str_contains($publicTemplate, 'data-submenu-toggle'), $themeName . ' : sous-menus sans bouton fléché');
        } else {
            assertSameValue(true, str_contains($publicTemplate, 'data-submenu-toggle'), $themeName . ' : commande de sous-menu accessible');
        }
        assertSameValue(true, str_contains($publicTemplate, '{{ navigation.submenu }}'), $themeName . ' : contenu de sous-menu modulaire');
        assertSameValue(true, str_contains($publicTemplate, '{{ document_title }}'), $themeName . ' : titre SEO centralisé');
        assertSameValue(true, str_contains($publicTemplate, '{% slot breadcrumb %}'), $themeName . ' : slot de fil d’Ariane central');
        assertSameValue(false, str_contains($publicTemplate, 'CHILD meta'), $themeName . ' : ancien plugin MetaGenerator retiré');
        assertSameValue(false, str_contains($publicTemplate, 'news_scroll'), $themeName . ' : ancien bloc NewsScroll retiré');
        assertSameValue(true, str_contains($publicTemplate, 'href="login.html"'), $themeName . ' : connexion publique discrète dans le footer');
        assertSameValue(false, str_contains($publicTemplate, '{% slot users %}'), $themeName . ' : formulaire de connexion retiré de la page publique');
        assertSameValue(true, str_contains($publicTemplate, 'Admin.html#content-management'), $themeName . ' : accès public à la gestion de contenu');
        assertSameValue(true, str_contains($publicTemplate, 'Admin.html#administration'), $themeName . ' : accès public à la configuration');
        $publicThemeCount++;
    }
    assertSameValue(true, $publicThemeCount >= 4, 'au moins quatre thèmes publics distribués');
    $applicationSource = (string) file_get_contents($root . '/src/Core/Application.php');
    assertSameValue(
        false,
        str_contains($applicationSource, "&& \$routeName !== ''"),
        'la résolution des slugs publics fonctionne aussi à la racine'
    );
    $menuPluginSource = (string) file_get_contents($root . '/var/modules/menu/index.php');
    assertSameValue(
        true,
        str_contains($menuPluginSource, '$menuFullRoute')
            && str_contains($menuPluginSource, '$request->isFrontPage()'),
        'le menu reconnaît un slug complet comme page d’accueil'
    );
    $engineSource = (string) file_get_contents($root . '/includes/engine.php');
    assertSameValue(
        true,
        str_contains(
            $engineSource,
            "\$notFoundTemplate = './var/templates/' . \$activeTheme . '/not-found.tpl';"
        ),
        'la page 404 suit le thème public actif'
    );
    $adminShellTemplate = (string) file_get_contents($root . '/var/admin/index.tpl');
    assertSameValue(
        true,
        str_contains($adminShellTemplate, 'href="Admin.html"')
            && str_contains($adminShellTemplate, '{{ ui_dashboard }}'),
        'retour global au tableau de bord depuis les pages administratives'
    );
    assertSameValue(true, is_file($root . '/src/Seo/PageMetadata.php'), 'service de métadonnées central');
    $publicUi = (string) file_get_contents($root . '/var/editor/portix-ui.js');
    assertSameValue(true, str_contains($publicUi, 'is-submenu-open'), 'interaction du menu déroulant');
    $articlePublicTemplate = (string) file_get_contents($root . '/var/modules/articles/templates/index.tpl');
    $breadcrumbTemplate = (string) file_get_contents($root . '/var/templates/shared/breadcrumb.tpl');
    $articlePublicSource = (string) file_get_contents($root . '/var/modules/articles/index.php');
    assertSameValue(
        true,
        str_contains($breadcrumbTemplate, 'article-breadcrumb')
            && str_contains($articlePublicSource, '$breadcrumbTrail->add'),
        'fil d’Ariane central utilisé par Articles'
    );
    assertSameValue(true, str_contains($articlePublicTemplate, 'rel="prev"'), 'navigation vers l’article précédent');
    assertSameValue(true, str_contains($articlePublicTemplate, 'rel="next"'), 'navigation vers l’article suivant');
    assertSameValue(false, str_contains($articlePublicTemplate, 'articles-lire-'), 'ancien préfixe public retiré');
    $articleOrderTemplate = (string) file_get_contents($root . '/var/modules/articles/templates/admin-order.tpl');
    $articleOrderScript = (string) file_get_contents($root . '/var/modules/articles/admin-order.js');
    assertSameValue(true, str_contains($articleOrderTemplate, 'articles_order_tree'), 'écran d’organisation éditoriale');
    assertSameValue(true, str_contains($articleOrderScript, 'data-sortable-item'), 'glisser-déposer éditorial');
    assertSameValue(true, str_contains($articleOrderScript, 'data-move'), 'boutons haut et bas');
    $articleAdminController = (string) file_get_contents(
        $root . '/var/modules/articles/src/ArticleAdminController.php'
    );
    assertSameValue(
        true,
        str_contains($articleAdminController, 'if (!$user->isAdmin())')
            && str_contains($articleAdminController, 'http_response_code(403);'),
        'configuration du menu Articles réservée aux administrateurs'
    );
    foreach (['admin-list.tpl', 'admin-categories.tpl'] as $articleAdminTemplate) {
        $articleAdminMarkup = (string) file_get_contents(
            $root . '/var/modules/articles/templates/' . $articleAdminTemplate
        );
        assertSameValue(
            true,
            str_contains($articleAdminMarkup, '{% if articles_can_configure_menu == "1" %}'),
            'lien de configuration du menu masqué aux rédacteurs'
        );
    }
    $articleSchema = (string) file_get_contents($root . '/var/modules/articles/database/articles/desc.csv');
    assertSameValue(true, str_contains($articleSchema, 'position; numeric;'), 'position SQLite des articles');
    foreach (['34', 'chomeur', 'temp2', 'mgc'] as $removedTheme) {
        assertSameValue(false, is_dir($root . '/var/templates/' . $removedTheme), 'thème historique retiré');
    }
    assertSameValue(true, is_file($root . '/var/templates/modern/index.tpl'), 'thème moderne conservé');
    $articlesManifest = (string) file_get_contents($root . '/var/modules/articles/desc.xml');
    assertSameValue(
        true,
        str_contains($articlesManifest, '<PublicNavigation>navigation.php</PublicNavigation>'),
        'fournisseur de navigation déclaré par le module'
    );
    assertSameValue(
        true,
        str_contains($articlesManifest, '<PublicRouteResolver>routes.php</PublicRouteResolver>'),
        'résolveur d’URL publique déclaré par le module'
    );
    $engine = (string) file_get_contents($root . '/includes/engine.php');
    assertSameValue(true, str_contains($engine, "['public_navigation']"), 'point d’extension public générique');
    assertSameValue(false, str_contains($engine, 'ArticleNavigation'), 'aucun couplage du noyau à Articles');
    $application = (string) file_get_contents($root . '/src/Core/Application.php');
    assertSameValue(true, str_contains($application, 'PublicRouteResolver'), 'résolution publique modulaire');
    assertSameValue(false, str_contains($application, 'ArticleRepository'), 'aucun couplage du routeur à Articles');
    assertSameValue(
        true,
        str_contains($engine, '!is_file($entryFile) || is_link($entryFile)'),
        'un enregistrement de module périmé ne provoque pas d’erreur fatale'
    );
    $database = new Database($temporaryRoot . '/database/portix2.sqlite');
    (new Migrator($database))->migrate();
    $repositories = new RepositoryRegistry($database);
    $repositories->menu()->create('Accueil', 'Accueil', 'accueil.html');
    $repositories->menu()->create('News', 'Actualités', 'news.html');
    $repositories->menu()->create('Contact', 'Contact', 'contact.html');
    $guideUrl = $repositories->menu()->localUrlFromName('Guide bêta');
    $repositories->menu()->create('Guide bêta', '', $guideUrl);
    $repositories->menu()->reorder(['Contact', 'Accueil', 'News', 'Guide bêta']);
    assertSameValue(
        ['Contact', 'Accueil', 'News', 'Guide bêta'],
        array_column($repositories->menu()->allOrdered(), 'name'),
        'ordre du menu'
    );
    $menuRows = $repositories->menu()->allOrdered();
    assertSameValue('', (string) $menuRows[3]['description'], 'lien de menu sans description');
    assertSameValue('guide-beta.html', (string) $menuRows[3]['url'], 'URL générée depuis le nom du lien');
    assertSameValue('guide-beta-2.html', $repositories->menu()->localUrlFromName('Guide bêta'), 'collision entre liens du menu');
    assertSameValue(
        'actualite-news-interne',
        $repositories->news()->uniqueSlug('news-interne', ['news', 'contact']),
        'protection des routes réservées'
    );

    $image = '../var/uploads/images/0123456789abcdef0123456789abcdef.png';
    $clean = PortixHtmlSanitizer::clean('<p><img src="' . $image . '" alt="Test"></p>');
    if (strpos($clean, 'src="var/uploads/images/') === false) {
        throw new RuntimeException('La normalisation du chemin d’image a échoué.');
    }

    $installedModules = [];
    foreach (glob($root . '/var/modules/*/desc.xml') ?: [] as $file) {
        $manifest = \Portix\Module\ModuleManifest::fromXml($file, basename(dirname($file)));
        $installedModules[] = $manifest;
    }
    $_LANG = [];
    require $root . '/var/lang/fr.php';
    $uxTranslator = new \Portix\Support\Translator($_LANG);
    $sections = \Portix\Navigation\AdminNavigation::sections(
        $root, $installedModules, true, false, ['Admin', 'contact', 'settings'], $uxTranslator
    );
    assertSameValue(['writing', 'management', 'configuration'], array_keys($sections), 'trois espaces');
    assertSameValue(true, in_array('Admin-config-themes.html', array_column($sections['configuration'], 'url'), true), 'thèmes dans Configuration');
    assertSameValue(false, in_array('Admin-config-themes.html', array_column($sections['management'], 'url'), true), 'pas de doublon thèmes dans Administration');
    assertSameValue(false, str_contains($dashboardTemplate, 'admin_news_count'), 'pas de compteur éditorial en haut du tableau de bord');
    foreach (['writing', 'management', 'configuration'] as $area) {
        assertSameValue(true, str_contains($administrationLayout, 'Admin-' . $area . '.html'), 'raccourci vers chaque espace');
        assertSameValue(true, str_contains($dashboardTemplate, 'Admin-' . $area . '.html'), 'destination du raccourci');
    }
    assertSameValue(true, str_contains($dashboardTemplate, 'action-content') && str_contains($dashboardTemplate, 'action-management') && str_contains($dashboardTemplate, 'action-configuration'), 'code couleur du tableau de bord');

    $active = [];
    foreach ($sections as $section => $entries) {
        foreach ($entries as $entry) {
            assertSameValue(false, $entry['icon'] === '', 'icône de navigation');
            if ($entry['current'] === 'is-current') { $active[] = [$section, $entry['url']]; }
        }
    }
    assertSameValue([['configuration', 'Admin-contact-settings.html']], $active, 'réglages Contact classés dans Configuration');
    $editorSections = \Portix\Navigation\AdminNavigation::sections($root, $installedModules, false, true, ['Admin'], $uxTranslator);
    assertSameValue([], $editorSections['management'], 'administration absente pour un rédacteur');
    assertSameValue([], $editorSections['configuration'], 'configuration absente pour un rédacteur');
    $anonymousSections = \Portix\Navigation\AdminNavigation::sections($root, $installedModules, false, false, ['Admin'], $uxTranslator);
    assertSameValue(['writing'=>[], 'management'=>[], 'configuration'=>[]], $anonymousSections, 'navigation privée');
    echo "Routeur éditorial, ordre du menu et images : OK\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'Test routeur/menu : ECHEC - ' . $error->getMessage() . "\n");
    $status = 1;
} finally {
    $_SERVER = $originalServer;
    unset($repositories);
    if ($database instanceof Database) {
        $database->close();
    }
    unset($database);
    gc_collect_cycles();
    removeRoutingTree($temporaryRoot);
}

exit($status);

function assertSameValue(mixed $expected, mixed $actual, string $label): void
{
    if ($expected !== $actual) {
        throw new RuntimeException(
            $label . ' : attendu ' . var_export($expected, true)
            . ', obtenu ' . var_export($actual, true)
        );
    }
}

function removeRoutingTree(string $directory): void
{
    if (!is_dir($directory) || !str_starts_with(basename($directory), 'portix2-routing-smoke-')) {
        return;
    }
    for ($attempt = 0; $attempt < 10 && is_dir($directory); $attempt++) {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iterator as $item) {
            $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
        }
        @rmdir($directory);
        if (is_dir($directory)) {
            usleep(100000);
        }
    }
}
