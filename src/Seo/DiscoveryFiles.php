<?php
declare(strict_types=1);

namespace Portix\Seo;

use PDO;
use Portix\Configuration\SiteConfiguration;
use Portix\Http\Request;
use Portix\Repository\RepositoryRegistry;

/** Alternate discovery formats share the existing sitemap's public URL set. */
final class DiscoveryFiles
{
    private const TYPES = [
        'llms.txt' => 'text/plain', 'sitemap.txt' => 'text/plain',
        'agents.txt' => 'text/plain', 'agents.json' => 'application/json',
        'feed.xml' => 'application/rss+xml', 'atom.xml' => 'application/atom+xml',
        'feed.json' => 'application/feed+json',
    ];

    public function __construct(private readonly string $root, private readonly PDO $pdo) {}

    public function respond(Request $request, SiteConfiguration $configuration, RepositoryRegistry $repositories, array $modules): bool
    {
        $path = $request->path();
        $name = substr($path, strlen($request->basePath()) + 1);
        if (!isset(self::TYPES[$name])) { return false; }
        if (!in_array($request->method(), ['GET', 'HEAD'], true)) {
            http_response_code(405); header('Allow: GET, HEAD'); return true;
        }
        $base = PublicUrl::resolve((string) $configuration->get('site_url', ''), $request);
        $body = $this->render($name, $base, $configuration->all(), $modules, $repositories->menu()->allOrdered());
        $etag = '"' . hash('sha256', $body) . '"';
        header('Content-Type: ' . self::TYPES[$name] . '; charset=UTF-8');
        // Always revalidate: unpublishing must not leave a stale cached export.
        header('Cache-Control: no-cache, max-age=0, must-revalidate');
        header('ETag: ' . $etag);
        if (in_array($name, ['agents.txt', 'agents.json'], true)) { header('Access-Control-Allow-Origin: *'); }
        header('X-Content-Type-Options: nosniff');
        header('Link: <' . $base . '/llms.txt>; rel="describedby"');
        $matches = explode(',', (string) ($_SERVER['HTTP_IF_NONE_MATCH'] ?? ''));
        foreach ($matches as $match) {
            $match = trim($match);
            if ($match === '*' || preg_replace('/^W\//', '', $match) === $etag) { http_response_code(304); return true; }
        }
        if ($request->method() !== 'HEAD') { echo $body; }
        return true;
    }

    public static function links(string $base): string
    {
        return '<' . $base . '/llms.txt>; rel="describedby", '
            . '<' . $base . '/feed.xml>; rel="alternate"; type="application/rss+xml", '
            . '<' . $base . '/atom.xml>; rel="alternate"; type="application/atom+xml", '
            . '<' . $base . '/feed.json>; rel="alternate"; type="application/feed+json"';
    }

    public function render(string $name, string $base, array $settings, array $modules, array $menu): string
    {
        if (!isset(self::TYPES[$name])) { throw new \InvalidArgumentException('Format de découverte inconnu.'); }
        $base = rtrim($base, '/');
        $parts = parse_url($base);
        if (!is_array($parts) || !in_array($parts['scheme'] ?? '', ['http', 'https'], true)
            || empty($parts['host']) || isset($parts['user']) || isset($parts['pass'])
            || isset($parts['query']) || isset($parts['fragment']) || preg_match('/[\x00-\x20<>"\\\\]/', $base)) {
            throw new \InvalidArgumentException('Adresse publique invalide.');
        }
        if (in_array($name, ['agents.txt', 'agents.json'], true)) {
            if ($name === 'agents.txt') {
                return "# agents.txt\n# Standard: https://agents-txt.com\n# JSON: " . $base . "/agents.json\n"
                    . "# No agent interaction capabilities are declared.\n"
                    . "# Public content index: " . $base . "/llms.txt\n"
                    . "# Sitemap: " . $base . "/sitemap.xml\n";
            }
            $site = ['name' => self::text((string) ($settings['title'] ?? '')) ?: (string) $parts['host'], 'url' => $base];
            $description = self::text((string) ($settings['site_description'] ?? ''));
            if ($description !== '') { $site['description'] = $description; }
            return json_encode(['$schema' => 'https://agents-txt.com/schema/agents-json/v1.0.json', 'version' => '1.0', 'standard' => 'https://agents-txt.com', 'site' => $site], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR) . "\n";
        }
        $xml = (new SitemapGenerator($this->root, $this->pdo))->render($base, $modules, $menu, (string) ($settings['defaultpage'] ?? ''));
        $document = new \DOMDocument();
        if (!$document->loadXML($xml, LIBXML_NONET)) { throw new \RuntimeException('Sitemap XML invalide.'); }
        $urls = [];
        foreach ($document->getElementsByTagName('loc') as $node) { $urls[$node->textContent] = true; }
        $home = $base . '/';
        $records = [];
        $enabled = [];
        foreach ($modules as $module) { if (!empty($module['enabled'])) { $enabled[strtolower((string) ($module['prefix'] ?? ''))] = true; } }
        foreach (array_keys($urls) as $url) {
            $relative = substr($url, strlen($base) + 1);
            if (preg_match('/^(?:articles|news)-preview-/i', $relative) || $relative === 'search.html') { unset($urls[$url]); }
            foreach ($modules as $module) {
                $prefix = (string) ($module['prefix'] ?? '');
                if ($prefix !== '' && empty($module['enabled']) && !isset($enabled[strtolower($prefix)])
                    && ($relative === $prefix . '.html' || str_starts_with($relative, $prefix . '-'))) { unset($urls[$url]); }
            }
        }
        $this->collect($records, $urls, $base, $home, $settings, $enabled);
        ksort($urls, SORT_STRING);
        if ($name === 'sitemap.txt') { return implode("\n", array_keys($urls)) . "\n"; }
        $title = self::text((string) ($settings['title'] ?? ''));
        if ($title === '') { $title = (string) $parts['host']; }
        $description = self::text((string) ($settings['site_description'] ?? ''));
        if ($description === '') { $description = self::text((string) ($settings['seo_home_description'] ?? '')); }
        if ($name === 'llms.txt') { return $this->llms($title, $description, $base, $urls, $records, $menu); }
        $items = array_values(array_filter($records, static fn(array $r): bool => $r['kind'] !== 'category'));
        usort($items, static fn(array $a, array $b): int => strcmp($b['updated'], $a['updated']) ?: strcmp($a['url'], $b['url']));
        $items = array_slice($items, 0, 50);
        return match ($name) {
            'feed.xml' => $this->rss($title, $description, $base, $items),
            'atom.xml' => $this->atom($title, $description, $base, $items),
            'feed.json' => $this->json($title, $description, $base, $items),
        };
    }

    private function collect(array &$records, array &$urls, string $base, string $home, array $settings, array $enabled): void
    {
        $homeTarget = strtolower((string) Request::normalizeHomePageTarget((string) ($settings['defaultpage'] ?? '')));
        foreach (['articles', 'news'] as $table) {
            if (!$this->tableExists($table)) { continue; }
            $isEnabled = isset($enabled[$table]);
            // Even an obsolete menu link must not add an unpublished or disabled article.
            $columns = $table === 'articles' ? 'title,slug,status,summary,updated_at,published_at,category_id' : 'title,slug,status,intro AS summary,updated_at,published_at';
            foreach ($this->pdo->query('SELECT ' . $columns . ' FROM ' . $table)->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $path = (string) $row['slug'] . '.html';
                $url = $homeTarget === strtolower((string) $row['slug']) ? $home : $base . '/' . $path;
                if (!$isEnabled || $row['status'] !== 'published') { unset($urls[$url]); continue; }
                if (!isset($urls[$url])) { continue; }
                $updated = self::date((string) ($row['updated_at'] ?? '')) ?: self::date((string) ($row['published_at'] ?? ''));
                $records[$url] = [
                    'kind' => $table, 'url' => $url, 'title' => self::text((string) $row['title']),
                    'summary' => self::excerpt((string) $row['summary']), 'updated' => $updated,
                    'published' => self::date((string) ($row['published_at'] ?? '')),
                ];
            }
        }
        if (isset($enabled['articles']) && $this->tableExists('article_categories')) {
            foreach ($this->pdo->query('SELECT name,slug FROM article_categories')->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $url = $base . '/articles-categorie-' . (string) $row['slug'] . '.html';
                if (isset($urls[$url])) { $records[$url] = ['kind' => 'category', 'url' => $url, 'title' => self::text((string) $row['name']), 'summary' => '', 'updated' => '', 'published' => '']; }
            }
        }
    }

    private function llms(string $title, string $description, string $base, array $urls, array $records, array $menu): string
    {
        $out = '# ' . self::md($title) . "\n\n";
        if ($description !== '') { $out .= '> ' . self::md($description) . "\n\n"; }
        $out .= "Les liens ci-dessous renvoient aux pages publiques au format HTML.\n\n";
        $out .= "## Accès au site\n\n" . self::link($title, $base . '/') . "\n";
        $out .= self::link('Plan du site XML', $base . '/sitemap.xml') . "\n";
        $out .= self::link('Flux RSS', $base . '/feed.xml') . "\n";
        $known = [$base . '/' => true];
        foreach (['category' => 'Rubriques', 'articles' => 'Articles', 'news' => 'Actualités'] as $kind => $heading) {
            $section = '';
            foreach ($records as $url => $record) {
                if ($record['kind'] !== $kind) { continue; }
                $known[$url] = true;
                $section .= self::link($record['title'] !== '' ? $record['title'] : $url, $url);
                if ($record['summary'] !== '') { $section .= ': ' . self::md($record['summary']); }
                $section .= "\n";
            }
            if ($section !== '') { $out .= "\n## " . $heading . "\n\n" . $section; }
        }
        $labels = [];
        foreach ($menu as $item) { $labels[rtrim($base, '/') . '/' . ltrim((string) ($item['url'] ?? ''), '/')] = self::text((string) ($item['name'] ?? '')); }
        $others = '';
        foreach (array_keys($urls) as $url) { if (!isset($known[$url])) { $others .= self::link(($labels[$url] ?? '') ?: $url, $url) . "\n"; } }
        if ($others !== '') { $out .= "\n## Autres pages publiques\n\n" . $others; }
        return $out;
    }

    private function rss(string $title, string $description, string $base, array $items): string
    {
        $out = '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom"><channel>';
        $out .= '<title>' . self::xml($title) . '</title><link>' . self::xml($base . '/') . '</link><description>' . self::xml($description !== '' ? $description : $title) . '</description>';
        $out .= '<atom:link href="' . self::xml($base . '/feed.xml') . '" rel="self" type="application/rss+xml"/>';
        foreach ($items as $item) {
            $out .= '<item><guid isPermaLink="true">' . self::xml($item['url']) . '</guid><link>' . self::xml($item['url']) . '</link><title>' . self::xml($item['title']) . '</title>';
            if ($item['summary'] !== '') { $out .= '<description>' . self::xml($item['summary']) . '</description>'; }
            if ($item['published'] !== '') { $out .= '<pubDate>' . gmdate(DATE_RSS, strtotime($item['published'])) . '</pubDate>'; }
            $out .= '</item>';
        }
        return $out . '</channel></rss>' . "\n";
    }

    private function atom(string $title, string $description, string $base, array $items): string
    {
        $items = array_values(array_filter($items, static fn(array $r): bool => $r['updated'] !== ''));
        // An empty feed uses a stable, real source modification time, not a fictitious article date.
        $updated = $items[0]['updated'] ?? gmdate(DATE_ATOM, (int) filemtime(__FILE__));
        $out = '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<feed xmlns="http://www.w3.org/2005/Atom">';
        $out .= '<id>' . self::xml($base . '/atom.xml') . '</id><title>' . self::xml($title) . '</title><updated>' . self::xml($updated) . '</updated><author><name>' . self::xml($title) . '</name></author>';
        $out .= '<link rel="self" href="' . self::xml($base . '/atom.xml') . '"/><link rel="alternate" href="' . self::xml($base . '/') . '"/>';
        if ($description !== '') { $out .= '<subtitle>' . self::xml($description) . '</subtitle>'; }
        foreach ($items as $item) {
            $out .= '<entry><id>' . self::xml($item['url']) . '</id><title>' . self::xml($item['title']) . '</title><link rel="alternate" href="' . self::xml($item['url']) . '"/><updated>' . self::xml($item['updated']) . '</updated>';
            if ($item['published'] !== '') { $out .= '<published>' . self::xml($item['published']) . '</published>'; }
            if ($item['summary'] !== '') { $out .= '<summary>' . self::xml($item['summary']) . '</summary>'; }
            $out .= '</entry>';
        }
        return $out . '</feed>' . "\n";
    }

    private function json(string $title, string $description, string $base, array $items): string
    {
        $out = ['version' => 'https://jsonfeed.org/version/1.1', 'title' => $title, 'home_page_url' => $base . '/', 'feed_url' => $base . '/feed.json', 'items' => []];
        if ($description !== '') { $out['description'] = $description; }
        foreach ($items as $item) {
            $row = ['id' => $item['url'], 'url' => $item['url'], 'title' => $item['title'], 'content_text' => $item['summary'] !== '' ? $item['summary'] : $item['title']];
            if ($item['updated'] !== '') { $row['date_modified'] = $item['updated']; }
            if ($item['published'] !== '') { $row['date_published'] = $item['published']; }
            $out['items'][] = $row;
        }
        return json_encode($out, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT) . "\n";
    }

    private function tableExists(string $name): bool
    {
        $s = $this->pdo->prepare("SELECT 1 FROM sqlite_master WHERE type='table' AND name=:name"); $s->execute(['name' => $name]); return $s->fetchColumn() !== false;
    }
    private static function text(string $value): string { return trim((string) preg_replace('/\s+/u', ' ', preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8')))); }
    private static function excerpt(string $value): string { $value = self::text($value); preg_match('/^.{0,500}/us', $value, $match); return $match[0] ?? ''; }
    private static function md(string $value): string { return str_replace(['\\', '[', ']', '*', '_', '`', '<', '>', '#', '|', '(', ')', '!'], ['\\\\', '\\[', '\\]', '\\*', '\\_', '\\`', '&lt;', '&gt;', '\\#', '\\|', '\\(', '\\)', '\\!'], self::text($value)); }
    private static function link(string $title, string $url): string { return '- [' . self::md($title) . '](<' . str_replace(['<', '>'], ['%3C', '%3E'], $url) . '>)'; }
    private static function xml(string $value): string { return htmlspecialchars($value, ENT_QUOTES | ENT_XML1 | ENT_SUBSTITUTE, 'UTF-8'); }
    private static function date(string $value): string
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}(?:[ T]\d{2}:\d{2}:\d{2}(?:Z|[+-]\d{2}:\d{2})?)?$/', $value)) { return ''; }
        try { $date = new \DateTimeImmutable($value, new \DateTimeZone('UTC')); } catch (\Throwable) { return ''; }
        $errors = \DateTimeImmutable::getLastErrors();
        if (is_array($errors) && ($errors['warning_count'] || $errors['error_count'])) { return ''; }
        return $date->setTimezone(new \DateTimeZone('UTC'))->format(DATE_ATOM);
    }
}
