<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require dirname(__DIR__).'/includes/class/html_sanitizer.class.php';
$input='<table><caption>Repères</caption><thead><tr><th scope="col" colspan="2" style="width: 60.125%; background:url(javascript:alert(1))" onclick="evil()">Titre</th></tr></thead><tbody><tr><td rowspan="2" style="width: 40%; position:fixed">Texte <a href="https://example.org" target="_blank">lien</a></td><td style="width:101%">B</td></tr><tr><td style="width:expression(alert(1))">C</td></tr></tbody></table>';
$html=PortixHtmlSanitizer::clean($input);
foreach(['<caption>Repères</caption>','scope="col"','colspan="2"','rowspan="2"','width: 60.125%;','width: 40%;','https://example.org'] as $value){if(!str_contains($html,$value))throw new RuntimeException('Missing '.$value);}
foreach(['onclick','javascript:','position:','expression(','101%','background:','contenteditable','data-portix'] as $value){if(str_contains($html,$value))throw new RuntimeException('Unsafe '.$value);}
if(PortixHtmlSanitizer::clean($html)!==$html)throw new RuntimeException('Sanitizer roundtrip');
echo 'Table geometry: widths, headings, spans, caption, links, CSS/XSS filtering and idempotence PASS (PHP '.PHP_VERSION.")\n";
