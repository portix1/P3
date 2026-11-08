<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(403); exit(1); }
require dirname(__DIR__).'/bootstrap/autoload.php';
use Portix\Module\ModuleManifest;
use Portix\Module\PublicSessionPolicy;
use Portix\Navigation\AdminNavigation;
use Portix\Support\Translator;
use Portix\Http\Request;
function extensionCheck(bool $ok, string $message): void { if (!$ok) { throw new RuntimeException($message); } }
$root=sys_get_temp_dir().'/p3-extensions-'.bin2hex(random_bytes(6));
$directory=$root.'/var/modules/catalog';mkdir($directory.'/lang',0770,true);
$xml=<<<'XML'
<modules><Name>Catalogue</Name><Version>1.0.0</Version><Prefix>catalog</Prefix><Type>module</Type><Path>catalog</Path><Enabled>1</Enabled><AdminAccess>editor</AdminAccess><MinimumP3>0.8.4</MinimumP3><AdminNavigation><Item area="writing" action="" role="editor" icon="file-text" label="catalog_compose"/><Item area="configuration" action="settings" role="admin" icon="settings" label="catalog_settings"/><Item area="management" action="report" role="admin" icon="chart-bar" label="catalog_report"/></AdminNavigation><SessionlessPostActions>measure</SessionlessPostActions></modules>
XML;
file_put_contents($directory.'/desc.xml',$xml);file_put_contents($directory.'/config.php','<?php');
file_put_contents($directory.'/lang/fr.php',"<?php \$_LANG += ['catalog_compose'=>'Catalogue','catalog_settings'=>'Réglages catalogue','catalog_report'=>'Résultats catalogue'];");
file_put_contents($directory.'/lang/en.php',"<?php \$_LANG += ['catalog_compose'=>'Catalog','catalog_settings'=>'Catalog settings','catalog_report'=>'Catalog report'];");
$globals=$_SERVER;
try {
    $manifest=ModuleManifest::fromXml($directory.'/desc.xml','catalog');
    $rejected=false;try { ModuleManifest::assertCompatible($manifest,'0.8.3-rc4'); } catch (RuntimeException) { $rejected=true; }
    extensionCheck($rejected,'Old core rejected by minimum version');
    ModuleManifest::assertCompatible($manifest,'0.8.4');
    $admin=AdminNavigation::sections($root,[$manifest],true,false,['Admin','catalog','settings'],new Translator([]),'en');
    $editor=AdminNavigation::sections($root,[$manifest],false,true,['Admin','catalog'],new Translator([]),'fr');
    extensionCheck(in_array('Admin-catalog.html',array_column($editor['writing'],'url'),true),'Declared editorial function available');
    extensionCheck($editor['configuration']===[] && $editor['management']===[],'Editor cannot see privileged declarations');
    $settings=array_values(array_filter($admin['configuration'],static fn($row)=>$row['url']==='Admin-catalog-settings.html'));
    extensionCheck(count($settings)===1 && $settings[0]['name']==='Catalog settings' && $settings[0]['current']==='is-current','Declared locale and active area respected');
    $manifest['enabled']=false;
    $disabled=AdminNavigation::sections($root,[$manifest],true,false,[],new Translator([]));
    foreach ($disabled as $rows) { foreach ($rows as $row) { extensionCheck(!str_contains($row['url'],'catalog'),'Disabled declarations hidden'); } }
    extensionCheck(AdminNavigation::sections($root,[$manifest],false,false,[],new Translator([]))===['writing'=>[],'management'=>[],'configuration'=>[]],'Anonymous has no privileged navigation');
    $_SERVER=['REQUEST_METHOD'=>'POST','REQUEST_URI'=>'/catalog-measure.html','SCRIPT_NAME'=>'/index.php'];
    extensionCheck(PublicSessionPolicy::allows(Request::fromGlobals(),$root),'Generic declared public action can avoid anonymous sessions');
    $_SERVER['REQUEST_URI']='/catalog-settings.html';
    extensionCheck(!PublicSessionPolicy::allows(Request::fromGlobals(),$root),'Other actions retain normal session policy');
    $_SERVER['REQUEST_METHOD']='GET';$_SERVER['REQUEST_URI']='/catalog-measure.html';
    extensionCheck(!PublicSessionPolicy::allows(Request::fromGlobals(),$root),'GET is not a sessionless POST opt-in');
    foreach ([str_replace('area="configuration"','area="root"',$xml),str_replace('action="settings"','action="../settings"',$xml),str_replace('role="admin"','role="editor"',$xml),str_replace('>measure<','>../admin<',$xml)] as $bad) {
        file_put_contents($directory.'/desc.xml',$bad);$rejected=false;
        try { ModuleManifest::fromXml($directory.'/desc.xml','catalog'); } catch (RuntimeException) { $rejected=true; }
        extensionCheck($rejected,'Invalid extension declaration rejected');
    }
    echo "Extensions génériques : manifeste, version minimale, trois espaces, rôles, traductions et sessions publiques : OK\n";
} finally {
    $_SERVER=$globals;
    $it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($it as $file) { $file->isDir()?@rmdir($file->getPathname()):@unlink($file->getPathname()); }@rmdir($root);
}
