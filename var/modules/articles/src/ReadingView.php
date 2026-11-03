<?php
declare(strict_types=1);
namespace PortixModule\Articles;

/** Display-only transformations; never writes editorial content. */
final class ReadingView
{
    public static function body(string $html, string $tableLabel): string
    {
        return self::document($html, $tableLabel, false, '')['body'];
    }

    /** @return array{body:string,toc:string} */
    public static function document(string $html, string $tableLabel, bool $tocEnabled, string $tocLabel, string $pageUrl = ''): array
    {
        $result = ['body'=>$html, 'toc'=>''];
        if (!$tocEnabled && stripos($html, '<table') === false) { return $result; }
        $previous = libxml_use_internal_errors(true);
        try {
            $dom = new \DOMDocument('1.0', 'UTF-8');
            $dom->loadHTML('<?xml encoding="UTF-8"><div id="p3-reading-root">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
            $root = $dom->getElementById('p3-reading-root');
            if ($root === null) { return $result; }
            $tables = iterator_to_array($dom->getElementsByTagName('table'));
            foreach ($tables as $table) {
                if ($table->parentNode instanceof \DOMElement && $table->parentNode->getAttribute('class') === 'article-table-scroll') { continue; }
                $wrapper = $dom->createElement('div');
                $wrapper->setAttribute('class', 'article-table-scroll');
                $wrapper->setAttribute('role', 'region');
                $wrapper->setAttribute('tabindex', '0');
                $caption = $table->getElementsByTagName('caption')->item(0);
                $wrapper->setAttribute('aria-label', $caption !== null && trim($caption->textContent) !== '' ? trim($caption->textContent) : $tableLabel);
                $table->parentNode->replaceChild($wrapper, $table);
                $wrapper->appendChild($table);
            }
            if ($tocEnabled) {
                $escape=static fn(string $text): string=>htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                $ids=[];
                foreach((new \DOMXPath($dom))->query('//*[@id]') as $node){$id=$node->getAttribute('id');$ids[$id]=($ids[$id]??0)+1;}
                foreach(['p3-series-parts','main-content','p3-reading-toc'] as $id)$ids[$id]=($ids[$id]??0)+1;
                $items='';
                foreach((new \DOMXPath($dom))->query('.//h2 | .//h3 | .//h4 | .//h5 | .//h6', $root) as $heading){
                    $text=trim($heading->textContent);if($text==='')continue;
                    $id=$heading->getAttribute('id');
                    if($id===''||($ids[$id]??0)>1){
                        $base='p3-heading-'.\Portix\Support\Slugger::fromText($text,'section');$id=$base;$suffix=2;
                        while(isset($ids[$id]))$id=$base.'-'.$suffix++;
                        $ids[$id]=1;$heading->setAttribute('id',$id);
                    }
                    $items.='<li class="article-heading-level-'.$heading->tagName.'"><a href="'.$escape($pageUrl).'#'.$escape(rawurlencode($id)).'">'.$escape($text).'</a></li>';
                }
                if($items!=='')$result['toc']='<nav class="article-heading-toc" aria-label="'.$escape($tocLabel).'"><details id="p3-reading-toc"><summary>'.$escape($tocLabel).'</summary><ol>'.$items.'</ol></details></nav>';
            }
            $output = '';
            foreach ($root->childNodes as $child) { $output .= $dom->saveHTML($child); }
            $result['body']=$output;
            return $result;
        } finally {
            libxml_clear_errors(); libxml_use_internal_errors($previous);
        }
    }
}
