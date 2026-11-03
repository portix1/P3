<?php
declare(strict_types=1);
require dirname(__DIR__).'/bootstrap/autoload.php';
require dirname(__DIR__).'/includes/class/settings.class.php';
use Portix\Infrastructure\Database; use Portix\Infrastructure\Migrator; use Portix\Repository\ContentSafetyRepository;
$tmp=sys_get_temp_dir().'/p3-safety-'.bin2hex(random_bytes(5)); mkdir($tmp); mkdir($tmp.'/var',0777,true); mkdir($tmp.'/var/sys',0777,true);
if(!defined('PORTIX_ROOT')) define('PORTIX_ROOT',$tmp);
try{
 $db=new Database($tmp.'/test.sqlite'); (new Migrator($db))->migrate(); $pdo=$db->connection();
 $pdo->exec("INSERT INTO news(title,slug,created_by,intro,body,status,created_at,updated_at) VALUES('Test','test','admin','','v1','draft','2026-01-01','2026-01-01')");
 $repo=new ContentSafetyRepository($pdo); $row=$pdo->query('SELECT * FROM news WHERE id=1')->fetch(); $repo->captureRevision('news',1,$row,'admin');
 $pdo->exec("UPDATE news SET body='v2' WHERE id=1"); $repo->restoreRevision(1,'admin'); if($pdo->query('SELECT body FROM news WHERE id=1')->fetchColumn()!=='v1') throw new RuntimeException('revision restore');
 $row=$pdo->query('SELECT * FROM news WHERE id=1')->fetch(); $repo->moveToTrash('news',1,'Test',$row,'admin'); $pdo->exec('DELETE FROM news WHERE id=1'); $trash=$repo->trash(); $repo->restoreTrash((int)$trash[0]['id']); if((int)$pdo->query('SELECT COUNT(*) FROM news WHERE id=1')->fetchColumn()!==1) throw new RuntimeException('trash restore');
 echo "Protection des contenus : OK\n";
} finally { @unlink($tmp.'/test.sqlite'); @unlink($tmp.'/var/sys/settings-content_safety.json'); @rmdir($tmp.'/var/sys'); @rmdir($tmp.'/var'); @rmdir($tmp); }
