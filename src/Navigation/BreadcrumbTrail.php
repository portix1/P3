<?php
declare(strict_types=1);

namespace Portix\Navigation;

use Portix\Seo\PageMetadata;
use Portix\View\Template;

/**
 * Centralise le fil d'Ariane public de Portix.
 *
 * Les modules ajoutent uniquement les niveaux intermédiaires. Le noyau ajoute
 * automatiquement Accueil puis la page courante depuis PageMetadata, et utilise
 * ensuite cette même liste pour l'affichage HTML et BreadcrumbList JSON-LD.
 */
final class BreadcrumbTrail
{
    /** @var list<array{name:string,url:string}> */
    private array $items = [];

    public function __construct(private readonly string $homeLabel)
    {
        $home = trim($homeLabel);
        if ($home !== '') {
            $this->items[] = ['name' => $home, 'url' => '/'];
        }
    }

    public function add(string $name, string $url): void
    {
        $name = trim($name);
        $url = trim($url);
        if ($name === '' || $url === '') {
            return;
        }

        $lastIndex = array_key_last($this->items);
        $last = $lastIndex === null ? null : $this->items[$lastIndex];
        if (is_array($last) && self::sameUrl((string) $last['url'], $url)) {
            $this->items[$lastIndex] = ['name' => $name, 'url' => $url];
            return;
        }

        $this->items[] = ['name' => $name, 'url' => $url];
    }

    /** @return list<array{name:string,url:string}> */
    public function items(): array
    {
        return $this->items;
    }

    public function apply(
        Template $template,
        PageMetadata $metadata,
        string $basePath,
        bool $isFrontPage,
        string $ariaLabel
    ): void {
        if ($isFrontPage) {
            return;
        }

        $current = $metadata->breadcrumbItem();
        if ($current === null) {
            return;
        }

        $lastIndex = array_key_last($this->items);
        $last = $lastIndex === null ? null : $this->items[$lastIndex];
        if (is_array($last)
            && self::sameUrl((string) $last['url'], (string) $current['url'])) {
            $this->items[$lastIndex] = $current;
        } else {
            $this->items[] = $current;
        }

        if (count($this->items) < 2) {
            return;
        }

        $metadata->breadcrumbs($this->items);
        $template->set('breadcrumb_label', trim($ariaLabel) !== '' ? trim($ariaLabel) : 'Breadcrumb');
        $template->set('breadcrumb_has_items', '1');

        $lastIndex = array_key_last($this->items);
        foreach ($this->items as $index => $item) {
            $url = (string) $item['url'];
            $template->addRow('breadcrumbitem', [
                'name' => (string) $item['name'],
                'url' => $url === '/' ? rtrim($basePath, '/') . '/' : $url,
                'current' => $index === $lastIndex ? '1' : '0',
            ]);
        }
        $template->setSlot('breadcrumb', './var/templates/shared/breadcrumb.tpl');
    }

    private static function sameUrl(string $left, string $right): bool
    {
        return rtrim(trim($left), '/') === rtrim(trim($right), '/');
    }

}
