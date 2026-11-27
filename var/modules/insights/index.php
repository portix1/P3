<?php
declare(strict_types=1);

$insightsTracker = static function () use ($database, $pageMetadata, $request, &$route, &$user): void {
    $status = http_response_code();
    if ($route === 'Admin' || $request->method() !== 'GET'
        || ($status !== false && $status !== 200)
        || str_contains($pageMetadata->robots(), 'noindex')
        || $user->isAdmin() || $user->isRedactor()
        || ($_SERVER['HTTP_DNT'] ?? '') === '1'
        || ($_SERVER['HTTP_X_UG_AUDIT'] ?? '') === '1'
        || ($_GET['ug_audit'] ?? '') === '1') {
        return;
    }

    $agent = substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500);
    if (stripos($agent, 'universguitare-audit') !== false) {
        return;
    }

    try {
        $settings = PortixSettings::read('insights', [
            'mode' => 'off',
            'robots' => 1,
            'analytics_retention_days' => 730,
            'application_retention_days' => 30,
            'audit_retention_days' => 365,
        ]);
        if ($settings['mode'] !== 'daily') {
            return;
        }

        require_once __DIR__ . '/src/AgentClassifier.php';
        require_once __DIR__ . '/src/DailyAudience.php';
        require_once __DIR__ . '/src/TrafficSources.php';
        $canonical = $pageMetadata->breadcrumbItem()['url'] ?? '';
        $page = $request->isFrontPage() ? '/' : ($canonical ?: $request->routeName() . '.html');
        $agentCategory = \Portix\Insights\AgentClassifier::classify($agent);
        if ($agentCategory !== \Portix\Insights\AgentClassifier::BROWSER) {
            // Robots and AI agents never count as readers; only their passage is tallied.
            if ((int) ($settings['robots'] ?? 1) === 1) {
                (new \Portix\Insights\DailyAudience($database))->recordRobot(
                    $page, $pageMetadata->documentTitle(), $agentCategory, $settings
                );
            }
            return;
        }
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
            $settings,
            null,
            null,
            \Portix\Insights\TrafficSources::utmSource($_GET['utm_source'] ?? null)
        );
    } catch (Throwable $error) {
        error_log('Insights collection failed: ' . $error::class);
    }
};
