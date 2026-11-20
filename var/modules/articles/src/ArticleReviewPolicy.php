<?php
declare(strict_types=1);

namespace PortixModule\Articles;

use RuntimeException;

/**
 * Centralised server-side checks for article publication transitions.
 * The interface never grants additional permissions.
 */
final class ArticleReviewPolicy
{
    /** Fingerprint of the persisted article, including content and publication state. */
    public static function version(array $article): string
    {
        unset($article['category_name']);
        ksort($article);
        return hash('sha256', json_encode($article, JSON_THROW_ON_ERROR));
    }

    public static function assertVersion(array $article, mixed $expected): void
    {
        if (!is_string($expected) || !hash_equals(self::version($article), $expected)) {
            throw new RuntimeException('articles_review_changed');
        }
    }

    public static function transition(
        string $current,
        string $next,
        bool $isAdmin,
        bool $canEdit,
        bool $canPublish,
        string $rawNote = ''
    ): ?string {
        if ($next === 'published') {
            if (!$canPublish || !in_array($current, ['draft', 'pending'], true)
                || ($current === 'pending' && !$isAdmin)) {
                throw new RuntimeException('security_access_denied');
            }
            return null;
        }
        if ($next === 'pending') {
            if (!$canEdit || $current !== 'draft') {
                throw new RuntimeException('security_access_denied');
            }
            return null;
        }
        if ($next === 'draft') {
            if ($current === 'pending' && $isAdmin) {
                $note = trim(strip_tags($rawNote));
                if ($note === '' || mb_strlen($note, 'UTF-8') > 2000) {
                    throw new RuntimeException('articles_review_note_required');
                }
                return $note;
            }
            if ($current === 'published' && $canPublish) {
                return null;
            }
            throw new RuntimeException('security_access_denied');
        }
        throw new RuntimeException('articles_invalid_status');
    }
}
