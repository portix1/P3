<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(403); exit(1); }
require dirname(__DIR__) . '/bootstrap/autoload.php';
use Portix\Theme\ThemePackageManager;
use Portix\Theme\ThemeCatalog;
function packageAssert(bool $ok, string $label): void { if (!$ok) { throw new RuntimeException($label); } }
function packageCopy(string $from, string $to): void { if (!is_dir($to)) { mkdir($to, 0700, true); } foreach (new FilesystemIterator($from, FilesystemIterator::SKIP_DOTS) as $file) { if ($file->isDir()) { packageCopy($file->getPathname(), $to . '/' . $file->getFilename()); } else { copy($file->getPathname(), $to . '/' . $file->getFilename()); } } }
function packageRemove(string $dir): void { if (!is_dir($dir)) { return; } foreach (new FilesystemIterator($dir, FilesystemIterator::SKIP_DOTS) as $file) { $file->isDir() && !$file->isLink() ? packageRemove($file->getPathname()) : unlink($file->getPathname()); } rmdir($dir); }
function makeThemeZip(string $source, string $zipFile, string $version, array $additional = []): void {
    $manifest = json_decode((string) file_get_contents($source . '/theme.json'), true, 512, JSON_THROW_ON_ERROR); $manifest['version'] = $version;
    $zip = new ZipArchive(); $zip->open($zipFile, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    $zip->addFromString('six-cordes/theme.json', json_encode($manifest, JSON_THROW_ON_ERROR));
    foreach ($manifest['files'] as $path => $_) { $zip->addFile($source . '/' . $path, 'six-cordes/' . $path); }
    foreach ($additional as $path => $bytes) { $zip->addFromString($path, $bytes); }
    $zip->close();
}
function packageRefuses(callable $call, string $label): void { try { $call(); } catch (RuntimeException) { return; } throw new RuntimeException('Refus attendu : ' . $label); }
$root = dirname(__DIR__); $temp = sys_get_temp_dir() . '/p3-theme-packages-' . bin2hex(random_bytes(6)); $status = 0;
try {
    mkdir($temp, 0700, true); mkdir($temp . '/var/templates', 0700, true);
    packageCopy($root . '/var/templates/shared', $temp . '/var/templates/shared');
    $core = trim((string) file_get_contents($root . '/VERSION')); $manager = new ThemePackageManager($temp, $core);
    $zip = $temp . '/theme.zip'; $source = $root . '/var/templates/six-cordes';
    makeThemeZip($source, $zip, '0.1.0'); $plan = $manager->stage($zip);
    packageAssert(!is_dir($temp . '/var/templates/six-cordes'), 'Analyse sans installation');
    $manager->install($plan['token']);
    packageAssert((new ThemeCatalog($temp, $core))->inspect('six-cordes')['version'] === '0.1.0', 'Installation neuve');
    packageAssert($manager->backups() === [], 'Pas de restauration fictive pour une installation neuve');
    makeThemeZip($source, $zip, '0.0.9');
    packageRefuses(fn () => $manager->stage($zip), 'rétrogradation du thème');
    $target = $temp . '/var/templates/six-cordes';file_put_contents($target . '/personnel.css', 'p{color:red}');
    makeThemeZip($source, $zip, '0.1.1');$plan = $manager->stage($zip);$receipt = $manager->install($plan['token']);
    packageAssert(file_get_contents($target . '/personnel.css') === 'p{color:red}', 'Fichier supplémentaire conservé');
    packageAssert(count($manager->backups()) === 1, 'Version précédente sauvegardée');
    $manager->restore($receipt['token']);
    packageAssert((new ThemeCatalog($temp, $core))->inspect('six-cordes')['version'] === '0.1.0', 'Retour à la version précédente');
    packageAssert(file_get_contents($target . '/personnel.css') === 'p{color:red}', 'Fichier supplémentaire restauré');
    file_put_contents($target . '/style.css', "\n/* locale */", FILE_APPEND);
    $plan = $manager->stage($zip);packageAssert(in_array('style.css', $plan['conflicts'], true), 'Conflit présenté');
    packageRefuses(fn () => $manager->install($plan['token']), 'écrasement de personnalisation');$manager->cancel($plan['token']);
    packageAssert(str_contains((string) file_get_contents($target . '/style.css'), 'locale'), 'Personnalisation intacte');
    copy($source . '/style.css', $target . '/style.css');
    $plan = $manager->stage($zip);file_put_contents($target . '/apres-analyse.css', 'new');
    packageRefuses(fn () => $manager->install($plan['token']), 'changement après analyse');$manager->cancel($plan['token']);unlink($target . '/apres-analyse.css');
    $plan = $manager->stage($zip);$receipt = $manager->install($plan['token']);
    file_put_contents($target . '/personnel.css', 'modifié après installation');
    packageRefuses(fn () => $manager->restore($receipt['token']), 'restauration écrasant une modification récente');
    file_put_contents($target . '/personnel.css', 'p{color:red}');$manager->restore($receipt['token']);
    $guarded = new ThemePackageManager($temp, $core, static function (): void { throw new RuntimeException('Échec simulé avant activation'); });
    $plan = $guarded->stage($zip);packageRefuses(fn () => $guarded->install($plan['token']), 'échec activation');
    packageAssert((new ThemeCatalog($temp, $core))->inspect('six-cordes')['version'] === '0.1.0' && file_exists($target . '/index.tpl'), 'Échec : thème précédent rétabli');$guarded->cancel($plan['token']);
    foreach (['../escape.tpl','six-cordes/../escape.tpl','six-cordes/run.php','other/index.tpl'] as $path) {
        makeThemeZip($source, $zip, '0.1.1', [$path => 'x']);packageRefuses(fn () => $manager->stage($zip), 'archive dangereuse : ' . $path);
    }
    makeThemeZip($source, $zip, '0.1.1', ['six-cordes/extra.css' => 'x']);packageRefuses(fn () => $manager->stage($zip), 'fichier non déclaré');
    $z = new ZipArchive();$z->open($zip, ZipArchive::CREATE | ZipArchive::OVERWRITE);$z->addFromString('shared/theme.json', '{}');$z->close();packageRefuses(fn () => $manager->stage($zip), 'shared protégé');
    makeThemeZip($source, $zip, '0.1.1');$z = new ZipArchive();$z->open($zip);$z->addFromString('six-cordes/style.css','altéré');$z->close();packageRefuses(fn () => $manager->stage($zip), 'empreinte incorrecte');
    makeThemeZip($source, $zip, '0.1.1');$plan = $manager->stage($zip);
    file_put_contents($temp . '/var/private/theme-packages/stage-' . $plan['token'] . '/payload/var/templates/six-cordes/style.css', 'altéré après analyse');
    packageRefuses(fn () => $manager->install($plan['token']), 'paquet altéré après analyse');$manager->cancel($plan['token']);
    $plan = $manager->stage($zip);$planFile = $temp . '/var/private/theme-packages/stage-' . $plan['token'] . '/plan.json';
    $expired = $plan;$expired['created'] = time() - 1801;file_put_contents($planFile, json_encode($expired, JSON_THROW_ON_ERROR));
    packageRefuses(fn () => $manager->plan($plan['token']), 'aperçu expiré');packageRefuses(fn () => $manager->install($plan['token']), 'installation expirée');$manager->cancel($plan['token']);
    $z = new ZipArchive();$z->open($zip, ZipArchive::CREATE | ZipArchive::OVERWRITE);$z->addFromString('six-cordes/style.css', 'link-target');$z->setExternalAttributesName('six-cordes/style.css', ZipArchive::OPSYS_UNIX, 0120777 << 16);$z->close();
    packageRefuses(fn () => $manager->stage($zip), 'lien symbolique dans ZIP');
    makeThemeZip($source, $zip, '0.1.1', ['six-cordes/bomb.css' => str_repeat('x', 26 * 1024 * 1024)]);
    packageRefuses(fn () => $manager->stage($zip), 'taille extraite excessive');
    makeThemeZip($source, $zip, '0.1.1');$plan = $manager->stage($zip);$receipt = $manager->install($plan['token']);
    $snapshot = $temp . '/var/private/theme-packages/backup-' . $receipt['token'] . '/previous';file_put_contents($snapshot . '/personnel.css', 'sauvegarde altérée');
    packageRefuses(fn () => $manager->restore($receipt['token']), 'sauvegarde altérée');
    packageAssert((new ThemeCatalog($temp, $core))->inspect('six-cordes')['version'] === '0.1.1', 'Thème courant intact après refus de restauration');
    packageRefuses(fn () => $manager->install('../../x'), 'jeton dangereux');
    packageAssert(!file_exists($temp . '/escape.tpl') && is_file($temp . '/var/templates/shared/breadcrumb.tpl'), 'Aucun chemin externe écrit');
    echo "Paquets de thèmes : analyse, installation, conflits, sauvegarde, restauration et échec simulé : OK\n";
} catch (Throwable $error) { fwrite(STDERR, 'Test paquets thèmes : ÉCHEC - ' . $error->getMessage() . "\n"); $status = 1; }
finally { packageRemove($temp); }
exit($status);
