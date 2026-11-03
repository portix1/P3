<?php
declare(strict_types=1);
$source=(string)file_get_contents(dirname(__DIR__).'/src/Maintenance/UpdateSafety.php');
foreach(['createRollbackPoint','restoreRollbackPoint','listRollbackPoints','application'] as $marker){ if(!str_contains($source,$marker)) throw new RuntimeException('rollback marker: '.$marker); }
echo "Rollback : OK\n";
