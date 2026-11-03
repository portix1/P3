<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
$root=dirname(__DIR__);require $root.'/bootstrap/autoload.php';require $root.'/includes/class/html_sanitizer.class.php';require $root.'/var/modules/articles/src/ArticleRepository.php';
$db=new Portix\Infrastructure\Database($argv[1]??(sys_get_temp_dir().'/p3-table-editor-fixture.sqlite'));(new Portix\Infrastructure\Migrator($db))->migrate();$pdo=$db->connection();(new Portix\Module\ModuleInstaller($db,new Portix\Repository\ModuleRepository($pdo)))->installDirectory($root.'/var/modules/articles');
$repo=new PortixModule\Articles\ArticleRepository($pdo);$input=json_decode(stream_get_contents(STDIN),true);
if(isset($input['html'])){
 $html=PortixHtmlSanitizer::clean($input['html']);
 $article=$repo->find(1);
 if($article===null){$repo->createArticle(null,'Test tableau','test-tableau',$html,'draft','test',null,'2026-10-23');}
 else{$repo->updateArticle(1,null,'Test tableau','test-tableau',$html,'draft',null,'2026-10-23');}
}
echo json_encode(['html'=>$repo->find(1)['body']??'','version'=>PHP_VERSION],JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
