<?php
declare(strict_types=1);

namespace Portix\Repository;

use PDO;
use RuntimeException;

final class ContentSafetyRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /** @return array{trash_retention_days:int,trash_never_auto:bool,revision_limit:int,last_trash_purge_day:string} */
    public function settings(): array
    {
        $raw = \PortixSettings::read('content_safety', [
            'trash_retention_days' => 30,
            'trash_never_auto' => false,
            'revision_limit' => 20,
            'last_trash_purge_day' => '',
        ]);
        return [
            'trash_retention_days' => max(1, min(90, (int) ($raw['trash_retention_days'] ?? 30))),
            'trash_never_auto' => !empty($raw['trash_never_auto']),
            'revision_limit' => max(1, min(100, (int) ($raw['revision_limit'] ?? 20))),
            'last_trash_purge_day' => (string) ($raw['last_trash_purge_day'] ?? ''),
        ];
    }

    /** @param array<string,mixed> $settings */
    public function saveSettings(array $settings): void
    {
        \PortixSettings::write('content_safety', [
            'trash_retention_days' => max(1, min(90, (int) ($settings['trash_retention_days'] ?? 30))),
            'trash_never_auto' => !empty($settings['trash_never_auto']),
            'revision_limit' => max(1, min(100, (int) ($settings['revision_limit'] ?? 20))),
            'last_trash_purge_day' => (string) ($settings['last_trash_purge_day'] ?? ''),
        ]);
    }

    /** @param array<string,mixed> $snapshot */
    public function captureRevision(string $type, int $contentId, array $snapshot, string $actor): void
    {
        $this->assertRevisionType($type);
        $json = $this->encode($snapshot);
        $statement = $this->pdo->prepare(
            'INSERT INTO content_revisions (content_type, content_id, title, snapshot_json, created_by, created_at) '
            . 'VALUES (:type, :content_id, :title, :snapshot, :actor, :created_at)'
        );
        $statement->execute([
            'type' => $type,
            'content_id' => $contentId,
            'title' => (string) ($snapshot['title'] ?? ''),
            'snapshot' => $json,
            'actor' => $actor,
            'created_at' => gmdate('Y-m-d H:i:s'),
        ]);
        $this->pruneRevisions($type, $contentId, $this->settings()['revision_limit']);
    }

    /** @param array<string,mixed> $snapshot */
    public function moveToTrash(string $type, int $originalId, string $title, array $snapshot, string $actor): void
    {
        $this->assertTrashType($type);
        $statement = $this->pdo->prepare(
            'INSERT INTO content_trash (content_type, original_id, title, snapshot_json, deleted_by, deleted_at) '
            . 'VALUES (:type, :original_id, :title, :snapshot, :actor, :deleted_at)'
        );
        $statement->execute([
            'type' => $type,
            'original_id' => $originalId,
            'title' => $title,
            'snapshot' => $this->encode($snapshot),
            'actor' => $actor,
            'deleted_at' => gmdate('Y-m-d H:i:s'),
        ]);
    }

    /** @return list<array<string,mixed>> */
    public function trash(): array
    {
        return $this->pdo->query('SELECT * FROM content_trash ORDER BY id DESC')->fetchAll();
    }

    /** @return list<array<string,mixed>> */
    public function revisions(int $limit = 200): array
    {
        $statement = $this->pdo->prepare(
            'SELECT * FROM content_revisions ORDER BY id DESC LIMIT :limit'
        );
        $statement->bindValue(':limit', max(1, min(1000, $limit)), PDO::PARAM_INT);
        $statement->execute();
        return $statement->fetchAll();
    }

    public function purgeTrashIfDue(): int
    {
        $settings = $this->settings();
        $today = gmdate('Y-m-d');
        if ($settings['trash_never_auto'] || $settings['last_trash_purge_day'] === $today) {
            return 0;
        }
        $deleted = $this->purgeTrash($settings['trash_retention_days']);
        $settings['last_trash_purge_day'] = $today;
        $this->saveSettings($settings);
        return $deleted;
    }

    public function purgeTrash(int $days): int
    {
        $cutoff = gmdate('Y-m-d H:i:s', strtotime('-' . max(1, min(90, $days)) . ' days'));
        $statement = $this->pdo->prepare('DELETE FROM content_trash WHERE deleted_at < :cutoff');
        $statement->execute(['cutoff' => $cutoff]);
        return $statement->rowCount();
    }

    public function deleteTrashPermanently(int $trashId): void
    {
        $statement = $this->pdo->prepare('DELETE FROM content_trash WHERE id = :id');
        $statement->execute(['id' => $trashId]);
    }

    public function restoreTrash(int $trashId): void
    {
        $statement = $this->pdo->prepare('SELECT * FROM content_trash WHERE id = :id');
        $statement->execute(['id' => $trashId]);
        $row = $statement->fetch();
        if (!is_array($row)) {
            throw new RuntimeException('safety_trash_not_found');
        }
        $snapshot = $this->decode((string) $row['snapshot_json']);
        $type = (string) $row['content_type'];
        $this->pdo->beginTransaction();
        try {
            if ($type === 'article') {
                $this->restoreArticle($snapshot);
            } elseif ($type === 'news') {
                $this->restoreNews($snapshot);
            } elseif ($type === 'article_category') {
                $this->restoreCategory($snapshot);
            } else {
                throw new RuntimeException('safety_invalid_type');
            }
            $delete = $this->pdo->prepare('DELETE FROM content_trash WHERE id = :id');
            $delete->execute(['id' => $trashId]);
            $this->pdo->commit();
        } catch (\Throwable $error) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $error;
        }
    }

    public function restoreRevision(int $revisionId, string $actor): void
    {
        $statement = $this->pdo->prepare('SELECT * FROM content_revisions WHERE id = :id');
        $statement->execute(['id' => $revisionId]);
        $revision = $statement->fetch();
        if (!is_array($revision)) {
            throw new RuntimeException('safety_revision_not_found');
        }
        $type = (string) $revision['content_type'];
        $contentId = (int) $revision['content_id'];
        $snapshot = $this->decode((string) $revision['snapshot_json']);
        $current = $this->currentSnapshot($type, $contentId);
        if ($current === null) {
            throw new RuntimeException('safety_content_not_found');
        }
        $this->pdo->beginTransaction();
        try {
            $this->captureRevision($type, $contentId, $current, $actor);
            if ($type === 'article') {
                $this->updateArticleFromSnapshot($snapshot);
            } else {
                $this->updateNewsFromSnapshot($snapshot);
            }
            $this->pdo->commit();
        } catch (\Throwable $error) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $error;
        }
    }

    /** @return array<string,mixed>|null */
    public function currentSnapshot(string $type, int $id): ?array
    {
        $this->assertRevisionType($type);
        $table = $type === 'article' ? 'articles' : 'news';
        $statement = $this->pdo->prepare('SELECT * FROM ' . $table . ' WHERE id = :id');
        $statement->execute(['id' => $id]);
        $row = $statement->fetch();
        return is_array($row) ? $row : null;
    }

    /** @return array<string,mixed> */
    public function categorySnapshot(int $id): array
    {
        $statement = $this->pdo->prepare('SELECT * FROM article_categories WHERE id = :id');
        $statement->execute(['id' => $id]);
        $category = $statement->fetch();
        if (!is_array($category)) {
            throw new RuntimeException('articles_category_not_found');
        }
        $articles = $this->pdo->prepare('SELECT id FROM articles WHERE category_id = :id ORDER BY id');
        $articles->execute(['id' => $id]);
        $children = $this->pdo->prepare('SELECT id FROM article_categories WHERE parent_id = :id ORDER BY id');
        $children->execute(['id' => $id]);
        $category['_article_ids'] = array_map('intval', array_column($articles->fetchAll(), 'id'));
        $category['_child_ids'] = array_map('intval', array_column($children->fetchAll(), 'id'));
        return $category;
    }

    private function pruneRevisions(string $type, int $contentId, int $limit): void
    {
        $statement = $this->pdo->prepare(
            'DELETE FROM content_revisions WHERE content_type = :type AND content_id = :content_id '
            . 'AND id NOT IN (SELECT id FROM content_revisions WHERE content_type = :type2 '
            . 'AND content_id = :content_id2 ORDER BY id DESC LIMIT :limit)'
        );
        $statement->bindValue(':type', $type);
        $statement->bindValue(':content_id', $contentId, PDO::PARAM_INT);
        $statement->bindValue(':type2', $type);
        $statement->bindValue(':content_id2', $contentId, PDO::PARAM_INT);
        $statement->bindValue(':limit', max(1, min(100, $limit)), PDO::PARAM_INT);
        $statement->execute();
    }

    /** @param array<string,mixed> $s */
    private function restoreArticle(array $s): void
    {
        $this->assertFree('articles', (int) $s['id'], (string) $s['slug']);
        if (($s['category_id'] ?? null) !== null && !$this->idExists('article_categories', (int) $s['category_id'])) {
            $s['category_id'] = null;
        }
        $st = $this->pdo->prepare('INSERT INTO articles '
            . '(id, category_id, title, slug, summary, body, status, position, created_by, author_name, featured_image, published_at, created_at, updated_at) '
            . 'VALUES (:id,:category_id,:title,:slug,:summary,:body,:status,:position,:created_by,:author_name,:featured_image,:published_at,:created_at,:updated_at)');
        $st->execute($this->pick($s, ['id','category_id','title','slug','summary','body','status','position','created_by','author_name','featured_image','published_at','created_at','updated_at']));
    }

    /** @param array<string,mixed> $s */
    private function restoreNews(array $s): void
    {
        $this->assertFree('news', (int) $s['id'], (string) $s['slug']);
        $st = $this->pdo->prepare('INSERT INTO news '
            . '(id,title,slug,created_by,intro,body,status,published_at,created_at,updated_at) '
            . 'VALUES (:id,:title,:slug,:created_by,:intro,:body,:status,:published_at,:created_at,:updated_at)');
        $st->execute($this->pick($s, ['id','title','slug','created_by','intro','body','status','published_at','created_at','updated_at']));
    }

    /** @param array<string,mixed> $s */
    private function restoreCategory(array $s): void
    {
        $this->assertFree('article_categories', (int) $s['id'], (string) $s['slug']);
        if (($s['parent_id'] ?? null) !== null && !$this->idExists('article_categories', (int) $s['parent_id'])) {
            $s['parent_id'] = null;
        }
        $hasDescription = in_array('description', array_column(
            $this->pdo->query('PRAGMA table_info(article_categories)')->fetchAll(), 'name'
        ), true);
        $fields = ['id','name','slug','parent_id','position','is_visible'];
        if ($hasDescription) {
            // Old trash entries have no description; keep them restorable.
            $s['description'] = (string) ($s['description'] ?? '');
            $fields[] = 'description';
        }
        $st = $this->pdo->prepare('INSERT INTO article_categories (' . implode(',', $fields) . ') '
            . 'VALUES (:' . implode(',:', $fields) . ')');
        $st->execute($this->pick($s, $fields));
        $attach = $this->pdo->prepare('UPDATE articles SET category_id = :category WHERE id = :id AND category_id IS NULL');
        foreach (($s['_article_ids'] ?? []) as $id) {
            $attach->execute(['category' => (int) $s['id'], 'id' => (int) $id]);
        }
        $child = $this->pdo->prepare('UPDATE article_categories SET parent_id = :category WHERE id = :id AND parent_id IS NULL');
        foreach (($s['_child_ids'] ?? []) as $id) {
            $child->execute(['category' => (int) $s['id'], 'id' => (int) $id]);
        }
    }

    /** @param array<string,mixed> $s */
    private function updateArticleFromSnapshot(array $s): void
    {
        $st = $this->pdo->prepare('UPDATE articles SET category_id=:category_id,title=:title,slug=:slug,summary=:summary,body=:body,status=:status,position=:position,created_by=:created_by,author_name=:author_name,featured_image=:featured_image,published_at=:published_at,created_at=:created_at,updated_at=:updated_at WHERE id=:id');
        $st->execute($this->pick($s, ['id','category_id','title','slug','summary','body','status','position','created_by','author_name','featured_image','published_at','created_at','updated_at']));
    }

    /** @param array<string,mixed> $s */
    private function updateNewsFromSnapshot(array $s): void
    {
        $st = $this->pdo->prepare('UPDATE news SET title=:title,slug=:slug,created_by=:created_by,intro=:intro,body=:body,status=:status,published_at=:published_at,created_at=:created_at,updated_at=:updated_at WHERE id=:id');
        $st->execute($this->pick($s, ['id','title','slug','created_by','intro','body','status','published_at','created_at','updated_at']));
    }

    private function idExists(string $table, int $id): bool
    {
        $statement = $this->pdo->prepare('SELECT 1 FROM ' . $table . ' WHERE id = :id');
        $statement->execute(['id' => $id]);
        return $statement->fetchColumn() !== false;
    }

    private function assertFree(string $table, int $id, string $slug): void
    {
        $st = $this->pdo->prepare('SELECT 1 FROM ' . $table . ' WHERE id=:id OR slug=:slug LIMIT 1');
        $st->execute(['id'=>$id,'slug'=>$slug]);
        if ($st->fetchColumn() !== false) {
            throw new RuntimeException('safety_restore_conflict');
        }
    }

    /** @param array<string,mixed> $source @param list<string> $keys @return array<string,mixed> */
    private function pick(array $source, array $keys): array
    {
        $out=[];
        foreach ($keys as $key) { $out[$key] = $source[$key] ?? null; }
        return $out;
    }

    /** @param array<string,mixed> $value */
    private function encode(array $value): string
    {
        $json = json_encode($value, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE);
        if (!is_string($json)) { throw new RuntimeException('safety_snapshot_failed'); }
        return $json;
    }

    /** @return array<string,mixed> */
    private function decode(string $json): array
    {
        $value=json_decode($json,true);
        if (!is_array($value)) { throw new RuntimeException('safety_snapshot_invalid'); }
        return $value;
    }

    private function assertRevisionType(string $type): void
    {
        if (!in_array($type,['article','news'],true)) { throw new RuntimeException('safety_invalid_type'); }
    }
    private function assertTrashType(string $type): void
    {
        if (!in_array($type,['article','news','article_category'],true)) { throw new RuntimeException('safety_invalid_type'); }
    }
}
