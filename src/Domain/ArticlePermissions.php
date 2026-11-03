<?php
declare(strict_types=1);
namespace Portix\Domain;
final class ArticlePermissions
{
    public const DEFAULTS = ['create' => true, 'edit_own' => true, 'edit_others' => false,
        'publish' => false, 'edit_published' => false, 'delete_own' => false,
        'all_categories' => true, 'include_children' => false, 'categories' => []];
    public static function decode(string $json): array
    {
        $data = json_decode($json, true);
        if (!is_array($data)) { return array_replace(self::DEFAULTS, ['create'=>false,'edit_own'=>false,'all_categories'=>false]); }
        $result = self::DEFAULTS;
        foreach (self::DEFAULTS as $key => $value) {
            if ($key === 'categories') {
                if (isset($data[$key]) && is_array($data[$key])) {
                    $result[$key] = array_values(array_unique(array_filter($data[$key], static fn ($id): bool => is_int($id) && $id > 0)));
                }
            } elseif (array_key_exists($key, $data)) { $result[$key] = $data[$key] === true; }
        }
        return $result;
    }
    public static function category(array $permissions, ?int $id, array $categories): bool
    {
        if ($permissions['all_categories']) { return true; }
        $seen = [];
        $parents = [];
        foreach ($categories as $row) { $parents[(int)$row['id']] = $row['parent_id'] === null ? null : (int)$row['parent_id']; }
        while ($id !== null && !isset($seen[$id])) {
            if (!array_key_exists($id, $parents)) { return false; }
            if (in_array($id, $permissions['categories'], true)) { return true; }
            if (!$permissions['include_children']) { return false; }
            $seen[$id] = true; $id = $parents[$id];
        }
        return false;
    }
    public static function allows(UserAccount $user, string $action, ?array $article, ?int $target, array $categories): bool
    {
        if ($user->isAdmin()) { return true; }
        if (!$user->isRedactor()) { return false; }
        $p = $user->articlePermissions();
        if (!self::category($p, $target, $categories)) { return false; }
        if ($article !== null) {
            $source = $article['category_id'] === null ? null : (int)$article['category_id'];
            if (!self::category($p, $source, $categories)) { return false; }
            $own = ($article['created_by'] ?? '') === $user->pseudo();
            if (!$p[$own ? 'edit_own' : 'edit_others']) { return false; }
            if (($article['status'] ?? '') === 'published' && !$p['edit_published']) { return false; }
        } elseif (!$p['create']) { return false; }
        if ($action === 'publish') { return $p['publish']; }
        if ($action === 'delete') { return $article !== null && ($article['created_by'] ?? '') === $user->pseudo() && $p['delete_own']; }
        return $action === 'edit';
    }
}
