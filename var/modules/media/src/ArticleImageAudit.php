<?php
declare(strict_types=1);

namespace Portix\Media;

use DOMDocument;
use DOMElement;
use PDO;
use RuntimeException;

/**
 * Read-only inventory of article body images without a meaningful alternative.
 * A missing alt is not automatically decorative. No file or article is changed.
 */
final class ArticleImageAudit
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * @return array{articles:int,images:int,decorative:int,described:int,issues:list<array<string,mixed>>,errors:list<string>}
     */
    public function report(): array
    {
        if (!class_exists(DOMDocument::class)) {
            throw new RuntimeException('Extension PHP DOM indisponible.');
        }
        $summary = ['articles' => 0, 'images' => 0, 'decorative' => 0, 'described' => 0,
            'issues' => [], 'errors' => []];
        $statement = $this->pdo->query(
            'SELECT id, title, slug, status, body FROM articles ORDER BY id'
        );
        while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
            $summary['articles']++;
            $html = (string) ($row['body'] ?? '');
            if (stripos($html, '<img') === false) {
                continue;
            }
            $dom = new DOMDocument('1.0', 'UTF-8');
            $previous = libxml_use_internal_errors(true);
            try {
                $loaded = $dom->loadHTML(
                    '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body>'
                    . $html . '</body></html>',
                    LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING
                );
            } finally {
                libxml_clear_errors();
                libxml_use_internal_errors($previous);
            }
            if (!$loaded) {
                $summary['errors'][] = 'Article #' . (int) $row['id'] . ' : HTML illisible.';
                continue;
            }
            $index = 0;
            foreach ($dom->getElementsByTagName('img') as $image) {
                if (!$image instanceof DOMElement) {
                    continue;
                }
                $index++;
                $summary['images']++;
                $figure = $image->parentNode;
                while ($figure instanceof DOMElement && strtolower($figure->tagName) !== 'figure') {
                    $figure = $figure->parentNode;
                }
                $explicitDecorative = $figure instanceof DOMElement
                    && $figure->getAttribute('data-decorative') === '1';
                if ($explicitDecorative && trim($image->getAttribute('alt')) === '') {
                    $summary['decorative']++;
                    continue;
                }
                if ($image->hasAttribute('alt') && trim($image->getAttribute('alt')) !== '') {
                    $summary['described']++;
                    continue;
                }
                $caption = '';
                if ($figure instanceof DOMElement) {
                    foreach ($figure->getElementsByTagName('figcaption') as $captionNode) {
                        $caption = trim((string) $captionNode->textContent);
                        break;
                    }
                }
                $summary['issues'][] = [
                    'id' => (int) $row['id'], 'title' => (string) $row['title'],
                    'slug' => (string) $row['slug'], 'status' => (string) $row['status'],
                    'position' => $index, 'src' => mb_substr($image->getAttribute('src'), 0, 500),
                    'caption' => mb_substr($caption, 0, 250),
                    'reason' => $image->hasAttribute('alt') ? 'vide' : 'absent',
                ];
            }
        }
        return $summary;
    }
}
