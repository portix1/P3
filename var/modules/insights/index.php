<?php
declare(strict_types=1);

require_once __DIR__ . '/src/AudienceMetrics.php';
require_once __DIR__ . '/src/ConfirmedAudienceMetrics.php';
require_once __DIR__ . '/src/TrafficSources.php';
$insightsLegacyAudience = new \Portix\Insights\AudienceMetrics($database->connection());
$insightsAudience = new \Portix\Insights\ConfirmedAudienceMetrics($database->connection());
if (!$insightsAudience->ready() || !$insightsLegacyAudience->ready()) {
    (new \Portix\Module\ModuleSchemaInstaller($database))->install('insights', __DIR__ . '/database');
}

$insightSettings = PortixSettings::read('insights', [
    'mode' => 'minimal',
    'analytics_retention_days' => 730,
    'application_retention_days' => 30,
    'audit_retention_days' => 365,
    'last_purge_day' => '',
    'secret' => '',
]);
if (!is_string($insightSettings['secret'] ?? null) || strlen((string) $insightSettings['secret']) < 32) {
    $insightSettings['secret'] = bin2hex(random_bytes(32));
    PortixSettings::write('insights', $insightSettings);
}

$insightsTracker = static function () use (
    $insightSettings,
    $repositories,
    $request,
    $pageMetadata,
    $route,
    $user,
    $database,
    $insightsAudience,
    $insightsLegacyAudience
): void {
    $mode = (string) ($insightSettings['mode'] ?? 'minimal');
    $staff = isset($user) && is_object($user)
        && ((method_exists($user, 'isAdmin') && $user->isAdmin())
            || (method_exists($user, 'isRedactor') && $user->isRedactor()));
    if ($mode === 'off'
        || $staff
        || $route === 'Admin'
        || $request->method() !== 'GET'
        || (string) ($_SERVER['HTTP_DNT'] ?? '') === '1') {
        return;
    }

    $userAgent = mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500);
    $bot = preg_match(
        '/bot|crawl|spider|slurp|bingpreview|facebookexternalhit|headless|monitor|uptime|ghost-rider|lighthouse|pagespeed/i',
        $userAgent
    ) === 1;
    $device = preg_match('/tablet|ipad/i', $userAgent) === 1
        ? 'tablet'
        : (preg_match('/mobile|android|iphone/i', $userAgent) === 1 ? 'mobile' : 'desktop');
    $referrerHost = 'direct';
    $referrer = (string) ($_SERVER['HTTP_REFERER'] ?? '');
    if ($referrer !== '') {
        $host = parse_url($referrer, PHP_URL_HOST);
        if (is_string($host) && preg_match('/^[A-Za-z0-9.-]+$/', $host) === 1) {
            $referrerHost = strtolower($host);
        }
    }

    $referrerHost = \Portix\Insights\TrafficSources::classify($referrerHost, $request->publicHost());

    $visitorKey = null;
    if ($mode === 'estimated' && !$bot) {
        $cookieName = 'portix_visitor';
        $validCookie = preg_match('/^[a-f0-9]{32}$/D', (string) ($_COOKIE[$cookieName] ?? '')) === 1;
        $browserId = $validCookie ? (string) $_COOKIE[$cookieName] : bin2hex(random_bytes(16));
        if (!$validCookie && !headers_sent()) {
            setcookie($cookieName, $browserId, [
                'expires' => time() + 31536000,
                'path' => rtrim($request->basePath(), '/') . '/',
                'secure' => $request->isSecure(),
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        }
        // Sending Set-Cookie is not evidence that the client accepted it.
        if ($validCookie) { $visitorKey = hash_hmac('sha256', $browserId, (string) $insightSettings['secret']); }
    }

    $database->transaction(static function () use ($repositories, $request, $pageMetadata, $referrerHost, $device, $bot, $visitorKey, $insightsAudience): void {
        $day = gmdate('Y-m-d');
        $repositories->insights()->recordView(
            $day, mb_substr($request->routeName() ?: 'accueil', 0, 500),
            $pageMetadata->documentTitle(), $referrerHost, $device, $bot, null
        );
        if (!$bot) { $insightsAudience->record($day, $visitorKey); }
    });
    if ((string) ($insightSettings['last_purge_day'] ?? '') !== gmdate('Y-m-d')) {
        $repositories->insights()->purge(
            (int) ($insightSettings['analytics_retention_days'] ?? 730),
            (int) ($insightSettings['application_retention_days'] ?? 30),
            (int) ($insightSettings['audit_retention_days'] ?? 365)
        );
        $insightsAudience->purge((int) $insightSettings['analytics_retention_days']);
        $insightsLegacyAudience->purge((int) $insightSettings['analytics_retention_days']);
        $insightSettings['last_purge_day'] = gmdate('Y-m-d');
        PortixSettings::write('insights', $insightSettings);
    }
};
