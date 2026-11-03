<?php
declare(strict_types=1);

use Portix\Observability\EventLogger;

PortixSecurity::requireRole($user, 'admin');
require_once __DIR__ . '/src/AdminLabels.php';
$insightsLabels = \Portix\Insights\AdminLabels::forLanguage(basename((string) ($CONFIG['lang'] ?? 'fr')));
$_LANG = array_merge($_LANG ?? [], $insightsLabels);
$translator = new \Portix\Support\Translator($_LANG);

require_once __DIR__ . '/src/AudienceMetrics.php';
require_once __DIR__ . '/src/ConfirmedAudienceMetrics.php';
require_once __DIR__ . '/src/TrafficSources.php';
$insightsLegacyAudience = new \Portix\Insights\AudienceMetrics($database->connection());
$insightsAudience = new \Portix\Insights\ConfirmedAudienceMetrics($database->connection());
$settings = PortixSettings::read('insights', [
    'mode' => 'minimal',
    'analytics_retention_days' => 730,
    'application_retention_days' => 30,
    'audit_retention_days' => 365,
    'last_purge_day' => '',
    'secret' => '',
]);
$view = (string) ($argument[2] ?? 'dashboard');
$message = '';
$eventLogger = new EventLogger($database->connection());

if ($view === 'export') {
    $days = max(7, min(365, (int) ($_GET['days'] ?? 30)));
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="portix-statistiques-' . gmdate('Ymd') . '.csv"');
    header('Cache-Control: private, no-store');
    $stream = fopen('php://output', 'wb');
    if ($stream !== false) {
        fwrite($stream, "\xEF\xBB\xBF");
        fputcsv($stream, ['date', 'pages_vues', 'anciennes_estimations_visites', 'robots', 'pages_cookie_confirme_depuis_1_0_2', 'pages_sans_cookie_confirme_depuis_1_0_2'], ';', '"', '');
        $confirmedDaily = array_column($insightsAudience->daily($days), null, 'day');
        foreach ($repositories->insights()->daily($days) as $row) {
            fputcsv($stream, [
                $row['day'], $row['page_views'], $row['visits_estimated'], $row['bot_views'],
                $confirmedDaily[$row['day']]['confirmed_pages'] ?? '',
                $confirmedDaily[$row['day']]['unconfirmed_pages'] ?? '',
            ], ';', '"', '');
        }
        fclose($stream);
    }
    exit;
}

// Bind before computed values; no dependency on a later includes/lang.php pass.
foreach ($insightsLabels as $insightsLabel => $insightsText) {
    $template->set($insightsLabel, html_entity_decode((string) $insightsText, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
}

if (isset($_POST['insights_action'])) {
    try {
        PortixSecurity::requirePostWithCsrf();
        $action = (string) $_POST['insights_action'];
        if ($action === 'settings') {
            $mode = (string) ($_POST['mode'] ?? 'minimal');
            if (!in_array($mode, ['off', 'minimal', 'estimated'], true)) {
                throw new RuntimeException($translator->text('insights_invalid_settings'));
            }
            $settings['mode'] = $mode;
            $settings['analytics_retention_days'] = max(30, min(3650, (int) ($_POST['analytics_days'] ?? 730)));
            $settings['application_retention_days'] = max(7, min(365, (int) ($_POST['application_days'] ?? 30)));
            $settings['audit_retention_days'] = max(30, min(3650, (int) ($_POST['audit_days'] ?? 365)));
            if (!is_string($settings['secret'] ?? null) || strlen((string) $settings['secret']) < 32) {
                $settings['secret'] = bin2hex(random_bytes(32));
            }
            PortixSettings::write('insights', $settings);
            $eventLogger->audit($user->pseudo(), 'insights.settings', 'insights', [], $requestId);
            $message = $translator->text('insights_settings_saved');
        } elseif ($action === 'purge') {
            $repositories->insights()->purge(
                (int) $settings['analytics_retention_days'],
                (int) $settings['application_retention_days'],
                (int) $settings['audit_retention_days']
            );
            $eventLogger->audit($user->pseudo(), 'insights.purge', 'insights', [], $requestId);
            $insightsAudience->purge((int) $settings['analytics_retention_days']);
            $insightsLegacyAudience->purge((int) $settings['analytics_retention_days']);
            $message = $translator->text('insights_purged');
        }
    } catch (Throwable $error) {
        $message = $translator->exception($error);
    }
}

$template->set('insights_message', $message);
$template->set('insights_dashboard_current', $view === 'dashboard' ? 'is-current' : '');
$template->set('insights_logs_current', $view === 'logs' ? 'is-current' : '');
$template->set('insights_audit_current', $view === 'audit' ? 'is-current' : '');
$template->set('insights_settings_current', $view === 'settings' ? 'is-current' : '');

if ($view === 'logs') {
    $template->setSlot('main', 'var/modules/insights/templates/logs.tpl');
    foreach ($repositories->insights()->recentApplicationLogs() as $row) {
        $template->addRow('applicationlog', [
            'date' => (string) $row['created_at'],
            'level' => (string) $row['level'],
            'event' => (string) $row['event'],
            'message' => (string) $row['message'],
            'request_id' => (string) $row['request_id'],
        ]);
    }
    return;
}
if ($view === 'audit') {
    $template->setSlot('main', 'var/modules/insights/templates/audit.tpl');
    foreach ($repositories->insights()->recentAuditLogs() as $row) {
        $template->addRow('auditlog', [
            'date' => (string) $row['created_at'],
            'actor' => (string) $row['actor'],
            'action' => (string) $row['action'],
            'target' => (string) $row['target'],
            'request_id' => (string) $row['request_id'],
        ]);
    }
    return;
}
if ($view === 'settings') {
    $template->setSlot('main', 'var/modules/insights/templates/settings.tpl');
    $template->set('insights_mode_off', $settings['mode'] === 'off' ? 'selected' : '');
    $template->set('insights_mode_minimal', $settings['mode'] === 'minimal' ? 'selected' : '');
    $template->set('insights_mode_estimated', $settings['mode'] === 'estimated' ? 'selected' : '');
    $template->set('insights_analytics_days', (string) $settings['analytics_retention_days']);
    $template->set('insights_application_days', (string) $settings['application_retention_days']);
    $template->set('insights_audit_days', (string) $settings['audit_retention_days']);
    return;
}

$days = max(7, min(365, (int) ($_GET['days'] ?? 30)));
$totals = $repositories->insights()->totals($days);
$audience = $insightsAudience->summary($days);
$daily = $repositories->insights()->daily($days);
$maximum = max(array_merge(
    [1],
    array_map(static fn (array $row): int => (int) $row['page_views'], $daily)
));
$template->setSlot('main', 'var/modules/insights/templates/dashboard.tpl');
$template->set('insights_days', (string) $days);
foreach ([7, 30, 90, 365] as $period) {
    $template->set('insights_period_' . $period . '_selected', $days === $period ? 'selected' : '');
}
$template->set('insights_confirmed_pages', number_format($audience['confirmed_pages'], 0, ',', ' '));
$template->set('insights_unconfirmed_pages', number_format($audience['unconfirmed_pages'], 0, ',', ' '));
$template->set('insights_page_views', number_format($totals['page_views'], 0, ',', ' '));
$template->set('insights_visits', $audience['available'] ? number_format($audience['visitor_days'], 0, ',', ' ') : $translator->text('insights_not_measured'));
$template->set('insights_bots', number_format($totals['bot_views'], 0, ',', ' '));
$template->set('insights_unique_visitors', $audience['available'] ? number_format($audience['unique'], 0, ',', ' ') : $translator->text('insights_not_measured'));
$template->set('insights_new_visitors', $audience['available'] ? number_format($audience['new'], 0, ',', ' ') : $translator->text('insights_not_measured'));
$template->set('insights_returning_visitors', $audience['available'] ? number_format($audience['known'], 0, ',', ' ') : $translator->text('insights_not_measured'));
$template->set('insights_pages_per_visitor', ($audience['ratio'] !== null ? number_format($audience['ratio'], 2, ',', ' ') : $translator->text('insights_not_measured')));
$template->set('insights_avg_unique_day', ($audience['available'] ? number_format($audience['visitor_days'] / $days, 1, ',', ' ') : $translator->text('insights_not_measured')));
$template->set('insights_avg_views_day', number_format($totals['page_views'] / $days, 1, ',', ' '));
$template->set('insights_has_visits', '1');
$note = $translator->text($settings['mode'] === 'estimated' ? 'insights_measuring' : 'insights_not_collecting');
if ($audience['first_day'] !== '') { $note .= ' ' . sprintf($translator->text('insights_first_day'), $audience['first_day']); }
$note .= ' ' . $translator->text('insights_ratio_help');
$template->set('insights_measurement_note', $note);
$previousAudience = $insightsLegacyAudience->summary($days);
$template->set('insights_previous_audience_note', $previousAudience['available'] ? sprintf($translator->text('insights_previous_audience_help'), number_format($previousAudience['unique'], 0, ',', ' ')) : '');
$template->set('insights_legacy_note', $totals['visits_estimated'] > 0 ? sprintf($translator->text('insights_legacy_help'), number_format($totals['visits_estimated'], 0, ',', ' ')) : '');
foreach ($daily as $row) {
    $template->addRow('insightday', [
        'day' => (string) $row['day'],
        'views' => (string) $row['page_views'],
        'percent' => (string) max(2, (int) round(((int) $row['page_views'] / $maximum) * 100)),
    ]);
}
foreach ($repositories->insights()->topPages($days) as $row) {
    $template->addRow('insightpage', [
        'route' => (string) $row['route'],
        'title' => (string) $row['title'],
        'views' => (string) $row['views'],
    ]);
}
foreach (\Portix\Insights\TrafficSources::ranking($database->connection(), $days, $request->publicHost()) as $row) {
    $template->addRow('insightreferrer', [
        'host' => match ((string) $row['host']) {
            'internal' => $translator->text('insights_referrer_internal'),
            'direct' => $translator->text('insights_referrer_direct'),
            default => (string) $row['host'],
        },
        'views' => (string) $row['views'],
    ]);
}
foreach ($repositories->insights()->devices($days) as $row) {
    $template->addRow('insightdevice', [
        'device' => $translator->text('insights_device_' . (string) $row['device']),
        'views' => (string) $row['views'],
    ]);
}
