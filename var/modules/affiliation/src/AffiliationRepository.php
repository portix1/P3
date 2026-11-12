<?php
declare(strict_types=1);

namespace PortixModule\Affiliation;

use PDO;
use RuntimeException;

final class AffiliationRepository
{
    public function __construct(private readonly PDO $pdo) {}

    public function isInstalled(): bool
    {
        $st = $this->pdo->prepare("SELECT 1 FROM sqlite_master WHERE type='table' AND name='affiliate_products'");
        $st->execute();
        return $st->fetchColumn() !== false;
    }

    /** @return list<array<string,mixed>> */
    public function partners(): array
    {
        return $this->pdo->query('SELECT p.*, (SELECT COUNT(*) FROM affiliate_offers o WHERE o.partner_id=p.id) offer_count FROM affiliate_partners p ORDER BY p.position, p.name COLLATE NOCASE, p.id')->fetchAll();
    }

    /** @return array<string,mixed>|null */
    public function partner(int $id): ?array
    {
        $st = $this->pdo->prepare('SELECT * FROM affiliate_partners WHERE id=:id');
        $st->execute(['id'=>$id]);
        $row = $st->fetch();
        return $row === false ? null : $row;
    }

    public function savePartner(?int $id, string $name, string $websiteUrl, bool $enabled): int
    {
        $name = trim($name);
        $websiteUrl = trim($websiteUrl);
        if ($name === '' || mb_strlen($name) > 160) throw new RuntimeException('affiliation_invalid_partner');
        if ($websiteUrl !== '' && filter_var($websiteUrl, FILTER_VALIDATE_URL) === false) throw new RuntimeException('affiliation_invalid_url');
        $now = gmdate('Y-m-d H:i:s');
        if ($id === null) {
            $pos=(int)$this->pdo->query('SELECT COALESCE(MAX(position),-1)+1 FROM affiliate_partners')->fetchColumn();
            $st=$this->pdo->prepare('INSERT INTO affiliate_partners(name,website_url,enabled,position,created_at,updated_at) VALUES(:name,:url,:enabled,:position,:created,:updated)');
            $st->execute(['name'=>$name,'url'=>$websiteUrl,'enabled'=>$enabled?1:0,'position'=>$pos,'created'=>$now,'updated'=>$now]);
            return (int)$this->pdo->lastInsertId();
        }
        $st=$this->pdo->prepare('UPDATE affiliate_partners SET name=:name,website_url=:url,enabled=:enabled,updated_at=:updated WHERE id=:id');
        $st->execute(['id'=>$id,'name'=>$name,'url'=>$websiteUrl,'enabled'=>$enabled?1:0,'updated'=>$now]);
        return $id;
    }

    public function deletePartner(int $id): void
    {
        $st=$this->pdo->prepare('DELETE FROM affiliate_partners WHERE id=:id'); $st->execute(['id'=>$id]);
    }

    /** @return list<array<string,mixed>> */
    public function products(): array
    {
        return $this->pdo->query('SELECT p.*, (SELECT COUNT(*) FROM affiliate_offers o WHERE o.product_id=p.id) offer_count, (SELECT COUNT(*) FROM affiliate_article_products ap WHERE ap.product_id=p.id) article_count FROM affiliate_products p ORDER BY p.position,p.name COLLATE NOCASE,p.id')->fetchAll();
    }

    /** @return array<string,mixed>|null */
    public function product(int $id): ?array
    {
        $st=$this->pdo->prepare('SELECT * FROM affiliate_products WHERE id=:id'); $st->execute(['id'=>$id]); $row=$st->fetch(); return $row===false?null:$row;
    }

    public function saveProduct(?int $id, string $name, string $description, string $imageUrl, bool $enabled, array $articleIds): int
    {
        $name=trim($name); $description=trim($description); $imageUrl=trim($imageUrl);
        if ($name==='' || mb_strlen($name)>200) throw new RuntimeException('affiliation_invalid_product');
        if (mb_strlen($description)>1000) throw new RuntimeException('affiliation_invalid_description');
        if ($imageUrl!=='' && mb_strlen($imageUrl)>500) throw new RuntimeException('affiliation_invalid_image');
        $now=gmdate('Y-m-d H:i:s');
        $this->pdo->beginTransaction();
        try {
            if ($id===null) {
                $pos=(int)$this->pdo->query('SELECT COALESCE(MAX(position),-1)+1 FROM affiliate_products')->fetchColumn();
                $st=$this->pdo->prepare('INSERT INTO affiliate_products(name,description,image_url,enabled,position,created_at,updated_at) VALUES(:name,:description,:image,:enabled,:position,:created,:updated)');
                $st->execute(['name'=>$name,'description'=>$description,'image'=>$imageUrl,'enabled'=>$enabled?1:0,'position'=>$pos,'created'=>$now,'updated'=>$now]);
                $id=(int)$this->pdo->lastInsertId();
            } else {
                $st=$this->pdo->prepare('UPDATE affiliate_products SET name=:name,description=:description,image_url=:image,enabled=:enabled,updated_at=:updated WHERE id=:id');
                $st->execute(['id'=>$id,'name'=>$name,'description'=>$description,'image'=>$imageUrl,'enabled'=>$enabled?1:0,'updated'=>$now]);
            }
            $this->pdo->prepare('DELETE FROM affiliate_article_products WHERE product_id=:id')->execute(['id'=>$id]);
            $insert=$this->pdo->prepare('INSERT INTO affiliate_article_products(article_id,product_id,position) VALUES(:article,:product,:position)');
            $position=0;
            foreach(array_values(array_unique(array_filter(array_map('intval',$articleIds),fn($v)=>$v>0))) as $articleId) {
                $check=$this->pdo->prepare('SELECT 1 FROM articles WHERE id=:id'); $check->execute(['id'=>$articleId]);
                if ($check->fetchColumn()!==false) $insert->execute(['article'=>$articleId,'product'=>$id,'position'=>$position++]);
            }
            $this->pdo->commit(); return $id;
        } catch (\Throwable $e) { if($this->pdo->inTransaction())$this->pdo->rollBack(); throw $e; }
    }

    public function deleteProduct(int $id): void { $st=$this->pdo->prepare('DELETE FROM affiliate_products WHERE id=:id'); $st->execute(['id'=>$id]); }

    /** @return list<int> */
    public function productArticleIds(int $id): array
    {
        $st=$this->pdo->prepare('SELECT article_id FROM affiliate_article_products WHERE product_id=:id ORDER BY position,id'); $st->execute(['id'=>$id]);
        return array_map('intval',$st->fetchAll(PDO::FETCH_COLUMN));
    }

    /** @return list<array<string,mixed>> */
    public function articles(): array
    {
        $categoriesByParent = [];
        foreach ($this->pdo->query('SELECT id,name,parent_id,position FROM article_categories')->fetchAll() as $category) {
            $parent = $category['parent_id'] === null ? 0 : (int) $category['parent_id'];
            $categoriesByParent[$parent][] = $category;
        }

        $articlesByCategory = [];
        foreach ($this->pdo->query('SELECT id,title,status,category_id,position FROM articles')->fetchAll() as $article) {
            $parent = $article['category_id'] === null ? 0 : (int) $article['category_id'];
            $articlesByCategory[$parent][] = $article;
        }

        $ordered = [];
        $walk = function (int $parent, string $categoryPath = '') use (&$walk, &$ordered, &$categoriesByParent, &$articlesByCategory): void {
            $items = [];
            foreach ($articlesByCategory[$parent] ?? [] as $article) {
                $items[] = ['type' => 'article', 'id' => (int) $article['id'], 'position' => (int) $article['position'], 'label' => (string) $article['title'], 'item' => $article];
            }
            foreach ($categoriesByParent[$parent] ?? [] as $category) {
                $items[] = ['type' => 'category', 'id' => (int) $category['id'], 'position' => (int) $category['position'], 'label' => (string) $category['name'], 'item' => $category];
            }

            $seenPositions = [];
            $hasCollision = false;
            foreach ($items as $item) {
                if (isset($seenPositions[$item['position']])) {
                    $hasCollision = true;
                    break;
                }
                $seenPositions[$item['position']] = true;
            }
            usort($items, static function (array $left, array $right) use ($hasCollision, $parent): int {
                if ($hasCollision && $left['type'] !== $right['type']) {
                    $categoriesFirst = $parent === 0;
                    return ($left['type'] === ($categoriesFirst ? 'category' : 'article')) ? -1 : 1;
                }
                if ($hasCollision) {
                    return [$left['position'], $left['label'], $left['id']] <=> [$right['position'], $right['label'], $right['id']];
                }
                return [$left['position'], $left['type'], $left['label'], $left['id']]
                    <=> [$right['position'], $right['type'], $right['label'], $right['id']];
            });

            foreach ($items as $entry) {
                if ($entry['type'] === 'article') {
                    $article = $entry['item'];
                    $article['category_path'] = $categoryPath !== '' ? rtrim($categoryPath, ' ›') : '';
                    $ordered[] = $article;
                    continue;
                }
                $walk((int) $entry['id'], $categoryPath . $entry['label'] . ' › ');
            }
        };
        $walk(0);

        return $ordered;
    }

    /** @return list<array<string,mixed>> */
    public function offers(): array
    {
        return $this->pdo->query('SELECT o.*,p.name product_name,r.name partner_name FROM affiliate_offers o JOIN affiliate_products p ON p.id=o.product_id JOIN affiliate_partners r ON r.id=o.partner_id ORDER BY p.name COLLATE NOCASE,o.position,r.name COLLATE NOCASE,o.id')->fetchAll();
    }

    /** @return list<array<string,mixed>> */
    public function offersForProduct(int $productId): array
    {
        $st = $this->pdo->prepare(
            'SELECT o.*, r.name partner_name FROM affiliate_offers o '
            . 'JOIN affiliate_partners r ON r.id=o.partner_id '
            . 'WHERE o.product_id=:product '
            . 'ORDER BY o.position, r.position, r.name COLLATE NOCASE, o.id'
        );
        $st->execute(['product' => $productId]);
        return $st->fetchAll();
    }

    /** @return array<string,mixed>|null */
    public function offer(int $id): ?array
    {
        $st=$this->pdo->prepare('SELECT * FROM affiliate_offers WHERE id=:id');$st->execute(['id'=>$id]);$r=$st->fetch();return $r===false?null:$r;
    }

    public function saveOffer(?int $id,int $productId,int $partnerId,string $url,string $label,bool $enabled): int
    {
        $url=trim($url);$label=trim($label);
        if(filter_var($url,FILTER_VALIDATE_URL)===false || !preg_match('#^https?://#i',$url)) throw new RuntimeException('affiliation_invalid_url');
        if(mb_strlen($url)>2000) throw new RuntimeException('affiliation_invalid_url');
        if(mb_strlen($label)>120) throw new RuntimeException('affiliation_invalid_label');
        if($this->product($productId)===null || $this->partner($partnerId)===null) throw new RuntimeException('affiliation_invalid_relation');
        $now=gmdate('Y-m-d H:i:s');
        if($id===null){
            $st=$this->pdo->prepare('SELECT COALESCE(MAX(position),-1)+1 FROM affiliate_offers WHERE product_id=:id');$st->execute(['id'=>$productId]);$pos=(int)$st->fetchColumn();
            $st=$this->pdo->prepare('INSERT INTO affiliate_offers(product_id,partner_id,affiliate_url,button_label,enabled,position,created_at,updated_at) VALUES(:product,:partner,:url,:label,:enabled,:position,:created,:updated)');
            $st->execute(['product'=>$productId,'partner'=>$partnerId,'url'=>$url,'label'=>$label,'enabled'=>$enabled?1:0,'position'=>$pos,'created'=>$now,'updated'=>$now]);return (int)$this->pdo->lastInsertId();
        }
        $st=$this->pdo->prepare('UPDATE affiliate_offers SET product_id=:product,partner_id=:partner,affiliate_url=:url,button_label=:label,enabled=:enabled,updated_at=:updated WHERE id=:id');
        $st->execute(['id'=>$id,'product'=>$productId,'partner'=>$partnerId,'url'=>$url,'label'=>$label,'enabled'=>$enabled?1:0,'updated'=>$now]);return $id;
    }

    public function deleteOffer(int $id): void { $st=$this->pdo->prepare('DELETE FROM affiliate_offers WHERE id=:id');$st->execute(['id'=>$id]); }

    /** @return list<array<string,mixed>> */
    public function productsForArticle(int $articleId, ?int $limit = null): array
    {
        $selection = ' FROM affiliate_article_products ap '
            . 'JOIN affiliate_products p ON p.id=ap.product_id '
            . 'WHERE ap.article_id=:article AND p.enabled=1 ORDER BY ap.position,p.position,p.id'
            . ($limit === null ? '' : ' LIMIT :limit');
        $parameters = ['article' => $articleId];
        if ($limit !== null) {
            $parameters['limit'] = max(0, $limit);
        }

        $statement = $this->pdo->prepare('SELECT p.*' . $selection);
        $statement->execute($parameters);
        $products = $statement->fetchAll();
        if ($products === []) {
            return [];
        }

        $statement = $this->pdo->prepare(
            'SELECT o.*, r.name AS partner_name FROM affiliate_offers o '
            . 'JOIN affiliate_partners r ON r.id=o.partner_id '
            . 'WHERE o.enabled=1 AND r.enabled=1 AND o.product_id IN (SELECT p.id' . $selection . ') '
            . 'ORDER BY o.product_id,o.position,r.position,o.id'
        );
        $statement->execute($parameters);
        $offersByProduct = [];
        foreach ($statement as $offer) {
            $offersByProduct[(int) $offer['product_id']][] = $offer;
        }
        foreach ($products as &$product) {
            $product['offers'] = $offersByProduct[(int) $product['id']] ?? [];
        }
        unset($product);
        return $products;
    }
}
