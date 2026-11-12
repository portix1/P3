<?php
declare(strict_types=1);
if ((require __DIR__.'/boot.php') === false) { return; }
use PortixModule\Home\Renderer;

if (!$user->isAdmin()) { http_response_code(403); $template->setSlot('main','./var/admin/templates/access-denied.tpl'); return; }
$homeView=(string)($argument[2]??'');
if (!in_array($homeView,['','settings','report'],true)) { http_response_code(404); $template->setSlot('main','./var/admin/templates/not-found.tpl'); return; }
$e=Renderer::e(...); $t=static fn(string $key):string=>$translator->text($key);
$message='';
if ($request->method()==='POST') {
    if (!\PortixSecurity::csrfIsValid()) { http_response_code(403); $template->setSlot('main','./var/admin/templates/access-denied.tpl'); return; }
    try {
        if ($homeView==='settings') {
            $data=\PortixModule\Home\Settings::normalize($_POST);
            $data['secret']=strlen($homeSettings['secret'])>=32?$homeSettings['secret']:bin2hex(random_bytes(32));
            \PortixSettings::write('home',$data,$homeRoot); $homeSettings=$data; $homeRepository->clearCache();
        } elseif ($homeView==='') {
            $action=(string)($_POST['home_action']??'');
            $id=max(0,(int)($_POST['id']??0));
            if ($action==='delete') {
                if (($_POST['confirm_delete']??'')!=='1' || $id<1) { throw new InvalidArgumentException('home_invalid'); }
                $q=$database->connection()->prepare('DELETE FROM home_sections WHERE id=?'); $q->execute([$id]); $homeRepository->clearCache();
            } elseif ($action==='save') {
                $data=$_POST;
                if (isset($_FILES['home_image']) && (int)$_FILES['home_image']['error']!==UPLOAD_ERR_NO_FILE) {
                    if (!\Portix\Media\MediaBridge::active()) { throw new InvalidArgumentException('home_invalid_image'); }
                    if (!in_array(strtolower(pathinfo((string)$_FILES['home_image']['name'],PATHINFO_EXTENSION)),['jpg','jpeg','png','webp'],true)) { throw new InvalidArgumentException('home_invalid_image'); }
                    require_once $homeRoot.'/var/modules/media/src/MediaManager.php';
                    $media=(new \Portix\Media\MediaManager($database->connection(),$homeRoot))->upload($_FILES['home_image'],$user->pseudo());
                    $data['image_path']=$media['public_path'];
                }
                $homeRepository->save($data,$id);
            } else { throw new InvalidArgumentException('home_invalid'); }
        } else { throw new InvalidArgumentException('home_invalid'); }
        $message=$t('home_saved');
    } catch (InvalidArgumentException $error) { http_response_code(422); $message=$translator->text($error->getMessage(),$t('home_error'));
    } catch (Throwable $error) { error_log('Home admin: '.$error->getMessage()); http_response_code(422); $message=$t('home_error'); }
}
$title=$t($homeView==='settings'?'home_settings':($homeView==='report'?'home_report':'home_admin'));
$html='<header class="page-heading"><h1 data-admin-icon="house">'.$e($title).'</h1><p>'.$e($t('home_help')).'</p></header>';
$html.='<p role="status">'.$e($message).'</p><p><a href="home.html" target="_blank" rel="noopener">'.$e($t('home_preview')).'</a></p>';
$csrf='<input type="hidden" name="csrf_token" value="'.$e(\PortixSecurity::csrfToken()).'">';
$input=static function(string $label,string $name,string $value,string $type='text',string $extra='')use($e,$t):string {
    return '<label>'.$e($t($label)).'<input type="'.$type.'" name="'.$name.'" value="'.$e($value).'" '.$extra.'></label>';
};
$check=static fn(string $label,string $name,bool $value):string=>'<label><input type="checkbox" name="'.$name.'" value="1"'.($value?' checked':'').'> '.$e($t($label)).'</label>';
$select=static function(string $label,string $name,array $options,string $value)use($e,$t):string {
    $s='<label>'.$e($t($label)).'<select name="'.$name.'">';
    foreach ($options as $key=>$text) { $s.='<option value="'.$e((string)$key).'"'.((string)$key===$value?' selected':'').'>'.$e($text).'</option>'; }
    return $s.'</select></label>';
};
if ($homeView==='settings') {
    $html.='<p>'.$e($t('home_settings_help')).'</p><p>'.$e($t('home_install_help')).'</p><form method="post" action="Admin-home-settings.html">'.$csrf.'<div class="form-grid">';
    $html.=$input('home_days','days',(string)$homeSettings['days'],'number','min="7" max="90" required');
    $html.=$input('home_exploration','exploration',(string)$homeSettings['exploration'],'number','min="20" max="100" required');
    $html.=$input('home_refresh','refresh_minutes',(string)$homeSettings['refresh_minutes'],'number','min="5" max="1440" required');
    $html.=$check('home_tracking','tracking',(bool)$homeSettings['tracking']).'</div>';
    $html.='<button type="submit" title="'.$e($t('home_save')).'">'.$e($t('home_save')).'</button></form>';
} elseif ($homeView==='report') {
    $rows=(new \PortixModule\Home\Metrics($database->connection(),$homeSettings['secret']))->report();
    $html.='<p>'.$e($t('home_report_help')).'</p>';
    $titles=[];
    if ($homeRepository->installed('articles')) { $titles=$database->connection()->query('SELECT id,title FROM articles')->fetchAll(PDO::FETCH_KEY_PAIR); }
    $sections=array_column($homeRepository->sections(true),'title','id');
    foreach ($rows as $row) { if ((int)$row['section_id']===0) {
        $html.='<p>'.$e($t('home_views')).' : '.(int)$row['visible'].' · '.$e($t('home_converted')).' : '.(int)$row['clicks'].'</p>';
    } }
    $html.='<div class="home-report"><table><thead><tr>';
    foreach (['home_slot','home_visible','home_clicks','home_rate'] as $label) { $html.='<th scope="col">'.$e($t($label)).'</th>'; }
    $html.='</tr></thead><tbody>';
    foreach ($rows as $row) { if ((int)$row['section_id']===0) { continue; }
        $label=($sections[$row['section_id']]??'#'.$row['section_id']).' / '.($titles[$row['article_id']]??'#'.$row['article_id']).' / '.$row['position'];
        $html.='<tr><td>'.$e($label).'</td><td>'.(int)$row['visible'].'</td><td>'.(int)$row['clicks'].'</td><td>'.($row['visible']>0?round(100*$row['clicks']/$row['visible'],1).' %':'—').'</td></tr>';
    }
    $html.='</tbody></table></div>';
    if ($rows===[]) { $html.='<p>'.$e($t('home_no_metrics')).'</p>'; }
} else {
    $sections=$homeRepository->sections(true);
    $sections[]=['id'=>0,'title'=>'','kind'=>'articles','layout'=>'feature','category_id'=>0,'image_path'=>'','image_alt'=>'','body'=>'','item_count'=>2,'descendants'=>1,'weighted'=>1,'position'=>count($sections)*10,'enabled'=>1];
    $cats=['0'=>'—']; foreach ($homeRepository->categories() as $cat) { $cats[(string)$cat['id']]=$cat['name']; }
    $images=[''=>$t('home_no_image')];
    if ($homeRepository->installed('media_items')) { foreach ($database->connection()->query("SELECT public_path,original_name FROM media_items WHERE family='image' ORDER BY id DESC") as $image) {
        if (\PortixModule\Home\Image::path($image['public_path'])!=='') { $images[$image['public_path']]=$image['original_name']; }
    } }
    $html.='<p>'.$e($t('home_install_help')).'</p>';
    foreach ($sections as $section) {
        $id=(int)$section['id'];
        if ($section['image_path']!=='' && !isset($images[$section['image_path']])) { $images[$section['image_path']]=$section['image_path']; }
        $html.='<details'.($id===0?' open':'').'><summary>'.$e($id===0?$t('home_new'):($section['title']?:'#'.$id)).'</summary><form method="post" action="Admin-home.html" enctype="multipart/form-data">'.$csrf.'<input type="hidden" name="id" value="'.$id.'"><div class="form-grid">';
        $html.=$input('home_title_field','title',$section['title'],'text','maxlength="160"');
        $html.=$select('home_kind','kind',['articles'=>$t('home_articles'),'news'=>$t('home_news'),'affiliation'=>$t('home_affiliation'),'text'=>$t('home_text')],$section['kind']);
        $html.=$select('home_category','category_id',$cats,(string)$section['category_id']);
        $html.=$select('home_layout','layout',['feature'=>$t('home_feature'),'category'=>$t('home_category_layout'),'cards'=>$t('home_cards'),'text'=>$t('home_text_layout')],$section['layout']);
        $html.=$input('home_count','item_count',(string)$section['item_count'],'number','min="1" max="6" required');
        $html.=$input('home_order','position',(string)$section['position'],'number','min="0" max="10000" required');
        $html.=$select('home_image','image_path',$images,$section['image_path']);
        if (\Portix\Media\MediaBridge::active()) { $html.='<label>'.$e($t('home_upload')).'<input type="file" name="home_image" accept="image/jpeg,image/png,image/webp"></label>'; }
        $html.=$input('home_alt','image_alt',$section['image_alt'],'text','maxlength="300"');
        $html.='<p>'.$e($t('home_image_help')).'</p><label class="form-grid-wide">'.$e($t('home_body')).'<textarea name="body" maxlength="10000" rows="4">'.$e($section['body']).'</textarea></label>';
        foreach (['enabled'=>'home_enabled','descendants'=>'home_descendants','weighted'=>'home_weighted'] as $key=>$label) { $html.=$check($label,$key,(bool)$section[$key]); }
        $html.='</div><button name="home_action" value="save" title="'.$e($t('home_save')).'">'.$e($t('home_save')).'</button>';
        if ($id>0) { $html.='<p>'.$check('home_delete_confirm','confirm_delete',false).'<button name="home_action" value="delete" title="'.$e($t('home_delete')).'">'.$e($t('home_delete')).'</button></p>'; }
        $html.='</form></details>';
    }
}
$template->set('home_admin_html',\Portix\View\Html::trusted($html));
$template->setSlot('main',__DIR__.'/templates/admin.tpl');
