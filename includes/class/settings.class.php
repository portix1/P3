<?php
/** Atomic JSON settings stored in the protected var/sys directory. */
final class PortixSettings
{
    public static function read($name, $defaults = array(), $root = '.')
    {
        $file = self::path($name, $root);
        if (!is_file($file)) {
            return (array) $defaults;
        }
        $content = file_get_contents($file);
        $decoded = $content === false ? null : json_decode($content, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('Fichier de réglages JSON invalide : ' . basename($file));
        }
        return $decoded + (array) $defaults;
    }

    public static function write($name, $settings, $root = '.')
    {
        if (!is_array($settings)) {
            throw new InvalidArgumentException('Les réglages doivent être un tableau.');
        }
        $file = self::path($name, $root);
        $directory = dirname($file);
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException('Impossible de créer le dossier des réglages.');
        }
        $json = json_encode(
            $settings,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );
        if (!is_string($json)) {
            throw new RuntimeException('Impossible d’encoder les réglages JSON.');
        }
        $temporary = $file . '.' . bin2hex(random_bytes(6)) . '.tmp';
        $backup = null;
        try {
            if (file_put_contents($temporary, $json . "\n", LOCK_EX) === false) {
                throw new RuntimeException('Impossible d’écrire les réglages.');
            }
            @chmod($temporary, 0600);
            if (DIRECTORY_SEPARATOR === '\\' && is_file($file)) {
                $backup = $file . '.bak-' . bin2hex(random_bytes(6));
                if (!rename($file, $backup)) {
                    throw new RuntimeException('Impossible de préparer le remplacement des réglages.');
                }
            }
            if (!rename($temporary, $file)) {
                if ($backup !== null) {
                    @rename($backup, $file);
                }
                throw new RuntimeException('Impossible de remplacer les réglages.');
            }
            if ($backup !== null) {
                @unlink($backup);
            }
        } finally {
            if (is_file($temporary)) {
                @unlink($temporary);
            }
        }
    }

    private static function path($name, $root)
    {
        if (!preg_match('/^[a-z][a-z0-9_-]*$/', (string) $name)) {
            throw new InvalidArgumentException('Nom de réglages invalide.');
        }
        return rtrim((string) $root, '/\\') . DIRECTORY_SEPARATOR . 'var'
            . DIRECTORY_SEPARATOR . 'sys' . DIRECTORY_SEPARATOR . $name . '.json';
    }
}
