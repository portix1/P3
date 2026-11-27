<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit(1);
}
require dirname(__DIR__, 4) . '/bootstrap/autoload.php';
require dirname(__DIR__) . '/src/AgentClassifier.php';
require dirname(__DIR__) . '/src/DailyAudience.php';

use Portix\Insights\AgentClassifier as Agents;
use Portix\Insights\TrafficSources as Sources;

$check = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

// 1. User-Agent classification: one case per type, ordinary browsers stay readers.
$agents = [
    'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:133.0) Gecko/20100101 Firefox/133.0' => Agents::BROWSER,
    'Mozilla/5.0 (iPhone; CPU iPhone OS 18_1 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.1 Mobile/15E148 Safari/604.1' => Agents::BROWSER,
    'Mozilla/5.0 (Linux; Android 14; SM-S916B) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0 Mobile Safari/537.36' => Agents::BROWSER,
    '' => Agents::BROWSER,
    'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)' => Agents::SEARCH,
    'Mozilla/5.0 (compatible; bingbot/2.0; +http://www.bing.com/bingbot.htm)' => Agents::SEARCH,
    'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/13.1.1 Safari/605.1.15 (Applebot/0.1)' => Agents::SEARCH,
    'Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; GPTBot/1.2; +https://openai.com/gptbot)' => Agents::AI_TRAINING,
    'Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; ClaudeBot/1.0; +claudebot@anthropic.com)' => Agents::AI_TRAINING,
    'CCBot/2.0 (https://commoncrawl.org/faq/)' => Agents::AI_TRAINING,
    'Mozilla/5.0 (compatible; Applebot-Extended/0.1)' => Agents::AI_TRAINING,
    'Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko); compatible; ChatGPT-User/1.0; +https://openai.com/bot' => Agents::AI_USER,
    'Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; Claude-User/1.0; +claude-user@anthropic.com)' => Agents::AI_USER,
    'Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; Perplexity-User/1.0)' => Agents::AI_USER,
    'Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko); compatible; OAI-SearchBot/1.0' => Agents::AI_USER,
    'Mozilla/5.0 (Windows NT 10.0) Chrome/131.0 Safari/537.36 Anthropic' => Agents::AI_USER,
    'facebookexternalhit/1.1 (+http://www.facebook.com/externalhit_uatext.php)' => Agents::OTHER,
    'Mozilla/5.0 (X11; Linux x86_64) HeadlessChrome/131.0 Safari/537.36' => Agents::OTHER,
    'curl/8.5.0' => Agents::OTHER,
];
foreach ($agents as $agent => $expected) {
    $check(Agents::classify($agent) === $expected, 'Classement inattendu pour « ' . $agent . ' »');
}

// 2. Source categories and utm_source filtering.
$sources = [
    ['www.google.com', null, Sources::SEARCH], ['www.google.be', null, Sources::SEARCH],
    ['duckduckgo.com', null, Sources::SEARCH], ['search.brave.com', null, Sources::SEARCH],
    ['chatgpt.com', null, Sources::AI], ['gemini.google.com', null, Sources::AI],
    ['www.perplexity.ai', null, Sources::AI], ['claude.ai', null, Sources::AI],
    ['direct', 'chatgpt.com', Sources::AI], ['direct', 'newsletter', Sources::DIRECT],
    ['m.facebook.com', null, Sources::SITE], ['notgoogle.com', null, Sources::SITE],
    ['internal', null, Sources::INTERNAL], ['direct', null, Sources::DIRECT],
];
foreach ($sources as [$referrer, $utm, $expected]) {
    $check(Sources::category($referrer, $utm) === $expected, 'Provenance inattendue pour ' . $referrer . '/' . ($utm ?? '-'));
}
$check(Sources::utmSource('ChatGPT.com') === 'chatgpt.com', 'utm_source normalisé');
foreach ([null, '', ['x'], '<script>', str_repeat('a', 65), 'a b'] as $invalid) {
    $check(Sources::utmSource($invalid) === null, 'utm_source invalide refusé');
}

// 3. Robots are counted apart and never as readers or Home popularity.
$database = new \Portix\Infrastructure\Database(':memory:');
(new \Portix\Module\ModuleSchemaInstaller($database))->install('insights', dirname(__DIR__) . '/database');
$audience = new \Portix\Insights\DailyAudience($database);
$pdo = $database->connection();
$day = strtotime('today UTC');
$retention = ['analytics_retention_days' => 30, 'application_retention_days' => 7, 'audit_retention_days' => 30];

$audience->recordRobot('/', 'Accueil', Agents::SEARCH, $retention, $day + 10);
$audience->recordRobot('frise.html', 'Guitare qui frise', Agents::AI_USER, $retention, $day + 20);
$audience->recordRobot('frise.html', 'Guitare qui frise', Agents::AI_USER, $retention, $day + 30);
$check((int) $pdo->query('SELECT COALESCE(SUM(views + visitors), 0) FROM analytics_v2_daily')->fetchColumn() === 0,
    'Un robot ne doit compter ni page vue ni visiteur');
$check((int) $pdo->query('SELECT COUNT(*) FROM analytics_pages')->fetchColumn() === 0,
    'Un robot ne doit pas alimenter les compteurs d’Accueil');
$check((int) $pdo->query('SELECT COUNT(*) FROM analytics_v2_seen')->fetchColumn() === 0,
    'Aucune empreinte pour un robot');
$aiUser = $audience->ranking('robot', 1, 20, Agents::AI_USER);
$check(count($aiUser) === 1 && (int) $aiUser[0]['total'] === 2, 'Passages IA pour un utilisateur comptés');
$aiPages = $audience->ranking('robot_page', 1, 20, Agents::AI_USER . '|');
$check(count($aiPages) === 1 && $aiPages[0]['dimension'] === Agents::AI_USER . '|frise.html'
    && (int) $aiPages[0]['total'] === 2 && $aiPages[0]['title'] === 'Guitare qui frise', 'Page lue par une IA comptée');

// 4. Readers: categories, utm_source and source by entry page on the first page of the day only.
$audience->record('frise.html', 'Guitare qui frise', 'frise', 'chatgpt.com', '192.0.2.10', 'Firefox', $retention, $day + 100,
    null, 'chatgpt.com');
$audience->record('truss.html', 'Truss rod', 'truss', 'internal', '192.0.2.10', 'Firefox', $retention, $day + 200);
$audience->record('/', 'Accueil', null, 'www.google.be', '198.51.100.7', 'Safari', $retention, $day + 300);
$audience->record('/', 'Accueil', null, 'direct', '203.0.113.5', 'Edge', $retention, $day + 400);
$totals = static fn (string $kind): array => array_column($audience->ranking($kind, 1, 50), 'total', 'dimension');
$check($totals('source') == [Sources::AI => 1, Sources::SEARCH => 1, Sources::DIRECT => 1],
    'Provenance classée uniquement pour la première page du jour');
$check($totals('utm') == ['chatgpt.com' => 1], 'utm_source enregistré');
$check($totals('entry_source') == ['ai|frise.html' => 1, 'search|/' => 1, 'direct|/' => 1],
    'Provenance par page d’entrée');
$check((int) $pdo->query('SELECT views FROM analytics_v2_daily')->fetchColumn() === 4, 'Pages vues des lecteurs inchangées');

// 5. Export details carry every counter with its day.
$kinds = array_unique(array_column($audience->details(1), 'kind'));
foreach (['robot', 'robot_page', 'source', 'utm', 'entry_source', 'entry', 'referrer', 'article'] as $kind) {
    $check(in_array($kind, $kinds, true), 'Export détaillé sans ' . $kind);
}

echo "Statistiques : classement des robots et des IA, provenance, utm_source, page d'entrée et export vérifiés.\n";
