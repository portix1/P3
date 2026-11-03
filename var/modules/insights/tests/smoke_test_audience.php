<?php
declare(strict_types=1);
$root=dirname(__DIR__,4);
require $root.'/bootstrap/autoload.php';
require dirname(__DIR__).'/src/AudienceMetrics.php';
function audienceCheck(bool $ok,string $label): void {if(!$ok){throw new RuntimeException($label);}echo 'PASS '.$label.PHP_EOL;}
$fixture=sys_get_temp_dir().'/p3-audience-'.bin2hex(random_bytes(8));mkdir($fixture);
$db=new Portix\Infrastructure\Database($fixture.'/test.sqlite');
(new Portix\Infrastructure\Migrator($db))->migrate();
$installer=new Portix\Module\ModuleSchemaInstaller($db);$installer->install('insights',dirname(__DIR__).'/database');$installer->install('insights',dirname(__DIR__).'/database');
$pdo=$db->connection();$store=new Portix\Insights\AudienceMetrics($pdo);
$today=gmdate('Y-m-d');$yesterday=gmdate('Y-m-d',strtotime('-1 day'));$old=gmdate('Y-m-d',strtotime('-60 days'));
$pdo->exec("INSERT INTO analytics_daily VALUES ('$today',2512,1150,824)");
$pdo->exec("INSERT INTO analytics_unique_visitors VALUES ('known','$old','$yesterday')");
$pdo->exec("INSERT INTO analytics_visitor_days VALUES ('legacy','$yesterday','known')");
$summary=$store->summary(30);audienceCheck(!$summary['available'] && $summary['ratio']===null,'legacy totals and visitor records do not masquerade as new detailed measurements');
$store->record($yesterday,'known');$store->record($today,'known');$store->record($today,'known');$store->record($today,'new');
$summary=$store->summary(30);
audienceCheck($summary['unique']===2 && $summary['new']===1 && $summary['known']===1,'unique/new/previously known browsers');
audienceCheck($summary['visitor_days']===3 && $summary['pages']===4 && $summary['ratio']===2.0,'same-day deduplication and matched numerator, independent of 2512 legacy page views');
audienceCheck($summary['first_day']===$yesterday,'first retained detailed measurement day');
audienceCheck((int)$pdo->query('SELECT visits_estimated FROM analytics_daily')->fetchColumn()===1150,'legacy estimates unchanged');
$summary=$store->summary(1);audienceCheck($summary['unique']===2 && $summary['visitor_days']===2 && $summary['pages']===3,'period filters numerator and denominator together');
$store->record($old,'expired');$store->purge(30);audienceCheck((int)$pdo->query("SELECT COUNT(*) FROM analytics_audience_daily WHERE day='$old'")->fetchColumn()===0,'detailed measurements follow retention');
$pdo->exec('DROP TABLE analytics_audience_daily');audienceCheck(!$store->summary(30)['available'],'incomplete schema is reported as unavailable');
try{$store->record($today,'x');throw new LogicException('missing schema was ignored');}catch(RuntimeException $e){audienceCheck(str_contains($e->getMessage(),'incomplet'),'recording rejects incomplete schema explicitly');}
$installer->install('insights',dirname(__DIR__).'/database');audienceCheck($store->ready(),'additive schema can be repaired without replacing the database');
audienceCheck((int)$pdo->query('SELECT visits_estimated FROM analytics_daily')->fetchColumn()===1150,'repair preserves legacy totals');
audienceCheck($pdo->query('PRAGMA integrity_check')->fetchColumn()==='ok','SQLite integrity');
unset($store,$pdo);$db->close();foreach(glob($fixture.'/*')?:[] as $file){unlink($file);}rmdir($fixture);
echo 'AUDIENCE TESTS COMPLETE PHP '.PHP_VERSION.PHP_EOL;
