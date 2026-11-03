<?php
declare(strict_types=1);

require_once './var/modules/articles/src/ArticleRepository.php';
require_once './var/modules/articles/src/ArticleNavigation.php';

$articleNavigationRepository = new \PortixModule\Articles\ArticleRepository($database->connection());
(new \PortixModule\Articles\ArticleNavigation(
    $articleNavigationRepository,
    $translator
))->append($template, $argument);
