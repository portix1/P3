<?php
declare(strict_types=1);

namespace Portix\Seo;

use DateTimeImmutable;
use DateTimeZone;
use Throwable;

final class PageMetadata
{
    private string $pageTitle = '';
    private string $homeTitle = '';
    private bool $isHomePage = false;
    private string $description;
    private string $canonicalPath = '';
    private string $type = 'website';
    private ?string $publishedAt = null;
    private ?string $modifiedAt = null;
    private string $author = '';
    private string $image = '';
    private string $schemaType = 'Article';
    private string $robots = 'index,follow';
    /** @var list<array{name:string,url:string}> */
    private array $breadcrumbs = [];

    public function __construct(
        private readonly string $siteName,
        string $defaultDescription,
        private readonly string $siteUrl,
        private readonly string $defaultAuthor = '',
        private readonly string $publisherName = '',
        private readonly string $publisherLogo = '',
        private readonly string $defaultImage = ''
    ) {
        $this->description = self::descriptionText($defaultDescription);
    }

    public function define(
        string $title,
        string $description = '',
        string $canonicalPath = '',
        string $type = 'website',
        ?string $publishedAt = null,
        ?string $modifiedAt = null,
        string $author = '',
        string $image = '',
        string $schemaType = 'Article'
    ): void {
        $this->pageTitle = self::plainText($title, 180);
        $cleanDescription = self::descriptionText($description);
        if ($cleanDescription !== '') {
            $this->description = $cleanDescription;
        }
        $canonicalPath = trim($canonicalPath);
        $this->canonicalPath = $canonicalPath === '/' ? '/' : ltrim($canonicalPath, '/');
        $this->type = in_array($type, ['website', 'article'], true) ? $type : 'website';
        $this->publishedAt = $publishedAt;
        $this->modifiedAt = $modifiedAt;
        $this->author = self::plainText($author !== '' ? $author : $this->defaultAuthor, 160);
        $this->image = trim($image);
        $this->schemaType = in_array($schemaType, ['Article', 'TechArticle', 'NewsArticle'], true)
            ? $schemaType
            : 'Article';
    }

    /** @param list<array{name:string,url:string}> $breadcrumbs */
    public function breadcrumbs(array $breadcrumbs): void
    {
        $this->breadcrumbs = [];
        foreach ($breadcrumbs as $item) {
            $name = self::plainText((string) ($item['name'] ?? ''), 160);
            $url = trim((string) ($item['url'] ?? ''));
            if ($name !== '' && $url !== '') {
                $this->breadcrumbs[] = ['name' => $name, 'url' => $url];
            }
        }
    }

    /** @return array{name:string,url:string}|null */
    public function breadcrumbItem(): ?array
    {
        if ($this->pageTitle === '' || $this->canonicalPath === '') {
            return null;
        }

        return ['name' => $this->pageTitle, 'url' => $this->canonicalPath];
    }

    /** Apply home-specific metadata without changing the displayed content or article headline. */
    public function configureHomePage(string $title = '', string $description = ''): void
    {
        $this->isHomePage = true;
        $this->canonicalPath = '/';
        $this->homeTitle = self::plainText($title, 180);
        $cleanDescription = self::descriptionText($description);
        if ($cleanDescription !== '') {
            $this->description = $cleanDescription;
        }
    }

    public function documentTitle(): string
    {
        $title = $this->homeTitle !== '' ? $this->homeTitle : $this->pageTitle;
        return $title === '' || strcasecmp($title, $this->siteName) === 0
            ? $this->siteName
            : $title . ' — ' . $this->siteName;
    }

    public function description(): string { return $this->description; }
    public function type(): string { return $this->type; }

    public function imageUrl(): string
    {
        return $this->structuredImageUrl($this->image !== '' ? $this->image : $this->defaultImage);
    }

    public function noIndex(): void { $this->robots = 'noindex,follow'; }
    public function noIndexNoFollow(): void { $this->robots = 'noindex,nofollow'; }
    public function robots(): string { return $this->robots; }

    public function canonicalUrl(): string
    {
        if ($this->siteUrl === '' || $this->canonicalPath === '') {
            return '';
        }
        return $this->absolute($this->canonicalPath);
    }

    public function jsonLd(): string
    {
        $nodes = [];
        $canonical = $this->canonicalUrl();
        $siteName = self::plainText($this->siteName, 160);
        if ($this->isHomePage && $canonical !== '' && $siteName !== '') {
            $nodes[] = [
                '@type' => 'WebSite',
                '@id' => $canonical . '#website',
                'name' => $siteName,
                'url' => $canonical,
            ];
        }
        if ($this->type === 'article' && $this->pageTitle !== '') {
            $article = [
                '@type' => $this->schemaType,
                'headline' => $this->pageTitle,
            ];
            if ($canonical !== '') {
                $article['url'] = $canonical;
                $article['mainEntityOfPage'] = ['@type' => 'WebPage', '@id' => $canonical];
            }
            if ($this->description !== '') {
                $article['description'] = $this->description;
            }
            $published = self::schemaDate($this->publishedAt);
            if ($published !== null) {
                $article['datePublished'] = $published;
            }
            $modified = self::schemaDate($this->modifiedAt);
            if ($modified !== null) {
                $article['dateModified'] = $modified;
            }
            if ($this->author !== '') {
                $article['author'] = ['@type' => 'Person', 'name' => $this->author];
            }
            $image = $this->structuredImageUrl($this->image);
            if ($image !== '') {
                $article['image'] = [$image];
            }
            $publisher = self::plainText($this->publisherName !== '' ? $this->publisherName : $this->siteName, 160);
            if ($publisher !== '') {
                $publisherNode = ['@type' => 'Organization', 'name' => $publisher];
                $logo = $this->structuredImageUrl($this->publisherLogo);
                if ($logo !== '') {
                    $publisherNode['logo'] = ['@type' => 'ImageObject', 'url' => $logo];
                }
                $article['publisher'] = $publisherNode;
            }
            $nodes[] = $article;
        }
        if ($this->breadcrumbs !== []) {
            $items = [];
            foreach ($this->breadcrumbs as $index => $item) {
                $items[] = [
                    '@type' => 'ListItem',
                    'position' => $index + 1,
                    'name' => $item['name'],
                    'item' => $this->absolute($item['url']),
                ];
            }
            $nodes[] = ['@type' => 'BreadcrumbList', 'itemListElement' => $items];
        }
        if ($nodes === []) {
            return '';
        }
        $data = ['@context' => 'https://schema.org', '@graph' => $nodes];
        return (string) json_encode(
            $data,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP
        );
    }

    public static function plainText(string $value, int $maximum = 320): string
    {
        $value = (string) preg_replace(
            '~<(?:br\s*/?|/p|/div|/li|/h[1-6]|/blockquote|/tr)\s*>~iu',
            ' ',
            $value
        );
        $value = html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = trim((string) preg_replace('/\s+/u', ' ', $value));
        return self::utf8Substr($value, 0, max(1, $maximum));
    }

    public static function descriptionText(string $value, int $maximum = 170): string
    {
        $value = self::plainText($value, 1000);
        if (self::utf8Length($value) <= $maximum) {
            return $value;
        }
        $candidate = self::utf8Substr($value, 0, $maximum + 1);
        $space = strrpos($candidate, ' ');
        if ($space !== false && $space >= 120) {
            $candidate = substr($candidate, 0, $space);
        } else {
            $candidate = self::utf8Substr($candidate, 0, $maximum);
        }
        return rtrim($candidate, " \t\n\r\0\x0B,;:-") . '…';
    }

    public static function firstImageFromHtml(string $html): string
    {
        if (preg_match('/<img\b[^>]*\bsrc\s*=\s*(["\'])(.*?)\1/isu', $html, $match) !== 1) {
            return '';
        }
        return trim(html_entity_decode((string) $match[2], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    private static function utf8Length(string $value): int
    {
        if (function_exists('mb_strlen')) {
            return mb_strlen($value, 'UTF-8');
        }
        if (function_exists('iconv_strlen')) {
            $length = iconv_strlen($value, 'UTF-8');
            if ($length !== false) {
                return $length;
            }
        }
        return strlen($value);
    }

    private static function utf8Substr(string $value, int $start, int $length): string
    {
        if (function_exists('mb_substr')) {
            return mb_substr($value, $start, $length, 'UTF-8');
        }
        if (function_exists('iconv_substr')) {
            $slice = iconv_substr($value, $start, $length, 'UTF-8');
            if ($slice !== false) {
                return $slice;
            }
        }
        return substr($value, $start, $length);
    }

    private static function schemaDate(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }
        try {
            $date = new DateTimeImmutable(trim($value), new DateTimeZone('UTC'));
            return $date->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\\TH:i:s\\Z');
        } catch (Throwable) {
            return null;
        }
    }

    private function structuredImageUrl(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }
        if (preg_match('#^https?://#i', $value) === 1) {
            return filter_var($value, FILTER_VALIDATE_URL) !== false ? $value : '';
        }
        if (preg_match('#^[a-z][a-z0-9+.-]*:#i', $value) === 1 || str_starts_with($value, '//')) {
            return '';
        }
        return $this->siteUrl === '' ? '' : $this->absolute($value);
    }

    private function absolute(string $path): string
    {
        $path = trim($path);
        if (preg_match('#^https?://#i', $path) === 1) {
            return $path;
        }
        return $this->siteUrl === '' ? $path : rtrim($this->siteUrl, '/') . '/' . ltrim($path, '/');
    }
}
