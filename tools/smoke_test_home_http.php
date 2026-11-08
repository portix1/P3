<?php
declare(strict_types=1);
if (PHP_SAPI!=='cli') { http_response_code(403); exit(1); }
$source=dirname(__DIR__);
require $source.'/bootstrap/autoload.php';require $source.'/includes/class/settings.class.php';
$fixture=sys_get_temp_dir().'/p3-home-http-'.bin2hex(random_bytes(6));
mkdir($fixture,0770,true);
$skip=['.git','database','sessions','tmp','dist','var/private','var/sys','var/uploads','var/cache'];
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
    foreach ($home->categories() as $i=>$category) { $home->save(['kind'=>'articles','category_id'=>$category['id'],'layout'=>['feature','category','cards'][$i%3],'title'=>$category['name'],'enabled'=>1,'descendants'=>1,'weighted'=>1,'item_count'=>2,'position'=>$i],0); }
    PortixSettings::write('home',['days'=>28,'exploration'=>50,'refresh_minutes'=>60,'tracking'=>true,'secret'=>str_repeat('x',64)],$fixture);
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
    $check($response[0]===200 && str_contains($response[1],'data-home-slot'),'Public home renders');
    $check(!preg_grep('/^Set-Cookie:/i',$response[2]),'Initial measurement does not require a cookie');
    preg_match('/data-home-ticket="([^"]+)"/',$response[1],$match);$ticket=html_entity_decode($match[1]??'',ENT_QUOTES);
    preg_match('/data-home-slot="([^"]+)"/',$response[1],$slot);
    $event=['ticket'=>$ticket,'page'=>str_repeat('a',32),'events'=>[['kind'=>'page'],['kind'=>'visible','slot'=>$slot[1]],['kind'=>'click','slot'=>$slot[1]]]];
    $post=$request('/home-events.html','POST',json_encode($event),['Content-Type: application/json','Origin: '.$base]);
    $check($post[0]===204,'Same-origin event accepted including a nondefault port');
    $check(!preg_grep('/^Set-Cookie:/i',$post[2]),'Telemetry creates no session cookie');
    $request('/home-events.html','POST',json_encode($event),['Content-Type: application/json']);
    $check((int)$db->connection()->query('SELECT SUM(clicks) FROM home_metrics WHERE section_id>0')->fetchColumn()===1,'HTTP replay deduplicated');
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
        $editor=$request('/Admin-home.html','GET','',$headers);
        $check($editor[0]===200 && str_contains($editor[1],'home_action'),'Staff composition screen');
        preg_match('/name="csrf_token" value="([^"]+)"/',$editor[1],$token);
        $count=count($home->sections(true));
        $check($request('/Admin-home.html','POST',http_build_query(['home_action'=>'delete','id'=>1,'confirm_delete'=>1,'csrf_token'=>'wrong']),$headers)[0]===403,'Invalid CSRF refused');
        $check(count($home->sections(true))===$count,'CSRF leaves zones intact');
        $save=['csrf_token'=>$token[1]??'','home_action'=>'save','kind'=>'text','layout'=>'text','title'=>'Test '.$actor,'body'=>'Safe text','enabled'=>1,'position'=>50];
        $check($request('/Admin-home.html','POST',http_build_query($save),$headers)[0]===200,'Staff can save a zone');
        $check(count($home->sections(true))===$count+1,'Zone persisted');
        foreach (['settings','report'] as $view) { $check($request('/Admin-home-'.$view.'.html','GET','',$headers)[0]===($actor==='admin'?200:403),'Settings/reports permissions'); }
        $staffHome=$request('/','GET','',$headers);
        $check(!str_contains($staffHome[1],'home/assets/home.js'),'Staff measurement excluded');
    }
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
