<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$root = dirname(__DIR__, 4); chdir($root);
require $root.'/bootstrap/autoload.php';
require $root.'/var/modules/articles/src/ArticleRepository.php';
require $root.'/includes/class/security.class.php';
function compactCheck(bool $ok, string $label): void { if (!$ok) throw new RuntimeException($label); }
$database = new Portix\Infrastructure\Database(':memory:');
(new Portix\Infrastructure\Migrator($database))->migrate();
$pdo=$database->connection();
(new Portix\Module\ModuleInstaller($database,new Portix\Repository\ModuleRepository($pdo)))->installDirectory($root.'/var/modules/articles');
$articleRepository=new PortixModule\Articles\ArticleRepository($pdo); $ids=[];
foreach (['published','draft','published','published'] as $i=>$status) $ids[]=$articleRepository->createArticle(null,'Article '.($i+1),'compact-'.($i+1),'<p>Body</p>',$status,'test',null,'2026-01-01');
$seriesId=$articleRepository->editorial()->saveSeries(null,'Example series','Description',true,$ids);
$_LANG=[];require $root.'/var/lang/fr.php';require $root.'/var/modules/articles/lang/fr.php';$translator=new Portix\Support\Translator($_LANG);
foreach ([$argv[1] ?? 'visible'] as $mode) {
 if($mode==='hidden')$articleRepository->editorial()->saveSeries($seriesId,'Example series','Description',false,$ids);
 if($mode==='standalone')$articleRepository->editorial()->deleteSeries($seriesId);
 $template=new Portix\View\Template($root.'/var/modules/articles/templates/index.tpl');foreach($_LANG as $k=>$v)$template->set($k,$v);
 $breadcrumbTrail=new Portix\Navigation\BreadcrumbTrail('Home');$pageMetadata=new Portix\Seo\PageMetadata('Test','','https://example.test');$argument=['articles','page','compact','3'];
 require $root.'/var/modules/articles/index.php';$html=$template->render();
 if($mode==='standalone'){compactCheck(!str_contains($html,'p3-series-parts'),'standalone no series control');continue;}
 compactCheck(substr_count($html,'Liste des parties')===1,'one list label');
 preg_match('~<summary>(.*?)</summary>~s',$html,$m);$text=trim(strip_tags($m[1]??''));
 compactCheck($text===($mode==='visible'?'Example series ':'').'(2/3) · Liste des parties','compact summary and optional title');
 compactCheck(str_contains($html,'aria-label="Partie 2 sur 3"'),'screen reader position');
 compactCheck(str_contains($html,'<details id="p3-series-parts">'),'native initially closed disclosure');
 compactCheck(!str_contains($html,'reading-ui.js')&&!str_contains($html,'compact-2.html'),'no JS required and no draft link');
 compactCheck(str_contains($html,'rel="prev" href="compact-1.html"')&&str_contains($html,'rel="next" href="compact-4.html"'),'published neighbors');
}
echo "Compact series: one label, visible/hidden title, public numbering, native disclosure, standalone and navigation PASS\n";
