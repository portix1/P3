<?php
declare(strict_types=1);

namespace Portix\Seo;

use InvalidArgumentException;
use PDO;
use RuntimeException;

final class Sitemap
{
    private const XML_NAMESPACE = 'http://www.sitemaps.org/schemas/sitemap/0.9';
    private const MAX_URLS = 50000;
    private const MAX_UNCOMPRESSED_BYTES = 52428800;
    private const MAX_LOCATION_LENGTH = 2048;

    public function __construct(private readonly PDO $pdo)
    {
    }

    public function xml(string $siteUrl): string
    {
        $siteUrl = self::normalizeSiteUrl($siteUrl);
        $entries = $this->entries();
        if (count($entries) > self::MAX_URLS) {
            throw new RuntimeException(
                'Le sitemap dépasse la limite de 50 000 URL ; un index de sitemaps est requis.'
            );
        }

        $xml = [
            '<?xml version="1.0" encoding="UTF-8"?>',
            '<urlset xmlns="' . self::XML_NAMESPACE . '">',
        ];
        foreach ($entries as $entry) {
            $location = $siteUrl . '/' . ltrim($entry['path'], '/');
            if (strlen($location) > self::MAX_LOCATION_LENGTH) {
                throw new RuntimeException('Une URL du sitemap dépasse 2 048 caractères.');
            }
            $xml[] = '  <url>';
            $xml[] = '    <loc>' . self::escapeXml($location) . '</loc>';
            if ($entry['lastmod'] !== '') {
                $xml[] = '    <lastmod>' . self::escapeXml($entry['lastmod']) . '</lastmod>';
            }
            $xml[] = '  </url>';
        }
        $xml[] = '</urlset>';
        $document = implode("\n", $xml) . "\n";
        if (strlen($document) > self::MAX_UNCOMPRESSED_BYTES) {
            throw new RuntimeException(
                'Le sitemap dépasse 50 Mo non compressés ; un index de sitemaps est requis.'
            );
        }
        return $document;
    }

    public function robots(string $siteUrl): string
    {
        $siteUrl = self::normalizeSiteUrl($siteUrl);
        $path = (string) (parse_url($siteUrl, PHP_URL_PATH) ?? '');
        $basePath = rtrim($path, '/');
        $restricted = [
            '/Admin',
            '/login.html',
            '/personnal.html',
            '/forgot-password.html',
            '/reset-password.html',
            '/disconnect.html',
        ];
        $lines = ['User-agent: *', 'Allow: ' . ($basePath === '' ? '/' : $basePath . '/')];
        foreach ($restricted as $route) {
            $lines[] = 'Disallow: ' . $basePath . $route;
        }
        $lines[] = '';
        $lines[] = 'Sitemap: ' . $siteUrl . '/sitemap.xml';
        return implode("\n", $lines) . "\n";
    }

    /** @return list<array{path:string,lastmod:string}> */
    public function entries(): array
    {
        /** @var array<string,array{path:string,lastmod:string}> $entries */
        $entries = [];
        $enabledModules = $this->enabledPublicModules();
        foreach ($enabledModules as $prefix) {
            $this->addEntry($entries, $prefix . '.html');
        }

        if (isset($enabledModules['news']) && $this->tableExists('news')) {
            $latestNewsUpdate = (string) $this->pdo->query(
                "SELECT COALESCE(MAX(updated_at), '') FROM news WHERE status = 'published'"
            )->fetchColumn();
            $this->addEntry($entries, 'news.html', $latestNewsUpdate);
            $statement = $this->pdo->query(
                "SELECT slug, updated_at FROM news WHERE status = 'published' "
                . 'ORDER BY published_at DESC, id DESC'
            );
            foreach ($statement->fetchAll() as $row) {
                $this->addEntry(
                    $entries,
                    rawurlencode((string) $row['slug']) . '.html',
                    (string) $row['updated_at']
                );
            }
        }

        if (isset($enabledModules['articles'])
            && $this->tableExists('articles')
            && $this->tableExists('article_categories')) {
            $latestArticleUpdate = (string) $this->pdo->query(
                "SELECT COALESCE(MAX(updated_at), '') FROM articles WHERE status = 'published'"
            )->fetchColumn();
            $this->addEntry($entries, 'articles.html', $latestArticleUpdate);
            $categories = $this->pdo->query(
                'WITH RECURSIVE category_tree(root_id, descendant_id) AS ('
                . 'SELECT id, id FROM article_categories '
                . 'UNION ALL '
                . 'SELECT tree.root_id, child.id FROM category_tree tree '
                . 'JOIN article_categories child ON child.parent_id = tree.descendant_id'
                . ') '
                . 'SELECT category.slug, MAX(article.updated_at) AS updated_at '
                . 'FROM article_categories category '
                . 'JOIN category_tree tree ON tree.root_id = category.id '
                . 'JOIN articles article ON article.category_id = tree.descendant_id '
                . "AND article.status = 'published' "
                . 'GROUP BY category.id, category.slug, category.position, category.name '
                . 'ORDER BY category.position, category.name COLLATE NOCASE, category.id'
            );
            foreach ($categories->fetchAll() as $row) {
                // Une catégorie cachée quitte seulement la navigation : son URL reste publique et indexable.
                $this->addEntry(
                    $entries,
                    'articles-categorie-' . rawurlencode((string) $row['slug']) . '.html',
                    (string) $row['updated_at']
                );
            }
            $articles = $this->pdo->query(
                "SELECT slug, updated_at FROM articles WHERE status = 'published' "
                . 'ORDER BY updated_at DESC, id DESC'
            );
            foreach ($articles->fetchAll() as $row) {
                $this->addEntry(
                    $entries,
                    rawurlencode((string) $row['slug']) . '.html',
                    (string) $row['updated_at']
                );
            }
        }

        if (isset($enabledModules['dwl'])
            && $this->tableExists('downloads')
            && $this->tableExists('download_categories')) {
            $latestDownloadUpdate = (string) $this->pdo->query(
                "SELECT COALESCE(MAX(updated_at), '') FROM downloads WHERE status = 'published'"
            )->fetchColumn();
            $this->addEntry($entries, 'dwl.html', $latestDownloadUpdate);
            $categories = $this->pdo->query(
                'SELECT category.slug, MAX(download.updated_at) AS updated_at '
                . 'FROM download_categories category '
                . 'JOIN downloads download ON download.category_id = category.id '
                . "WHERE download.status = 'published' "
                . 'GROUP BY category.id, category.slug, category.position '
                . 'ORDER BY category.position, category.slug'
            );
            foreach ($categories->fetchAll() as $row) {
                $this->addEntry(
                    $entries,
                    'dwl-cat-' . rawurlencode((string) $row['slug']) . '.html',
                    (string) $row['updated_at']
                );
            }
        }

        return array_values($entries);
    }

    /** @return array<string,string> */
    private function enabledPublicModules(): array
    {
        $modules = [];
        $statement = $this->pdo->query(
            "SELECT prefix FROM modules WHERE enabled = 1 AND type = 'module' ORDER BY priority, name"
        );
        foreach ($statement->fetchAll() as $row) {
            $prefix = trim((string) $row['prefix']);
            if (preg_match('/^[A-Za-z0-9][A-Za-z0-9_-]{0,199}$/', $prefix) !== 1) {
                continue;
            }
            $modules[strtolower($prefix)] = rawurlencode($prefix);
        }
        return $modules;
    }

    /** @param array<string,array{path:string,lastmod:string}> $entries */
    private function addEntry(array &$entries, string $path, string $updatedAt = ''): void
    {
        if ($path === '' || preg_match('/[\x00-\x1F\x7F?#]/', $path) === 1) {
            return;
        }
        $lastmod = self::lastModificationDate($updatedAt);
        if (isset($entries[$path]) && $entries[$path]['lastmod'] > $lastmod) {
            return;
        }
        $entries[$path] = ['path' => $path, 'lastmod' => $lastmod];
    }

    private function tableExists(string $table): bool
    {
        $statement = $this->pdo->prepare(
            "SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = :name"
        );
        $statement->execute(['name' => $table]);
        return $statement->fetchColumn() !== false;
    }

    private static function normalizeSiteUrl(string $siteUrl): string
    {
        $siteUrl = rtrim(trim($siteUrl), '/');
        $parts = parse_url($siteUrl);
        if (!is_array($parts)
            || filter_var($siteUrl, FILTER_VALIDATE_URL) === false
            || !in_array(strtolower((string) ($parts['scheme'] ?? '')), ['http', 'https'], true)
            || empty($parts['host'])
            || isset($parts['user'])
            || isset($parts['pass'])
            || isset($parts['query'])
            || isset($parts['fragment'])) {
            throw new InvalidArgumentException('Adresse publique du site invalide pour le sitemap.');
        }
        return $siteUrl;
    }

    private static function lastModificationDate(string $value): string
    {
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', trim($value), $match) !== 1) {
            return '';
        }
        $year = (int) $match[1];
        $month = (int) $match[2];
        $day = (int) $match[3];
        return checkdate($month, $day, $year)
            ? sprintf('%04d-%02d-%02d', $year, $month, $day)
            : '';
    }

    private static function escapeXml(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_XML1 | ENT_SUBSTITUTE, 'UTF-8');
    }
}
