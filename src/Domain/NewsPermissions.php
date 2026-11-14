<?php
declare(strict_types=1);

namespace Portix\Domain;

/** News has no categories or pending-review state. Reuse editorial account rights. */
final class NewsPermissions
{
    public static function allows(UserAccount $user, string $action, ?array $news = null): bool
    {
        if (!in_array($action, ['edit', 'publish', 'delete', 'pin'], true)) { return false; }
        if ($user->isAdmin()) { return true; }
        if (!$user->isRedactor() || in_array($action, ['delete', 'pin'], true)) { return false; }
        $p = $user->articlePermissions();
        if ($news === null) {
            if (!$p['create']) { return false; }
        } else {
            $own = ($news['created_by'] ?? '') === $user->pseudo();
            if (!$p[$own ? 'edit_own' : 'edit_others']) { return false; }
            if (($news['status'] ?? '') === 'published' && !$p['edit_published']) { return false; }
        }
        return $action === 'edit' || $p['publish'];
    }
}
