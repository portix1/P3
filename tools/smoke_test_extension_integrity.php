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
    // The trust anchor must match the exact released bytes and ZIPs in the repository.
    $root = dirname(__DIR__);
    $published = (string) file_get_contents($root . '/distribution/catalogue.json');
    ExtensionUpdates::assertTrustedCatalogue($published);
    integrityRefuses(
        static fn () => ExtensionUpdates::assertTrustedCatalogue($published . ' '),
        'Catalogue altered without a signed-off code change'
    );
    $manifest = json_decode($published, true, 32, JSON_THROW_ON_ERROR);
    foreach ($manifest['packages'] as $package) {
        $zip = $root . '/distribution/' . $package['file'];
        integrityCheck(is_file($zip), 'ZIP officiel introuvable : ' . $package['file']);
        integrityCheck(
            hash_equals($package['sha256'], (string) hash_file('sha256', $zip)),
            'Empreinte du ZIP officiel différente du catalogue : ' . $package['file']
        );
        integrityCheck(
            ExtensionUpdates::verifyUploadedArchive($package['kind'], $package['file'], $zip, $published),
            'Contrôle du paquet officiel refusé : ' . $package['file']
        );
    }
    // Keep the official Articles package aligned with the currently shipped source.
    $articlesEntries = array_values(array_filter($manifest['packages'],
        static fn (array $entry): bool => $entry['kind'] === 'module' && $entry['id'] === 'articles'
    ));
    integrityCheck(count($articlesEntries) === 1, 'Une seule version officielle du module Articles');
    $articlesEntry = $articlesEntries[0];
    $articlesRoot = $root . '/var/modules/articles';
    $articlesManifest = ModuleManifest::fromXml($articlesRoot . '/desc.xml', 'articles');
    $workingVersion = (string) $articlesManifest['version'];
    $moduleVersion = trim((string) file_get_contents($articlesRoot . '/MODULE-VERSION'));
    integrityCheck($workingVersion === $moduleVersion, 'Manifeste et MODULE-VERSION désynchronisés');
    $unreleasedVersion = $workingVersion !== $articlesEntry['version'];
    // A development pull request may carry a newer module while the trusted
    // catalogue deliberately keeps the last published, immutable ZIP.
    // The flag is NEVER enabled by the stable/release validation workflow.
    if ($unreleasedVersion) {
        integrityCheck(getenv('P3_DEVELOPMENT_VALIDATION') === '1'
            && version_compare($workingVersion, (string)$articlesEntry['version'], '>'),
            'Une version Articles non publiée est interdite dans une distribution stable');
    }
    integrityCheck($articlesEntry['p3_min'] === $articlesManifest['minimum_p3'],
        'Version minimale P3 du catalogue incohérente');
    ModuleManifest::assertCompatible($articlesManifest, trim((string)file_get_contents($root . '/VERSION')));
    integrityRefuses(static fn () => ModuleManifest::assertCompatible($articlesManifest, '0.9.1'),
        'Articles 1.4.9 exige P3 0.9.2 ou sa candidate');
    $zip = new ZipArchive();
    integrityCheck($zip->open($root . '/distribution/' . $articlesEntry['file']) === true,
        'Archive officielle Articles illisible');
    try {
        foreach (['desc.xml', 'MODULE-VERSION', 'index.php', 'templates/index.tpl'] as $relative) {
            integrityCheck($zip->getFromName('articles/' . $relative) !== false,
                'Archive officielle Articles incomplète : ' . $relative);
            if (!$unreleasedVersion) {
                integrityCheck($zip->getFromName('articles/' . $relative) ===
                    file_get_contents($articlesRoot . '/' . $relative),
                    'Archive Articles différente des sources pour ' . $relative);
            }
        }
    } finally {
        $zip->close();
    }
    $reflection = new ReflectionClass(ExtensionUpdates::class);
    $anchor = $reflection->getReflectionConstant('TRUSTED_REF');
    $trustedRef = $anchor !== false ? $anchor->getValue() : '';
    integrityCheck(is_string($trustedRef) && preg_match('/\\A[a-f0-9]{40}\\z/D', $trustedRef) === 1,
        'La référence Git figée doit être un SHA-1 Git complet');
    $official = ExtensionUpdates::available($published, [
        'module' => ['articles' => ['version' => '1.4.0', 'name' => 'Articles']],
        'theme' => ['six-cordes-ux-clair' => ['version' => '0.1.0', 'name' => 'Six Cordes']],
    ]);
    integrityCheck(count($official) === 2, 'Catalogue publié doit contenir deux mises à jour');
    foreach ($official as $row) {
        integrityCheck(
            str_contains($row['url'], '/' . $trustedRef . '/distribution/')
                && !str_contains($row['url'], '/main/'),
            'Le lien du ZIP ne pointe pas sur le commit figé.'
        );
    }
    echo "Catalogue figé, empreinte du manifeste et empreintes des ZIP publiés : OK\n";
    echo "Catalogue SHA-256, intégrité des archives et refus de rétrogradation des modules : OK\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'Test intégrité extensions : ÉCHEC - ' . $error->getMessage() . "\n");
    exit(1);
} finally {
    @unlink($archive);
}
