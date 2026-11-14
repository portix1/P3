<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(403); exit(1); }
require dirname(__DIR__) . '/bootstrap/autoload.php';
require dirname(__DIR__) . '/includes/class/security.class.php';
require dirname(__DIR__) . '/var/modules/articles/src/ArticleRepository.php';
require dirname(__DIR__) . '/var/modules/articles/src/ArticleAdminController.php';
require dirname(__DIR__) . '/var/modules/articles/src/ArticleNavigation.php';
use Portix\Domain\ArticlePermissions as P;
use Portix\Domain\UserAccount as U;
function permissionAssert(bool $ok, string $label): void { if (!$ok) { throw new RuntimeException($label); } }
function permissionUser(array $overrides = [], bool $admin = false): U { return new U(['pseudo'=>'writer', 'is_admin'=>$admin, 'is_redactor'=>true, 'article_permissions'=>json_encode($overrides)]); }
function permissionRun(): void {
    $root = dirname(__DIR__); chdir($root);
    $cats = [['id'=>1,'parent_id'=>null],['id'=>2,'parent_id'=>1],['id'=>3,'parent_id'=>2],['id'=>4,'parent_id'=>null]];
    $own = ['created_by'=>'writer','category_id'=>2,'status'=>'draft'];
    $other = array_replace($own,['created_by'=>'admin']);
    $live = array_replace($own,['status'=>'published']);
    $u = permissionUser();
    permissionAssert(P::allows($u,'edit',null,null,$cats), 'default create uncategorized');
    permissionAssert(P::allows($u,'edit',$own,2,$cats), 'default edit own draft');
    foreach (['publish','delete'] as $action) { permissionAssert(!P::allows($u,$action,$own,2,$cats),'default deny '.$action); }
    permissionAssert(!P::allows($u,'edit',$other,2,$cats),'deny other author');
    permissionAssert(!P::allows($u,'edit',$live,2,$cats),'deny published');
    $restricted = permissionUser(['all_categories'=>false,'categories'=>[1]]);
    permissionAssert(!P::allows($restricted,'edit',$own,2,$cats),'children explicit opt in');
    $restricted = permissionUser(['all_categories'=>false,'categories'=>[1],'include_children'=>true]);
    permissionAssert(P::allows($restricted,'edit',$own,3,$cats),'recursive descendants and move within scope');
    permissionAssert(!P::allows($restricted,'edit',$own,4,$cats),'destination outside scope');
    permissionAssert(!P::allows($restricted,'edit',array_replace($own,['category_id'=>4]),2,$cats),'source outside scope');
    permissionAssert(!P::allows($restricted,'edit',null,null,$cats),'uncategorized denied');
    permissionAssert(!P::allows(permissionUser(['all_categories'=>false]),'edit',null,2,$cats),'empty selection denies all');
    permissionAssert(!P::allows(permissionUser(['all_categories'=>false,'categories'=>[99]]),'edit',null,99,$cats),'deleted category denies');
    permissionAssert(!P::category(P::decode('{"all_categories":false,"include_children":true}'),5,[['id'=>5,'parent_id'=>6],['id'=>6,'parent_id'=>5]]),'cycle terminates');
    permissionAssert(P::allows(permissionUser(['edit_others'=>true]),'edit',$other,2,$cats),'grant edit other');
    permissionAssert(P::allows(permissionUser(['publish'=>true]),'publish',$own,2,$cats),'grant publish');
    permissionAssert(P::allows(permissionUser(['edit_published'=>true]),'edit',$live,2,$cats),'grant published editing');
    permissionAssert(P::allows(permissionUser(['delete_own'=>true]),'delete',$own,2,$cats),'grant own deletion');
    permissionAssert(!P::allows(permissionUser(['delete_own'=>true,'edit_others'=>true]),'delete',$other,2,$cats),'never delete other author');
    permissionAssert(P::allows(permissionUser([],true),'delete',$other,4,$cats),'admin bypass');
    permissionAssert(!P::allows(new U(['pseudo'=>'x','is_admin'=>false,'is_redactor'=>false]),'edit',$own,2,$cats),'non writer denied');
    permissionAssert(!P::decode('{broken')['create'],'corrupt persisted rights fail closed');
    session_start();
    $firstCsrf = PortixSecurity::csrfToken();
    $secondCsrf = PortixSecurity::rotateCsrfToken();
    permissionAssert(strlen($secondCsrf) === 64 && $firstCsrf !== $secondCsrf, 'CSRF rotates');
    permissionAssert(!PortixSecurity::csrfIsValid($firstCsrf) && PortixSecurity::csrfIsValid($secondCsrf), 'old token refused after rotation');
    session_destroy();
    $configDir = sys_get_temp_dir() . '/p3-config-permissions-' . bin2hex(random_bytes(8));
    try {
        $configuration = Portix\Configuration\SiteConfiguration::load($configDir);
        $configuration->replace(['title'=>'Permissions test']);
        $configFile = $configDir . '/var/sys/config.json';
        clearstatcache(true, $configFile);
        permissionAssert(DIRECTORY_SEPARATOR === chr(92) || (fileperms($configFile) & 0777) === 0600, 'config private mode');
        $configuration->update(['title'=>'Updated']);
        permissionAssert(Portix\Configuration\SiteConfiguration::load($configDir)->get('title') === 'Updated', 'private config remains writable');
        $user = $u;
        $template = new Portix\View\Template($root . '/var/admin/index.tpl');
        $argument = ['Admin','contact'];
        foreach (['GET','POST'] as $method) {
            $_SERVER['REQUEST_METHOD'] = $method;
            $_POST = ['contact_admin_action'=>'reply'];
            http_response_code(200);
            require $root . '/var/modules/contact/config.php';
            permissionAssert(http_response_code() === 403, 'contact denied to writer '.$method);
        }
    } finally {
        @unlink($configDir . '/var/sys/config.json');
        @rmdir($configDir . '/var/sys'); @rmdir($configDir . '/var'); @rmdir($configDir);
        $_POST = [];
    }
    $_SESSION=['csrf_token'=>'']; permissionAssert(!PortixSecurity::csrfIsValid(''),'empty CSRF token refused');
    $_SESSION=['csrf_token'=>123]; permissionAssert(!PortixSecurity::csrfIsValid('123'),'non-string CSRF token refused');
    $_SESSION=['csrf_token'=>'fixture']; permissionAssert(PortixSecurity::csrfIsValid('fixture')&&!PortixSecurity::csrfIsValid('wrong'),'valid and invalid CSRF token');
    foreach (['usages','analyse','alt_audit','replace','delete','restore','inventory','recover','webp_candidates','webp_migrate','unknown'] as $operation) {
        permissionAssert(Portix\Domain\MediaPermissions::requiresAdmin($operation), 'media restricted '.$operation);
    }
    foreach (['list','upload','set_accessibility'] as $operation) {
        permissionAssert(!Portix\Domain\MediaPermissions::requiresAdmin($operation), 'media editorial '.$operation);
    }
    $previewStore = new Portix\Preview\SessionPreviewStore();
    $_SESSION=['authenticated'=>true,'user_nick'=>'writer','auth_version'=>1];
    $previewToken=$previewStore->create('article',['title'=>'private preview']);
    permissionAssert($previewStore->get('article',$previewToken)['title']==='private preview','same account preview');
    permissionAssert($previewStore->get('news',$previewToken)===null,'wrong preview kind denied');
    $_SESSION['user_nick']='another';
    permissionAssert($previewStore->get('article',$previewToken)===null,'other account preview denied');
    $_SESSION['user_nick']='writer';
    $previewToken=$previewStore->create('news',['title'=>'private news']);
    $_SESSION['auth_version']=2;
    permissionAssert($previewStore->get('news',$previewToken)===null,'changed permission version preview denied');
    $previewToken=$previewStore->create('news',['title'=>'private news']);
    Portix\Preview\SessionPreviewStore::clear();
    permissionAssert($previewStore->get('news',$previewToken)===null,'login change clears previews');
    $_SESSION=[];
    permissionAssert($previewStore->get('news',$previewToken)===null,'anonymous preview denied');
    $newsOwn = ['created_by'=>'writer','status'=>'draft'];
    $newsOther = array_replace($newsOwn,['created_by'=>'admin']);
    $newsLive = array_replace($newsOwn,['status'=>'published']);
    $newsRights = static fn(U $account, string $action, ?array $row = null): bool => Portix\Domain\NewsPermissions::allows($account, $action, $row);
    permissionAssert($newsRights($u,'edit',$newsOwn),'news own draft editable');
    permissionAssert(!$newsRights($u,'publish',$newsOwn),'news review cannot publish');
    permissionAssert(!$newsRights($u,'edit',$newsOther),'news other author denied');
    permissionAssert(!$newsRights($u,'edit',$newsLive),'news published denied');
    permissionAssert(!$newsRights(permissionUser(['create'=>false]),'edit'),'news creation denied');
    $trusted = permissionUser(['publish'=>true,'edit_published'=>true]);
    permissionAssert($newsRights($trusted,'publish',$newsLive),'trusted news publication');
    permissionAssert(!$newsRights($trusted,'pin',$newsLive)&&!$newsRights($trusted,'delete',$newsOwn),'news structural pin and deletion reserved');
    permissionAssert($newsRights(permissionUser([],true),'pin',$newsLive),'administrator news pin');
    $db = new Portix\Infrastructure\Database(':memory:');
    (new Portix\Infrastructure\Migrator($db))->migrate();
    (new Portix\Infrastructure\Migrator($db))->migrate();
    $pdo = $db->connection();
    $repo = new Portix\Repository\UserRepository($pdo);
    $repo->create('writer','hash','','','',false,true);
    $repo->saveArticlePermissions('writer',['all_categories'=>false,'categories'=>[1],'include_children'=>true]);
    permissionAssert($repo->find('writer')->authVersion()===2,'rights invalidate old sessions');
    permissionAssert($repo->find('writer')->articlePermissions()['categories']===[1],'persisted permissions');
    $installer = new Portix\Module\ModuleInstaller($db,new Portix\Repository\ModuleRepository($pdo));
    $installer->installDirectory($root.'/var/modules/articles');
    $articles = new PortixModule\Articles\ArticleRepository($pdo);
    $a=$articles->createCategory('Allowed','allowed',null,true,'');
    $b=$articles->createCategory('Child','child',$a,true,'');
    $outside=$articles->createCategory('Outside','outside',null,true,'');
    $now=gmdate('Y-m-d H:i:s');
    $id=$articles->createArticle($b,'Own','own','<p>Original</p>','draft','writer',null,$now,'','','');
    $otherId=$articles->createArticle($b,'Other','other','<p>Private</p>','draft','admin',null,$now,'','','');
    $_LANG=[]; require $root.'/var/lang/fr.php';require $root.'/var/modules/articles/lang/fr.php';
    $translator=new Portix\Support\Translator($_LANG);
    $controller=new PortixModule\Articles\ArticleAdminController($articles,$root,$translator);
    $user=$repo->find('writer');
    $newsRepo = new Portix\Repository\NewsRepository($pdo);
    $newsId = $newsRepo->create('Own news','own-news','writer','Intro','Body','draft',null,$now);
    $otherNewsId = $newsRepo->create('Private news','private-news','admin','Intro','Body','draft',null,$now);
    $newsController = new Portix\Controller\Admin\NewsAdminController($newsRepo,$root,[],$translator,new Portix\Repository\ContentSafetyRepository($pdo));
    foreach ([['edit',$otherNewsId],['preview',$otherNewsId],['delete',$newsId]] as $route) {
        foreach (['GET','POST'] as $method) {
            $_SERVER['REQUEST_METHOD']=$method; $_POST=['csrf_token'=>'fixture']; http_response_code(200);
            $newsTpl=new Portix\View\Template($root.'/var/admin/templates/news-form.tpl');
            $newsController->handle($newsTpl,array_merge(['Admin','news'],$route),$user);
            permissionAssert(http_response_code()===403,'news forbidden '.$method.' '.implode('/',$route));
        }
    }
    $_SESSION=['csrf_token'=>'fixture']; $_SERVER['REQUEST_METHOD']='POST';
    $_POST=['csrf_token'=>'fixture','title'=>'Forged news','status'=>'published','is_pinned'=>'1','intro'=>'','content'=>''];
    $newsTpl=new Portix\View\Template($root.'/var/admin/templates/news-form.tpl');
    $newsController->handle($newsTpl,['Admin','news','edit',(string)$newsId],$user);
    permissionAssert($newsRepo->find($newsId)['status']==='draft'&&$newsRepo->find($newsId)['title']==='Own news','news forged publication has no effect');
    $_SERVER['REQUEST_METHOD']='GET'; $_POST=[];
    $newsTpl=new Portix\View\Template($root.'/var/admin/templates/news-list.tpl');
    $newsController->handle($newsTpl,['Admin','news'],$user);
    permissionAssert(count($newsTpl->rows('newsrow'))===1&&$newsTpl->rows('newsrow')[0]['title']==='Own news','news list hides unauthorized drafts');
    foreach ([['edit',$otherId],['preview',$otherId],['delete',$id],['category','new'],['order']] as $route) {
        $_SERVER['REQUEST_METHOD']='GET'; $_POST=[]; http_response_code(200);
        $tpl=new Portix\View\Template($root.'/var/modules/articles/templates/admin-form.tpl');
        $controller->handle($tpl,array_merge(['Admin','articles'],$route),$user);
        permissionAssert(http_response_code()===403,'route denied '.implode('/',$route));
    }
    $_SESSION=['csrf_token'=>'fixture'];$_SERVER['REQUEST_METHOD']='POST';
    foreach ([['status'=>'published','category_id'=>$b],['status'=>'draft','category_id'=>$outside]] as $changes) {
        $_POST=array_replace(['csrf_token'=>'fixture','title'=>'Modified','slug'=>'modified','content'=>'<p>Changed</p>'], $changes);
        $tpl=new Portix\View\Template($root.'/var/modules/articles/templates/admin-form.tpl');
        $tpl->set('csrf_token','fixture'); foreach ($_LANG as $key=>$value) { $tpl->set($key,$value); }
        $controller->handle($tpl,['Admin','articles','edit',(string)$id],$user);
        permissionAssert($articles->find($id)['title']==='Own','forged POST does not mutate');
        permissionAssert(str_contains($tpl->render(),'Accès refusé'),'error displayed for forbidden POST');
    }
    $_POST=[];$_SERVER['REQUEST_METHOD']='GET';
    $tpl=new Portix\View\Template($root.'/var/modules/articles/templates/admin-form.tpl');$tpl->set('csrf_token','fixture'); foreach ($_LANG as $key=>$value) { $tpl->set($key,$value); }
    $controller->handle($tpl,['Admin','articles'],$user);$html=json_encode($tpl->rows('articlerow')); 
    permissionAssert(str_contains($html,'Own')&&!str_contains($html,'Other'),'list filters other authors');
    permissionAssert(str_contains($html,'"next_status":"pending"'), 'draft offers review request, not direct publication');
    permissionAssert(!str_contains($html,'"next_status":"published"'), 'untrusted writer cannot directly publish');
    $tpl=new Portix\View\Template($root.'/var/modules/articles/templates/admin-form.tpl');$tpl->set('csrf_token','fixture'); foreach ($_LANG as $key=>$value) { $tpl->set($key,$value); }
    $controller->handle($tpl,['Admin','articles','edit',(string)$id],$user);$html=$tpl->render();
    permissionAssert(!str_contains($html,'>Outside</option>')&&!str_contains($html,'value="published"'),'form filters category and publish');
    // Migration from the exact previous schema preserves user/content data.
    $pdo->exec('ALTER TABLE users DROP COLUMN article_permissions');
    $pdo->exec("UPDATE schema_migrations SET version=7 WHERE version=9");$pdo->exec('PRAGMA user_version=7');
    (new Portix\Infrastructure\Migrator($db))->migrate();
    permissionAssert($repo->find('writer')->articlePermissions()===P::DEFAULTS,'old users inherit defaults');
    permissionAssert($articles->find($id)['title']==='Own','upgrade preserves content');
    // The leaf root has articles but no child category: header must omit its dropdown, sidebar keeps articles.
    $leaf=$articles->createCategory('Leaf','leaf',null,true,'');
    $articles->createArticle($leaf,'Public','public','text','published','admin',$now,$now,'','','');
    $tpl=new Portix\View\Template($root.'/var/templates/six-cordes/index.tpl');
    (new PortixModule\Articles\ArticleNavigation($articles,$translator))->append($tpl,['articles','categorie','leaf']);
    $rows=$tpl->rows('navigation');
    $leafRows=array_values(array_filter($rows, static fn(array $row): bool => $row['name']==='Leaf'));
    permissionAssert(count($leafRows)===1 && $leafRows[0]['has_header_submenu']==='0' && $leafRows[0]['has_submenu']==='1','leaf has only sidebar submenu');
    echo "Permissions: defaults, overrides, recursive scope, ownership, forged routes/POST, persistence, migration and leaf menu OK\n";
}
try { permissionRun(); } catch (Throwable $error) { fwrite(STDERR,'Permissions : ÉCHEC - '.$error->getMessage()."\n".$error->getTraceAsString()."\n");exit(1); }
