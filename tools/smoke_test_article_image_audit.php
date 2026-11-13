<?php
declare(strict_types=1);

require dirname(__DIR__) . '/var/modules/media/src/ArticleImageAudit.php';

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec('CREATE TABLE articles (id INTEGER PRIMARY KEY, title TEXT, slug TEXT, status TEXT, body TEXT)');
$insert = $pdo->prepare('INSERT INTO articles (id,title,slug,status,body) VALUES (?,?,?,?,?)');
$insert->execute([1, 'La forme du corps', 'forme-corps', 'published',
    '<figure class="portix-image-figure"><img src="var/uploads/images/a.webp" alt=""><figcaption>Vue du corps</figcaption></figure>'
    . '<figure data-decorative="1"><img src="var/uploads/images/b.webp" alt=""></figure>'
    . '<p><img src="var/uploads/images/c.webp" alt="Profil arrière du manche"></p>'
    . '<img src="var/uploads/images/d.webp">' ]);
$insert->execute([2, 'Article brouillon', 'brouillon', 'draft',
    '<p>Sans image</p>']);
$before = $pdo->query('SELECT body FROM articles WHERE id=1')->fetchColumn();
$report = (new \Portix\Media\ArticleImageAudit($pdo))->report();
function auditCheck(bool $okay, string $label): void {
    if (!$okay) { throw new RuntimeException($label); }
}
auditCheck($report['articles'] === 2, 'All articles are scanned');
auditCheck($report['images'] === 4, 'Every image counted');
auditCheck($report['described'] === 1, 'Described image counted');
auditCheck($report['decorative'] === 1, 'Explicit decorative image counted');
auditCheck(count($report['issues']) === 2, 'Empty and missing alt reported separately');
auditCheck($report['issues'][0]['id'] === 1
    && $report['issues'][0]['position'] === 1
    && $report['issues'][0]['reason'] === 'vide'
    && $report['issues'][0]['caption'] === 'Vue du corps', 'Caption and location retained');
auditCheck($report['issues'][1]['position'] === 4
    && $report['issues'][1]['reason'] === 'absent', 'Missing attribute flagged');
auditCheck($pdo->query('SELECT body FROM articles WHERE id=1')->fetchColumn() === $before,
    'Scan never modifies editorial HTML');
auditCheck($report['errors'] === [], 'No parser errors');
echo "Audit des attributs alt : lecture seule, légendes, décoratif et images sans alt : OK\n";
