<?php
declare(strict_types=1);

namespace Portix\Configuration;

use JsonException;
use RuntimeException;

final class SiteConfiguration
{
    private const DEFAULTS = [
        'database_file' => 'portix2.sqlite',
        'lang' => 'fr',
        'template' => 'modern',
        'defaultpage' => 'news',
        'title' => 'P3',
        'site_url' => '',
        'site_description' => '',
        'seo_home_title' => '',
        'seo_home_description' => '',
        'seo_default_author' => '',
        'seo_publisher_name' => '',
        'seo_publisher_logo' => '',
        'seo_default_image' => '',
        'mail_sender_name' => '',
        'mail_sender_email' => '',
        'debug' => false,
    ];

    /** @var array<string, mixed> */
    private array $values;

    private function __construct(private readonly string $file, array $values)
    {
        $this->values = array_replace(self::DEFAULTS, $values);
    }

    public static function load(string $root): self
    {
        $file = rtrim($root, '/\\') . '/var/sys/config.json';
        if (!is_file($file)) {
            return new self($file, []);
        }

        $json = file_get_contents($file);
        if ($json === false) {
            throw new RuntimeException('Impossible de lire la configuration du site.');
        }

        try {
            $values = json_decode($json, true, 32, JSON_THROW_ON_ERROR);
        } catch (JsonException $error) {
            throw new RuntimeException('La configuration JSON du site est invalide.', 0, $error);
        }

        if (!is_array($values)) {
            throw new RuntimeException('La configuration du site doit être un objet JSON.');
        }

        return new self($file, $values);
    }

    public static function databaseRelativePath(string $filename): string
    {
        if (preg_match('/\A[A-Za-z0-9][A-Za-z0-9_-]{0,63}\.sqlite\z/', $filename) !== 1
            || preg_match('/\A(?:CON|PRN|AUX|NUL|COM[0-9]|LPT[0-9])\.sqlite\z/i', $filename) === 1) {
            throw new RuntimeException('Nom de fichier SQLite invalide.');
        }

        return 'database/' . $filename;
    }

    public function databasePath(): string
    {
        return self::databaseRelativePath((string) $this->get('database_file'));
    }

    public function isInstalled(): bool
    {
        return is_file($this->file);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->values[$key] ?? $default;
    }

    /** @return array<string, mixed> */
    public function all(): array
    {
        return $this->values;
    }

    /** @param array<string, mixed> $values */
    public function replace(array $values): void
    {
        $this->values = array_replace(self::DEFAULTS, $values);
        $this->write();
    }

    /** @param array<string, mixed> $values */
    public function update(array $values): void
    {
        $this->values = array_replace($this->values, $values);
        $this->write();
    }

    private function write(): void
    {
        $directory = dirname($this->file);
        if (!is_dir($directory) && !mkdir($directory, 0770, true) && !is_dir($directory)) {
            throw new RuntimeException('Impossible de créer le dossier de configuration.');
        }

        try {
            $json = json_encode(
                $this->values,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
            ) . "\n";
        } catch (JsonException $error) {
            throw new RuntimeException('Impossible de sérialiser la configuration.', 0, $error);
        }

        $temporary = $this->file . '.tmp-' . bin2hex(random_bytes(8));
        if (file_put_contents($temporary, $json, LOCK_EX) === false) {
            throw new RuntimeException('Impossible d’écrire la configuration temporaire.');
        }
        $backup = null;
        if (DIRECTORY_SEPARATOR === '\\' && is_file($this->file)) {
            $backup = $this->file . '.bak-' . bin2hex(random_bytes(6));
            if (!rename($this->file, $backup)) {
                @unlink($temporary);
                throw new RuntimeException('Impossible de préparer le remplacement de la configuration.');
            }
        }
        if (!rename($temporary, $this->file)) {
            if ($backup !== null) {
                @rename($backup, $this->file);
            }
            @unlink($temporary);
            throw new RuntimeException('Impossible d’activer la nouvelle configuration.');
        }
        if ($backup !== null) {
            @unlink($backup);
        }
    }
}
