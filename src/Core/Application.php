<?php
declare(strict_types=1);

namespace Portix\Core;

use Portix\Configuration\SiteConfiguration;
use Portix\Http\Request;
use Portix\Infrastructure\Database;
use Portix\Infrastructure\Migrator;
use Portix\Observability\EventLogger;
use Portix\Repository\RepositoryRegistry;
use Portix\Seo\PageMetadata;
use Portix\Seo\PublicUrl;
use Portix\Seo\SitemapGenerator;
use Throwable;

final class Application
{
    private ?RepositoryRegistry $repositories = null;
    private bool $migrated = false;
    private string $requestId;

    public function __construct(
        private readonly string $root,
        private readonly Request $request,
        private readonly SiteConfiguration $configuration,
        private readonly Database $database
    ) {
        $this->requestId = bin2hex(random_bytes(8));
    }

    public function run(): void
    {
        try {
            \PortixSecurity::sendHeaders();
            header('X-Request-Id: ' . $this->requestId);
            if (!$this->configuration->isInstalled()) {
                header('Location: ' . $this->request->basePath() . '/install.php');
                return;
            }

            $redirect = PublicUrl::redirectLocation(
                (string) $this->configuration->get('site_url', ''),
                $this->request
            );
            if ($redirect !== null) {
                http_response_code(in_array($this->request->method(), ['GET', 'HEAD'], true) ? 301 : 308);
                header('Location: ' . $redirect);
                header('Cache-Control: no-store');
                return;
            }

            $this->migrate();
            \Portix\Media\MediaBridge::configure($this->database->connection(), $this->root, !in_array($this->request->method(), ['GET', 'HEAD'], true));
            \PortixSecurity::configureRateLimiter($this->database->connection());
            $repositories = $this->repositories();
            $modules = $repositories->modules()->allOrdered();
            if ((new \Portix\Seo\DiscoveryFiles($this->root, $this->database->connection()))->respond($this->request, $this->configuration, $repositories, $modules)) {
                return;
            }
            if ($this->respondToCrawlerFiles($repositories, $modules)) {
                return;
            }
            $homeTarget = Request::normalizeHomePageTarget((string) $this->configuration->get('defaultpage', ''));
            $routeName = strtolower($this->request->routeName());
            if ($this->request->isLegacyHome()
                || (!$this->request->isFrontPage() && $homeTarget !== null && $routeName === strtolower($homeTarget))) {
                http_response_code(301);
                header('Location: ' . ($this->request->basePath() !== '' ? $this->request->basePath() : '') . '/');
                return;
            }

            $this->render($repositories, $modules);
        } catch (Throwable $error) {
            $this->renderFailure($error);
        }
    }

    public function migrate(): void
    {
        if ($this->migrated) {
            return;
        }
        (new Migrator($this->database))->migrate();
        // Register only the newly bundled media module; preserve existing module settings.
        $mediaDirectory = $this->root . '/var/modules/media';
        if (is_file($mediaDirectory . '/desc.xml') && is_file($mediaDirectory . '/config.php')) {
            $modules = new \Portix\Repository\ModuleRepository($this->database->connection());
            if ($modules->findByPrefix('media') === null) {
                $manifest = \Portix\Module\ModuleManifest::fromXml($mediaDirectory . '/desc.xml', 'media');
                $this->database->transaction(function () use ($mediaDirectory, $manifest, $modules): void {
                    (new \Portix\Module\ModuleSchemaInstaller($this->database))->install('media', $mediaDirectory . '/database');
                    $modules->synchronizeDefinition($manifest);
                });
            }
        }
        $this->migrated = true;
    }

    public function repositories(): RepositoryRegistry
    {
        $this->migrate();
        return $this->repositories ??= new RepositoryRegistry($this->database);
    }

    public function root(): string { return $this->root; }
    public function request(): Request { return $this->request; }
    public function configuration(): SiteConfiguration { return $this->configuration; }
    public function database(): Database { return $this->database; }

    /** @param list<array<string, mixed>> $modules */
    private function render(RepositoryRegistry $repositories, array $modules): void
    {
        if (!defined('PROFILING')) {
            define('PROFILING', false);
        }
        if (!defined('DEBUG')) {
            define('DEBUG', (bool) $this->configuration->get('debug', false));
        }

        $CONFIG = $this->configuration->all();
        $_LANG = ['TEMPLATE_PATH' => (string) $CONFIG['template']];
        $Isadmin = false;
        $modulePrefixes = array_map(
            static fn (array $module): string => (string) $module['prefix'],
            array_values(array_filter(
                $modules,
                static fn (array $module): bool => !empty($module['enabled'])
            ))
        );
        $requestedArguments = $this->request->routeArguments(
            (string) $CONFIG['defaultpage'],
            $modulePrefixes
        );
        $routeName = $this->request->routeName();
        $firstArgument = (string) ($requestedArguments[0] ?? '');
        $knownStandaloneRoutes = ['disconnect', 'login', 'personnal', 'forgot-password', 'reset-password'];
        if (count($requestedArguments) === 1
            && !in_array(strtolower($firstArgument), array_map('strtolower', $modulePrefixes), true)
            && !in_array(strtolower($firstArgument), $knownStandaloneRoutes, true)
            && strcasecmp($firstArgument, 'Admin') !== 0) {
            $news = $repositories->news()->findPublishedBySlug($firstArgument);
            if ($news !== null) {
                $requestedArguments = ['news', 'slug', $firstArgument];
            } else {
                $moduleRoute = (new \Portix\Module\PublicRouteResolver(
                    $this->root,
                    $this->database->connection()
                ))->resolve($firstArgument, $modules);
                $requestedArguments = $moduleRoute ?? ['__not_found'];
            }
        }
        $requestedRoute = isset($requestedArguments[0])
            ? (string) $requestedArguments[0]
            : (string) $CONFIG['defaultpage'];
        $isAdministrationRequest = $requestedRoute === 'Admin';
        $privateResponse = $isAdministrationRequest || session_status() === PHP_SESSION_ACTIVE
            || in_array(strtolower($requestedRoute), ['login', 'personnal', 'forgot-password', 'reset-password', 'disconnect'], true);
        if (!headers_sent()) {
            header('Vary: Cookie', false);
            if ($privateResponse) { header('Cache-Control: private, no-store, max-age=0'); }
            if (strtolower($requestedRoute) === 'reset-password') { header('Referrer-Policy: no-referrer'); }
        }
        $templateFile = $isAdministrationRequest
            ? './var/admin/index.tpl'
            : './var/templates/' . basename((string) $CONFIG['template']) . '/index.tpl';
        $template = new \Portix\View\Template($templateFile);
        $template->set('media_enabled', \Portix\Media\MediaBridge::active() ? '1' : '0');
        $template->set('title', $CONFIG['title']);
        $template->set('site_home', 'accueil');
        $template->set('base_url', $this->request->basePath());
        $template->set('language_code', basename((string) $CONFIG['lang']));
        $template->set('csrf_token', \PortixSecurity::csrfToken());
        $template->set(
            'asset_version',
            $this->assetVersion(basename((string) $CONFIG['template']))
        );
        $template->set(
            'portix_version',
            is_file($this->root . '/VERSION')
                ? trim((string) file_get_contents($this->root . '/VERSION'))
                : ''
        );

        $defaultSocialImage = trim((string) ($CONFIG['seo_default_image'] ?? ''));
        if ($defaultSocialImage === ''
            && in_array((string) ($CONFIG['template'] ?? ''), ['univers-guitare', 'univers-guitare2'], true)
            && is_file($this->root . '/var/templates/univers-guitare/assets/guitar-fretboard-header-0703.webp')) {
            $defaultSocialImage = 'var/templates/univers-guitare/assets/guitar-fretboard-header-0703.webp';
        }
        $pageMetadata = new PageMetadata(
            (string) $CONFIG['title'],
            (string) ($CONFIG['site_description'] ?? ''),
            PublicUrl::resolve((string) ($CONFIG['site_url'] ?? ''), $this->request),
            (string) ($CONFIG['seo_default_author'] ?? ''),
            (string) ($CONFIG['seo_publisher_name'] ?? ''),
            (string) ($CONFIG['seo_publisher_logo'] ?? ''),
            $defaultSocialImage
        );

        $request = $this->request;
        $requestId = $this->requestId;
        $configuration = $this->configuration;
        $database = $this->database;
        require $this->root . '/includes/engine.php';
        if (!$isAdministrationRequest && $this->request->isFrontPage() && (int) http_response_code() < 400) {
            $pageMetadata->configureHomePage(
                (string) ($CONFIG['seo_home_title'] ?? ''),
                (string) ($CONFIG['seo_home_description'] ?? '')
            );
        }
        if (!$isAdministrationRequest
            && isset($breadcrumbTrail)
            && $breadcrumbTrail instanceof \Portix\Navigation\BreadcrumbTrail) {
            $breadcrumbTrail->apply(
                $template,
                $pageMetadata,
                $this->request->basePath(),
                $this->request->isFrontPage(),
                isset($translator) && $translator instanceof \Portix\Support\Translator
                    ? $translator->text('ui_breadcrumb', 'Breadcrumb')
                    : 'Breadcrumb'
            );
        }
        if (!$isAdministrationRequest && !str_contains($pageMetadata->robots(), 'noindex') && !headers_sent()) {
            header('Link: ' . \Portix\Seo\DiscoveryFiles::links(PublicUrl::resolve((string) $this->configuration->get('site_url', ''), $this->request)), false);
        }
        $template->set('document_title', $pageMetadata->documentTitle());
        $template->set('meta_description', $pageMetadata->description());
        $template->set('meta_has_description', $pageMetadata->description() !== '' ? '1' : '0');
        $template->set('meta_canonical', $pageMetadata->canonicalUrl());
        $template->set('meta_has_canonical', $pageMetadata->canonicalUrl() !== '' ? '1' : '0');
        $template->set('meta_og_type', $pageMetadata->type());
        $template->set('meta_image', $pageMetadata->imageUrl());
        $template->set('meta_has_image', $pageMetadata->imageUrl() !== '' ? '1' : '0');
        $template->set('meta_robots', $pageMetadata->robots());
        $template->set('meta_json_ld', \Portix\View\Html::trusted($pageMetadata->jsonLd()));
        $template->set('meta_has_json_ld', $pageMetadata->jsonLd() !== '' ? '1' : '0');
        if (!headers_sent()) {
            if ($privateResponse || !empty($isAuthenticated) || session_status() === PHP_SESSION_ACTIVE) {
                header('Cache-Control: private, no-store, max-age=0');
            } elseif (!$this->hasResponseHeader('Cache-Control')) {
                if (in_array($this->request->method(), ['GET', 'HEAD'], true)) {
                    header('Cache-Control: public, max-age=300, must-revalidate');
                } else {
                    header('Cache-Control: no-store');
                }
            }
        }
        if (!headers_sent()) { header('Content-Type: text/html; charset=UTF-8'); }
        echo \Portix\Media\MediaBridge::versionHtml($template->render());
    }

    private function hasResponseHeader(string $name): bool
    {
        $prefix = strtolower($name) . ':';
        foreach (headers_list() as $header) {
            if (str_starts_with(strtolower($header), $prefix)) {
                return true;
            }
        }

        return false;
    }

    private function assetVersion(string $theme): string
    {
        $files = [
            $this->root . '/VERSION',
            $this->root . '/var/templates/' . $theme . '/style.min.css',
            $this->root . '/var/editor/portix-editor.js',
            $this->root . '/var/editor/portix-editor.css',
            $this->root . '/var/editor/portix-ui.js',
            $this->root . '/var/admin/style.css',
            $this->root . '/var/admin/icons.css',
            $this->root . '/var/admin/icons.js',
            $this->root . '/var/modules/menu/admin-menu.js',
            $this->root . '/var/modules/articles/admin-order.js',
        ];

        $latestModification = 0;
        foreach ($files as $file) {
            if (!is_file($file)) {
                continue;
            }
            $modification = filemtime($file);
            if ($modification !== false) {
                $latestModification = max($latestModification, $modification);
            }
        }

        if ($latestModification > 0) {
            $manifest = $this->root . '/var/templates/' . $theme . '/theme.json';
            return (string) $latestModification . (is_file($manifest) ? '-' . substr((string) hash_file('sha256', $manifest), 0, 12) : '');
        }

        return '0';
    }

    /** @param list<array<string, mixed>> $modules */
    private function respondToCrawlerFiles(RepositoryRegistry $repositories, array $modules): bool
    {
        if (!$this->request->isSitemap() && !$this->request->isRobotsFile()) {
            return false;
        }
        if (!in_array($this->request->method(), ['GET', 'HEAD'], true)) {
            http_response_code(405);
            header('Allow: GET, HEAD');
            return true;
        }

        $baseUrl = PublicUrl::resolve((string) $this->configuration->get('site_url', ''), $this->request);
        if ($this->request->isRobotsFile()) {
            $body = "User-agent: *\n"
                . "Disallow: /Admin\n"
                . "Disallow: /login.html\n"
                . "Disallow: /disconnect.html\n"
                . "Disallow: /forgot-password.html\n"
                . "Disallow: /reset-password.html\n"
                . "Disallow: /personnal.html\n"
                . "Disallow: /articles-preview-\n"
                . "Disallow: /news-preview-\n\n"
                . "Sitemap: " . $baseUrl . "/sitemap.xml\n";
            header('Content-Type: text/plain; charset=UTF-8');
        } else {
            $body = (new SitemapGenerator($this->root, $this->database->connection()))->render(
                $baseUrl,
                $modules,
                $repositories->menu()->allOrdered(),
                (string) $this->configuration->get('defaultpage', '')
            );
            header('Content-Type: application/xml; charset=UTF-8');
        }
        header('Cache-Control: no-cache, max-age=0, must-revalidate');
        if ($this->request->method() !== 'HEAD') {
            echo $body;
        }
        return true;
    }

    private function renderFailure(Throwable $error): void
    {
        error_log((string) $error);
        try {
            (new EventLogger($this->database->connection()))->application(
                'error',
                'application.failure',
                $error->getMessage(),
                ['exception' => $error::class, 'route' => $this->request->routeName()],
                $this->requestId
            );
        } catch (Throwable) {
        }
        http_response_code(500);
        header('Content-Type: text/html; charset=UTF-8');
        $debug = (bool) $this->configuration->get('debug', false);
        $message = $debug
            ? $error->getMessage() . "\n\n" . $error->getTraceAsString()
            : 'Une erreur interne est survenue. Consultez le journal PHP.';
        echo '<!doctype html><html lang="fr"><meta charset="utf-8"><title>Erreur P3</title>'
            . '<body><h1>Erreur P3</h1><pre>'
            . htmlspecialchars($message, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
            . '</pre></body></html>';
    }
}
