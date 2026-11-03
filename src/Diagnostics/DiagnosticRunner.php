<?php
declare(strict_types=1);

namespace Portix\Diagnostics;

use PDO;
use Portix\Theme\ThemeContract;
use Portix\View\Template;
use Throwable;

final class DiagnosticRunner
{
    public function __construct(
        private readonly string $root,
        private readonly PDO $pdo
    ) {
    }

    /** @return array{rows:list<array<string,string>>,pass:int,fail:int,warn:int,total:int,report:string} */
    public function environment(): array
    {
        $rows = [];
        $this->add($rows, 'php', 'PHP', version_compare(PHP_VERSION, '8.2.0', '>='), PHP_VERSION);
        foreach ([
            'pdo_sqlite' => 'PDO SQLite',
            'mbstring' => 'mbstring',
            'dom' => 'DOM',
            'gd' => 'GD',
            'zip' => 'ZIP',
        ] as $extension => $label) {
            $this->add($rows, 'ext-' . $extension, $label, extension_loaded($extension), extension_loaded($extension) ? 'Disponible' : 'Extension absente');
        }
        $this->add(
            $rows,
            'webp',
            'WebP',
            function_exists('imagewebp'),
            function_exists('imagewebp') ? 'Support imagewebp disponible' : 'Support WebP absent'
        );
        foreach ([
            'database' => 'Base de données',
            'var/sys' => 'Configuration',
            'var/uploads' => 'Téléversements',
            'var/private' => 'Données privées',
        ] as $relative => $label) {
            $this->addWriteCheck($rows, $relative, $label);
        }
        return $this->summarize($rows);
    }

    /** @return array{rows:list<array<string,string>>,pass:int,fail:int,warn:int,total:int,report:string} */
    public function run(string $mode): array
    {
        if (!in_array($mode, ['quick', 'full'], true)) {
            throw new \RuntimeException('Mode de diagnostic inconnu.');
        }

        $rows = $this->environment()['rows'];

        $this->checkDatabase($rows);
        $this->checkConfiguration($rows);
        $this->checkModules($rows);
        $this->checkThemes($rows);

        if ($mode === 'full') {
            $this->checkTemplates($rows);
            $this->checkDocumentation($rows);
            $this->checkServerRules($rows);
            $this->checkStaticAssets($rows);
        }

        return $this->summarize($rows);
    }

    /** @param list<array<string,string>> $rows */
    private function checkDatabase(array &$rows): void
    {
        try {
            $integrity = (string) $this->pdo->query('PRAGMA integrity_check')->fetchColumn();
            $this->add($rows, 'sqlite-integrity', 'SQLite — intégrité', $integrity === 'ok', $integrity);
        } catch (Throwable $error) {
            $this->add($rows, 'sqlite-integrity', 'SQLite — intégrité', false, $error->getMessage());
        }
    }

    /** @param list<array<string,string>> $rows */
    private function checkConfiguration(array &$rows): void
    {
        $file = $this->root . '/var/sys/config.json';
        if (!is_file($file)) {
            $this->add($rows, 'configuration', 'Configuration P3', false, 'config.json absent');
            return;
        }
        try {
            $value = json_decode((string) file_get_contents($file), true, 64, JSON_THROW_ON_ERROR);
            $this->add($rows, 'configuration', 'Configuration P3', is_array($value), is_array($value) ? 'JSON valide' : 'Format invalide');
        } catch (Throwable $error) {
            $this->add($rows, 'configuration', 'Configuration P3', false, 'JSON invalide');
        }
    }

    /** @param list<array<string,string>> $rows */
    private function checkModules(array &$rows): void
    {
        $count = 0;
        $errors = [];
        foreach (glob($this->root . '/var/modules/*', GLOB_ONLYDIR) ?: [] as $directory) {
            $manifest = $directory . '/desc.xml';
            if (!is_file($manifest)) {
                continue;
            }
            $count++;
            $xml = (string) file_get_contents($manifest);
            preg_match('#<Version>\s*([^<]+)\s*</Version>#s', $xml, $versionMatch);
            $version = trim((string) ($versionMatch[1] ?? ''));
            $versionFile = $directory . '/MODULE-VERSION';
            if ($version === '' || !is_file($versionFile) || trim((string) file_get_contents($versionFile)) !== $version) {
                $errors[] = basename($directory);
            }
        }
        $this->add(
            $rows,
            'modules',
            'Modules',
            $count > 0 && $errors === [],
            $errors === [] ? $count . ' module(s) cohérents' : 'Version incohérente : ' . implode(', ', $errors)
        );
    }

    /** @param list<array<string,string>> $rows */
    private function checkThemes(array &$rows): void
    {
        $results = ThemeContract::validateAll($this->root);
        $errors = [];
        foreach ($results as $theme => $themeErrors) {
            if ($themeErrors !== []) {
                $errors[] = $theme;
            }
        }
        $this->add(
            $rows,
            'themes',
            'Contrat des thèmes',
            $results !== [] && $errors === [],
            $errors === [] ? count($results) . ' thème(s) conformes' : 'Échec : ' . implode(', ', $errors)
        );
    }

    /** @param list<array<string,string>> $rows */
    private function checkTemplates(array &$rows): void
    {
        $count = 0;
        $errors = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->root . '/var', \FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            if (!$file->isFile() || strtolower($file->getExtension()) !== 'tpl') {
                continue;
            }
            $count++;
            try {
                (new Template($file->getPathname()))->validate();
            } catch (Throwable) {
                $errors[] = substr($file->getPathname(), strlen($this->root) + 1);
                if (count($errors) >= 5) {
                    break;
                }
            }
        }
        $this->add(
            $rows,
            'templates',
            'Templates',
            $count > 0 && $errors === [],
            $errors === [] ? $count . ' template(s) valides' : 'Erreur : ' . implode(', ', $errors)
        );
    }

    /** @param list<array<string,string>> $rows */
    private function checkDocumentation(array &$rows): void
    {
        $file = $this->root . '/src/Installation/default-content.json';
        try {
            $content = json_decode((string) file_get_contents($file), true, 128, JSON_THROW_ON_ERROR);
            $articles = is_array($content['articles'] ?? null) ? $content['articles'] : [];
            $this->add(
                $rows,
                'documentation',
                'Documentation initiale',
                count($articles) >= 8,
                count($articles) . ' article(s) documentaire(s)'
            );
        } catch (Throwable) {
            $this->add($rows, 'documentation', 'Documentation initiale', false, 'default-content.json invalide');
        }
    }

    /** @param list<array<string,string>> $rows */
    private function checkServerRules(array &$rows): void
    {
        $apache = (string) @file_get_contents($this->root . '/.htaccess');
        $iis = (string) @file_get_contents($this->root . '/web.config');
        $ok = $apache !== '' && $iis !== ''
            && str_contains($apache, 'var/modules/[^/]+/database')
            && str_contains($iis, 'var/modules/[^/]+/database');
        $this->add($rows, 'server-rules', 'Protection HTTP', $ok, $ok ? 'Règles Apache/IIS présentes' : 'Règles de protection incomplètes');
    }

    /** @param list<array<string,string>> $rows */
    private function checkStaticAssets(array &$rows): void
    {
        $errors = [];
        foreach (glob($this->root . '/var/templates/*/index.tpl') ?: [] as $templateFile) {
            $theme = basename(dirname($templateFile));
            if (!is_file(dirname($templateFile) . '/style.min.css')) {
                $errors[] = $theme;
            }
        }
        $this->add(
            $rows,
            'static-assets',
            'Ressources publiques',
            $errors === [],
            $errors === [] ? 'CSS compilées présentes' : 'CSS manquante : ' . implode(', ', $errors)
        );
    }

    /** @param list<array<string,string>> $rows */
    private function addWriteCheck(array &$rows, string $relative, string $label): void
    {
        $directory = $this->root . '/' . $relative;
        if (!is_dir($directory) || !is_writable($directory)) {
            $this->add($rows, 'write-' . str_replace('/', '-', $relative), $label, false, 'Dossier non inscriptible');
            return;
        }

        $probe = $directory . '/.p3-diagnostic-' . bin2hex(random_bytes(6));
        $written = @file_put_contents($probe, 'ok') === 2;
        $deleted = !$written || @unlink($probe);
        $this->add(
            $rows,
            'write-' . str_replace('/', '-', $relative),
            $label,
            $written && $deleted,
            $written && $deleted ? 'Création/suppression temporaire OK' : 'Test d’écriture/suppression échoué'
        );
    }

    /** @param list<array<string,string>> $rows */
    private function add(array &$rows, string $id, string $label, bool $ok, string $detail): void
    {
        $rows[] = [
            'id' => $id,
            'label' => $label,
            'status' => $ok ? 'pass' : 'fail',
            'status_label' => $ok ? 'OK' : 'Échec',
            'detail' => $this->cleanDetail($detail),
        ];
    }

    private function cleanDetail(string $detail): string
    {
        $detail = str_replace($this->root, '[racine P3]', $detail);
        $detail = preg_replace('/[\r\n\t]+/', ' ', $detail) ?? $detail;
        return mb_substr(trim($detail), 0, 500);
    }

    /** @param list<array<string,string>> $rows
     *  @return array{rows:list<array<string,string>>,pass:int,fail:int,warn:int,total:int,report:string}
     */
    private function summarize(array $rows): array
    {
        $pass = count(array_filter($rows, static fn (array $row): bool => $row['status'] === 'pass'));
        $fail = count($rows) - $pass;
        $lines = [
            'P3 ' . trim((string) @file_get_contents($this->root . '/VERSION')),
            'PHP ' . PHP_VERSION,
            'Tests : ' . count($rows) . ' — OK : ' . $pass . ' — Échecs : ' . $fail,
            '',
        ];
        foreach ($rows as $row) {
            $lines[] = '[' . strtoupper($row['status']) . '] ' . $row['label'] . ' — ' . $row['detail'];
        }
        return [
            'rows' => $rows,
            'pass' => $pass,
            'fail' => $fail,
            'warn' => 0,
            'total' => count($rows),
            'report' => implode("\n", $lines),
        ];
    }
}
