<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit(1);
}
require dirname(__DIR__, 4) . '/bootstrap/autoload.php';
require dirname(__DIR__) . '/src/DailyAudience.php';

$database = new \Portix\Infrastructure\Database(':memory:');
(new \Portix\Module\ModuleSchemaInstaller($database))->install('insights', dirname(__DIR__) . '/database');
$audience = new \Portix\Insights\DailyAudience($database);
$pdo = $database->connection();
$day = strtotime('today UTC');
$retention = ['analytics_retention_days' => 30, 'application_retention_days' => 7, 'audit_retention_days' => 30];

$audience->record('/', 'Accueil', null, 'example.test', '192.0.2.10', 'Firefox', $retention, $day);
$audience->record('article.html', 'Article', 'article', 'internal', '192.0.2.99', 'Firefox', $retention, $day + 4000);
$audience->record('article.html', 'Article', 'article', 'direct', '192.0.2.10', 'Safari', $retention, $day + 5000);
$audience->record('article.html', 'Article', 'article', 'direct', '', 'Safari', $retention, $day + 6000);
$row = $pdo->query('SELECT views, visitors, secret FROM analytics_v2_daily')->fetch();
if ((int) $row['views'] !== 4 || (int) $row['visitors'] !== 2) {
    throw new RuntimeException('Page totals and daily visitor estimates differ');
}
if ((int) $pdo->query("SELECT SUM(total) FROM analytics_v2_counts WHERE kind = 'entry'")->fetchColumn() !== 2
    || (int) $pdo->query("SELECT SUM(total) FROM analytics_v2_counts WHERE kind = 'article'")->fetchColumn() !== 3) {
    throw new RuntimeException('Entries and article views use separate counters');
}
if ((int) $pdo->query("SELECT SUM(total) FROM analytics_v2_counts WHERE kind = 'referrer'")->fetchColumn() !== 2
    || (int) $pdo->query("SELECT views FROM analytics_pages WHERE route = 'article'")->fetchColumn() !== 3) {
    throw new RuntimeException('Referrers and Home popularity must preserve their totals');
}
$audience->record('/', 'Accueil', null, 'direct', '192.0.2.10', 'Firefox', $retention, $day + 86400);
$days = $pdo->query('SELECT visitors, secret FROM analytics_v2_daily ORDER BY day')->fetchAll();
if ($days[0]['secret'] !== null || $days[1]['secret'] === $row['secret']
    || (int) $days[1]['visitors'] !== 1
    || (int) $pdo->query('SELECT COUNT(*) FROM analytics_v2_seen')->fetchColumn() !== 1) {
    throw new RuntimeException('Daily key rotation or fingerprint expiry failed');
}
echo "Statistiques : déduplication, troncature IP, comptage indépendant et rotation journalière vérifiés.\n";
