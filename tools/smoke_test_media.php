<?php
declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap/autoload.php';
require dirname(__DIR__) . '/var/modules/media/src/MediaManager.php';

use Portix\Infrastructure\Database;
use Portix\Media\MediaBridge;
use Portix\Media\MediaManager;
use Portix\Module\ModuleSchemaInstaller;

function mediaCheck(bool $condition, string $label): void
{
    if (!$condition) { throw new RuntimeException('FAIL: ' . $label); }
    echo 'PASS: ' . $label . PHP_EOL;
}
function mediaRefused(callable $operation, string $label): void
{
    try { $operation(); } catch (RuntimeException $error) { echo 'PASS: ' . $label . PHP_EOL; return; }
    throw new RuntimeException('FAIL: ' . $label);
}
echo 'START media services' . PHP_EOL;
$fixture = sys_get_temp_dir() . '/p3-media-' . bin2hex(random_bytes(8));
foreach (['database', 'tmp', 'var/uploads/images', 'var/uploads/media', 'var/sys', 'var/templates/test', 'var/private/media'] as $directory) {
    mkdir($fixture . '/' . $directory, 0755, true);
}
try {
    $database = new Database($fixture . '/database/test.sqlite');
    $pdo = $database->connection();
    $pdo->exec('CREATE TABLE modules (prefix TEXT, path TEXT, enabled INTEGER)');
    $pdo->exec('CREATE TABLE articles (id INTEGER PRIMARY KEY, title TEXT, body TEXT, featured_image TEXT, status TEXT)');
    $pdo->exec('CREATE TABLE news (id INTEGER PRIMARY KEY, title TEXT, body TEXT, intro TEXT, status TEXT)');
    $pdo->exec('CREATE TABLE content_revisions (id INTEGER PRIMARY KEY, snapshot_json TEXT)');
    $pdo->exec('CREATE TABLE content_trash (id INTEGER PRIMARY KEY, snapshot_json TEXT)');
    echo 'START schema' . PHP_EOL;
    $installer = new ModuleSchemaInstaller($database);
    $installer->install('media', dirname(__DIR__) . '/var/modules/media/database');
    $installer->install('media', dirname(__DIR__) . '/var/modules/media/database');
    mediaCheck($pdo->query('SELECT COUNT(*) FROM media_items')->fetchColumn() === 0, 'additive idempotent schema');
    MediaBridge::configure($pdo, $fixture, true);
    $manager = new MediaManager($pdo, $fixture);
    $path = 'var/uploads/images/' . str_repeat('a', 32) . '.png';
    $image = imagecreatetruecolor(12, 8); imagepng($image, $fixture . '/' . $path); imagedestroy($image);
    $media = $manager->register($path, 'Vue.png', 'Marc'); $id = (int) $media['id'];
    mediaCheck($media['width'] === 12 && $media['height'] === 8, 'real image metadata');
    $manager->register($path, 'Other.png');
    mediaCheck(count($manager->listing('', 'Vue')) === 1, 'same path reuses catalogue item');
    $statement = $pdo->prepare('INSERT INTO articles VALUES (1,:title,:body,:image,:status)');
    $statement->execute(['title'=>'Brouillon', 'body'=>'<img src="/' . $path . '?p3media=0123456789abcdef">', 'image'=>'', 'status'=>'draft']);
    mediaCheck(count($manager->usages($id)['current']) === 1, 'draft with query URL protects image');
    mediaRefused(fn()=>$manager->delete($id, true), 'current use blocks even confirmed deletion');

    $copyPath = 'var/uploads/images/' . str_repeat('b', 32) . '.png';
    copy($fixture . '/' . $path, $fixture . '/' . $copyPath);
    $copyRow = $manager->register($copyPath, 'Copie.png');
    $copyUses = $manager->usages((int) $copyRow['id']);
    mediaCheck($copyUses['current'] === [] && count($copyUses['identical_current']) === 1 && $copyUses['identical_current'][0]['path'] === $path, 'unused duplicate identifies the referenced identical file without inventing a direct use');
    $pdo->exec('DELETE FROM media_items WHERE id = ' . (int) $copyRow['id']);
    unlink($fixture . '/' . $copyPath);

    $pdo->exec('DELETE FROM articles');
    $statement = $pdo->prepare('INSERT INTO content_revisions VALUES (1,:snapshot)');
    $statement->execute(['snapshot'=>json_encode(['body'=>'<img src="' . $path . '">'])]);
    mediaCheck(count($manager->usages($id)['history']) === 1, 'revision recognised as history');
    mediaRefused(fn()=>$manager->delete($id, false), 'history requires specific confirmation');
    file_put_contents($fixture . '/var/templates/test/style.css', '.hero{background:url(/' . $path . ')}');
    mediaRefused(fn()=>$manager->delete($id, true), 'theme reference blocks deletion');
    unlink($fixture . '/var/templates/test/style.css');
    $pdo->exec('DELETE FROM content_revisions');
    $statement = $pdo->prepare('INSERT INTO content_trash VALUES (1,:snapshot)');
    $statement->execute(['snapshot'=>json_encode(['body'=>'<img src="' . $path . '">'])]);
    mediaCheck(count($manager->usages($id)['history']) === 1, 'trash recognised as history');
    $before = hash_file('sha256', $fixture . '/' . $path);
    $directory = $fixture . '/var/private/media/' . $id; mkdir($directory, 0700);
    $previous = imagecreatetruecolor(20, 10);imagepng($previous, $directory . '/previous.bin');imagedestroy($previous);
    $manager->restore($id);
    mediaCheck($manager->item($id)['width'] === 20 && $manager->item($id)['height'] === 10, 'previous version restored at same path');
    mediaCheck(hash_file('sha256', $directory . '/previous.bin') === $before, 'return to version keeps displaced version');
    $html = MediaBridge::versionHtml('<img src="' . $path . '"><a href="https://external.test/' . $path . '">X</a>');
    mediaCheck(str_contains($html, '?p3media=') && str_contains($html, 'href="https://external.test/' . $path . '"'), 'cache versioning preserves external URLs');
    $manager->delete($id, true);
    mediaCheck(!is_file($fixture . '/' . $path), 'confirmed history-only media deleted');
    $docPath = 'var/uploads/media/' . str_repeat('b', 32) . '.pdf';
    file_put_contents($fixture . '/' . $docPath, "%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\n%%EOF\n");
    $document = $manager->register($docPath, 'Plan.pdf');
    mediaCheck($document['family'] === 'pdf', 'PDF recognised and indexed');
    file_put_contents($fixture . '/' . $docPath, '<?php echo "not pdf";');
    mediaRefused(fn()=>$manager->register($docPath, 'Bad.pdf'), 'fake PDF rejected');
    $zipPath = 'var/uploads/media/' . str_repeat('c', 32) . '.zip';
    $zip = new ZipArchive();$zip->open($fixture . '/' . $zipPath, ZipArchive::CREATE);$zip->addFromString('hello.txt', 'Hello');$zip->close();
    $archive = $manager->register($zipPath, 'Archive.zip');
    mediaCheck($archive['family'] === 'zip', 'ZIP checked without extraction');
    mediaRefused(fn()=>$manager->register('var/uploads/media/../../outside.pdf', 'bad'), 'path traversal rejected');
    mediaCheck($manager->usages((int) $document['id'])['errors'] !== [], 'external file change never classified as unused');
    $inventory = $manager->inventory();mediaCheck(is_array($inventory['unknown']), 'inventory reports unrecognised files');
    $analysis = $manager->analyse();mediaCheck(isset($analysis[(string) $archive['id']]), 'explicit analysis yields candidates without deletion');
    mediaCheck(is_file($fixture . '/' . $zipPath), 'analysis never deletes files');
    $archiveId = (int) $archive['id'];
    $directory = $fixture . '/var/private/media/' . $archiveId; mkdir($directory, 0700);
    $quarantine = $fixture . '/var/private/media/deleted-fixture';
    rename($fixture . '/' . $zipPath, $quarantine);
    file_put_contents($directory . '/delete.json', json_encode(['id'=>$archiveId,'target'=>$zipPath,'quarantine'=>'deleted-fixture']));
    $manager->recover();
    mediaCheck(is_file($fixture . '/' . $zipPath), 'interrupted deletion restores resource while catalogue row exists');
    copy($fixture . '/' . $zipPath, $directory . '/before-fixture.bin');
    file_put_contents($directory . '/pending.json', json_encode(['id'=>$archiveId,'target'=>$zipPath,'backup'=>'before-fixture.bin','old'=>$archive['sha256'],'new'=>str_repeat('f',64)]));
    file_put_contents($fixture . '/' . $zipPath, 'interrupted replacement');
    $manager->recover();
    mediaCheck(hash_file('sha256', $fixture . '/' . $zipPath) === $archive['sha256'], 'interrupted replacement restores resource before database commit');
    mediaCheck($pdo->query('PRAGMA integrity_check')->fetchColumn() === 'ok', 'database integrity after operations');
    echo 'MEDIA TESTS COMPLETE — PHP ' . PHP_VERSION . PHP_EOL;
} catch (Throwable $error) { echo get_class($error) . ': ' . $error->getMessage() . PHP_EOL . $error->getTraceAsString() . PHP_EOL; throw $error;
} finally {
    MediaBridge::release();
    unset($manager, $pdo, $database, $installer, $statement);
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($fixture, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($files as $file) { $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname()); }
    rmdir($fixture);
}
