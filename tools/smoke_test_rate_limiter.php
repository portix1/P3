<?php
declare(strict_types=1);

use Portix\Infrastructure\Database;
use Portix\Infrastructure\Migrator;
use Portix\Security\RateLimiter;

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Ce test doit être exécuté en ligne de commande.\n");
    exit(1);
}

require dirname(__DIR__) . '/bootstrap/autoload.php';
require_once dirname(__DIR__) . '/includes/class/security.class.php';

$temporaryRoot = rtrim(sys_get_temp_dir(), '/\\') . DIRECTORY_SEPARATOR
    . 'portix-rate-limiter-smoke-' . getmypid() . '-' . bin2hex(random_bytes(4));
$database = null;
$pdo = null;
$status = 0;

try {
    $database = new Database($temporaryRoot . '/database/portix2.sqlite');
    (new Migrator($database))->migrate();
    $pdo = $database->connection();
    $now = 2_000_000_000;
    $clock = static function () use (&$now): int {
        return $now;
    };
    $limiter = new RateLimiter($pdo, $clock);

    assertRateSame(0, $limiter->remaining('login:account'), 'aucun blocage initial');
    assertRateSame(1, $limiter->recordFailures(['login:account']), 'premier échec ralenti une seconde');
    assertRateSame(1, $limiter->remaining('login:account'), 'blocage persistant après le premier échec');
    $now++;
    assertRateSame(2, $limiter->recordFailures(['login:account']), 'progression exponentielle du délai');
    $now += 901;
    assertRateSame(1, $limiter->recordFailures(['login:account']), 'historique oublié après quinze minutes');
    $limiter->clear('login:account');
    assertRateSame(0, $limiter->remaining('login:account'), 'clé de compte effacée après succès');

    $now += 100;
    assertRateSame(0, $limiter->consumeMany(['contact:ip'], 60, 1), 'premier contact accepté');
    assertRateSame(60, $limiter->consumeMany(['contact:ip'], 60, 1), 'second contact refusé dans la fenêtre');
    $now += 59;
    assertRateSame(1, $limiter->consumeMany(['contact:ip'], 60, 1), 'fin de fenêtre calculée');
    $now++;
    assertRateSame(0, $limiter->consumeMany(['contact:ip'], 60, 1), 'quota renouvelé après la fenêtre');

    assertRateSame(0, $limiter->consumeMany(['reset:blocked'], 60, 1), 'quota de référence consommé');
    assertRateTrue(
        $limiter->consumeMany(['reset:untouched', 'reset:blocked'], 60, 1) > 0,
        'quota multiple refusé atomiquement'
    );
    assertRateSame(
        0,
        (int) $pdo->query("SELECT COUNT(*) FROM rate_limits WHERE rate_key = 'reset:untouched'")->fetchColumn(),
        'aucune consommation partielle lors d’un refus'
    );

    $now += 100;
    $limiter->recordFailures(['purge:old']);
    $now += 100;
    assertRateTrue($limiter->purge(99) >= 1, 'purge des anciennes entrées');
    assertRateSame(
        0,
        (int) $pdo->query("SELECT COUNT(*) FROM rate_limits WHERE rate_key = 'purge:old'")->fetchColumn(),
        'entrée ancienne réellement supprimée'
    );

    $now += 100;
    $reflection = new ReflectionClass(PortixSecurity::class);
    $rateLimiterProperty = $reflection->getProperty('rateLimiter');
    $rateLimiterProperty->setAccessible(true);
    $rateLimiterProperty->setValue(null, $limiter);
    $_SERVER['REMOTE_ADDR'] = '192.0.2.25';
    assertRateSame(1, PortixSecurity::recordLoginFailure('Marc'), 'compte et IP ralentis ensemble');
    $now++;
    PortixSecurity::clearLoginFailures('Marc');
    assertRateSame(
        2,
        PortixSecurity::recordLoginFailure('AutreCompte'),
        'une connexion réussie ne remet pas à zéro l’historique de l’adresse IP'
    );
    assertRateSame(
        0,
        PortixSecurity::consumePasswordResetRateLimit('marc@example.test'),
        'première demande de récupération acceptée'
    );
    assertRateTrue(
        PortixSecurity::consumePasswordResetRateLimit('autre@example.test') > 0,
        'adresse IP commune limite aussi les demandes visant plusieurs comptes'
    );
    $storedSecurityKeys = (string) $pdo->query(
        "SELECT GROUP_CONCAT(rate_key, ',') FROM rate_limits "
        . "WHERE rate_key LIKE 'login-account:%' OR rate_key LIKE 'login-ip:%' "
        . "OR rate_key LIKE 'reset-email:%' OR rate_key LIKE 'reset-ip:%'"
    )->fetchColumn();
    assertRateTrue(
        !str_contains($storedSecurityKeys, 'Marc')
        && !str_contains($storedSecurityKeys, 'marc@example.test')
        && !str_contains($storedSecurityKeys, '192.0.2.25'),
        'identifiants bruts absents des clés persistantes'
    );

    $applicationSource = (string) file_get_contents(dirname(__DIR__) . '/src/Core/Application.php');
    $migrationPosition = strpos($applicationSource, '$this->migrate();');
    $configurationPosition = strpos($applicationSource, 'PortixSecurity::configureRateLimiter');
    $repositoriesPosition = strpos($applicationSource, '$repositories = $this->repositories();');
    assertRateTrue(
        $migrationPosition !== false
        && $configurationPosition !== false
        && $repositoriesPosition !== false
        && $migrationPosition < $configurationPosition
        && $configurationPosition < $repositoriesPosition,
        'limiteur initialisé seulement après la migration'
    );

    $contactSource = (string) file_get_contents(dirname(__DIR__) . '/var/modules/contact/index.php');
    $validationPosition = strpos($contactSource, 'mb_strlen($body) > 20000');
    $contactLimitPosition = strpos($contactSource, 'PortixSecurity::consumeContactRateLimit()');
    $contactWritePosition = strpos($contactSource, 'createConversation($name, $email, $subject, $body)');
    assertRateTrue(
        $validationPosition !== false
        && $contactLimitPosition !== false
        && $contactWritePosition !== false
        && $validationPosition < $contactLimitPosition
        && $contactLimitPosition < $contactWritePosition,
        'contact invalide ne consomme pas le quota'
    );

    $userZoneSource = (string) file_get_contents(dirname(__DIR__) . '/var/modules/UserZone/index.php');
    assertRateTrue(
        !str_contains($userZoneSource, 'password_reset_next_request')
        && str_contains($userZoneSource, 'consumePasswordResetRateLimit($email)'),
        'réinitialisation protégée hors session'
    );

    echo "Limitation persistante des requêtes : OK\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'Test du limiteur : ÉCHEC - ' . $error->getMessage() . "\n");
    $status = 1;
} finally {
    try {
        $reflection = new ReflectionClass(PortixSecurity::class);
        $rateLimiterProperty = $reflection->getProperty('rateLimiter');
        $rateLimiterProperty->setAccessible(true);
        $rateLimiterProperty->setValue(null, null);
    } catch (Throwable) {
    }
    unset($limiter, $rateLimiterProperty, $reflection);
    $pdo = null;
    if ($database instanceof Database) {
        $database->close();
    }
    unset($database);
    gc_collect_cycles();
    removeRateTestTree($temporaryRoot);
}

exit($status);

function assertRateTrue(bool $condition, string $label): void
{
    if (!$condition) {
        throw new RuntimeException($label . ' : échec.');
    }
}

function assertRateSame(mixed $expected, mixed $actual, string $label): void
{
    if ($expected !== $actual) {
        throw new RuntimeException(
            $label . ' : attendu ' . var_export($expected, true)
            . ', obtenu ' . var_export($actual, true)
        );
    }
}

function removeRateTestTree(string $path): void
{
    if (!is_dir($path) || !str_starts_with(basename($path), 'portix-rate-limiter-smoke-')) {
        return;
    }
    for ($attempt = 0; $attempt < 10 && is_dir($path); $attempt++) {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iterator as $item) {
            $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
        }
        @rmdir($path);
        if (is_dir($path)) {
            usleep(100000);
        }
    }
}
