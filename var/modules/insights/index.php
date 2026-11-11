<?php
declare(strict_types=1);

$insightsTracker = static function () use ($database, $pageMetadata, $request, &$route, &$user): void {
    $status = http_response_code();
    if ($route === 'Admin' || $request->method() !== 'GET'
        || ($status !== false && $status !== 200)
        || str_contains($pageMetadata->robots(), 'noindex')
        || $user->isAdmin() || $user->isRedactor()
        || ($_SERVER['HTTP_DNT'] ?? '') === '1') {
        return;
    }

    $agent = substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500);
    if (preg_match('/bot|crawl|spider|slurp|bingpreview|facebookexternalhit|headless|monitor|uptime|ghost-rider|lighthouse|pagespeed/i', $agent)) {
        return;
    }

    try {
        $settings = PortixSettings::read('insights', [
            'mode' => 'off',
            'analytics_retention_days' => 730,
            'application_retention_days' => 30,
            'audit_retention_days' => 365,
        ]);
        if ($settings['mode'] !== 'daily') {
            return;
        }

        require_once __DIR__ . '/src/DailyAudience.php';
        require_once __DIR__ . '/src/TrafficSources.php';
        $canonical = $pageMetadata->breadcrumbItem()['url'] ?? '';
        $page = $request->isFrontPage() ? '/' : ($canonical ?: $request->routeName() . '.html');
        $article = $route === 'articles' && $pageMetadata->type() === 'article'
            ? preg_replace('/\.html$/D', '', $canonical)
            : null;
        $host = parse_url((string) ($_SERVER['HTTP_REFERER'] ?? ''), PHP_URL_HOST);
        $referrer = is_string($host) && preg_match('/^[a-z0-9.-]{1,253}$/iD', $host)
            ? \Portix\Insights\TrafficSources::classify(strtolower($host), $request->publicHost())
            : 'direct';

        (new \Portix\Insights\DailyAudience($database))->record(
            $page,
            $pageMetadata->documentTitle(),
            $article ?: null,
            $referrer,
            (string) ($_SERVER['REMOTE_ADDR'] ?? ''),
            $agent,
            $settings
        );
    } catch (Throwable $error) {
        error_log('Insights collection failed: ' . $error::class);
    }
};
