<?php
declare(strict_types=1);

use Portix\Infrastructure\Database;
use Portix\Infrastructure\Migrator;
use Portix\Module\ModuleInstaller;
use Portix\Repository\RepositoryRegistry;
use PortixModule\UserZone\PasswordResetRepository;

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Ce test doit être exécuté en ligne de commande.\n");
    exit(1);
}

$root = dirname(__DIR__);
require $root . '/bootstrap/autoload.php';
require $root . '/var/modules/UserZone/src/PasswordResetRepository.php';

$temporaryRoot = rtrim(sys_get_temp_dir(), '/\\') . DIRECTORY_SEPARATOR
    . 'portix3-password-reset-' . getmypid() . '-' . bin2hex(random_bytes(4));
$database = null;
$status = 0;

// Release every repository/statement before deleting SQLite files (also on Windows).
$status = (static function () use ($root, $temporaryRoot): int {
$database = null;
$pdo = null;
$status = 0;

try {
    if (!mkdir($temporaryRoot, 0770, true)) {
        throw new RuntimeException('Impossible de créer le dossier temporaire.');
    }

    $database = new Database($temporaryRoot . '/database/portix2.sqlite');
    (new Migrator($database))->migrate();
    $repositories = new RepositoryRegistry($database);
    $installer = new ModuleInstaller($database, $repositories->modules());
    $result = $installer->installDirectory($root . '/var/modules/UserZone');
    resetAssertSame(['password_reset_tokens'], $result['tables'], 'table de réinitialisation');

    $pdo = $database->connection();
    resetAssertSame(
        'USR',
        (string) $pdo->query(
            "SELECT module_prefix FROM module_schema_tables WHERE table_name = 'password_reset_tokens'"
        )->fetchColumn(),
        'propriété de la table'
    );

    $repositories->users()->create(
        'alice',
        password_hash('AncienMotDePasse123!', PASSWORD_DEFAULT),
        'alice@example.test',
        'Durand',
        'Alice',
        true,
        false
    );
    resetAssertSame(
        'alice',
        $repositories->users()->findUniqueByEmail('ALICE@example.test')?->pseudo(),
        'recherche e-mail insensible à la casse'
    );

    $repositories->users()->create(
        'bob',
        password_hash('AncienMotDePasse456!', PASSWORD_DEFAULT),
        'shared@example.test',
        'Martin',
        'Bob',
        false,
        true
    );
    $repositories->users()->create(
        'carol',
        password_hash('AncienMotDePasse789!', PASSWORD_DEFAULT),
        'shared@example.test',
        'Petit',
        'Carol',
        false,
        true
    );
    resetAssertSame(
        null,
        $repositories->users()->findUniqueByEmail('shared@example.test'),
        'une adresse partagée ne permet pas de cibler un compte'
    );

    $plainToken = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $plainToken);
    $reset = new PasswordResetRepository($pdo);
    $reset->issue('alice', $tokenHash, 3600);
    resetAssertTrue($reset->isValid($tokenHash), 'jeton actif');
    resetAssertSame(
        $tokenHash,
        (string) $pdo->query('SELECT token_hash FROM password_reset_tokens LIMIT 1')->fetchColumn(),
        'empreinte stockée'
    );
    resetAssertTrue(
        (int) $pdo->query(
            "SELECT COUNT(*) FROM password_reset_tokens WHERE token_hash = " . $pdo->quote($plainToken)
        )->fetchColumn() === 0,
        'le jeton en clair ne doit jamais être stocké'
    );

    $before = $repositories->users()->find('alice');
    $newPassword = 'NouveauMotDePasse123!';
    $pseudo = $reset->resetPassword($tokenHash, password_hash($newPassword, PASSWORD_DEFAULT));
    resetAssertSame('alice', $pseudo, 'compte réinitialisé');
    $after = $repositories->users()->find('alice');
    resetAssertTrue($after !== null && $after->verifyPassword($newPassword), 'nouveau mot de passe');
    resetAssertSame(
        ($before?->authVersion() ?? 0) + 1,
        $after?->authVersion(),
        'invalidation des anciennes sessions'
    );
    resetAssertTrue(!$reset->isValid($tokenHash), 'jeton à usage unique');

    $loginTemplate = (string) file_get_contents($root . '/var/modules/UserZone/templates/users.tpl');
    resetAssertTrue(
        str_contains($loginTemplate, 'href="forgot-password.html"'),
        'lien mot de passe oublié sur la connexion'
    );

    echo "Réinitialisation sécurisée des mots de passe : OK\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'Test mot de passe oublié : ÉCHEC - ' . $error->getMessage() . "\n");
    $status = 1;
}
return $status;
})();
gc_collect_cycles();
resetRemoveTree($temporaryRoot);

exit($status);

function resetAssertTrue(bool $condition, string $label): void
{
    if (!$condition) {
        throw new RuntimeException($label . ' : échec.');
    }
}

function resetAssertSame(mixed $expected, mixed $actual, string $label): void
{
    if ($expected !== $actual) {
        throw new RuntimeException(
            $label . ' : attendu ' . var_export($expected, true)
            . ', obtenu ' . var_export($actual, true)
        );
    }
}

function resetRemoveTree(string $directory): void
{
    if (!is_dir($directory) || !str_starts_with(basename($directory), 'portix3-password-reset-')) {
        return;
    }
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($iterator as $item) {
        $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
    }
    @rmdir($directory);
}
