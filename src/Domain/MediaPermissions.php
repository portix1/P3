<?php
declare(strict_types=1);

namespace Portix\Domain;

final class MediaPermissions
{
    public static function requiresAdmin(string $operation): bool
    {
        // Reference scans expose unpublished content and internal file paths.
        return !in_array($operation, ['list', 'upload', 'set_accessibility'], true);
    }
}
