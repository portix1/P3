<?php
declare(strict_types=1);
namespace PortixModule\Home;

use PDO;
use InvalidArgumentException;

final class HomeRepository
{
    public function __construct(public readonly PDO $pdo) {}

    public function installed(string $table): bool
    {
        $q = $this->pdo->prepare("SELECT 1 FROM sqlite_master WHERE type='table' AND name=?");
        $q->execute([$table]);
        return (bool) $q->fetchColumn();
    }

    public function enabled(string $prefix): bool
    {
        $q = $this->pdo->prepare('SELECT enabled FROM modules WHERE prefix=?');
        $q->execute([$prefix]);
        return (int) $q->fetchColumn() === 1;
    }

    public function sections(bool $all = false): array
    {
        return $this->pdo->query('SELECT * FROM home_sections' . ($all ? '' : ' WHERE enabled=1') . ' ORDER BY position,id')->fetchAll(PDO::FETCH_ASSOC);
    }

    public function categories(): array
    {
        return $this->installed('article_categories') ? $this->pdo->query('SELECT id,name,slug,parent_id,is_visible FROM article_categories ORDER BY position,id')->fetchAll(PDO::FETCH_ASSOC) : [];
    }

    public function save(array $data, int $id): void
    {
        $kind = (string) ($data['kind'] ?? '');
        $layout = (string) ($data['layout'] ?? '');
        if (!in_array($kind, ['articles','latest','news','affiliation','text'], true)
            || !in_array($layout, ['feature','category','cards','text'], true)) {
            throw new InvalidArgumentException('home_invalid');
        }
        $category = max(0, (int) ($data['category_id'] ?? 0));
        if ($kind === 'articles' && !in_array($category, array_map('intval', array_column($this->categories(), 'id')), true)) {
            throw new InvalidArgumentException('home_category_required');
        }
        $image = Image::path((string) ($data['image_path'] ?? ''));
        if (trim((string) ($data['image_path'] ?? '')) !== '' && $image === '') { throw new InvalidArgumentException('home_invalid_image'); }
        $values = [
            'kind'=>$kind, 'title'=>mb_substr(trim((string) ($data['title'] ?? '')),0,160),
            'category_id'=>$category, 'layout'=>$layout, 'image_path'=>$image,
            'image_alt'=>mb_substr(trim((string) ($data['image_alt'] ?? '')),0,300),
            'body'=>mb_substr(trim((string) ($data['body'] ?? '')),0,10000),
            'item_count'=>max(1,min(6,(int) ($data['item_count'] ?? 2))),
            'descendants'=>isset($data['descendants']) ? 1 : 0, 'weighted'=>isset($data['weighted']) ? 1 : 0,
            'position'=>max(0,min(10000,(int) ($data['position'] ?? 0))), 'enabled'=>isset($data['enabled']) ? 1 : 0,
        ];
        if ($id > 0) {
            $sets = implode(',',array_map(static fn ($key) => $key.'=:'.$key,array_keys($values)));
            $q = $this->pdo->prepare('UPDATE home_sections SET '.$sets.' WHERE id=:id');
            $q->execute($values+['id'=>$id]);
        } else {
            if (count($this->sections(true)) >= 30) { throw new InvalidArgumentException('home_limit'); }
            $keys = array_keys($values);
            $q = $this->pdo->prepare('INSERT INTO home_sections ('.implode(',',$keys).') VALUES (:'.implode(',:',$keys).')');
            $q->execute($values);
        }
        $this->clearCache();
    }

    public function status(array $section, int $now, array $shown): string
    {
        if (!$section['enabled']) { return 'home_status_disabled'; }
        if ($section['kind']==='text') { return trim($section['body'])===''?'home_status_text':'home_status_ready'; }
        $module=$section['kind']==='latest'?'articles':$section['kind'];
        if (!$this->enabled($module)) { return 'home_status_module'; }
        if ($this->candidates($section,$now)===[]) { return 'home_status_empty'; }
        return in_array((int)$section['id'],$shown,true)?'home_status_ready':'home_status_duplicate';
    }

    public function clearCache(): void { $this->pdo->exec('DELETE FROM analytics_home_cache'); }

    /** Refresh the common draw, not a per-visitor profile. Revalidate content on every render. */
    public function selection(array $settings, int $now, bool $preview = false): array
    {
        $sections = $this->sections();
        $active = $this->pdo->query('SELECT prefix,enabled FROM modules')->fetchAll(PDO::FETCH_KEY_PAIR);
        $insights = $this->enabled('insights') ? \PortixSettings::read('insights',['mode'=>'minimal']) : ['mode'=>'off'];
        $stamp = [];
        foreach (['articles','news','affiliate_products','article_categories'] as $table) {
            if ($this->installed($table)) {
                $stamp[$table] = $this->pdo->query('SELECT COUNT(*) FROM '.$table)->fetchColumn();
            }
        }
        // Content is always reloaded below, so withdrawals and image edits never wait for the draw.
        $key = hash('sha256',json_encode([$sections,$active,$settings['days'],$settings['exploration'],$settings['refresh_minutes'],$insights['mode'],$stamp]));
        $q = $this->pdo->prepare('SELECT payload FROM analytics_home_cache WHERE entry_key=? AND expires_at>?');
        $q->execute([$key,$now]);
        $raw = $preview ? false : $q->fetchColumn();
        $draw = is_string($raw) ? json_decode($raw,true) : null;
        if (!is_array($draw)) {
            $draw = $this->draw($sections,$settings,$now,($insights['mode'] ?? 'off') !== 'off');
            if (!$preview) {
            $q = $this->pdo->prepare('INSERT INTO analytics_home_cache(entry_key,payload,expires_at) VALUES (?,?,?) ON CONFLICT(entry_key) DO UPDATE SET payload=excluded.payload,expires_at=excluded.expires_at');
            $q->execute([$key,json_encode($draw,JSON_THROW_ON_ERROR),$now+60*$settings['refresh_minutes']]);
            $this->pdo->prepare('DELETE FROM analytics_home_cache WHERE expires_at<?')->execute([$now]);
            $this->pdo->prepare('DELETE FROM analytics_home_events WHERE expires_at<?')->execute([$now]);
            $this->pdo->prepare('DELETE FROM analytics_home_metrics WHERE day<?')->execute([gmdate('Y-m-d',$now-90*86400)]);
        }
        }
        $used = [];
        foreach ($sections as $section) {
            if ($section['kind']!=='latest') { continue; }
            $fresh=array_values(array_filter($this->candidates($section,$now),static fn($item)=>!isset($used['articles:'.$item['id']])));
            $draw[(string)$section['id']]=array_column(array_slice($fresh,0,(int)$section['item_count']),'id');
            foreach ($draw[(string)$section['id']] as $id) { $used['articles:'.$id]=(int)$section['id']; }
        }
        $reserved=$used;
        $result = [];
        $used = [];
        $slots = 0;
        foreach ($sections as $section) {
            $ids = $draw[(string) $section['id']] ?? [];
            $items = $ids === [] || $slots >= 60 ? [] : $this->candidates($section, $now, $ids);
            $byId = array_column($items,null,'id');
            $chosen = [];
            foreach ($ids as $id) {
                if ($slots >= 60) { break; }
                if ($section['kind']==='articles' && isset($reserved['articles:'.$id])) { continue; }
                if (isset($byId[$id]) && !isset($used[($section['kind']==='latest'?'articles':$section['kind']).':'.$id])) {
                    $chosen[] = $byId[$id];
                    $used[($section['kind']==='latest'?'articles':$section['kind']).':'.$id] = true;
                    $slots++;
                }
            }
            if ($section['kind'] === 'text' ? trim($section['body']) !== '' : $chosen !== []) {
                $section['items'] = $chosen;
                $result[] = $section;
            }
        }
        return $result;
    }

    private function draw(array $sections, array $settings, int $now, bool $measure): array
    {
        $scores = [];
        if ($measure && $this->installed('analytics_pages')) {
            $q = $this->pdo->prepare('SELECT route,day,SUM(views) AS views FROM analytics_pages WHERE day>=? AND day<=? GROUP BY route,day');
            $q->execute([gmdate('Y-m-d',$now-($settings['days']-1)*86400),gmdate('Y-m-d',$now)]);
            foreach ($q as $row) {
                $age = max(0,($now-(int) strtotime($row['day'].' UTC'))/86400);
                $route = $row['route'];
                // P3 has short and module-prefixed article routes; do not invent fuzzy slug matches.
                if (str_starts_with($route,'articles-page-')) { $route = substr($route,14); }
                if (str_ends_with($route,'.html')) { $route = substr($route,0,-5); }
                $scores[$route] = ($scores[$route] ?? 0) + (int) $row['views'] * pow(0.5,$age/14);
            }
        }
        $draw = []; $used = [];
        usort($sections, static fn ($a,$b) => ($b['kind']==='latest') <=> ($a['kind']==='latest'));
        foreach ($sections as $section) {
            $items = array_values(array_filter($this->candidates($section,$now),static fn ($item) => !isset($used[($section['kind']==='latest'?'articles':$section['kind']).':'.$item['id']])));
            $draw[(string) $section['id']] = [];
            while ($items !== [] && count($draw[(string) $section['id']]) < (int) $section['item_count']) {
                $popular = array_map(static fn ($item) => log(1+($scores[$item['slug'] ?? ''] ?? 0)),$items);
                $sum = array_sum($popular); $n = count($items);
                $mix = $section['kind'] === 'articles' && $section['weighted'] && $sum > 0 ? $settings['exploration']/100 : 1.0;
                $weights = array_map(static fn ($p) => $mix/$n + (1-$mix)*($sum>0?$p/$sum:0),$popular);
                $random = random_int(0,PHP_INT_MAX-1)/PHP_INT_MAX; $index = $n-1;
                foreach ($weights as $i=>$weight) { $random -= $weight; if ($random<=0) { $index=$i; break; } }
                if ($section['kind']==='latest') { $index=0; }
                $item = $items[$index];
                $draw[(string) $section['id']][] = (int) $item['id'];
                $used[($section['kind']==='latest'?'articles':$section['kind']).':'.$item['id']] = true;
                array_splice($items,$index,1);
            }
        }
        return $draw;
    }

    private function candidates(array $section, int $now, ?array $selectedIds = null): array
    {
        if (in_array($section['kind'], ['articles','latest'], true)) {
            if (!$this->enabled('articles') || !$this->installed('articles')) { return []; }
            $categories = $this->categories(); $allowed = [(int) $section['category_id']];
            $visible = array_column($categories,'is_visible','id');
            if ($section['kind']==='latest') { $allowed=array_map('intval',array_keys(array_filter($visible))); }
            if ($allowed===[]) { return []; }
            if ($section['kind']!=='latest' && empty($visible[$allowed[0]])) { return []; }
            if ($section['kind']!=='latest' && $section['descendants']) {
                do { $before=count($allowed); foreach ($categories as $cat) {
                    if ($cat['is_visible'] && in_array((int) $cat['parent_id'],$allowed,true) && !in_array((int) $cat['id'],$allowed,true)) { $allowed[]=(int) $cat['id']; }
                } } while (count($allowed)>$before);
            }
            $date = gmdate('Y-m-d H:i:s', $now);
            $columns = $selectedIds === null ? 'id,slug' : 'id,title,slug,summary,body,featured_image AS image';
            $sql = "SELECT $columns FROM articles WHERE status='published' "
                . "AND (published_at IS NULL OR published_at='' OR datetime(published_at)<=datetime(?)) "
                . 'AND category_id IN (' . implode(',', array_fill(0, count($allowed), '?')) . ')';
            $parameters = [$date, ...$allowed];
            if ($selectedIds !== null) {
                $sql .= ' AND id IN (' . implode(',', array_fill(0, count($selectedIds), '?')) . ')';
                array_push($parameters, ...$selectedIds);
            }
            $q = $this->pdo->prepare($sql . ($section['kind']==='latest' ? " ORDER BY datetime(COALESCE(NULLIF(published_at,''),created_at)) DESC,id DESC" : ' ORDER BY id'));
            $q->execute($parameters);
            $items = $q->fetchAll(PDO::FETCH_ASSOC);
            if ($selectedIds === null || $items === []) {
                return $items;
            }
            foreach ($items as &$item) {
                $item['url']=rawurlencode($item['slug']).'.html';
                $item['series']=''; $item['series_url']='';
            } unset($item);
            if ($this->installed('article_series_parts') && $this->installed('article_series')) {
                $series = $this->pdo->query('SELECT p.article_id,s.title,p.position,p.series_id FROM article_series_parts p JOIN article_series s ON s.id=p.series_id ORDER BY s.id,p.position,p.id')->fetchAll(PDO::FETCH_ASSOC);
                $first=[];
                foreach ($series as $part) { $first[$part['series_id']] ??= (int) $part['article_id']; }
                $slugs=$this->pdo->query("SELECT id,slug FROM articles WHERE status='published' AND (published_at IS NULL OR published_at='' OR datetime(published_at)<=datetime('now'))")->fetchAll(PDO::FETCH_KEY_PAIR);
                $map=array_column($series,null,'article_id');
                foreach ($items as &$item) { if (isset($map[$item['id']])) {
                    $part=$map[$item['id']]; $item['series']=$part['title'];
                    $firstId=$first[$part['series_id']];
                    if (isset($slugs[$firstId]) && $firstId!==(int)$item['id']) { $item['series_url']=rawurlencode($slugs[$firstId]).'.html'; }
                } } unset($item);
            }
            return $items;
        }
        if ($section['kind'] === 'news') {
            if (!$this->enabled('news') || !$this->installed('news')) { return []; }
            $recent = "SELECT id,slug FROM news WHERE status='published' "
                . "AND (published_at IS NULL OR published_at='' OR datetime(published_at)<=datetime(?)) "
                . 'ORDER BY published_at DESC,id DESC LIMIT 12';
            $date = gmdate('Y-m-d H:i:s', $now);
            if ($selectedIds === null) {
                $q = $this->pdo->prepare($recent);
                $q->execute([$date]);
                return $q->fetchAll(PDO::FETCH_ASSOC);
            }
            $q = $this->pdo->prepare(
                "SELECT n.id,n.title,n.slug,n.intro AS summary,n.body,'' AS image FROM ($recent) recent "
                . 'JOIN news n ON n.id=recent.id WHERE n.id IN ('
                . implode(',', array_fill(0, count($selectedIds), '?')) . ')'
            );
            $q->execute([$date, ...$selectedIds]);
            return array_map(
                static fn (array $item): array => $item + ['url' => rawurlencode($item['slug']) . '.html'],
                $q->fetchAll(PDO::FETCH_ASSOC)
            );
        }
        if ($section['kind'] === 'affiliation') {
            if (!$this->enabled('affiliation') || !$this->installed('affiliate_products') || !$this->installed('affiliate_offers') || !$this->installed('affiliate_partners')) { return []; }
            $items=$this->pdo->query("SELECT id,name AS title,description AS summary,image_url AS image FROM affiliate_products WHERE enabled=1 ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
            $q=$this->pdo->prepare('SELECT o.affiliate_url AS url,o.button_label,p.name AS partner_name FROM affiliate_offers o JOIN affiliate_partners p ON p.id=o.partner_id WHERE o.product_id=? AND o.enabled=1 AND p.enabled=1 ORDER BY o.position,o.id');
            $result=[];
            foreach ($items as $item) {
                $q->execute([$item['id']]);
                $offers=array_values(array_filter($q->fetchAll(PDO::FETCH_ASSOC),static fn ($offer) => preg_match('~^https?://~i',$offer['url'])===1 && filter_var($offer['url'],FILTER_VALIDATE_URL)));
                if ($offers!==[]) { $item['offers']=$offers; $item['url']=$offers[0]['url']; $result[]=$item; }
            }
            return $result;
        }
        return [];
    }
}
