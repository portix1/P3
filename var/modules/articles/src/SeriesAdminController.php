<?php
declare(strict_types=1);
namespace PortixModule\Articles;
use Portix\View\Template;
use Portix\Support\Translator;
use RuntimeException;

final class SeriesAdminController
{
    public function __construct(private readonly ArticleRepository $articles,private readonly Translator $translator) {}
    public function handle(Template $template,array $arguments): void
    {
        $repository=$this->articles->editorial();
        $action=(string)($arguments[3]??'list');$id=null;$message='';$selected=[];
        if(!in_array($action,['list','new','edit','delete'],true))throw new RuntimeException('articles_invalid_id');
        if(in_array($action,['edit','delete'],true)){
            if(!ctype_digit((string)($arguments[4]??''))||(int)$arguments[4]<1)throw new RuntimeException('articles_invalid_id');
            $id=(int)$arguments[4];
            if($repository->findSeries($id)===null)throw new RuntimeException('articles_series_missing');
        }
        if($action==='delete'){
            \PortixSecurity::requirePostWithCsrf();$repository->deleteSeries($id);header('Location: Admin-articles-series.html');exit;
        }
        if($action==='new'||$action==='edit'){
            $draft=$id===null?['title'=>'','description'=>'','show_title'=>1]:$repository->findSeries($id);
            if($id!==null)foreach($repository->parts($id) as $part)$selected[(int)$part['id']]=(int)$part['position'];
            if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){
                try{
                    \PortixSecurity::requirePostWithCsrf();
                    $draft=['title'=>(string)($_POST['title']??''),'description'=>(string)($_POST['description']??''),'show_title'=>isset($_POST['show_title'])?1:0];
                    $raw=$_POST['parts']??[];if(!is_array($raw))throw new RuntimeException('articles_invalid_order');
                    $positions=$_POST['positions']??[];if(!is_array($positions))throw new RuntimeException('articles_invalid_order');
                    $ids=[];$selected=[];
                    foreach($raw as $value){if(!is_scalar($value)||!ctype_digit((string)$value)||(int)$value<1)throw new RuntimeException('articles_invalid_id');$ids[]=(int)$value;$position=$positions[(int)$value]??0;if(!is_scalar($position)||!ctype_digit((string)$position))throw new RuntimeException('articles_invalid_order');$selected[(int)$value]=(int)$position;}
                    usort($ids,static fn(int $a,int $b): int=>$selected[$a]<=>$selected[$b]);
                    $repository->saveSeries($id,$draft['title'],$draft['description'],(bool)$draft['show_title'],$ids);
                    header('Location: Admin-articles-series.html');exit;
                }catch(RuntimeException $e){$message=$this->translator->exception($e);}
            }
            $template->setSlot('main','./var/modules/articles/templates/admin-series-form.tpl');
            foreach(['title','description'] as $field)$template->set('series_'.$field.'_value',(string)$draft[$field]);
            $template->set('series_show_title_checked',$draft['show_title']?'checked':'');
            $template->set('series_action',$id===null?'Admin-articles-series-new.html':'Admin-articles-series-edit-'.$id.'.html');
            $rows=$this->articles->allForOrganization();
            usort($rows,static fn(array $a,array $b): int=>[isset($selected[(int)$a['id']])?0:1,$selected[(int)$a['id']]??PHP_INT_MAX,$a['title']]<=>[isset($selected[(int)$b['id']])?0:1,$selected[(int)$b['id']]??PHP_INT_MAX,$b['title']]);
            foreach($rows as $i=>$a)$template->addRow('seriesarticle',['id'=>(string)$a['id'],'title'=>(string)$a['title'],'category_id'=>(string)($a['category_id'] ?? ''), 'category'=>(string)($a['category_name'] ?: $this->translator->text('articles_uncategorized')), 'status'=>$this->translator->text('articles_status_'.$a['status']),'checked'=>isset($selected[(int)$a['id']])?'checked':'','position'=>(string)$i]);
        }else{
            $template->setSlot('main','./var/modules/articles/templates/admin-series.tpl');
            foreach($repository->series() as $s)$template->addRow('seriesrow',['id'=>(string)$s['id'],'title'=>(string)$s['title']]);
        }
        $template->set('articles_message',$message);
    }
}
