<?php
declare(strict_types=1);

use PortixModule\Search\SearchRepository;

require_once dirname(__DIR__) . '/src/SearchRepository.php';

if (!extension_loaded('pdo_sqlite') || !extension_loaded('mbstring')) {
    fwrite(STDERR, "PDO_SQLITE et MBSTRING requis.\n");
    exit(2);
}

$source = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$source->exec('CREATE TABLE article_categories (id INTEGER PRIMARY KEY, name TEXT)');
$source->exec('CREATE TABLE articles ('
    . 'id INTEGER PRIMARY KEY, category_id INTEGER, title TEXT, slug TEXT, '
    . 'summary TEXT, body TEXT, status TEXT, updated_at TEXT)');
$source->exec('CREATE TABLE news ('
    . 'id INTEGER PRIMARY KEY, title TEXT, slug TEXT, intro TEXT, body TEXT, status TEXT, updated_at TEXT)');
$source->exec("INSERT INTO article_categories VALUES (1, 'Boutique')");

$articles = [
    [1, null, 'Le chevalet', 'chevalet', '',
        '<h2>Avant de commencer</h2><p>Prenez le temps de lire chaque étape.</p>'
        . '<p>Sur une guitare, les <strong>réglages</strong> d’action corrigent le confort de jeu.</p>'
        . '<p>Ne mélangez pas ces opérations.</p>', 'published'],
    [2, null, 'Câblage semi-creux, gain & volume : 100 %', 'cablage', '',
        '<p>Ceci ne parle que de potentiomètres.</p>', 'published'],
    [3, null, 'Le corps', 'corps', '',
        '<p>Une guitare semi-creuse &amp; légère garde l’éclat d’origine.</p>', 'published'],
    [4, null, 'Résumé invisible', 'resume', 'termesynthese',
        '<p>Voici le contenu réellement affiché.</p>', 'published'],
    [5, 1, 'Les essences du bois', 'essences', '', '<p>Le choix du matériau.</p>', 'published'],
    [6, null, 'Confidentiel', 'brouillon', '', '<p>Contenu confidentiel.</p>', 'draft'],
    [7, null, 'Deux étapes', 'etapes', '',
        '<p>Frettage d’un manche.</p><p>Polissage des bords.</p>', 'published'],
    [8, null, 'La finition', 'finition', '',
        '<p>Patience … puis respectez la finition sans empressement.</p>', 'published'],
];
$insert = $source->prepare('INSERT INTO articles '
    . '(id, category_id, title, slug, summary, body, status, updated_at) '
    . 'VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
foreach ($articles as $article) {
    $insert->execute([...$article, '2026-10-02T06:00:00Z']);
}
$intro = '<p>Les nouveautés concernent la lutherie électrique cette semaine.</p>';
$source->prepare('INSERT INTO news VALUES (?, ?, ?, ?, ?, ?, ?)')->execute([
    1, 'Cette semaine', 'cette-semaine', $intro, '<p>Le détail du programme.</p>',
    'published', '2026-10-02T06:00:00Z',
]);

$index = sys_get_temp_dir() . '/portix-text-fragments-' . bin2hex(random_bytes(5)) . '.sqlite';
$checks = 0;
$check = static function (bool $condition, string $message) use (&$checks): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
    $checks++;
};
$findResult = static function (SearchRepository $repository, string $query, string $slug, string $kind = 'article'): array {
    foreach ($repository->search($query, $kind) as $result) {
        if ($result['slug'] === $slug) {
            return $result;
        }
    }
    throw new RuntimeException('Résultat manquant : ' . $query . ' / ' . $slug);
};
$fragment = static function (array $result) use ($check): string {
    $parts = explode('#:~:text=', $result['url'], 2);
    $check(count($parts) === 2 && $parts[1] !== '', 'Fragment absent ou vide.');

    $url = parse_url($parts[0]);
    $check(($url['path'] ?? '') === $result['slug'] . '.html', 'Route du résultat altérée.');

    parse_str((string) ($url['query'] ?? ''), $query);
    $fallback = (string) ($query['p3hl'] ?? '');
    $check($fallback !== '', 'Passage de repli p3hl absent.');
    $check($fallback === rawurldecode($parts[1]), 'Le repli et le fragment texte doivent viser le même passage.');

    $check(preg_match('/[\x01-\x03]/', $result['excerpt'] . $result['url']) !== 1, 'Marqueur exposé au visiteur.');
    $check(!str_contains($parts[1], '-') && !str_contains($parts[1], '&') && !str_contains($parts[1], ','),
        'Ponctuation réservée non encodée.');
    return rawurldecode($parts[1]);
};

try {
    $repository = new SearchRepository($source, $index);
    $sync = $repository->sync(true);
    if ($sync['mode'] === 'fts5') {
        $result = $findResult($repository, 'reg', 'chevalet');
        $text = $fragment($result);
        $check(str_contains($text, 'réglages'), 'Le préfixe doit cibler le mot complet avec son accent.');
        $check(str_contains('Sur une guitare, les réglages d’action corrigent le confort de jeu.', $text),
            'Le passage ne correspond pas au texte affiché.');
        $check(mb_strlen($text) > mb_strlen('réglages'), 'Le lien doit inclure du contexte.');
    }

    foreach (['fts5', 'fallback'] as $mode) {
        if ($mode === 'fts5' && $sync['mode'] !== 'fts5') {
            continue;
        }
        if ($mode === 'fallback') {
            // Simuler un SQLite sans FTS5, sans changer le moteur installé.
            (new ReflectionProperty($repository, 'fts5Available'))->setValue($repository, false);
        }
        $text = $fragment($findResult($repository, 'régl', 'chevalet'));
        $check(str_contains($text, 'réglages'), 'Mot complet absent en mode ' . $mode);
        $text = $fragment($findResult($repository, 'semi', 'cablage'));
        $check($text === 'Câblage semi-creux, gain & volume : 100 %', 'Repli sur le titre incorrect.');
        $text = $fragment($findResult($repository, 'légè', 'corps'));
        $check(str_contains('Une guitare semi-creuse & légère garde l’éclat d’origine.', $text),
            'Entité HTML ou tiret mal traité.');
        $check(str_contains($text, '&') && str_contains($text, 'semi-creuse'), 'Contexte incomplet.');
        $text = $fragment($findResult($repository, 'termesynthese', 'resume'));
        $check($text === 'Résumé invisible', 'Un résumé non affiché ne doit pas être ciblé.');
        $text = $fragment($findResult($repository, 'boutique', 'essences'));
        $check($text === 'Les essences du bois', 'Repli après une correspondance de catégorie incorrect.');
        $text = $fragment($findResult($repository, 'frettage polissage', 'etapes'));
        $check(str_contains('Frettage d’un manche.', $text) || str_contains('Polissage des bords.', $text),
            'Un fragment exact ne doit pas traverser deux paragraphes.');
        $text = $fragment($findResult($repository, 'patience', 'finition'));
        $check(str_contains($text, '…'), 'Les points de suspension réels doivent être conservés.');
        $text = $fragment($findResult($repository, 'lutherie', 'cette-semaine', 'news'));
        $check(str_contains('Les nouveautés concernent la lutherie électrique cette semaine.', $text),
            'Introduction de l’actualité incorrecte.');
        $check(str_contains($text, 'lutherie'), 'Mot recherché absent de l’introduction ciblée.');
        $check($repository->search('confidentiel') === [], 'Un brouillon ne doit pas être indexé.');
        $check($repository->search('zzzintrouvable') === [], 'Recherche vide incohérente.');
    }
    echo 'Fragments de texte : OK — ' . $checks . ' contrôles, FTS5 et compatibilité.' . PHP_EOL;
} catch (Throwable $error) {
    fwrite(STDERR, 'Fragments de texte : ÉCHEC — ' . $error->getMessage() . PHP_EOL);
    exit(1);
} finally {
    @unlink($index);
    @unlink($index . '-shm');
    @unlink($index . '-wal');
}
