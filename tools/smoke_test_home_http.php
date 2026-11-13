<?php
declare(strict_types=1);
if (PHP_SAPI!=='cli') { http_response_code(403); exit(1); }
$source=dirname(__DIR__);
require $source.'/bootstrap/autoload.php';require $source.'/includes/class/settings.class.php';
$fixture=sys_get_temp_dir().'/p3-home-http-'.bin2hex(random_bytes(6));
mkdir($fixture,0770,true);
$skip=['.git','database','sessions','tmp','dist','var/private','var/sys','var/uploads','var/cache','var/modules/home'];
$copy=static function(string $from,string $to,string $relative='')use(&$copy,$skip):void {
    foreach (scandir($from)?:[] as $name) {
        if ($name==='.' || $name==='..') { continue; }
        $rel=ltrim($relative.'/'.$name,'/');
        if (in_array($rel,$skip,true) || is_link($from.'/'.$name)) { continue; }
        if (is_dir($from.'/'.$name)) { @mkdir($to.'/'.$name,0770,true); $copy($from.'/'.$name,$to.'/'.$name,$rel); }
        else { copy($from.'/'.$name,$to.'/'.$name); }
    }
};
$server=null; $db=null; $status=0;
try {
    $copy($source,$fixture);
    $db=new Portix\Infrastructure\Database($fixture.'/database/home-test.sqlite');
    (new Portix\Infrastructure\Migrator($db))->migrate();
    $repos=new Portix\Repository\RepositoryRegistry($db);
    $installer=new Portix\Module\ModuleInstaller($db,$repos->modules());
    foreach (glob($fixture.'/var/modules/*/desc.xml')?:[] as $file) {
        $manifest=Portix\Module\ModuleManifest::fromXml($file,basename(dirname($file)));
        if ($manifest['schema_format']==='desc-csv' && !is_dir(dirname($file).'/database')) { mkdir(dirname($file).'/database',0770,true); }
        $installer->installDirectory(dirname($file));
    }
    foreach (['admin','editor'] as $actor) { $repos->users()->create($actor,password_hash('test-home-password',PASSWORD_DEFAULT),$actor.'@example.test','Test',$actor,$actor==='admin',true); }
    (new Portix\Installation\DefaultContentSeeder())->seed($db->connection(),'admin',gmdate('Y-m-d H:i:s'));
    $config=Portix\Configuration\SiteConfiguration::load($fixture);
    $config->update(['database_file'=>'home-test.sqlite','defaultpage'=>'home','template'=>'six-cordes-ux-clair','title'=>'Home test','debug'=>true]);
    foreach (['Image','HomeRepository'] as $class) { require_once $source.'/var/modules/home/src/'.$class.'.php'; }
    $home=new PortixModule\Home\HomeRepository($db->connection());
    PortixSettings::write('insights',['mode'=>'minimal'],$fixture);
    file_put_contents($fixture.'/router.php', '<?php $p=parse_url($_SERVER["REQUEST_URI"],PHP_URL_PATH); if($p!=="/" && is_file(__DIR__.$p)) { return false; } require __DIR__."/index.php";');
    $socket=stream_socket_server('tcp://127.0.0.1:0',$errno,$error);
    if (!$socket) { throw new RuntimeException($error); }
    $address=stream_socket_get_name($socket,false);fclose($socket);
    $base='http://'.$address;
    $server=proc_open([PHP_BINARY,'-S',$address,'-t',$fixture,$fixture.'/router.php'],[['pipe','r'],['file',$fixture.'/server.log','a'],['file',$fixture.'/server.log','a']],$pipes,$fixture);
    if (!is_resource($server)) { throw new RuntimeException('Test server unavailable'); }
    fclose($pipes[0]);
    $request=static function(string $path,string $method='GET',string $body='',array $headers=[])use($base):array {
        $context=stream_context_create(['http'=>['method'=>$method,'header'=>implode("\r\n",$headers),'content'=>$body,'ignore_errors'=>true,'follow_location'=>0,'timeout'=>10]]);
        $text=@file_get_contents($base.$path,false,$context);$responseHeaders=$http_response_header??[];
        preg_match('~HTTP/\S+ (\d+)~',$responseHeaders[0]??'',$match);
        return [(int)($match[1]??0),$text===false?'':$text,$responseHeaders];
    };
    for ($attempt=0;$attempt<40;$attempt++) { $response=$request('/');if ($response[0]!==0) { break; }usleep(100000); }
    $check=static function(bool $value,string $message):void { if (!$value) { throw new RuntimeException($message); } };
    // Exercise the actual ZIP upload screen, not a file-copy substitute.
    $login=$request('/login.html');
    preg_match('/PHPSESSID=([^;]+)/',implode("\n",$login[2]),$cookie);
    preg_match('/name="csrf_token" value="([^"]+)"/',$login[1],$token);
    $auth=['Content-Type: application/x-www-form-urlencoded','Cookie: PHPSESSID='.($cookie[1]??'')];
    $logged=$request('/login.html','POST',http_build_query(['pseudo'=>'admin','pass'=>'test-home-password','csrf_token'=>$token[1]??'']),$auth);
    if (preg_match('/PHPSESSID=([^;]+)/',implode("\n",$logged[2]),$cookie)) { $auth[1]='Cookie: PHPSESSID='.$cookie[1]; }
    $screen=$request('/Admin-config-modules.html','GET','',$auth);
    preg_match('/name="csrf_token" value="([^"]+)"/',$screen[1],$token);
    $check(!is_dir($fixture.'/var/modules/home'),'Core fixture starts without Home');
    $snapshots=[];
    foreach (['VERSION','src/Core/Application.php','src/Core/Bootstrap.php','src/Module/ModuleManifest.php','src/Navigation/AdminNavigation.php','var/editor/portix-content.css'] as $name) { $snapshots[$name]=hash_file('sha256',$fixture.'/'.$name); }
    foreach (glob($fixture.'/var/templates/*/style.min.css')?:[] as $file) { $snapshots[substr($file,strlen($fixture)+1)]=hash_file('sha256',$file); }
    $makeZip=static function(string $name,bool $incompatible=false)use($source,$fixture):string {
        $file=$fixture.'/tmp/'.$name; $zip=new ZipArchive();$zip->open($file,ZipArchive::CREATE|ZipArchive::OVERWRITE);
        $dir=$source.'/var/modules/home';
        $it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir,FilesystemIterator::SKIP_DOTS));
        foreach ($it as $item) { if (!$item->isFile() || $item->isLink()) { continue; }
            $relative=substr($item->getPathname(),strlen($dir)+1);
            $bytes=(string)file_get_contents($item->getPathname());
            if ($incompatible && $relative==='desc.xml') { $bytes=str_replace('<MinimumP3>0.8.4-rc4</MinimumP3>','<MinimumP3>99.0.0</MinimumP3>',$bytes); }
            $zip->addFromString('home/'.$relative,$bytes);
        }
        $zip->close();return (string)file_get_contents($file);
    };
    $homeVersion=trim((string)file_get_contents($source.'/var/modules/home/MODULE-VERSION'));
    $upload=static function(string $bytes,string $csrf)use($request,$auth,$homeVersion):array {
        $boundary='p3'.bin2hex(random_bytes(12));$body='';
        foreach (['module_action'=>'upload','csrf_token'=>$csrf] as $key=>$value) { $body.='--'.$boundary."\r\nContent-Disposition: form-data; name=\"".$key."\"\r\n\r\n".$value."\r\n"; }
        $body.='--'.$boundary."\r\nContent-Disposition: form-data; name=\"userfile\"; filename=\"P3-Accueil-".$homeVersion.".zip\"\r\nContent-Type: application/zip\r\n\r\n".$bytes."\r\n--".$boundary."--\r\n";
        return $request('/Admin-config-modules.html','POST',$body,['Content-Type: multipart/form-data; boundary='.$boundary,$auth[1]]);
    };
    $archive=$makeZip('home-install.zip');
    $installed=$upload($archive,$token[1]??'');
    $check($installed[0]===200,'Native module ZIP upload completed');
    $check($db->connection()->query("SELECT enabled FROM modules WHERE prefix='home'")->fetchColumn()==0,'Fresh installation leaves activation explicit');
    $db->connection()->exec("UPDATE modules SET enabled=1 WHERE prefix='home'");
    foreach ($home->categories() as $i=>$category) { $home->save(['kind'=>'articles','category_id'=>$category['id'],'layout'=>['feature','category','cards'][$i%3],'title'=>$category['name'],'enabled'=>1,'descendants'=>1,'weighted'=>1,'item_count'=>2,'position'=>$i],0); }
    // A real local raster exercises both article and category imagery.
    @mkdir($fixture.'/var/uploads/images',0770,true);
    copy($fixture.'/var/templates/six-cordes/assets/guitar-header.webp',$fixture.'/var/uploads/images/home-test.webp');
    $db->connection()->exec("UPDATE articles SET featured_image='var/uploads/images/home-test.webp' WHERE id=(SELECT MIN(id) FROM articles)");
    $db->connection()->exec("UPDATE home_sections SET image_path='var/uploads/images/home-test.webp',image_alt='Test category image' WHERE id=(SELECT MIN(id) FROM home_sections WHERE layout='category')");
    PortixSettings::write('home',['days'=>28,'exploration'=>50,'refresh_minutes'=>60,'tracking'=>true,'secret'=>str_repeat('x',64)],$fixture);
    $zoneCount=count($home->sections(true));
    $installed=$upload($archive,$token[1]??'');
    $check($installed[0]===200,'Native ZIP reinstall completed');
    $check($db->connection()->query("SELECT enabled FROM modules WHERE prefix='home'")->fetchColumn()==1,'Native reinstall preserves activation');
    $check(count($home->sections(true))===$zoneCount,'Native ZIP reinstall preserves editorial zones');
    $check(trim((string)file_get_contents($fixture.'/var/modules/home/MODULE-VERSION'))===$homeVersion,'Correct module package installed');
    $manifestHash=hash_file('sha256',$fixture.'/var/modules/home/desc.xml');
    $rejected=$upload($makeZip('home-incompatible.zip',true),$token[1]??'');
    $check(str_contains($rejected[1],'99.0.0') && hash_file('sha256',$fixture.'/var/modules/home/desc.xml')===$manifestHash,'Incompatible package rejected before replacing the installed module');
    foreach ($snapshots as $name=>$hash) { $check(hash_file('sha256',$fixture.'/'.$name)===$hash,'Installing ZIP leaves engine and theme unchanged: '.$name); }
    $response=$request('/');
    $check($response[0]===200 && str_contains($response[1],'data-home-slot'),'Public home renders');
    $check(!preg_grep('/^Set-Cookie:/i',$response[2]),'Initial measurement does not require a cookie');
    preg_match('/data-home-ticket="([^"]+)"/',$response[1],$match);$ticket=html_entity_decode($match[1]??'',ENT_QUOTES);
    preg_match('/data-home-slot="([^"]+)"/',$response[1],$slot);
    $event=['ticket'=>$ticket,'page'=>str_repeat('a',32),'events'=>[['kind'=>'page'],['kind'=>'visible','slot'=>$slot[1]],['kind'=>'click','slot'=>$slot[1]]]];
    $post=$request('/home-events.html','POST',json_encode($event),['Content-Type: application/json','Origin: '.$base]);
    $check($post[0]===204,'Same-origin event accepted including a nondefault port');
    $check(!preg_grep('/^Set-Cookie:/i',$post[2]),'Telemetry creates no session cookie');
    $request('/home-events.html','POST',json_encode($event),['Content-Type: application/json']);
    $check((int)$db->connection()->query('SELECT SUM(clicks) FROM analytics_home_metrics WHERE section_id>0')->fetchColumn()===1,'HTTP replay deduplicated');
    $bad=$event;$bad['ticket'].='bad';
    $check($request('/home-events.html','POST',json_encode($bad),['Content-Type: application/json'])[0]===422,'Bad signature rejected');
    $check($request('/home-events.html')[0]===405,'GET event refused');
    $anon=$request('/Admin-home.html');
    $check(in_array($anon[0],[200,403],true) && !str_contains($anon[1],'home_action'),'Anonymous cannot access composition controls');
    foreach (['admin','editor'] as $actor) {
        $login=$request('/login.html');
        preg_match('/PHPSESSID=([^;]+)/',implode("\n",$login[2]),$cookie);
        preg_match('/name="csrf_token" value="([^"]+)"/',$login[1],$token);
        $headers=['Content-Type: application/x-www-form-urlencoded','Cookie: PHPSESSID='.($cookie[1]??'')];
        $logged=$request('/login.html','POST',http_build_query(['pseudo'=>$actor,'pass'=>'test-home-password','csrf_token'=>$token[1]??'']),$headers);
        if (preg_match('/PHPSESSID=([^;]+)/',implode("\n",$logged[2]),$cookie)) { $headers[1]='Cookie: PHPSESSID='.$cookie[1]; }
        $configArea=$request('/Admin-configuration.html','GET','',$headers);
        $check($configArea[0]===($actor==='admin'?200:403), 'Only administrators enter Configuration');
        $check(str_contains($configArea[1],'Admin-home.html')===($actor==='admin'), 'Only administrators can find Home in Configuration');
        $management=$request('/Admin-management.html','GET','',$headers);
        $check($management[0]===($actor==='admin'?200:403), 'Only administrators enter Administration');
        $check(str_contains($configArea[1], 'href="Admin-menu.html"')===($actor==='admin'),
            'Main menu appears only in Configuration for administrators');
        $check(str_contains($configArea[1], 'href="Admin-footer.html"')===($actor==='admin'),
            'Footer menu appears only in Configuration for administrators');
        $check(!str_contains($management[1], 'href="Admin-menu.html"'),
            'Main menu absent from Administration');
        $check(!str_contains($management[1], 'href="Admin-footer.html"'),
            'Footer menu absent from Administration');
        foreach (['Admin-config-modules.html', 'Admin-diagnostics.html', 'Admin-safety.html'] as $entry) {
            $check(str_contains($configArea[1], 'href="' . $entry . '"') === ($actor === 'admin'), 'Configuration entry: '.$entry);
            $check(!str_contains($management[1], 'href="' . $entry . '"'), 'Entry absent from Administration: '.$entry);
        }
        foreach (['Admin-config-modules.html', 'Admin-modmgr.html'] as $managerRoute) {
            $manager = $request('/'.$managerRoute, 'GET', '', $headers);
            $check($manager[0]===($actor==='admin'?200:403), 'Module manager permissions: '.$managerRoute);
            if ($actor === 'admin') {
                $check(str_contains($manager[1], 'action="Admin-config-modules.html"'), 'Module forms use the Configuration route');
            }
        }
        foreach (['menu','footer'] as $structural) {
            $check($request('/Admin-'.$structural.'.html','GET','',$headers)[0]===($actor==='admin'?200:403), 'Structural menu GET restricted to administrators');
            if ($actor==='editor') {
                $check($request('/Admin-'.$structural.'.html','POST',http_build_query(['csrf_token'=>'invalid']),$headers)[0]===403, 'Structural menu POST restricted to administrators');
            }
        }
        $composition=$request('/Admin-home.html','GET','',$headers);
        $check($composition[0]===($actor==='admin'?200:403), 'Only administrators can compose Home');
        $count=count($home->sections(true));
        if ($actor==='admin') {
            $check(str_contains($composition[1],'home_action'), 'Home composition controls available to administrators');
            preg_match('/name="csrf_token" value="([^"]+)"/',$composition[1],$token);
            $check($request('/Admin-home.html','POST',http_build_query(['home_action'=>'delete','id'=>1,'confirm_delete'=>1,'csrf_token'=>'wrong']),$headers)[0]===403,'Invalid CSRF refused');
            $check(count($home->sections(true))===$count,'CSRF leaves zones intact');
            $save=['csrf_token'=>$token[1]??'','home_action'=>'save','kind'=>'text','layout'=>'text','title'=>'Test '.$actor,'body'=>'Safe text','enabled'=>1,'position'=>50];
            $check($request('/Admin-home.html','POST',http_build_query($save),$headers)[0]===200,'Administrator can save a zone');
            $check(count($home->sections(true))===$count+1,'Zone persisted');
            $draft=$home->sections(true);
            foreach ($draft as &$zone) { foreach (['enabled','descendants','weighted'] as $flag) { if (!$zone[$flag]) { unset($zone[$flag]); } } } unset($zone);
            $draft[0]['title']='UNPUBLISHED PREVIEW';
            $before=$home->sections(true);
            $previewPost=['csrf_token'=>$token[1]??'','home_action'=>'preview','sections'=>json_encode($draft)];
            $previewResponse=$request('/Admin-home.html','POST',http_build_query($previewPost),$headers);
            $previewJson=json_decode($previewResponse[1],true);
            $check($previewResponse[0]===200 && ($previewJson['ok']??false) && str_contains($previewJson['html'],'UNPUBLISHED PREVIEW'),'Draft is rendered before publication');
            $check($home->sections(true)===$before,'Preview rolls back every zone change');
            $draft=array_reverse($draft); $previewPost['sections']=json_encode($draft); $previewPost['home_action']='publish';
            $publishResponse=$request('/Admin-home.html','POST',http_build_query($previewPost),$headers);
            $check($publishResponse[0]===200 && (json_decode($publishResponse[1],true)['ok']??false),'Whole composition is published atomically');
            $check(array_column($home->sections(true),'id')===array_column($draft,'id'),'Published order follows the draft');
            $invalid=$draft; $invalid[0]['kind']='invalid'; $previewPost['sections']=json_encode($invalid);
            $before=$home->sections(true);
            $check($request('/Admin-home.html','POST',http_build_query($previewPost),$headers)[0]===422,'Invalid draft refused');
            $check($home->sections(true)===$before,'Failed publication preserves all zones');

        } else {
            $check(!str_contains($composition[1],'home_action'), 'No Home controls in denied editor response');
            $save=['csrf_token'=>'invalid','home_action'=>'save','kind'=>'text','layout'=>'text','title'=>'Denied editor','body'=>'Must not persist','enabled'=>1,'position'=>50];
            $check($request('/Admin-home.html','POST',http_build_query($save),$headers)[0]===403, 'Editor cannot save a Home zone by direct POST');
            $check(count($home->sections(true))===$count, 'Rejected editor POST leaves Home unchanged');
            foreach (['preview','publish'] as $action) { $check($request('/Admin-home.html','POST',http_build_query(['home_action'=>$action,'sections'=>'[]','csrf_token'=>'invalid']),$headers)[0]===403,'Editor cannot preview or publish composition'); }
        }
        foreach (['settings','report'] as $view) { $check($request('/Admin-home-'.$view.'.html','GET','',$headers)[0]===($actor==='admin'?200:403),'Settings/reports permissions'); }
        $staffHome=$request('/','GET','',$headers);
        $check(!str_contains($staffHome[1],'home/assets/home.js'),'Staff measurement excluded');
    }
    $empty = $request('/Admin-insights.html', 'GET', '', $auth);
    $check($empty[0] === 200 && str_contains($empty[1], 'Aucune mesure sur cette période.'), 'Empty dashboard is readable');
    PortixSettings::write('insights', ['mode' => 'daily'], $fixture);
    $slug = $db->connection()->query("SELECT slug FROM articles WHERE status = 'published' ORDER BY id LIMIT 1")->fetchColumn();
    $articleUrl = '/' . $slug . '.html';
    $visitor = ['User-Agent: Firefox test visitor', 'Referer: https://example.test/path?private=1'];
    $first = $request('/', 'GET', '', $visitor);
    $check($first[0] === 200 && !preg_grep('/^Set-Cookie:/i', $first[2]), 'Daily fingerprint sets no cookie');
    $check($request($articleUrl, 'GET', '', $visitor)[0] === 200, 'Public article is measurable');
    $request($articleUrl, 'GET', '', ['User-Agent: Googlebot']);
    $request($articleUrl, 'GET', '', [...$visitor, 'DNT: 1']);
    $request('/does-not-exist-test.html', 'GET', '', $visitor);
    $request($articleUrl, 'HEAD', '', $visitor);
    $request($articleUrl, 'GET', '', [...$visitor, ...$auth]);
    $check((int) $db->connection()->query('SELECT SUM(views) FROM analytics_v2_daily')->fetchColumn() === 2, 'Only valid anonymous views counted');
    $check((int) $db->connection()->query('SELECT SUM(visitors) FROM analytics_v2_daily')->fetchColumn() === 1, 'Same-day return deduplicated');
    $check($db->connection()->query("SELECT dimension FROM analytics_v2_counts WHERE kind = 'entry'")->fetchColumn() === '/', 'First entry preserved');
    $check($db->connection()->query("SELECT dimension FROM analytics_v2_counts WHERE kind = 'article'")->fetchColumn() === $slug, 'Canonical article slug measured');
    $check($db->connection()->query("SELECT dimension FROM analytics_v2_counts WHERE kind = 'referrer'")->fetchColumn() === 'example.test', 'Referrer stored as host only');
    $dashboard = $request('/Admin-insights.html', 'GET', '', $auth);
    $check($dashboard[0] === 200 && str_contains($dashboard[1], 'Pages par visiteur journalier estimé') && str_contains($dashboard[1], '2,00'), 'New dashboard displays the daily ratio');
    $check(str_contains($dashboard[1], 'class="insights-chart"') && str_contains($dashboard[1], 'class="insights-daily-views" max="2" value="2"')
        && str_contains($dashboard[1], 'class="insights-daily-visitors" max="2" value="1"'),
        'Daily chart displays both measures on a common scale');
    $chartStyles = $request('/var/modules/insights/assets/daily-chart.css?v=2.0.2');
    $check($chartStyles[0] === 200 && str_contains($chartStyles[1], '.insights-daily-visitors'), 'Chart stylesheet is served');
    $csv = $request('/Admin-insights-export.html', 'GET', '', $auth);
    $check($csv[0] === 200 && str_contains($csv[1], 'visiteurs_journaliers_estimes') && str_contains($csv[1], ';2;1'), 'CSV exports only daily aggregates');
    echo "Statistiques HTTP : empreinte quotidienne, exclusions, article, entrée, référent et export vérifiés.\n";

    if (in_array('--browser',$argv,true)) {
        putenv('HOME_TEST_URL='.$base);
        passthru('node '.escapeshellarg($source.'/tools/test_home_browser.cjs'),$exit);
        $check($exit===0,'Browser validation');
    }
    $db->connection()->exec("UPDATE modules SET enabled=0 WHERE prefix='home'");
    $check($request('/')[0]===404 && $request('/home-events.html','POST',json_encode($event),['Content-Type: application/json'])[0]===404,'Disabled module does not render or collect');
    echo "Accueil HTTP : public, rôles, CSRF, persistance, signature, port local et absence de nouveau cookie : OK\n";
} catch (Throwable $error) {
    fwrite(STDERR,$error->getMessage()."\n");
    if (is_file($fixture.'/server.log')) { fwrite(STDERR,substr((string)file_get_contents($fixture.'/server.log'),-2400)); }
    $status=1;
} finally {
    if (is_resource($server)) { proc_terminate($server);proc_close($server); }
    $home=null;$db?->close();
    if (is_dir($fixture)) { $it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($fixture,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);foreach ($it as $file) { $file->isDir()?@rmdir($file->getPathname()):@unlink($file->getPathname()); }@rmdir($fixture); }
}

exit($status);
