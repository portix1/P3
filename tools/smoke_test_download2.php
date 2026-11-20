<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { exit(1); }
$root = dirname(__DIR__);
require $root . '/bootstrap/autoload.php';
require $root . '/var/modules/download2/src/Library.php';

use Portix\Infrastructure\Database;
use Portix\Infrastructure\Migrator;
use Portix\Module\ModuleInstaller;
use Portix\Repository\RepositoryRegistry;
use PortixModule\Download2\Library;

function dl2Assert(bool $ok, string $message): void
{
    if (!$ok) { throw new RuntimeException('Download2 : ' . $message); }
}

$db = new Database(':memory:');
(new Migrator($db))->migrate();
$repo = new RepositoryRegistry($db);
$installer = new ModuleInstaller($db, $repo->modules());
$result = $installer->installDirectory($root . '/var/modules/download2');
dl2Assert($result['tables'] === ['dm2_categories','dm2_files'], 'schémas indépendants');
dl2Assert(!$result['module']['enabled'] && $result['module']['admin_access'] === 'admin', 'désactivé par défaut et réservé admin');
$library = new Library($db->connection());
$id = $library->create([
    'category_id'=>null, 'title'=>'Premier guide', 'description'=>'',
    'original_name'=>'guide.pdf', 'stored_name'=>str_repeat('a',48).'.pdf',
    'mime_type'=>'application/pdf', 'size_bytes'=>12345,
    'sha256'=>str_repeat('b',64), 'status'=>'draft',
]);
dl2Assert($library->published() === [], 'brouillon invisible');
$library->update($id, ['status'=>'published']);
$published = $library->published();
dl2Assert(count($published)===1 && (int)$published[0]['id']===$id, 'publication du document');
dl2Assert(!array_key_exists('download_count', $published[0]), 'aucun compteur dans les données publiques');
dl2Assert(!array_key_exists('stored_name', $published[0]), 'aucune adresse de stockage dans les données publiques');
$library->increment($id);
$library->increment($id);
dl2Assert((int)$library->find($id)['download_count']===2, 'compteur administratif');
$library->update($id, ['status'=>'draft']);
dl2Assert($library->published()===[], 'dépublication immédiate');
$library->increment($id);
dl2Assert((int)$library->find($id)['download_count']===2, 'pas de comptage pour brouillon');
$category=$library->createCategory('Notices','notices');
$library->update($id, ['category_id'=>$category,'status'=>'published']);
dl2Assert(count($library->published($category))===1, 'catégorie facultative');
dl2Assert(count($library->published($category+1))===0, 'catégorie non correspondante');
$library->delete($id);
dl2Assert($library->find($id)===null && $library->published()===[], 'suppression');
$public=(string)file_get_contents($root.'/var/modules/download2/templates/public.tpl');
$index=(string)file_get_contents($root.'/var/modules/download2/index.php');
dl2Assert(!str_contains($public,'counter') && !str_contains($public,'download_count')
    && !str_contains($index,'download_count'), 'aucune statistique publique');
echo "Download Manager 2 : installation, droits, brouillon, publication, comptage privé, catégories et suppression OK\n";
