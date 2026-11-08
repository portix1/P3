<?php
declare(strict_types=1);
$homeRoot=dirname(__DIR__,3);
if (!defined(\Portix\Module\ModuleManifest::class.'::EXTENSION_CONTRACT')
    || version_compare(trim((string)file_get_contents($homeRoot.'/VERSION')),'0.8.4-rc4','<')) {
    http_response_code(503);
    $pageMetadata->noIndex();
    $message=($CONFIG['lang']??'fr')==='en'
        ? 'This module requires P3 0.8.4-rc4 or later. Update the module extension core first.'
        : 'Ce module nécessite P3 0.8.4-rc4 ou plus récent. Installez d’abord le socle compatible.';
    $template->set('home_admin_html',\Portix\View\Html::trusted('<p>'.htmlspecialchars($message,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8').'</p>'));
    $template->setSlot('main',__DIR__.'/templates/admin.tpl');
    return false;
}
foreach (['Image','Settings','HomeRepository','Metrics','Renderer'] as $homeClass) { require_once __DIR__.'/src/'.$homeClass.'.php'; }
$homeRoot=dirname(__DIR__,3);
$homeRepository=new \PortixModule\Home\HomeRepository($database->connection());
if (!$homeRepository->installed('home_sections') || !$homeRepository->installed('analytics_home_metrics')) { (new \Portix\Module\ModuleSchemaInstaller($database))->install('home',__DIR__.'/database'); }
// Move only tables owned by the first development version of this module.
$homePdo=$database->connection();
$database->transaction(static function () use ($homePdo): void {
    foreach (['home_metrics'=>'analytics_home_metrics','home_events'=>'analytics_home_events','home_cache'=>'analytics_home_cache'] as $old=>$new) {
        $q=$homePdo->prepare("SELECT 1 FROM module_schema_tables WHERE table_name=? AND module_prefix='home'");
        $q->execute([$old]);
        $owned=(bool)$q->fetchColumn();$q->closeCursor();
        if (!$owned) { continue; }
        $exists=$homePdo->prepare("SELECT 1 FROM sqlite_master WHERE type='table' AND name=?");$exists->execute([$old]);
        $present=(bool)$exists->fetchColumn();$exists->closeCursor();
        if (!$present) { continue; }
        // A conflict aborts the transaction instead of discarding historical measurements.
        if ($old!=='home_cache') { $homePdo->exec('INSERT INTO '.$new.' SELECT * FROM '.$old); }
        $homePdo->exec('DROP TABLE '.$old);
        $homePdo->prepare("DELETE FROM module_schema_tables WHERE table_name=? AND module_prefix='home'")->execute([$old]);
    }
});
$homeSettings=\PortixModule\Home\Settings::read($homeRoot);
