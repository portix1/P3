<?php
declare(strict_types=1);

final class BackofficeProductionPatch
{
    private const DIRECTORY = 'var/private/backoffice-production';
    private const MAINTENANCE = 'var/sys/maintenance.lock';

    private static function manifest(): array
    {
        $json = file_get_contents(__DIR__ . '/manifest.json');
        if ($json === false) { throw new RuntimeException('Paquet du correctif incomplet.'); }
        return json_decode($json, true, 64, JSON_THROW_ON_ERROR);
    }

    private static function path(string $root, string $relative): string
    {
        if (($relative !== self::DIRECTORY && !preg_match('#\A(?:includes|src|var/admin|var/lang|var/modules|var/private/backoffice-production|var/sys)/[A-Za-z0-9_./-]+\z#', $relative))
            || in_array('..', explode('/', $relative), true)) {
            throw new RuntimeException('Chemin interdit.');
        }
        $path = rtrim($root, '/');
        foreach (explode('/', $relative) as $part) {
            $path .= '/' . $part;
            if (is_link($path)) { throw new RuntimeException('Lien symbolique refusé : ' . $relative); }
        }
        return $path;
    }

    public static function plan(string $root): array
    {
        if (trim((string) file_get_contents($root . '/VERSION')) !== '0.8.01-beta1-preview5') {
            throw new RuntimeException('Ce correctif est réservé à P3 0.8.01-beta1-preview5.');
        }
        $manifest = self::manifest();
        foreach ($manifest['dependencies'] as $relative => $hash) {
            $path = self::path($root, $relative);
            if (!is_file($path) || !hash_equals($hash, hash('sha256', str_replace("\r\n", "\n", (string) file_get_contents($path))))) {
                throw new RuntimeException('Composant différent de la version contrôlée : ' . $relative);
            }
        }
        $receipt = self::receipt($root);
        if (($receipt['state'] ?? '') === 'applying') {
            throw new RuntimeException('Application interrompue. Utiliser Restaurer avant de recommencer.');
        }
        $plan = [];
        foreach ($manifest['files'] as $relative => $operation) {
            $path = self::path($root, $relative);
            if (file_exists($path) && !is_file($path)) {
                throw new RuntimeException('Un dossier occupe le chemin attendu : ' . $relative);
            }
            $before = is_file($path) ? file_get_contents($path) : null;
            if ($before === false) { throw new RuntimeException('Lecture impossible : ' . $relative); }
            if (($operation['optional'] ?? false) && $before === null) { continue; }
            if (!is_dir(dirname($path)) || !is_writable(dirname($path))) {
                throw new RuntimeException('Dossier absent ou non modifiable : ' . dirname($relative));
            }
            $after = self::transform($relative, $before, $operation);
            if (str_ends_with($relative, '.php')) { token_get_all($after, TOKEN_PARSE); }
            $plan[$relative] = ['before' => $before, 'after' => $after,
                'hash' => $before === null ? null : hash('sha256', $before),
                'after_hash' => hash('sha256', $after), 'changed' => $before !== $after];
        }
        return $plan;
    }

    private static function transform(string $relative, ?string $before, array $operation): string
    {
        if ($operation['kind'] === 'create') {
            $after = $operation['content'];
            if ($before !== null && $before !== $after) {
                throw new RuntimeException('Un fichier différent existe déjà : ' . $relative);
            }
            return $after;
        }
        if ($before === null) { throw new RuntimeException('Fichier requis absent : ' . $relative); }
        $source = str_replace("\r\n", "\n", $before);
        if ($operation['kind'] === 'append') {
            if (str_contains($source, $operation['marker'])) { return $before; }
            $after = rtrim($source) . "\n";
            if (str_ends_with(rtrim($source), '?>')) { $after .= "<?php\n"; }
            $after .= $operation['content'];
        } else {
            $after = $source;
            foreach ($operation['hunks'] as $hunk) {
                $old = $hunk['before'];
                $new = $hunk['after'];
                if ($new !== '' && substr_count($after, $new) === 1) {
                    continue;
                } elseif ($old !== '' && substr_count($after, $old) === 1) {
                    $after = str_replace($old, $new, $after);
                } else {
                    throw new RuntimeException('Structure différente, application refusée : ' . $relative);
                }
            }
        }
        return str_contains($before, "\r\n") ? str_replace("\n", "\r\n", $after) : $after;
    }

    public static function fingerprint(array $plan): string
    {
        $hashes = array_map(static fn(array $entry): array => [$entry['hash'], $entry['after_hash']], $plan);
        return hash('sha256', json_encode($hashes, JSON_THROW_ON_ERROR));
    }

    private static function storage(string $root): string
    {
        $path = self::path($root, self::DIRECTORY);
        if (!is_dir($path) && !mkdir($path, 0700, true)) { throw new RuntimeException('Sauvegarde privée impossible.'); }
        self::atomic($path . '/.htaccess', "Require all denied\nDeny from all\n", 0600);
        self::atomic($path . '/web.config', '<?xml version="1.0"?><configuration><system.webServer><security><authorization><remove users="*" roles="" verbs=""/><add accessType="Deny" users="*"/></authorization></security></system.webServer></configuration>', 0600);
        return $path;
    }

    private static function atomic(string $path, string $bytes, int $permissions): void
    {
        if (is_link($path)) { throw new RuntimeException('Lien symbolique refusé.'); }
        $temp = tempnam(dirname($path), '.p3-backoffice-');
        if ($temp === false) { throw new RuntimeException('Préparation du fichier impossible.'); }
        try {
            if (file_put_contents($temp, $bytes, LOCK_EX) !== strlen($bytes)
                || !chmod($temp, $permissions) || !rename($temp, $path)) {
                throw new RuntimeException('Écriture atomique impossible : ' . basename($path));
            }
        } finally {
            if (is_file($temp)) { unlink($temp); }
        }
        if (function_exists('opcache_invalidate')) { opcache_invalidate($path, true); }
    }

    public static function receipt(string $root): ?array
    {
        $path = self::path($root, self::DIRECTORY . '/receipt.json');
        if (!is_file($path)) { return null; }
        return json_decode((string) file_get_contents($path), true, 64, JSON_THROW_ON_ERROR);
    }

    private static function saveReceipt(string $directory, array $receipt): void
    {
        self::atomic($directory . '/receipt.json', json_encode($receipt, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT), 0600);
    }

    private static function clearMaintenance(string $root, array $receipt): void
    {
        $path = self::path($root, self::MAINTENANCE);
        if (is_file($path) && file_get_contents($path) === $receipt['maintenance']) {
            if (!unlink($path)) { throw new RuntimeException('Retrait de la maintenance impossible.'); }
        }
    }

    public static function apply(string $root, string $expectedFingerprint, ?callable $afterWrite = null): array
    {
        $directory = self::storage($root);
        $lockPath = self::path($root, self::DIRECTORY . '/lock');
        $lock = fopen($lockPath, 'c');
        if ($lock === false || !flock($lock, LOCK_EX)) { throw new RuntimeException('Verrouillage impossible.'); }
        $receipt = null;
        try {
            $plan = self::plan($root);
            if (!hash_equals($expectedFingerprint, self::fingerprint($plan))) {
                throw new RuntimeException('Les fichiers ont changé depuis le contrôle. Recharger cette page.');
            }
            $changes = array_filter($plan, static fn(array $entry): bool => $entry['changed']);
            if ($changes === []) { return self::receipt($root) ?? ['state' => 'applied', 'backup' => '']; }
            $maintenancePath = self::path($root, self::MAINTENANCE);
            if (file_exists($maintenancePath)) { throw new RuntimeException('Une autre maintenance est déjà active.'); }
            $token = gmdate('Ymd-His') . '-' . bin2hex(random_bytes(5));
            $backupRelative = self::DIRECTORY . '/' . $token;
            $backup = self::path($root, $backupRelative);
            if (!mkdir($backup, 0700)) { throw new RuntimeException('Sauvegarde impossible.'); }
            $receipt = ['state' => 'preparing', 'backup' => $backupRelative, 'maintenance' => 'P3 back-office ' . $token,
                'version' => '0.8.01-beta1-preview5', 'files' => []];
            foreach ($changes as $relative => $entry) {
                $path = self::path($root, $relative);
                $mode = is_file($path) ? fileperms($path) & 0777 : 0644;
                $receipt['files'][$relative] = ['hash' => $entry['hash'], 'after_hash' => $entry['after_hash'], 'permissions' => $mode];
                if ($entry['before'] !== null) {
                    $saved = $backup . '/' . $relative;
                    if (!is_dir(dirname($saved)) && !mkdir(dirname($saved), 0700, true)) { throw new RuntimeException('Sauvegarde impossible.'); }
                    self::atomic($saved, $entry['before'], 0600);
                }
            }
            $receipt['state'] = 'applying';
            self::saveReceipt($directory, $receipt);
            $maintenance = @fopen($maintenancePath, 'x');
            if ($maintenance === false) { throw new RuntimeException('Une maintenance a commencé simultanément.'); }
            try {
                if (fwrite($maintenance, $receipt['maintenance']) !== strlen($receipt['maintenance'])) {
                    throw new RuntimeException('Activation de maintenance impossible.');
                }
            } finally { fclose($maintenance); }
            foreach ($changes as $relative => $entry) {
                $path = self::path($root, $relative);
                $hash = is_file($path) ? hash_file('sha256', $path) : null;
                if ($hash !== $entry['hash']) { throw new RuntimeException('Modification concurrente : ' . $relative); }
                self::atomic($path, $entry['after'], $receipt['files'][$relative]['permissions']);
                if ($afterWrite !== null) { $afterWrite($relative); }
            }
            $receipt['state'] = 'applied';
            self::saveReceipt($directory, $receipt);
            self::clearMaintenance($root, $receipt);
            return $receipt;
        } catch (Throwable $error) {
            if ($receipt !== null && $receipt['state'] === 'applying') {
                self::restoreFiles($root, $receipt);
                $receipt['state'] = 'restored';
                self::saveReceipt($directory, $receipt);
                self::clearMaintenance($root, $receipt);
            }
            throw $error;
        } finally { flock($lock, LOCK_UN); fclose($lock); }
    }

    private static function restoreFiles(string $root, array $receipt): void
    {
        // Verify every current file before restoring any of them.
        foreach ($receipt['files'] as $relative => $entry) {
            $path = self::path($root, $relative);
            $hash = is_file($path) ? hash_file('sha256', $path) : null;
            if ($hash !== $entry['hash'] && $hash !== $entry['after_hash']) {
                throw new RuntimeException('Restauration refusée, fichier modifié depuis le correctif : ' . $relative);
            }
            if ($entry['hash'] !== null) {
                $saved = self::path($root, $receipt['backup'] . '/' . $relative);
                if (!is_file($saved) || hash_file('sha256', $saved) !== $entry['hash']) {
                    throw new RuntimeException('Sauvegarde absente ou endommagée : ' . $relative);
                }
            }
        }
        foreach (array_reverse($receipt['files'], true) as $relative => $entry) {
            $path = self::path($root, $relative);
            if ($entry['hash'] === null) {
                if (is_file($path) && !unlink($path)) { throw new RuntimeException('Suppression du fichier ajouté impossible.'); }
            } else {
                $saved = self::path($root, $receipt['backup'] . '/' . $relative);
                self::atomic($path, (string) file_get_contents($saved), $entry['permissions']);
            }
            if (function_exists('opcache_invalidate')) { opcache_invalidate($path, true); }
        }
    }

    public static function restore(string $root, string $expectedReceiptHash): void
    {
        $directory = self::storage($root);
        $lock = fopen(self::path($root, self::DIRECTORY . '/lock'), 'c');
        if ($lock === false || !flock($lock, LOCK_EX)) { throw new RuntimeException('Verrouillage impossible.'); }
        try {
            $receipt = self::receipt($root);
            if ($receipt === null || !in_array($receipt['state'], ['applied', 'applying'], true)
                || !hash_equals($expectedReceiptHash, hash('sha256', json_encode($receipt, JSON_THROW_ON_ERROR)))) {
                throw new RuntimeException('État de sauvegarde différent. Recharger cette page.');
            }
            $maintenancePath = self::path($root, self::MAINTENANCE);
            if (file_exists($maintenancePath) && file_get_contents($maintenancePath) !== $receipt['maintenance']) {
                throw new RuntimeException('Une autre maintenance est active.');
            }
            if (!file_exists($maintenancePath)) {
                $file = @fopen($maintenancePath, 'x');
                if ($file === false) { throw new RuntimeException('Maintenance concurrente.'); }
                try { fwrite($file, $receipt['maintenance']); } finally { fclose($file); }
            }
            self::restoreFiles($root, $receipt);
            $receipt['state'] = 'restored';
            self::saveReceipt($directory, $receipt);
            self::clearMaintenance($root, $receipt);
        } finally { flock($lock, LOCK_UN); fclose($lock); }
    }
}
