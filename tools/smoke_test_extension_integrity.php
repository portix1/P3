<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit(1);
}
require dirname(__DIR__) . '/bootstrap/autoload.php';

use Portix\Maintenance\ExtensionUpdates;
use Portix\Module\ModuleManifest;

function integrityCheck(bool $ok, string $description): void
{
    if (!$ok) {
        throw new RuntimeException($description);
    }
}

function integrityRefuses(callable $action, string $description): void
{
    try {
        $action();
    } catch (RuntimeException | JsonException) {
        return;
    }
    throw new RuntimeException('Refus attendu : ' . $description);
}

$archive = tempnam(sys_get_temp_dir(), 'p3-extension-integrity-');
if ($archive === false) {
    throw new RuntimeException('Impossible de créer le fichier de test.');
}
try {
    $bytes = 'test de contenu de paquet officiel';
    file_put_contents($archive, $bytes);
    $sha = hash('sha256', $bytes);
    $themeSha = hash('sha256', 'another package');
    $packages = [
        [
            'kind' => 'module', 'id' => 'demo', 'version' => '1.1.0',
            'file' => 'P3-demo-1.1.0.zip', 'sha256' => $sha,
        ],
        [
            'kind' => 'theme', 'id' => 'theme-demo', 'version' => '2.0.0',
            'file' => 'P3-theme-demo-2.0.0.zip', 'sha256' => $themeSha,
        ],
    ];
    $catalogue = static fn (array $entries): string => json_encode(
        ['schema' => 1, 'packages' => $entries], JSON_THROW_ON_ERROR
    );
    $json = $catalogue($packages);

    integrityCheck(
        ExtensionUpdates::verifyUploadedArchive('module', 'P3-demo-1.1.0.zip', $archive, $json),
        'Archive officielle conforme acceptée'
    );
    integrityCheck(
        ExtensionUpdates::verifyUploadedArchive('module', 'custom-module.zip', $archive, $json) === false,
        'Import manuel non déclaré reste explicitement non vérifié'
    );
    integrityRefuses(
        static fn () => ExtensionUpdates::verifyUploadedArchive('theme', 'P3-demo-1.1.0.zip', $archive, $json),
        'Archive déclarée pour un autre type'
    );
    file_put_contents($archive, 'archive modifiée');
    integrityRefuses(
        static fn () => ExtensionUpdates::verifyUploadedArchive('module', 'P3-demo-1.1.0.zip', $archive, $json),
        'Empreinte modifiée'
    );
    file_put_contents($archive, $bytes);
    $wrong = $packages;
    $wrong[0]['sha256'] = 'malformed';
    integrityRefuses(
        static fn () => ExtensionUpdates::available($catalogue($wrong), []),
        'Catalogue sans SHA-256 valide'
    );
    $duplicate = [$packages[0], $packages[0]];
    integrityRefuses(
        static fn () => ExtensionUpdates::available($catalogue($duplicate), []),
        'Noms de fichiers de catalogue dupliqués'
    );
    $updates = ExtensionUpdates::available($json, [
        'module' => ['demo' => ['version' => '1.0.0', 'name' => 'Demo']],
        'theme' => ['theme-demo' => ['version' => '1.0.0', 'name' => 'Theme Demo']],
    ]);
    integrityCheck(count($updates) === 2, 'Deux mises à jour proposées');
    integrityCheck(
        $updates[0]['sha256'] === $sha && $updates[1]['sha256'] === $themeSha,
        'Empreintes transmises aux mises à jour'
    );

    $installed = ['prefix' => 'demo', 'path' => 'demo', 'type' => 'module', 'version' => '1.1.0'];
    $candidate = $installed;
    ModuleManifest::assertUpgrade($candidate, $installed); // Réinstallation autorisée.
    $candidate['version'] = '1.2.0';
    ModuleManifest::assertUpgrade($candidate, $installed);
    $candidate['version'] = '1.0.9';
    integrityRefuses(
        static fn () => ModuleManifest::assertUpgrade($candidate, $installed),
        'Rétrogradation'
    );
    $candidate['version'] = '';
    integrityRefuses(
        static fn () => ModuleManifest::assertUpgrade($candidate, $installed),
        'Disparition de version'
    );
    foreach (['path' => 'other', 'prefix' => 'other', 'type' => 'plugin'] as $key => $value) {
        $different = $installed;
        $different[$key] = $value;
        integrityRefuses(
            static fn () => ModuleManifest::assertUpgrade($different, $installed),
            'Usurpation : ' . $key
        );
    }
    echo "Catalogue SHA-256, intégrité des archives et refus de rétrogradation des modules : OK\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'Test intégrité extensions : ÉCHEC - ' . $error->getMessage() . "\n");
    exit(1);
} finally {
    @unlink($archive);
}
