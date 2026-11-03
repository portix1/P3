<?php
declare(strict_types=1);

use Portix\Maintenance\UpdateSafety;
use Portix\Observability\EventLogger;

PortixSecurity::requireRole($user, 'admin');
$safety=$repositories->contentSafety();
$view=(string)($argument[2] ?? 'trash');
$message='';
$logger=new EventLogger($database->connection());
if (isset($_POST['safety_action'])) {
    try {
        PortixSecurity::requirePostWithCsrf();
        $action=(string)$_POST['safety_action'];
        if ($action==='settings') {
            $days=(int)($_POST['trash_days'] ?? 30); $limit=(int)($_POST['revision_limit'] ?? 20);
            if ($days<1 || $days>90 || $limit<1 || $limit>100) throw new RuntimeException('safety_invalid_settings');
            $current=$safety->settings();
            $safety->saveSettings(['trash_retention_days'=>$days,'trash_never_auto'=>isset($_POST['trash_never']),'revision_limit'=>$limit,'last_trash_purge_day'=>$current['last_trash_purge_day']]);
            $message=$translator->text('safety_settings_saved');
        } elseif ($action==='restore_trash') {
            $safety->restoreTrash(max(1,(int)($_POST['id'] ?? 0))); $message=$translator->text('safety_restored');
        } elseif ($action==='delete_trash') {
            $safety->deleteTrashPermanently(max(1,(int)($_POST['id'] ?? 0))); $message=$translator->text('safety_deleted');
        } elseif ($action==='restore_revision') {
            $safety->restoreRevision(max(1,(int)($_POST['id'] ?? 0)),$user->pseudo()); $message=$translator->text('safety_revision_restored');
        } elseif ($action==='create_point') {
            UpdateSafety::createRollbackPoint(dirname(__DIR__,3)); $message=$translator->text('safety_point_created');
        } elseif ($action==='restore_point') {
            UpdateSafety::restoreRollbackPoint(dirname(__DIR__,3),(string)($_POST['name'] ?? '')); $message=$translator->text('safety_point_restored');
        }
        $logger->audit($user->pseudo(),'safety.'.$action,'safety',[],$requestId);
    } catch (Throwable $error) { $message=$translator->exception($error); }
}
foreach(['trash','revisions','rollback','settings'] as $tab) $template->set('safety_'.$tab.'_current',$view===$tab || ($tab==='trash'&&$view==='') ? 'is-current':'');
$template->set('safety_message',$message);
$typeLabel=static fn(string $type):string=>$translator->text('safety_'.$type,$type);
if ($view==='revisions') {
    $template->setSlot('main','var/modules/safety/templates/revisions.tpl'); $rows=$safety->revisions(); $template->set('safety_has_revisions',$rows ? '1':'0');
    foreach($rows as $r) $template->addRow('revisionrow',['id'=>(string)$r['id'],'type'=>$typeLabel((string)$r['content_type']),'title'=>(string)$r['title'],'date'=>(string)$r['created_at'],'actor'=>(string)$r['created_by']]); return;
}
if ($view==='settings') {
    $set=$safety->settings(); $template->setSlot('main','var/modules/safety/templates/settings.tpl'); $template->set('safety_trash_days_value',(string)$set['trash_retention_days']); $template->set('safety_trash_never_checked',$set['trash_never_auto']?'checked':''); $template->set('safety_revision_limit_value',(string)$set['revision_limit']); return;
}
if ($view==='rollback') {
    $template->setSlot('main','var/modules/safety/templates/rollback.tpl'); $rows=UpdateSafety::listRollbackPoints(dirname(__DIR__,3)); $template->set('safety_has_points',$rows?'1':'0'); foreach($rows as $r) $template->addRow('rollbackrow',['name'=>$r['name'],'version'=>$r['version'],'date'=>$r['date']]); return;
}
$template->setSlot('main','var/modules/safety/templates/trash.tpl'); $rows=$safety->trash(); $template->set('safety_has_trash',$rows?'1':'0'); foreach($rows as $r) $template->addRow('trashrow',['id'=>(string)$r['id'],'type'=>$typeLabel((string)$r['content_type']),'title'=>(string)$r['title'],'date'=>(string)$r['deleted_at'],'actor'=>(string)$r['deleted_by']]);
