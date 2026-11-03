<?php
declare(strict_types=1);
try {
    $repositories->contentSafety()->purgeTrashIfDue();
} catch (Throwable $error) {
    error_log((string) $error);
}
