<?php
declare(strict_types=1);

namespace PortixModule\Affiliation;

use PDO;

final class AffiliationRenderer
{
    public static function forArticle(PDO $pdo, int $articleId): string
    {
        try {
            require_once __DIR__ . '/AffiliationRepository.php';
            $repo = new AffiliationRepository($pdo);
            if (!$repo->isInstalled()) {
                return '';
            }

            // If the module is explicitly disabled in the registry, do not render it.
            // A missing registry row must not make valid installed data disappear silently.
            $state = $pdo->prepare(
                "SELECT enabled FROM modules "
                . "WHERE lower(prefix)=lower(:prefix) OR lower(path)=lower(:path) LIMIT 1"
            );
            $state->execute(['prefix' => 'affiliation', 'path' => 'affiliation']);
            $enabled = $state->fetchColumn();
            if ($enabled !== false && (int) $enabled !== 1) {
                return '';
            }

            $settings = \PortixSettings::read('affiliation', [
                'title' => 'Matériel en rapport avec cet article',
                'disclosure' => 'Certains liens ci-dessous sont des liens affiliés. Si vous effectuez un achat après les avoir suivis, Univers Guitare peut recevoir une commission, sans coût supplémentaire pour vous.',
                'max_products' => 4,
            ]);

            $products = array_slice(
                $repo->productsForArticle($articleId),
                0,
                max(1, min(12, (int) ($settings['max_products'] ?? 4)))
            );
            $products = array_values(array_filter(
                $products,
                static fn (array $product): bool => ($product['offers'] ?? []) !== []
            ));
            if ($products === []) {
                return '';
            }

            $escape = static fn (string $value): string => htmlspecialchars(
                $value,
                ENT_QUOTES | ENT_SUBSTITUTE,
                'UTF-8'
            );

            $html = '<aside class="article-affiliation" aria-labelledby="article-affiliation-title">';
            $html .= '<h2 id="article-affiliation-title">' . $escape((string) $settings['title']) . '</h2>';
            if (trim((string) ($settings['disclosure'] ?? '')) !== '') {
                $html .= '<p class="article-affiliation-disclosure">'
                    . $escape((string) $settings['disclosure']) . '</p>';
            }
            $html .= '<div class="article-affiliation-grid">';

            foreach ($products as $product) {
                $html .= '<section class="article-affiliation-product">';
                $image = trim((string) ($product['image_url'] ?? ''));
                if ($image !== '') {
                    $html .= '<img src="' . $escape($image) . '" alt="" loading="lazy" decoding="async">';
                }
                $html .= '<div class="article-affiliation-content"><h3>'
                    . $escape((string) $product['name']) . '</h3>';
                if (trim((string) ($product['description'] ?? '')) !== '') {
                    $html .= '<p>' . $escape((string) $product['description']) . '</p>';
                }
                $html .= '<div class="article-affiliation-actions">';
                foreach ($product['offers'] as $offer) {
                    $label = trim((string) ($offer['button_label'] ?? ''));
                    if ($label === '') {
                        $label = 'Voir chez ' . (string) $offer['partner_name'];
                    }
                    $html .= '<a class="article-affiliation-link" href="'
                        . $escape((string) $offer['affiliate_url'])
                        . '" target="_blank" rel="sponsored nofollow noopener">'
                        . $escape($label) . '</a>';
                }
                $html .= '</div></div></section>';
            }

            $cssFile = __DIR__ . '/../assets/public.css';
            $css = is_file($cssFile) ? (string) file_get_contents($cssFile) : '';
            return ($css !== '' ? '<style>' . $css . '</style>' : '')
                . $html . '</div></aside>';
        } catch (\Throwable $error) {
            error_log('[Portix3 affiliation] ' . $error->getMessage());
            return '';
        }
    }
}
