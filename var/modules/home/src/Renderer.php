<?php
declare(strict_types=1);
namespace PortixModule\Home;

final class Renderer
{
    public static function e(string $value): string { return htmlspecialchars($value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'); }
    public static function excerpt(string $value,int $limit=190): string
    {
        $value=preg_replace('~<(script|style)\b[^>]*>.*?</\1>~is','',$value)??'';
        $value=html_entity_decode(strip_tags(preg_replace('~</(?:p|h[1-6]|li|div)>|<br\s*/?>~i',' ',$value) ?? $value),ENT_QUOTES|ENT_HTML5,'UTF-8');
        $value=trim(preg_replace('/\s+/u',' ',$value)??'');
        if (mb_strlen($value)<=$limit) { return $value; }
        $cut=mb_substr($value,0,$limit); $last=mb_strrpos($cut,' ');
        return ($last!==false ? mb_substr($cut,0,$last) : $cut).'…';
    }
    public static function render(array $sections,string $root,array $labels,array $categories): array
    {
        $html=''; $slots=[]; $e=self::e(...);
        $categoryMap=array_column($categories,null,'id');
        foreach ($sections as $section) {
            $sid=(int)$section['id']; $layout=$section['layout'];
            $title=$section['title'] ?: ($section['kind']==='latest'?($labels['home_latest']??'Derniers articles'):($categoryMap[$section['category_id']]['name']??''));
            $html.='<section class="home-zone home-layout-'.$layout.' home-kind-'.$e($section['kind']).'" aria-label="'.$e($title).'">';
            if ($title!=='') { $html.='<h2>'.$e($title).'</h2>'; }
            $categoryImage=Image::thumbnail($root,$section['image_path']);
            if ($section['kind']==='text') {
                $html.='<div class="home-free-text">';
                if ($categoryImage!=='') { $html.='<span class="home-thumbnail">'.self::image($categoryImage,$section['image_alt']).'</span>'; }
                $html.=nl2br($e($section['body'])).'</div></section>'; continue;
            }
            if ($section['kind']==='affiliation') {
                $disclosure=\PortixSettings::read('affiliation',['disclosure'=>$labels['home_disclosure']]);
                $html.='<p class="home-disclosure">'.$e((string)$disclosure['disclosure']).'</p>';
            }
            $html.='<div class="home-items">';
            foreach ($section['items'] as $i=>$item) {
                $slot=$sid.'-'.$i;
                $track=in_array($section['kind'],['articles','latest'],true);
                if ($track) { $slots[$slot]=[$sid,(int)$item['id'],$i+1]; }
                $image='';
                if ($layout!=='text') {
                    $image=$layout==='category' && $i===0 && $categoryImage!=='' ? $categoryImage : Image::thumbnail($root,(string)($item['image']??''));
                    if ($image==='' && $i===0) { $image=$categoryImage; }
                }
                $attr=$track?' data-home-slot="'.$slot.'"':'';
                $external=$section['kind']==='affiliation'?' target="_blank" rel="sponsored nofollow noopener"':'';
                $html.='<article class="home-item'.($image!==''?' home-has-image':'').'"'.$attr.'>';
                if ($image!=='') { $html.='<a class="home-thumbnail" href="'.$e($item['url']).'" aria-label="'.$e($item['title']).'"'.$external.'>'.self::image($image,$image===$categoryImage?$section['image_alt']:'').'</a>'; }
                $html.='<div class="home-item-copy"><h3><a href="'.$e($item['url']).'"'.$external.'>'.$e($item['title']).'</a></h3>';
                $text=trim((string)($item['summary']??'')) ?: (string)($item['body']??'');
                $html.='<p>'.$e(self::excerpt($text)).'</p>';
                if (!empty($item['series'])) { $html.='<p class="home-series">'.$e($item['series']);
                    if (!empty($item['series_url'])) { $html.=' · <a href="'.$e($item['series_url']).'">'.$e($labels['home_series_start']).'</a>'; }
                    $html.='</p>';
                }
                if ($section['kind']==='affiliation') { foreach ($item['offers'] as $offer) {
                    $html.='<a class="home-offer" href="'.$e($offer['url']).'"'.$external.'>'.$e($offer['button_label']?:$offer['partner_name']).'</a> ';
                } }
                $html.='</div></article>';
            }
            $html.='</div>';
            if ($section['kind']==='articles' && isset($categoryMap[$section['category_id']])) {
                $html.='<a class="home-category-link" href="articles-categorie-'.$e(rawurlencode($categoryMap[$section['category_id']]['slug'])).'.html">'.$e($labels['home_all']).' →</a>';
            }
            $html.='</section>';
        }
        return [$html,$slots];
    }
    private static function image(string $path,string $alt): string
    {
        return '<img src="'.self::e($path).'" alt="'.self::e($alt).'" loading="lazy" decoding="async">';
    }
}
