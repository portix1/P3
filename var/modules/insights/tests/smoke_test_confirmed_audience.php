<?php
declare(strict_types=1);

require dirname(__DIR__, 4) . '/bootstrap/autoload.php';
require dirname(__DIR__) . '/src/ConfirmedAudienceMetrics.php';
require dirname(__DIR__) . '/src/TrafficSources.php';

function confirmedCheck(bool $ok, string $label): void
{
    if (!$ok) { throw new RuntimeException($label); }
    echo 'PASS ' . $label . PHP_EOL;
}

$fixture = sys_get_temp_dir() . '/p3-confirmed-' . bin2hex(random_bytes(8));
mkdir($fixture);
$db = new Portix\Infrastructure\Database($fixture . '/test.sqlite');
(new Portix\Infrastructure\Migrator($db))->migrate();
$installer = new Portix\Module\ModuleSchemaInstaller($db);
$installer->install('insights', dirname(__DIR__) . '/database');
$installer->install('insights', dirname(__DIR__) . '/database');
$pdo = $db->connection();
$store = new Portix\Insights\ConfirmedAudienceMetrics($pdo);
$today = gmdate('Y-m-d');
$yesterday = gmdate('Y-m-d', strtotime('-1 day'));
$old = gmdate('Y-m-d', strtotime('-60 days'));
$pdo->exec("INSERT INTO analytics_unique_visitors VALUES ('legacy','$today','$today')");
$pdo->exec("INSERT INTO analytics_audience_daily VALUES ('legacy','$today','legacy',900)");
for ($i = 0; $i < 1000; $i++) { $store->record($today, null); }
$s = $store->summary(30);
confirmedCheck(!$s['available'] && $s['unique'] === 0 && $s['ratio'] === null && $s['unconfirmed_pages'] === 1000, '1000 unidentified pages and legacy data create no confirmed browsers');
$store->record($yesterday, 'known');
$store->record($today, 'known');
$store->record($today, 'known');
$store->record($today, 'new');
$s = $store->summary(1);
confirmedCheck($s['unique'] === 2 && $s['new'] === 1 && $s['known'] === 1 && $s['pages'] === 3 && $s['ratio'] === 1.5 && $s['confirmed_pages'] === 3, 'period filter, confirmed denominator and matching page numerator');
$before = $store->summary(30);
$pdo->beginTransaction();
$store->record($today, 'rollback');
$pdo->rollBack();
confirmedCheck($store->summary(30) === $before, 'page breakdown and browser records share the outer transaction');
$store->record($old, 'expired');
$store->record($old, null);
$store->purge(30);
confirmedCheck((int) $pdo->query("SELECT COUNT(*) FROM analytics_confirmed_visitors WHERE visitor_key='expired'")->fetchColumn() === 0, 'expired confirmed identifier removed');
confirmedCheck((int) $pdo->query("SELECT COUNT(*) FROM analytics_collection_daily WHERE day='$old'")->fetchColumn() === 0, 'expired unidentified breakdown removed');
confirmedCheck((int) $pdo->query('SELECT page_views FROM analytics_audience_daily')->fetchColumn() === 900, 'legacy audience remains intact');
$pdo->exec('DROP TABLE analytics_collection_daily');
confirmedCheck(!$store->summary(30)['available'], 'missing schema reported as unavailable');
$installer->install('insights', dirname(__DIR__) . '/database');
confirmedCheck($store->ready(), 'additive repair restores new schema');
confirmedCheck(Portix\Insights\TrafficSources::classify('www.example.test', 'example.test') === 'internal', 'www and bare host grouped as internal');
confirmedCheck(Portix\Insights\TrafficSources::classify('example.test.evil.test', 'example.test') === 'example.test.evil.test', 'external lookalike is not internal');
confirmedCheck($pdo->query('PRAGMA integrity_check')->fetchColumn() === 'ok', 'SQLite integrity');
unset($store, $pdo, $installer);
$db->close();
foreach (glob($fixture . '/*') ?: [] as $file) { unlink($file); }
rmdir($fixture);
echo 'CONFIRMED AUDIENCE TESTS COMPLETE PHP ' . PHP_VERSION . PHP_EOL;
