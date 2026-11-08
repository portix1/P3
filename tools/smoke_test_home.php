<?php
declare(strict_types=1);
if (PHP_SAPI!=='cli') { http_response_code(403); exit(1); }
$root=dirname(__DIR__);
require $root.'/bootstrap/autoload.php';
require $root.'/includes/class/settings.class.php';
foreach (['Image','Settings','HomeRepository','Metrics','Renderer'] as $class) { require $root.'/var/modules/home/src/'.$class.'.php'; }
use Portix\Infrastructure\Database;
use Portix\Infrastructure\Migrator;
use Portix\Module\ModuleInstaller;
use Portix\Repository\ModuleRepository;
use PortixModule\Home\HomeRepository;
use PortixModule\Home\Metrics;
use PortixModule\Home\Renderer;
use PortixModule\Home\Image;
function homeCheck(bool $value,string $message): void { if (!$value) { throw new RuntimeException($message); } }
$temp=sys_get_temp_dir().'/p3-home-'.bin2hex(random_bytes(6));
mkdir($temp,0770,true);
$db=new Database($temp.'/site.sqlite');
try {
    $upgradeDb=new Database($temp.'/upgrade.sqlite');
    $upgradeConfig=\Portix\Configuration\SiteConfiguration::load($temp);
    $upgradeApp=new \Portix\Core\Application($root,\Portix\Http\Request::fromGlobals(),$upgradeConfig,$upgradeDb);
    $upgradeApp->migrate();
    homeCheck($upgradeDb->connection()->query("SELECT enabled FROM modules WHERE prefix='home'")->fetchColumn()===false,'Core does not register or install optional home module');
    homeCheck($upgradeConfig->get('defaultpage')==='news','Core preserves the landing page');
    $upgradeApp=null;$upgradeDb->close();
    (new Migrator($db))->migrate();
    $installer=new ModuleInstaller($db,new ModuleRepository($db->connection()));
    foreach (['articles','home','insights','affiliation','news'] as $module) { $installer->installDirectory($root.'/var/modules/'.$module); }
    $installer->installDirectory($root.'/var/modules/home');
    $pdo=$db->connection();
    // The first development ZIP used table names without the analytics convention.
    foreach (['analytics_home_metrics'=>'home_metrics','analytics_home_events'=>'home_events','analytics_home_cache'=>'home_cache'] as $new=>$old) {
        $pdo->exec('ALTER TABLE '.$new.' RENAME TO '.$old);
        $pdo->prepare('UPDATE module_schema_tables SET table_name=? WHERE table_name=?')->execute([$old,$new]);
    }
    $pdo->prepare('INSERT INTO home_metrics(entry_key,day,section_id,article_id,position,visible,clicks) VALUES(?,?,999,999,1,7,0)')->execute(['legacy-counter',gmdate('Y-m-d')]);
    $pdo->prepare('INSERT INTO home_events(entry_key,expires_at) VALUES(?,?)')->execute(['legacy-dedup',time()+3600]);
    $database=$db;
    require $root.'/var/modules/home/boot.php';
    homeCheck((int)$pdo->query("SELECT visible FROM analytics_home_metrics WHERE entry_key='legacy-counter'")->fetchColumn()===7,'Legacy module counters migrated without loss');
    homeCheck((bool)$pdo->query("SELECT 1 FROM analytics_home_events WHERE entry_key='legacy-dedup'")->fetchColumn(),'Legacy deduplication preserved');
    homeCheck(!$homeRepository->installed('home_metrics'),'Owned legacy tables replaced after transaction');
    $pdo->exec("DELETE FROM analytics_home_metrics WHERE entry_key='legacy-counter'");
    $repository=new HomeRepository($pdo); $now=time();
    for ($i=1;$i<=8;$i++) {
        $q=$pdo->prepare('INSERT INTO article_categories(id,name,slug,parent_id,position,is_visible,description) VALUES(?,?,?,?,0,1,?)');
        $q->execute([$i,'Cat '.$i,'cat-'.$i,$i===8?1:null,'']);
        $q=$pdo->prepare('INSERT INTO articles(id,category_id,title,slug,summary,body,status,position,created_by,published_at,created_at,updated_at,featured_image) VALUES(?,?,?,?,?,?,?,0,?,?,?,?,?)');
        $date=gmdate('Y-m-d H:i:s',$now);
        $q->execute([$i,$i,'Article '.$i,'article-'.$i,'<p>Résumé '.$i.'</p>','<p>Body</p>','published','tester',$date,$date,$date,'']);
        $repository->save(['kind'=>'articles','layout'=>['feature','category','cards','text'][$i%4],'category_id'=>$i,'title'=>'Zone '.$i,'item_count'=>2,'descendants'=>1,'weighted'=>1,'enabled'=>1,'position'=>$i],0);
    }
    $settings=['days'=>28,'exploration'=>50,'refresh_minutes'=>60];
    $draw=$repository->selection($settings,$now);
    homeCheck(count($draw)===7,'Eight categories supported with descendant/global deduplication');
    $ids=[]; foreach ($draw as $section) { foreach ($section['items'] as $item) { $ids[]=$item['id']; } }
    homeCheck(count($ids)===count(array_unique($ids)) && count($ids)===8,'All article IDs drawn once');
    homeCheck($repository->selection($settings,$now)===$draw,'Draw stable between cache refreshes');
    $pdo->exec("UPDATE articles SET status='draft' WHERE id=1");
    $filtered=$repository->selection($settings,$now);
    foreach ($filtered as $section) { homeCheck(!in_array(1,array_column($section['items'],'id')),'Withdrawn content hidden immediately'); }
    $pdo->exec("UPDATE articles SET published_at='2099-01-01' WHERE id=2");
    foreach ($repository->selection($settings,$now) as $section) { homeCheck(!in_array(2,array_column($section['items'],'id')),'Future content excluded'); }
    $pdo->exec("UPDATE modules SET enabled=0 WHERE prefix='articles'");
    homeCheck($repository->selection($settings,$now)===[],'Disabled article module hidden');
    $pdo->exec("UPDATE modules SET enabled=1 WHERE prefix='articles'");
    $repository->save(['kind'=>'text','layout'=>'text','title'=>'<script>','body'=>'<script>alert(1)</script>','enabled'=>1],0);
    $sections=$repository->selection($settings,$now);
    [$html,$slots]=Renderer::render($sections,$temp,['home_all'=>'Explore','home_series_start'=>'Start','home_disclosure'=>'Disclosure'],$repository->categories());
    homeCheck(!str_contains($html,'<script>') && str_contains($html,'&lt;script&gt;'),'Free text escaped');
    homeCheck(!str_contains(Renderer::excerpt('<script>secret</script><p>A &amp; B</p>'),'secret'),'Excerpt removes executable content');
    homeCheck(Renderer::excerpt('<p>A &amp; B</p>')==='A & B','Excerpt entities normalized');
    homeCheck(Image::path('https://evil.test/image.webp')==='' && Image::path('var/uploads/images/../../secret.png')==='' && Image::path('var/uploads/images/test.svg')==='','Only safe local raster paths');
    mkdir($temp.'/var/uploads/images',0770,true);
    $img=imagecreatetruecolor(1600,800); imagewebp($img,$temp.'/var/uploads/images/test.webp'); imagedestroy($img);
    $thumb=Image::thumbnail($temp,'var/uploads/images/test.webp');
    homeCheck(str_starts_with($thumb,'var/uploads/images/home-thumbs/'),'Local thumbnail generated');
    $imageRules=(string)file_get_contents($root.'/var/uploads/images/.htaccess');
    homeCheck((bool)preg_match('/<FilesMatch "([^"]+)"/',$imageRules,$denyPattern),'Read actual public image allowlist');
    homeCheck(preg_match('~'.$denyPattern[1].'~',basename($thumb))===0,'Generated thumbnail allowed by inherited Apache image policy');
    homeCheck(preg_match('~'.$denyPattern[1].'~','mini256-'.str_repeat('a',64).'.webp')===1,'Previous thumbnail naming reproduced the production denial');
    homeCheck(preg_match('~'.$denyPattern[1].'~','payload.php')===1,'Executable file names remain denied');
    $dimensions=getimagesize($temp.'/'.$thumb);
    homeCheck($dimensions[0]===256 && $dimensions[1]===128,'Thumbnail preserves proportions');
    $before=hash_file('sha256',$temp.'/var/uploads/images/test.webp');
    Image::thumbnail($temp,'var/uploads/images/test.webp');
    homeCheck($before===hash_file('sha256',$temp.'/var/uploads/images/test.webp'),'Original untouched');
    // Do not allow the thumbnail directory to escape uploads through a symlink.
    $metrics=new Metrics($pdo,str_repeat('s',64));
    $ticket=$metrics->ticket(['1-0'=>[1,1,1],'2-0'=>[2,2,1]],$now);
    $event=['ticket'=>$ticket,'page'=>str_repeat('a',32),'events'=>[['kind'=>'page'],['kind'=>'visible','slot'=>'1-0'],['kind'=>'click','slot'=>'1-0'],['kind'=>'click','slot'=>'2-0']]];
    $metrics->accept($event,$now); $metrics->accept($event,$now);
    $global=$pdo->query('SELECT * FROM analytics_home_metrics WHERE section_id=0')->fetch();
    homeCheck((int)$global['visible']===1 && (int)$global['clicks']===1,'Page loads and converted loads counted once');
    homeCheck((int)$pdo->query('SELECT SUM(clicks) FROM analytics_home_metrics WHERE section_id>0')->fetchColumn()===2,'Clicks deduplicated per card');
    foreach ([array_replace($event,['ticket'=>$ticket.'bad']),array_replace($event,['events'=>[['kind'=>'click','slot'=>'fake']]])] as $bad) {
        $rejected=false; try { $metrics->accept($bad,$now); } catch (InvalidArgumentException) { $rejected=true; }
        homeCheck($rejected,'Forged ticket or arbitrary slot rejected');
    }
    $expired=false; try { $metrics->accept($event,$now+7201); } catch (InvalidArgumentException) { $expired=true; }
    homeCheck($expired,'Expired public token rejected');
    homeCheck($pdo->query('PRAGMA integrity_check')->fetchColumn()==='ok','SQLite integrity');
    echo "Accueil : installation, catégories, tirage, cache, publication, XSS, miniatures et mesures : OK\n";
} finally {
    $pdo=null; $repository=null; $metrics=null; $installer=null; $db->close();
    $iterator=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($temp,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($iterator as $file) { $file->isDir()?@rmdir($file->getPathname()):@unlink($file->getPathname()); }
    @rmdir($temp);
}
