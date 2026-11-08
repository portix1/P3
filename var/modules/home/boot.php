<?php
declare(strict_types=1);
foreach (['Image','Settings','HomeRepository','Metrics','Renderer'] as $homeClass) { require_once __DIR__.'/src/'.$homeClass.'.php'; }
$homeRoot=dirname(__DIR__,3);
$homeRepository=new \PortixModule\Home\HomeRepository($database->connection());
if (!$homeRepository->installed('home_sections')) { (new \Portix\Module\ModuleSchemaInstaller($database))->install('home',__DIR__.'/database'); }
$homeSettings=\PortixModule\Home\Settings::read($homeRoot);
