<?php
/** Security-focused smoke test for WYSIWYG HTML filtering. */
if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Ce test doit être exécuté en ligne de commande.\n");
    exit(1);
}

require_once dirname(__DIR__) . '/includes/class/html_sanitizer.class.php';

$plainText = PortixHtmlSanitizer::clean("Première ligne\nDeuxième ligne");
if (strpos($plainText, '<br>') === false
    || strpos($plainText, 'Première ligne') === false
    || strpos($plainText, 'Deuxième ligne') === false) {
    fwrite(STDERR, "Les retours à la ligne du texte brut ne sont pas conservés.\n");
    exit(1);
}

$nonBreakingText = PortixHtmlSanitizer::clean(
    '<p>Ces' . "\xC2\xA0" . 'mots' . "\xE2\x80\xAF" . 'doivent pouvoir revenir à la ligne.</p>'
);
if (strpos($nonBreakingText, "\xC2\xA0") !== false
    || strpos($nonBreakingText, "\xE2\x80\xAF") !== false
    || strpos($nonBreakingText, 'Ces mots doivent') === false) {
    fwrite(STDERR, "Les espaces insécables du contenu ne sont pas normalisés.\n");
    exit(1);
}

$input = '<h2 onclick="alert(1)">Titre</h2>'
    . '<script>alert(2)</script>'
    . '<p class="bad ql-align-center" style="position:fixed">Texte</p>'
    . '<p><span style="color: rgb(230, 0, 0); position:fixed">Texte coloré</span></p>'
    . '<p><span style="color: expression(alert(1))">Couleur dangereuse</span></p>'
    . '<p><span class="ql-size-small">Petit texte</span><br>Suite</p>'
    . '<a href="javascript:alert(3)" target="_blank">Mauvais</a>'
    . '<a href="https://example.org" target="_blank">Valide</a>'
    . '<img src="https://example.org/externe.jpg" onerror="alert(4)">'
    . '<img class="portix-image-size-50 portix-image-align-left classe-refusee" '
    . 'src="../var/uploads/images/0123456789abcdef0123456789abcdef.png" alt="Locale">'
    . '<figure class="portix-image-figure portix-image-size-33 portix-image-align-right classe-refusee">'
    . '<img src="var/uploads/images/0123456789abcdef0123456789abcdef.png" alt="Vue détaillée">'
    . '<figcaption>Légende publique</figcaption></figure>'
    . '<img src="var/uploads/images/chevalet-telecaster-01234567.webp" alt="Nom SEO">'
    . '<div class="portix-video" data-provider="youtube" data-video-id="dQw4w9WgXcQ" data-title="Démo"><iframe src="https://evil.example"></iframe></div>'
    . '<div class="portix-video" data-provider="evil" data-video-id="12345">Refusée</div>'
    . '<iframe src="https://www.youtube.com/embed/dQw4w9WgXcQ"></iframe>';

$clean = PortixHtmlSanitizer::clean($input);
$forbidden = array(
    'script', 'onclick', 'onerror', 'javascript:', 'position:fixed',
    'expression(', 'classe-refusee', 'https://example.org/externe.jpg',
);
foreach ($forbidden as $value) {
    if (stripos($clean, $value) !== false) {
        fwrite(STDERR, 'Filtrage HTML échoué pour : ' . $value . "\n");
        exit(1);
    }
}
foreach (array('Titre', 'Texte', 'Mauvais', 'Valide') as $value) {
    if (strpos($clean, $value) === false) {
        fwrite(STDERR, 'Texte légitime absent : ' . $value . "\n");
        exit(1);
    }
}
if (class_exists('DOMDocument')) {
    if (strpos($clean, 'ql-align-center') === false
        || strpos($clean, 'href="https://example.org"') === false
        || strpos($clean, 'rel="noopener noreferrer"') === false
        || strpos($clean, 'var/uploads/images/0123456789abcdef0123456789abcdef.png') === false
        || strpos($clean, 'var/uploads/images/chevalet-telecaster-01234567.webp') === false
        || strpos($clean, 'class="portix-content-image ') === false
        || strpos($clean, 'portix-image-size-50') === false
        || strpos($clean, 'portix-image-align-left') === false
        || strpos($clean, 'class="portix-image-figure portix-image-size-33 portix-image-align-right"') === false
        || strpos($clean, 'alt="Vue détaillée"') === false
        || strpos($clean, '<figcaption>Légende publique</figcaption>') === false
        || strpos($clean, 'style="color: rgb(230, 0, 0);"') === false
        || strpos($clean, 'ql-size-small') === false
        || strpos($clean, '<br>') === false
        || strpos($clean, 'loading="lazy"') === false
        || strpos($clean, 'class="portix-video"') === false
        || strpos($clean, 'data-provider="youtube"') === false
        || strpos($clean, 'data-video-id="dQw4w9WgXcQ"') === false
        || strpos($clean, 'data-title="Démo"') === false
        || strpos($clean, '<iframe') !== false
        || strpos($clean, 'data-provider="evil"') !== false) {
        fwrite(STDERR, "Attributs WYSIWYG autorisés absents.\n");
        exit(1);
    }
}

$rich = PortixHtmlSanitizer::clean('<h1>Titre natif</h1><h6>Petit titre</h6><hr onclick="bad()">'
    . '<span class="ql-size-huge ql-font-serif" style="background-color: rgb(255, 255, 0);position:fixed">Texte</span>'
    . '<span style="background-color:url(javascript:bad())">Refusé</span>'
    . '<figure class="portix-image-figure portix-image-wrap-none" data-width="42"><img src="var/uploads/images/0123456789abcdef0123456789abcdef.png"><figcaption>Légende</figcaption></figure>'
    . '<table data-width="50"><tbody><tr><th scope="row" class="portix-cell-valign-middle">A</th><td>B</td></tr></tbody></table>'
    . '<figure data-width="101"><img src="var/uploads/images/0123456789abcdef0123456789abcdef.png"></figure>');
if (class_exists('DOMDocument')) {
    foreach (array('<h1>', '<h6>', '<hr>', 'ql-size-huge', 'ql-font-serif', 'background-color: rgb(255, 255, 0);', 'data-width="42"', 'data-width="50"', 'portix-image-wrap-none', 'portix-cell-valign-middle', 'scope="row"') as $marker) {
        if (strpos($rich, $marker) === false) { fwrite(STDERR, "Format enrichi absent : " . $marker . "\n"); exit(1); }
    }
    foreach (array('onclick', 'javascript:', 'position:fixed', 'data-width="101"') as $marker) {
        if (strpos($rich, $marker) !== false) { fwrite(STDERR, "Format dangereux conservé : " . $marker . "\n"); exit(1); }
    }
    if (PortixHtmlSanitizer::clean($rich) !== $rich) { fwrite(STDERR, "Le nettoyage enrichi doit être idempotent.\n"); exit(1); }
}

$decorativeHtml = PortixHtmlSanitizer::clean(
    '<figure class="portix-image-figure" data-decorative="1" onclick="bad()">'
    . '<img src="var/uploads/images/0123456789abcdef0123456789abcdef.png" alt="Ne doit pas être annoncé">'
    . '<figcaption>Légende toujours visible</figcaption></figure>'
);
if (class_exists('DOMDocument')) {
    if (!str_contains($decorativeHtml, 'data-decorative="1"')
        || !str_contains($decorativeHtml, 'alt=""')
        || !str_contains($decorativeHtml, '<figcaption>Légende toujours visible</figcaption>')
        || str_contains($decorativeHtml, 'onclick')
        || PortixHtmlSanitizer::clean($decorativeHtml) !== $decorativeHtml) {
        throw new RuntimeException('Accessibilité : image décorative non conservée ou assainissement non idempotent.');
    }
}

$editorScript = (string) file_get_contents(dirname(__DIR__) . '/var/editor/portix-editor.js');
$editorStyles = (string) file_get_contents(dirname(__DIR__) . '/var/editor/portix-editor.css');
foreach (array(
    'portix-link-target-option',
    "checkbox.type = 'checkbox'",
    "link.setAttribute('target', '_blank')",
    'portixSoftBreak',
    "applyFormat('alt', altInput.value",
    "'image-caption'",
    'portix-image-move',
    'moveImage(direction)',
    'syncCaptionPreview',
    "surface.addEventListener('drop'",
    'event.stopPropagation()',
    'createTableTools',
    'createFormulaTools',
    'createMarkdownTools',
    'createVideoTools',
    'registerVideoFormat',
    'normaliseVideoData',
    'youtube-nocookie.com',
    "quill.insertEmbed(index, 'portix-video'",
    'markdownToHtml',
    "tableModule.insertTable",
    "quill.insertEmbed(index, 'formula'",
    "dangerouslyPasteHTML",
) as $editorMarker) {
    if (strpos($editorScript, $editorMarker) === false) {
        fwrite(STDERR, 'Fonction WYSIWYG absente : ' . $editorMarker . "\n");
        exit(1);
    }
}
if (strpos($editorScript, "'image-alt'") !== false) {
    fwrite(STDERR, "Le texte alternatif ne doit pas redéfinir le format alt natif de Quill.\n");
    exit(1);
}
if (strpos($editorStyles, 'a[target="_blank"]::after') === false
    || strpos($editorStyles, 'p[data-portix-caption]:has(> img)::after') === false
    || strpos($editorStyles, 'p[data-portix-caption]:has(> img.portix-image-align-left)') === false
    || strpos($editorStyles, 'p[data-portix-caption]:has(> img) > img') === false) {
    fwrite(STDERR, "Un repère visuel du WYSIWYG est absent.\n");
    exit(1);
}
$contentStyles = (string) file_get_contents(dirname(__DIR__) . '/var/editor/portix-content.css');
if (strpos($editorStyles, '.ql-editor p + p') === false
    || strpos($contentStyles, '.news-rich-text p + p') === false
    || strpos($contentStyles, '.portix-image-figure > figcaption') === false
    || strpos($contentStyles, '.portix-image-figure.portix-image-align-left') === false
    || strpos($contentStyles, '.portix-image-figure.portix-image-align-right') === false) {
    fwrite(STDERR, "La distinction visuelle entre paragraphe et saut de ligne est absente.\n");
    exit(1);
}

if (strpos($editorStyles, '.portix-rich-tools') === false
    || strpos($editorStyles, '.portix-table-tools') === false
    || strpos($editorStyles, '.portix-editor-dialog') === false
    || strpos($contentStyles, 'table.portix-table') === false
    || strpos($contentStyles, '.portix-formula') === false
    || strpos($contentStyles, '.portix-video') === false
    || strpos($editorStyles, '.portix-video') === false) {
    fwrite(STDERR, "Les styles des tableaux/formules/Markdown sont incomplets.\n");
    exit(1);
}

$sanitizerSource = (string) file_get_contents(dirname(__DIR__) . '/includes/class/html_sanitizer.class.php');
foreach (array(
    "'table', 'caption', 'thead', 'tbody', 'tfoot', 'tr', 'th', 'td'",
    '$tag === \'table\'',
    '$tag === \'th\' || $tag === \'td\'',
    '$class === \'portix-formula\'',
    'isAllowedVideo',
    "data-video-id",
) as $sanitizerMarker) {
    if (strpos($sanitizerSource, $sanitizerMarker) === false) {
        fwrite(STDERR, 'Sanitizer enrichi incomplet : ' . $sanitizerMarker . "\n");
        exit(1);
    }
}

$uploadsHtaccess = (string) file_get_contents(dirname(__DIR__) . '/var/uploads/images/.htaccess');
if (strpos($uploadsHtaccess, '[a-f0-9]{8}\\.webp') === false) {
    fwrite(STDERR, "Le serveur doit autoriser les noms d’images SEO générés par Portix.\n");
    exit(1);
}

echo "Filtrage HTML WYSIWYG : OK\n";
