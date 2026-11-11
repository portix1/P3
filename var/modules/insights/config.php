<?php
declare(strict_types=1);

use Portix\Insights\DailyAudience;
use Portix\Observability\EventLogger;

PortixSecurity::requireRole($user, 'admin');
require_once __DIR__ . '/src/AdminLabels.php';
require_once __DIR__ . '/src/DailyAudience.php';
$insightsLabels = \Portix\Insights\AdminLabels::forLanguage((string) ($CONFIG['lang'] ?? 'fr'));
$translator = new \Portix\Support\Translator(array_merge($_LANG ?? [], $insightsLabels));
foreach ($insightsLabels as $label => $value) {
    $template->set($label, $value);
}

$audience = new DailyAudience($database);
$settings = PortixSettings::read('insights', [
    'mode' => 'off',
    'analytics_retention_days' => 730,
    'application_retention_days' => 30,
    'audit_retention_days' => 365,
]);
$view = (string) ($argument[2] ?? 'dashboard');
$message = '';

if ($view === 'settings' && isset($_POST['insights_action'])) {
    try {
        PortixSecurity::requirePostWithCsrf();
        $logger = new EventLogger($database->connection());
        if ($_POST['insights_action'] === 'settings') {
            $mode = (string) ($_POST['mode'] ?? 'off');
            if (!in_array($mode, ['off', 'daily'], true)) {
                throw new RuntimeException($translator->text('insights_invalid_settings'));
            }
            $settings['mode'] = $mode;
            $settings['analytics_retention_days'] = max(30, min(3650, (int) ($_POST['analytics_days'] ?? 730)));
            $settings['application_retention_days'] = max(7, min(365, (int) ($_POST['application_days'] ?? 30)));
            $settings['audit_retention_days'] = max(30, min(3650, (int) ($_POST['audit_days'] ?? 365)));
            PortixSettings::write('insights', $settings);
            $logger->audit($user->pseudo(), 'insights.settings', 'insights', [], $requestId);
            $message = $translator->text('insights_settings_saved');
        } elseif ($_POST['insights_action'] === 'purge') {
            $database->transaction(static function () use ($repositories, $settings, $audience): void {
                $repositories->insights()->purge(
                    (int) $settings['analytics_retention_days'],
                    (int) $settings['application_retention_days'],
                    (int) $settings['audit_retention_days']
                );
                $audience->purge((int) $settings['analytics_retention_days']);
            });
            $logger->audit($user->pseudo(), 'insights.purge', 'insights', [], $requestId);
            $message = $translator->text('insights_purged');
        }
    } catch (Throwable $error) {
        $message = $translator->exception($error);
    }
}
$template->set('insights_notice', $message);
foreach (['dashboard', 'logs', 'audit', 'settings'] as $section) {
    $template->set('insights_' . $section . '_current', $view === $section ? 'is-current' : '');
}

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
    $template->set('insights_mode_daily', $settings['mode'] === 'daily' ? 'selected' : '');
    $template->set('insights_mode_off', $settings['mode'] !== 'daily' ? 'selected' : '');
    $template->set('insights_analytics_days', (string) $settings['analytics_retention_days']);
    $template->set('insights_application_days', (string) $settings['application_retention_days']);
    $template->set('insights_audit_days', (string) $settings['audit_retention_days']);
    return;
}

$days = (int) ($_GET['days'] ?? 30);
if (!in_array($days, [7, 30, 90, 365], true)) {
    $days = 30;
}
$daily = $audience->daily($days);

if ($view === 'export') {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="p3-statistiques-' . gmdate('Ymd') . '.csv"');
    header('Cache-Control: private, no-store');
    $stream = fopen('php://output', 'wb');
    if ($stream !== false) {
        fputcsv($stream, ['date_utc', 'pages_vues', 'visiteurs_journaliers_estimes'], ';', '"', '');
        foreach ($daily as $row) {
            fputcsv($stream, [$row['day'], $row['views'], $row['visitors']], ';', '"', '');
        }
        fclose($stream);
    }
    exit;
}

$template->setSlot('main', 'var/modules/insights/templates/dashboard.tpl');
$template->set('insights_days', (string) $days);
foreach ([7, 30, 90, 365] as $period) {
    $template->set('insights_period_' . $period . '_selected', $days === $period ? 'selected' : '');
}
$views = array_sum(array_column($daily, 'views'));
$visitors = array_sum(array_column($daily, 'visitors'));
$template->set('insights_page_views', (string) $views);
$template->set('insights_visits', (string) $visitors);
$template->set('insights_pages_per_visitor', $visitors > 0 ? number_format($views / $visitors, 2, ',', ' ') : '—');
$template->set('insights_collection_status', $translator->text(
    $settings['mode'] === 'daily' ? 'insights_collecting' : 'insights_collection_off'
));
foreach ($daily as $row) {
    $template->addRow('insightday', [
        'day' => $row['day'], 'views' => (string) $row['views'], 'visitors' => (string) $row['visitors'],
    ]);
}
foreach (['article', 'entry', 'referrer'] as $kind) {
    foreach ($audience->ranking($kind, $days) as $row) {
        $name = $row['dimension'];
        if ($kind === 'referrer' && in_array($name, ['internal', 'direct'], true)) {
            $name = $translator->text('insights_referrer_' . $name);
        }
        $template->addRow('insight' . $kind, [
            'name' => $name,
            'title' => $row['title'] ?: $name,
            'total' => (string) $row['total'],
        ]);
    }
}
