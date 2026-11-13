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
    public static function transition(
        string $current,
        string $next,
        bool $isAdmin,
        bool $canEdit,
        bool $canPublish,
        string $rawNote = ''
    ): ?string {
        if ($next === 'published') {
            if (!$canPublish || !in_array($current, ['draft', 'pending'], true)) {
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
