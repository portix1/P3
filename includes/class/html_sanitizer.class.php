<?php
/** Allow-list HTML cleaner for content produced by the Portix 3 editor. */
final class PortixHtmlSanitizer
{
    private const ALLOWED_TAGS = array(
        'p', 'br', 'hr', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'strong', 'b', 'em', 'i', 'u', 's',
        'blockquote', 'ul', 'ol', 'li', 'a', 'img', 'figure', 'figcaption',
        'table', 'caption', 'thead', 'tbody', 'tfoot', 'tr', 'th', 'td',
        'pre', 'code', 'span', 'sub', 'sup', 'div',
    );

    private const REMOVE_WITH_CONTENT = array(
        'script', 'style', 'iframe', 'object', 'embed', 'svg', 'math',
        'form', 'input', 'button', 'textarea', 'select', 'option',
    );

    public static function clean($html)
    {
        $html = trim((string) $html);
        if ($html === '') {
            return '';
        }
        if (strlen($html) > 1048576) {
            throw new InvalidArgumentException('Le contenu dépasse la limite de 1 Mio.');
        }

        // Historical news sometimes contain plain text rather than editor HTML.
        // Preserve their intentional line breaks instead of letting HTML collapse them.
        if ($html === strip_tags($html)) {
            return self::plainTextFallback($html);
        }

        if (!class_exists('DOMDocument')) {
            return self::plainTextFallback($html);
        }

        $previousErrors = libxml_use_internal_errors(true);
        $document = new DOMDocument('1.0', 'UTF-8');
        $wrapperId = 'portix2-editor-root';
        $loaded = $document->loadHTML(
            '<?xml encoding="UTF-8"><div id="' . $wrapperId . '">' . $html . '</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previousErrors);

        if (!$loaded) {
            return self::plainTextFallback($html);
        }

        $root = $document->getElementById($wrapperId);
        if (!$root) {
            return self::plainTextFallback($html);
        }

        self::cleanChildren($root);
        $result = '';
        foreach ($root->childNodes as $child) {
            $result .= $document->saveHTML($child);
        }

        return trim($result);
    }

    public static function text($value)
    {
        return htmlspecialchars(
            trim((string) $value),
            ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5,
            'UTF-8'
        );
    }

    private static function cleanChildren($parent)
    {
        $node = $parent->firstChild;
        while ($node) {
            $next = $node->nextSibling;
            self::cleanNode($node);
            $node = $next;
        }
    }

    private static function cleanNode($node)
    {
        if ($node->nodeType === XML_TEXT_NODE) {
            $node->nodeValue = self::normaliseBreakableSpaces($node->nodeValue);
            return;
        }
        if ($node->nodeType !== XML_ELEMENT_NODE) {
            if ($node->parentNode) {
                $node->parentNode->removeChild($node);
            }
            return;
        }

        $tag = strtolower($node->nodeName);
        if (in_array($tag, self::REMOVE_WITH_CONTENT, true)) {
            $node->parentNode->removeChild($node);
            return;
        }

        self::cleanChildren($node);
        if (!in_array($tag, self::ALLOWED_TAGS, true)) {
            self::unwrap($node);
            return;
        }

        $attributes = array();
        foreach ($node->attributes as $attribute) {
            $attributes[strtolower($attribute->name)] = $attribute->value;
        }
        while ($node->attributes->length > 0) {
            $node->removeAttributeNode($node->attributes->item(0));
        }

        // PortixCallouts 1: retain only the four supported container types.
        if ($tag === 'div' && preg_match('/(?:^|\s)portix-callout(?:\s|$)/', (string) ($attributes['class'] ?? ''))
            && preg_match('/(?:^|\s)portix-callout-(advice|warning|equipment|note)(?:\s|$)/', (string) ($attributes['class'] ?? ''), $calloutType)) {
            $node->setAttribute('class', 'portix-callout portix-callout-' . $calloutType[1]);
            return;
        }

        if ($tag === 'div') {
            $classes = preg_split('/\s+/', trim((string) ($attributes['class'] ?? '')));
            if (!in_array('portix-video', $classes, true)) {
                self::unwrap($node);
                return;
            }
            $provider = strtolower(trim((string) ($attributes['data-provider'] ?? '')));
            $videoId = trim((string) ($attributes['data-video-id'] ?? ''));
            if (!self::isAllowedVideo($provider, $videoId)) {
                $node->parentNode->removeChild($node);
                return;
            }
            while ($node->firstChild) {
                $node->removeChild($node->firstChild);
            }
            $node->setAttribute('class', 'portix-video');
            $node->setAttribute('data-provider', $provider);
            $node->setAttribute('data-video-id', $videoId);
            $width = (string) ($attributes['data-width'] ?? '100');
            if (!in_array($width, array('33', '50', '66', '75', '100'), true)) {
                $width = '100';
            }
            $node->setAttribute('data-width', $width);
            $title = self::shortAttribute($attributes['data-title'] ?? '', 250);
            if ($title !== '') {
                $node->setAttribute('data-title', $title);
            }
            return;
        }

        // Keep Quill's Shift+Enter marker across save and re-edit.
        // The marker is recognized only on BR; ignore all other classes there.
        if ($tag === 'br' && preg_match('/(?:^|\s)portix-soft-break(?:\s|$)/', (string) ($attributes['class'] ?? ''))) {
            $node->setAttribute('class', 'portix-soft-break');
            return;
        }

        if (isset($attributes['class'])) {
            $classes = $tag === 'figure'
                ? self::allowedFigureClasses($attributes['class'])
                : self::allowedClasses($attributes['class']);
            if ($classes !== '') {
                $node->setAttribute('class', $classes);
            }
        }

        if ($tag === 'span' && isset($attributes['style'])) {
            $style = '';
            foreach (array('color', 'background-color') as $property) {
                $color = self::allowedTextColor($attributes['style'], $property);
                if ($color !== null) { $style .= $property . ': ' . $color . ';'; }
            }
            if ($style !== '') { $node->setAttribute('style', $style); }
        }

        if ($tag === 'span' && isset($attributes['class'])
            && preg_match('/(?:^|\s)portix-formula(?:\s|$)/', $attributes['class'])) {
            $latex = self::shortAttribute($attributes['data-latex'] ?? $node->textContent, 4000);
            if ($latex === '') {
                self::unwrap($node);
                return;
            }
            $node->setAttribute('class', 'portix-formula');
            $node->setAttribute('data-latex', $latex);
            if (($attributes['data-display'] ?? '') === '1') {
                $node->setAttribute('data-display', '1');
            }
            while ($node->firstChild) {
                $node->removeChild($node->firstChild);
            }
            $node->appendChild($node->ownerDocument->createTextNode($latex));
        }

        if ($tag === 'table') {
            $node->setAttribute('class', 'portix-table');
        }

        if (in_array($tag, array('figure', 'img', 'table'), true) && isset($attributes['data-width'])) {
            $width = filter_var($attributes['data-width'], FILTER_VALIDATE_INT);
            if ($width !== false && $width >= 10 && $width <= 100) {
                $node->setAttribute('data-width', (string) $width);
            }
        }

        if ($tag === 'caption') {
            $caption = self::shortAttribute($node->textContent, 500);
            while ($node->firstChild) {
                $node->removeChild($node->firstChild);
            }
            $node->appendChild($node->ownerDocument->createTextNode($caption));
        }

        if ($tag === 'th') {
            $scope = strtolower((string) ($attributes['scope'] ?? ''));
            if (in_array($scope, array('row', 'col', 'rowgroup', 'colgroup'), true)) {
                $node->setAttribute('scope', $scope);
            }
        }

        if ($tag === 'th' || $tag === 'td') {
            // Column geometry only: never admit arbitrary CSS from editorial HTML.
            if (isset($attributes['style']) && preg_match('/(?:^|;)\s*width\s*:\s*(\d+(?:\.\d{1,3})?)%\s*(?:;|$)/i', $attributes['style'], $match)) {
                $width = (float) $match[1];
                if ($width > 0 && $width <= 100) { $node->setAttribute('style', 'width: ' . $match[1] . '%;'); }
            }

            foreach (array('colspan', 'rowspan') as $spanAttribute) {
                if (!isset($attributes[$spanAttribute])) {
                    continue;
                }
                $span = (int) $attributes[$spanAttribute];
                if ($span >= 2 && $span <= 20) {
                    $node->setAttribute($spanAttribute, (string) $span);
                }
            }
        }

        if ($tag === 'li' && isset($attributes['data-list'])
            && in_array($attributes['data-list'], array('ordered', 'bullet', 'checked', 'unchecked'), true)) {
            $node->setAttribute('data-list', $attributes['data-list']);
        }

        if ($tag === 'a') {
            if (isset($attributes['href']) && self::isSafeUrl($attributes['href'])) {
                $node->setAttribute('href', trim($attributes['href']));
            }
            if (isset($attributes['title'])) {
                $title = function_exists('mb_substr')
                    ? mb_substr($attributes['title'], 0, 250, 'UTF-8')
                    : substr($attributes['title'], 0, 250);
                $node->setAttribute('title', $title);
            }
            if (isset($attributes['target']) && $attributes['target'] === '_blank') {
                $node->setAttribute('target', '_blank');
                $node->setAttribute('rel', 'noopener noreferrer');
            }
        }

        // Explicit, editorially chosen decorative images retain their intent
        // across save/re-edit. Never infer this flag from an empty alt.
        if ($tag === 'figure' && ($attributes['data-decorative'] ?? '') === '1') {
            $node->setAttribute('data-decorative', '1');
            foreach ($node->getElementsByTagName('img') as $decorativeImage) {
                $decorativeImage->setAttribute('alt', '');
            }
        }

        if ($tag === 'figcaption') {
            $caption = self::shortAttribute($node->textContent, 500);
            while ($node->firstChild) {
                $node->removeChild($node->firstChild);
            }
            $node->appendChild($node->ownerDocument->createTextNode($caption));
        }

        if ($tag === 'img') {
            if (!isset($attributes['src']) || !self::isSafeImageUrl($attributes['src'])) {
                $node->parentNode->removeChild($node);
                return;
            }
            $normalisedSource = preg_replace(
                '~^(?:\.\./|/)~',
                '',
                trim((string) $attributes['src'])
            );
            $node->setAttribute('src', (string) $normalisedSource);
            $node->setAttribute('alt', self::shortAttribute($attributes['alt'] ?? '', 250));
            if (isset($attributes['title'])) {
                $node->setAttribute('title', self::shortAttribute($attributes['title'], 250));
            }
            $node->setAttribute('class', self::allowedImageClasses($attributes['class'] ?? ''));
            $node->setAttribute('loading', 'lazy');
            $node->setAttribute('decoding', 'async');
        }

        if ($tag === 'figure' && $node->getElementsByTagName('img')->length === 0) {
            $node->parentNode->removeChild($node);
        }
    }

    private static function allowedClasses($value)
    {
        $allowed = array();
        foreach (preg_split('/\s+/', trim((string) $value)) as $class) {
            if (preg_match('/^ql-align-(center|right|justify)$/', $class)
                || preg_match('/^ql-indent-[1-8]$/', $class)
                || $class === 'ql-direction-rtl'
                || preg_match('/^ql-size-(small|large|huge)$/', $class)
                || preg_match('/^ql-font-(serif|monospace)$/', $class)
                || preg_match('/^portix-cell-valign-(top|middle|bottom)$/', $class)
                || $class === 'portix-callout-label'
                || $class === 'portix-callout-title'
                || $class === 'portix-formula') {
                $allowed[] = $class;
            }
        }
        return implode(' ', array_unique($allowed));
    }

    private static function allowedImageClasses($value)
    {
        $allowed = array('portix-content-image');
        $sizeFound = false;
        $alignmentFound = false;
        foreach (preg_split('/\s+/', trim((string) $value)) as $class) {
            if (!$sizeFound && preg_match('/^portix-image-size-(25|33|50|66|75|100)$/', $class)) {
                $allowed[] = $class;
                $sizeFound = true;
            } elseif (!$alignmentFound && preg_match('/^portix-image-align-(left|center|right)$/', $class)) {
                $allowed[] = $class;
                $alignmentFound = true;
            }
        }
        return implode(' ', $allowed);
    }

    private static function allowedFigureClasses($value)
    {
        $allowed = array('portix-image-figure');
        if (preg_match('/(?:^|\s)portix-image-wrap-none(?:\s|$)/', (string) $value)) { $allowed[] = 'portix-image-wrap-none'; }
        $sizeFound = false;
        $alignmentFound = false;
        foreach (preg_split('/\s+/', trim((string) $value)) as $class) {
            if (!$sizeFound && preg_match('/^portix-image-size-(25|33|50|66|75|100)$/', $class)) {
                $allowed[] = $class;
                $sizeFound = true;
            } elseif (!$alignmentFound && preg_match('/^portix-image-align-(left|center|right)$/', $class)) {
                $allowed[] = $class;
                $alignmentFound = true;
            }
        }
        return implode(' ', $allowed);
    }

    private static function allowedTextColor($style, $property = 'color')
    {
        $color = null;
        foreach (explode(';', (string) $style) as $declaration) {
            $parts = explode(':', $declaration, 2);
            if (count($parts) !== 2 || strtolower(trim($parts[0])) !== $property) {
                continue;
            }
            $value = trim($parts[1]);
            if (preg_match('/^#(?:[0-9a-f]{3}|[0-9a-f]{6})$/i', $value)) {
                $color = strtolower($value);
                continue;
            }
            if (preg_match(
                '/^rgb\(\s*([0-9]{1,3})\s*,\s*([0-9]{1,3})\s*,\s*([0-9]{1,3})\s*\)$/i',
                $value,
                $matches
            )) {
                $red = (int) $matches[1];
                $green = (int) $matches[2];
                $blue = (int) $matches[3];
                if ($red <= 255 && $green <= 255 && $blue <= 255) {
                    $color = 'rgb(' . $red . ', ' . $green . ', ' . $blue . ')';
                }
            }
        }
        return $color;
    }

    private static function isSafeUrl($url)
    {
        $url = trim(html_entity_decode((string) $url, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if ($url === '' || strncmp($url, '//', 2) === 0
            || preg_match('/[\x00-\x1F\x7F]/', $url)) {
            return false;
        }

        $scheme = parse_url($url, PHP_URL_SCHEME);
        if ($scheme === null || $scheme === false || $scheme === '') {
            return true;
        }
        return in_array(strtolower($scheme), array('http', 'https', 'mailto'), true);
    }

    private static function isAllowedVideo($provider, $videoId)
    {
        if ($provider === 'youtube') {
            return preg_match('/^[A-Za-z0-9_-]{11}$/', $videoId) === 1;
        }
        if ($provider === 'vimeo') {
            return preg_match('/^[0-9]{5,12}$/', $videoId) === 1;
        }
        if ($provider === 'dailymotion') {
            return preg_match('/^[A-Za-z0-9]{5,16}$/', $videoId) === 1;
        }
        return false;
    }

    private static function isSafeImageUrl($url)
    {
        $url = trim(html_entity_decode((string) $url, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        return preg_match(
            '~^(?:\.\./|/)?var/uploads/images/(?:[a-f0-9]{32}\.(?:jpe?g|png|webp)|[a-z0-9](?:[a-z0-9-]{0,71}[a-z0-9])?-[a-f0-9]{8}\.webp)(?:\?p3media=[a-f0-9]{16})?$~i',
            $url
        ) === 1;
    }

    private static function shortAttribute($value, $length)
    {
        $value = trim(strip_tags((string) $value));
        return function_exists('mb_substr')
            ? mb_substr($value, 0, $length, 'UTF-8')
            : substr($value, 0, $length);
    }

    private static function unwrap($node)
    {
        $parent = $node->parentNode;
        if (!$parent) {
            return;
        }
        while ($node->firstChild) {
            $parent->insertBefore($node->firstChild, $node);
        }
        $parent->removeChild($node);
    }

    private static function plainTextFallback($html)
    {
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = self::normaliseBreakableSpaces($text);
        return '<p>' . nl2br(self::text($text), false) . '</p>';
    }

    private static function normaliseBreakableSpaces($value)
    {
        return str_replace(
            array("\xC2\xA0", "\xE2\x80\xAF"),
            ' ',
            (string) $value
        );
    }
}
