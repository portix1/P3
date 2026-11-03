<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Ce test doit être exécuté en ligne de commande.\n");
}

$root = dirname(__DIR__);
require $root . '/bootstrap/autoload.php';
$configData = json_decode((string) file_get_contents($root . '/var/sys/config.json'), true);
$homeTarget = \Portix\Http\Request::normalizeHomePageTarget(
    (string) (is_array($configData) ? ($configData['defaultpage'] ?? 'news') : 'news')
) ?? 'news';
$configuredSiteUrl = rtrim(trim((string) (is_array($configData) ? ($configData['site_url'] ?? '') : '')), '/');
$canonicalBaseUrl = httpTestCanonicalBaseUrl($configuredSiteUrl);

if (!class_exists(DOMDocument::class)) {
    fwrite(STDERR, "Test HTTP : ÉCHEC - l’extension DOM est requise.\n");
    exit(1);
}

$baseUrl = isset($argv[1]) ? (string) $argv[1] : (string) getenv('PORTIX_BASE_URL');
$baseUrl = trim($baseUrl !== '' ? $baseUrl : 'http://localhost/P3');
if (str_starts_with($baseUrl, '--base-url=')) {
    $baseUrl = substr($baseUrl, strlen('--base-url='));
}
$baseUrl = rtrim($baseUrl, '/');
$parts = parse_url($baseUrl);
if (!is_array($parts)
    || !in_array((string) ($parts['scheme'] ?? ''), ['http', 'https'], true)
    || empty($parts['host'])
    || isset($parts['user'])
    || isset($parts['pass'])
    || isset($parts['query'])
    || isset($parts['fragment'])) {
    fwrite(STDERR, "Test HTTP : ÉCHEC - adresse de base invalide.\n");
    exit(1);
}

$status = 0;
try {
    $front = httpTestRequest($baseUrl . '/');
    httpTestAssert($front['status'] === 200, 'la racine canonique doit répondre en HTTP 200');
    httpTestSecurityHeaders($front, str_starts_with($baseUrl, 'https://'));
    httpTestHtml($front, 'accueil canonique');
    httpTestAssert(
        str_contains(strtolower((string) ($front['headers']['cache-control'][0] ?? '')), 'public')
            && str_contains((string) ($front['headers']['cache-control'][0] ?? ''), 'max-age=300'),
        'accueil anonyme : cache public court absent'
    );
    httpTestAssert(
        !isset($front['headers']['set-cookie']),
        'accueil anonyme : une session PHP est encore créée'
    );
    httpTestAssert(
        str_contains($front['body'], 'href="login.html"') && str_contains($front['body'], '☠'),
        'accueil : lien de connexion discret absent du pied de page anonyme'
    );

    $homeAliases = array_values(array_unique(['accueil.html', $homeTarget . '.html']));
    foreach ($homeAliases as $legacyHome) {
        $response = httpTestRequest($baseUrl . '/' . $legacyHome);
        httpTestAssert($response['status'] === 301, $legacyHome . ' doit rediriger définitivement vers la racine');
        $location = (string) ($response['headers']['location'][0] ?? '');
        $locationPath = parse_url($location, PHP_URL_PATH);
        $basePath = (string) (parse_url($baseUrl, PHP_URL_PATH) ?? '');
        httpTestAssert(
            is_string($locationPath) && rtrim($locationPath, '/') === rtrim($basePath, '/'),
            $legacyHome . ' : destination canonique invalide'
        );
        httpTestSecurityHeaders($response, str_starts_with($baseUrl, 'https://'));
        echo '  OK  ' . $legacyHome . ' -> /' . PHP_EOL;
    }
    if ($homeTarget !== 'news') {
        $newsListing = httpTestRequest($baseUrl . '/news.html');
        httpTestAssert($newsListing['status'] === 200, 'news.html doit rester accessible quand une autre page est l’accueil');
        httpTestSecurityHeaders($newsListing, str_starts_with($baseUrl, 'https://'));
        httpTestHtml($newsListing, 'liste des actualités');
        echo '  OK  news.html' . PHP_EOL;
    }

    $routes = [
        'articles.html' => 'articles',
        'contact.html' => 'contact',
        'dwl.html' => 'téléchargements',
        'login.html' => 'connexion',
        'forgot-password.html' => 'mot de passe oublié',
        'reset-password.html' => 'réinitialisation du mot de passe',
        'Admin.html' => 'administration anonyme',
    ];
    foreach ($routes as $route => $label) {
        $response = httpTestRequest($baseUrl . '/' . $route);
        httpTestAssert($response['status'] === 200, $label . ' doit répondre en HTTP 200');
        httpTestSecurityHeaders($response, str_starts_with($baseUrl, 'https://'));
        httpTestHtml($response, $label);
        if ($route === 'contact.html') {
            httpTestAssert(
                str_contains(strtolower((string) ($response['headers']['cache-control'][0] ?? '')), 'no-store'),
                'contact : la page à jeton CSRF doit rester hors cache public'
            );
        }
        if ($route === 'login.html') {
            httpTestAssert(
                str_contains($response['body'], 'href="forgot-password.html"'),
                'connexion : lien mot de passe oublié absent'
            );
        }
        echo '  OK  ' . $route . PHP_EOL;
    }

    $sitemap = httpTestRequest($baseUrl . '/sitemap.xml');
    httpTestAssert($sitemap['status'] === 200, 'sitemap.xml doit répondre en HTTP 200');
    httpTestSecurityHeaders($sitemap, str_starts_with($baseUrl, 'https://'));
    httpTestAssert(
        str_contains(strtolower((string) ($sitemap['headers']['content-type'][0] ?? '')), 'application/xml'),
        'sitemap.xml doit être servi en application/xml'
    );
    $sitemapDocument = new DOMDocument();
    httpTestAssert($sitemapDocument->loadXML($sitemap['body']), 'sitemap.xml doit être un document XML valide');
    $sitemapXPath = new DOMXPath($sitemapDocument);
    $sitemapXPath->registerNamespace('s', 'http://www.sitemaps.org/schemas/sitemap/0.9');
    $sitemapLocations = $sitemapXPath->query('//s:url/s:loc');
    httpTestAssert($sitemapLocations !== false && $sitemapLocations->length > 0, 'sitemap.xml doit contenir des URL');
    $expectedCanonicalBase = $canonicalBaseUrl !== '' ? $canonicalBaseUrl : $baseUrl;
    $expectedHome = $expectedCanonicalBase . '/';
    $locations = [];
    foreach ($sitemapLocations ?: [] as $location) {
        $locations[] = rtrim((string) $location->textContent, '/') . '/';
    }
    httpTestAssert(in_array(rtrim($expectedHome, '/') . '/', $locations, true), 'sitemap.xml doit contenir la racine canonique');
    httpTestAssert(
        !in_array(rtrim($expectedCanonicalBase . '/accueil.html', '/') . '/', $locations, true)
            && !in_array(rtrim($expectedCanonicalBase . '/' . $homeTarget . '.html', '/') . '/', $locations, true),
        'sitemap.xml ne doit pas dupliquer la page d’accueil canonique'
    );
    if ($homeTarget !== 'news') {
        httpTestAssert(
            in_array(rtrim($expectedCanonicalBase . '/news.html', '/') . '/', $locations, true),
            'sitemap.xml doit conserver news.html quand la liste des actualités n’est pas l’accueil'
        );
    }

    $robots = httpTestRequest($baseUrl . '/robots.txt');
    httpTestAssert($robots['status'] === 200, 'robots.txt doit répondre en HTTP 200');
    httpTestSecurityHeaders($robots, str_starts_with($baseUrl, 'https://'));
    httpTestAssert(
        str_contains((string) $robots['body'], 'Sitemap: ')
            && str_contains((string) $robots['body'], '/sitemap.xml'),
        'robots.txt doit annoncer sitemap.xml'
    );
    foreach (['Disallow: /Admin', 'Disallow: /login.html', 'Disallow: /disconnect.html', 'Disallow: /articles-preview-', 'Disallow: /news-preview-'] as $directive) {
        httpTestAssert(str_contains((string) $robots['body'], $directive), 'robots.txt : directive absente ' . $directive);
    }

    $missing = httpTestRequest($baseUrl . '/page-portix-inexistante.html');
    httpTestAssert($missing['status'] === 404, 'une route inconnue doit répondre en HTTP 404');
    httpTestSecurityHeaders($missing, str_starts_with($baseUrl, 'https://'));
    httpTestHtml($missing, 'page introuvable');

    echo 'HTTP, structure HTML et accessibilité élémentaire : OK (' . $baseUrl . ')' . PHP_EOL;
} catch (Throwable $error) {
    fwrite(STDERR, 'Test HTTP : ÉCHEC - ' . $error->getMessage() . PHP_EOL);
    $status = 1;
}
exit($status);


function httpTestCanonicalBaseUrl(string $url): string
{
    if ($url === '') {
        return '';
    }
    $parts = parse_url($url);
    if (!is_array($parts)
        || !in_array(strtolower((string) ($parts['scheme'] ?? '')), ['http', 'https'], true)
        || trim((string) ($parts['host'] ?? '')) === ''
        || isset($parts['user'])
        || isset($parts['pass'])
        || isset($parts['query'])
        || isset($parts['fragment'])) {
        return '';
    }
    return $url;
}

/** @return array{status:int,headers:array<string,list<string>>,body:string} */
function httpTestRequest(string $url): array
{
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'ignore_errors' => true,
            'follow_location' => 0,
            'timeout' => 15,
            'header' => "Accept: text/html,application/xhtml+xml\r\n"
                . "User-Agent: Portix3-Release-Validator/0.7\r\n",
        ],
    ]);
    $body = @file_get_contents($url, false, $context);
    $rawHeaders = $http_response_header ?? [];
    if ($body === false && $rawHeaders === []) {
        $lastError = error_get_last();
        throw new RuntimeException(
            'connexion impossible à ' . $url
            . (is_array($lastError) ? ' (' . (string) $lastError['message'] . ')' : '')
        );
    }

    $status = 0;
    $headers = [];
    foreach ($rawHeaders as $line) {
        if (preg_match('~^HTTP/\S+\s+([1-5][0-9]{2})\b~i', $line, $match) === 1) {
            $status = (int) $match[1];
            continue;
        }
        $separator = strpos($line, ':');
        if ($separator === false) {
            continue;
        }
        $name = strtolower(trim(substr($line, 0, $separator)));
        $headers[$name][] = trim(substr($line, $separator + 1));
    }
    if ($status === 0) {
        throw new RuntimeException('statut HTTP illisible pour ' . $url);
    }
    return ['status' => $status, 'headers' => $headers, 'body' => (string) $body];
}

/** @param array{status:int,headers:array<string,list<string>>,body:string} $response */
function httpTestSecurityHeaders(array $response, bool $https): void
{
    $expected = [
        'x-content-type-options' => 'nosniff',
        'referrer-policy' => 'strict-origin-when-cross-origin',
        'x-frame-options' => 'DENY',
    ];
    foreach ($expected as $name => $value) {
        httpTestAssert(
            strcasecmp((string) ($response['headers'][$name][0] ?? ''), $value) === 0,
            'en-tête HTTP absent ou invalide : ' . $name
        );
    }
    httpTestAssert(
        str_contains((string) ($response['headers']['content-security-policy'][0] ?? ''), "default-src 'self'"),
        'politique CSP absente'
    );
    httpTestAssert(
        str_contains((string) ($response['headers']['permissions-policy'][0] ?? ''), 'camera=()'),
        'politique de permissions absente'
    );
    if ($https) {
        httpTestAssert(
            str_contains((string) ($response['headers']['strict-transport-security'][0] ?? ''), 'max-age='),
            'HSTS absent en HTTPS'
        );
    }
}

/** @param array{status:int,headers:array<string,list<string>>,body:string} $response */
function httpTestHtml(array $response, string $label): void
{
    $contentType = strtolower((string) ($response['headers']['content-type'][0] ?? ''));
    httpTestAssert(
        str_contains($contentType, 'text/html') && str_contains($contentType, 'charset=utf-8'),
        $label . ' : Content-Type HTML UTF-8 absent'
    );
    $body = $response['body'];
    httpTestAssert(
        preg_match('/^\s*<!doctype\s+html>/i', $body) === 1,
        $label . ' : doctype HTML5 absent'
    );
    httpTestAssert(
        preg_match('/\{%.*?%\}|<IF\s|<ENDIF>|<!--\s*(?:BEGIN|END|CHILD|FILE)\b/is', $body) !== 1,
        $label . ' : directive de template non résolue'
    );
    httpTestAssert(
        preg_match('/\{\{\s*[A-Za-z_][A-Za-z0-9_.]*\s*\}\}/', $body) !== 1,
        $label . ' : variable de template non résolue'
    );

    $previous = libxml_use_internal_errors(true);
    $document = new DOMDocument();
    $loaded = $document->loadHTML($body, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);
    httpTestAssert($loaded, $label . ' : document HTML illisible');
    $xpath = new DOMXPath($document);

    $html = $document->getElementsByTagName('html')->item(0);
    httpTestAssert(
        $html instanceof DOMElement && preg_match('/^[a-z]{2,3}(?:-[A-Za-z0-9]+)*$/', $html->getAttribute('lang')) === 1,
        $label . ' : langue du document absente ou invalide'
    );
    $title = $document->getElementsByTagName('title')->item(0);
    httpTestAssert($title !== null && trim($title->textContent) !== '', $label . ' : titre de document vide');
    httpTestAssert($xpath->query('//main')->length === 1, $label . ' : la page doit contenir un élément main unique');
    httpTestAssert($xpath->query('//h1')->length === 1, $label . ' : la page doit contenir un titre h1 unique');
    httpTestAssert(
        $xpath->query('//meta[translate(@name,"ABCDEFGHIJKLMNOPQRSTUVWXYZ","abcdefghijklmnopqrstuvwxyz")="viewport"]')->length === 1,
        $label . ' : meta viewport absente'
    );

    $ids = [];
    foreach ($xpath->query('//*[@id]') as $element) {
        if (!$element instanceof DOMElement) {
            continue;
        }
        $id = trim($element->getAttribute('id'));
        httpTestAssert($id !== '', $label . ' : attribut id vide');
        httpTestAssert(!isset($ids[$id]), $label . ' : identifiant HTML dupliqué : ' . $id);
        $ids[$id] = true;
    }
    foreach ($document->getElementsByTagName('img') as $image) {
        httpTestAssert($image->hasAttribute('alt'), $label . ' : image sans attribut alt');
    }
    foreach (['font', 'center', 'marquee', 'frame', 'frameset'] as $obsolete) {
        httpTestAssert(
            $document->getElementsByTagName($obsolete)->length === 0,
            $label . ' : élément HTML obsolète <' . $obsolete . '>'
        );
    }
    foreach ($xpath->query('//*') as $element) {
        if (!$element instanceof DOMElement) {
            continue;
        }
        foreach ($element->attributes as $attribute) {
            httpTestAssert(
                !str_starts_with(strtolower($attribute->name), 'on'),
                $label . ' : gestionnaire JavaScript intégré interdit (' . $attribute->name . ')'
            );
        }
    }

    foreach ($xpath->query('//input|//select|//textarea') as $control) {
        if (!$control instanceof DOMElement) {
            continue;
        }
        $type = strtolower($control->getAttribute('type'));
        if ($control->tagName === 'input' && in_array($type, ['hidden', 'submit', 'button', 'reset'], true)) {
            continue;
        }
        httpTestAssert(
            httpTestHasAccessibleName($control, $xpath, $ids),
            $label . ' : champ de formulaire sans nom accessible (' . $control->tagName . ')'
        );
    }
    foreach ($xpath->query('//button|//a[@href]') as $interactive) {
        if (!$interactive instanceof DOMElement) {
            continue;
        }
        httpTestAssert(
            httpTestHasAccessibleName($interactive, $xpath, $ids),
            $label . ' : lien ou bouton sans nom accessible'
        );
    }
}

/** @param array<string, bool> $ids */
function httpTestHasAccessibleName(DOMElement $element, DOMXPath $xpath, array $ids): bool
{
    if (trim($element->getAttribute('aria-label')) !== '') {
        return true;
    }
    $labelledBy = preg_split('/\s+/', trim($element->getAttribute('aria-labelledby'))) ?: [];
    foreach ($labelledBy as $id) {
        if ($id !== '' && isset($ids[$id])) {
            return true;
        }
    }
    if (trim($element->textContent) !== '') {
        return true;
    }
    if ($element->tagName === 'input' && trim($element->getAttribute('value')) !== '') {
        return true;
    }
    $id = trim($element->getAttribute('id'));
    if ($id !== '') {
        foreach ($xpath->query('//label[@for]') as $label) {
            if ($label instanceof DOMElement
                && $label->getAttribute('for') === $id
                && trim($label->textContent) !== '') {
                return true;
            }
        }
    }
    for ($parent = $element->parentNode; $parent !== null; $parent = $parent->parentNode) {
        if ($parent instanceof DOMElement && strtolower($parent->tagName) === 'label') {
            return trim($parent->textContent) !== '';
        }
    }
    foreach ($element->getElementsByTagName('img') as $image) {
        if (trim($image->getAttribute('alt')) !== '') {
            return true;
        }
    }
    return false;
}

function httpTestAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}
