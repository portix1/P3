<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
$root=dirname(__DIR__,4);require $root.'/bootstrap/autoload.php';require $root.'/includes/class/html_sanitizer.class.php';require dirname(__DIR__).'/src/ArticleRepository.php';require dirname(__DIR__).'/src/ReadingTocBatch.php';
use PortixModule\Articles\ReadingTocBatch as Batch;
function tocAssert(bool $ok,string $label):void{if(!$ok)throw new RuntimeException($label);}
try{
 $db=new Portix\Infrastructure\Database(':memory:');(new Portix\Infrastructure\Migrator($db))->migrate();$pdo=$db->connection();(new Portix\Module\ModuleInstaller($db,new Portix\Repository\ModuleRepository($pdo)))->installDirectory(dirname(__DIR__));$a=new PortixModule\Articles\ArticleRepository($pdo);$r=$a->editorial();
 $three='<h1>Title</h1><h2>A</h2><h3>B</h3><h4>C</h4>';$four=$three.'<h5>D</h5>';
 tocAssert(Batch::headingCount($three)===3,'h1 excluded');tocAssert(Batch::headingCount($four)===4,'mixed levels count');tocAssert(Batch::headingCount('<h2>&nbsp;</h2><h3> </h3>&lt;h2&gt;escaped&lt;/h2&gt;')===0,'empty and escaped ignored');tocAssert(Batch::headingCount('<script><h2>Bad</h2></script>')===0,'script ignored');
 $ids=[];foreach([[$three,'published'],[$four,'published'],[$four,'draft'],[$three,'published'],[$four,'published']] as $i=>$entry)$ids[]=$a->createArticle(null,'Article '.$i,'batch-'.$i,$entry[0],$entry[1],'test',null,'2026-01-01');
 $r->saveReading($ids[0],false,[]);$r->saveReading($ids[1],false,[$ids[0],$ids[3]]);$r->saveReading($ids[3],true,[]);$r->saveReading($ids[4],true,[]);$series=$r->saveSeries(null,'Series','',true,[$ids[1],$ids[0]]);
 $before=$pdo->query('SELECT * FROM articles ORDER BY id')->fetchAll();$relations=$pdo->query('SELECT * FROM article_relations ORDER BY id')->fetchAll();$parts=$r->parts($series);
 $tmp=sys_get_temp_dir().'/p3-toc-batch-'.bin2hex(random_bytes(6));mkdir($tmp,0700,true);
 $result=Batch::applyOnce($db,$tmp);tocAssert($result['enabled']===2,'strictly more than three, including drafts');tocAssert(!$r->reading($ids[0])['toc_enabled'],'short false unchanged');tocAssert($r->reading($ids[1])['toc_enabled']&&$r->reading($ids[2])['toc_enabled'],'long enabled');tocAssert($r->reading($ids[3])['toc_enabled'],'short true unchanged');tocAssert($pdo->query('SELECT * FROM article_relations ORDER BY id')->fetchAll()===$relations&&$r->parts($series)===$parts,'relations and series retained');tocAssert($before===$pdo->query('SELECT * FROM articles ORDER BY id')->fetchAll(),'content URLs dates statuses unchanged');
 $backup=json_decode(file_get_contents($tmp.'/'.$result['backup']),true,512,JSON_THROW_ON_ERROR);tocAssert(count($backup['changes'])===2&&$backup['changes'][0]['previous']===0&&$backup['changes'][1]['previous']===null,'prior options backup');
 $r->saveReading($ids[1],false,[$ids[0],$ids[3]]);tocAssert(Batch::applyOnce($db,$tmp)===null&&!$r->reading($ids[1])['toc_enabled'],'one-time operation preserves later manual override');
 tocAssert($pdo->query('PRAGMA integrity_check')->fetchColumn()==='ok'&&$pdo->query('PRAGMA foreign_key_check')->fetch()===false,'SQLite integrity');
 // Force backup failure: no preference or completion marker may be written.
 $pdo->exec("DELETE FROM article_settings WHERE setting_key='toc_four_headings_batch_1'");$block=sys_get_temp_dir().'/p3-toc-block-'.bin2hex(random_bytes(6));mkdir($block.'/var/private',0700,true);file_put_contents($block.'/var/private/reading-toc-backups','blocked');
 set_error_handler(static fn():bool=>true);try{Batch::applyOnce($db,$block);throw new LogicException('missing backup refusal');}catch(RuntimeException $e){tocAssert($e->getMessage()==='articles_toc_batch_backup_failed','backup failure');}finally{restore_error_handler();}
 tocAssert(!$r->reading($ids[1])['toc_enabled']&&$pdo->query("SELECT count(*) FROM article_settings WHERE setting_key='toc_four_headings_batch_1'")->fetchColumn()===0,'failed batch rolled back');
 echo "TOC batch: strict threshold, nonempty h2-h6, drafts, preferences backup, preservation, idempotence and rollback PASS\n";
}catch(Throwable $e){fwrite(STDERR,$e->getMessage()."\n".$e->getTraceAsString()."\n");exit(1);}
