<?php
declare(strict_types=1);

// Isolate the patch engine: no production database, files or configuration.
$package = dirname(__DIR__);
$fixture = sys_get_temp_dir() . '/p3-backoffice-lines-' . bin2hex(random_bytes(6));
$support = $fixture . '/tools/backoffice-production';
$relative = 'var/modules/insights/templates/nav.tpl';
$manifest = json_decode((string) file_get_contents($package . '/tools/backoffice-production/manifest.json'), true, 64, JSON_THROW_ON_ERROR);
$operation = $manifest['files'][$relative];
$old = str_replace("\r\n", "\n", $operation['hunks'][0]['before']);
$new = str_replace("\r\n", "\n", $operation['hunks'][0]['after']);

function check(bool $condition, string $message): void
{
    if (!$condition) { throw new RuntimeException($message); }
}

function removeFixture(string $directory): void
{
    foreach (scandir($directory) ?: [] as $name) {
        if ($name === '.' || $name === '..') { continue; }
        $path = $directory . '/' . $name;
        if (is_dir($path) && !is_link($path)) { removeFixture($path); }
        else { unlink($path); }
    }
    rmdir($directory);
}

try {
    mkdir($support, 0700, true);
    mkdir($fixture . '/var/modules/insights/templates', 0700, true);
    mkdir($fixture . '/var/sys', 0700, true);
    file_put_contents($fixture . '/VERSION', '0.8.01-beta1-preview5');
    copy($package . '/tools/backoffice-production/Patch.php', $support . '/Patch.php');
    require $support . '/Patch.php';
    $count = 0;
    foreach (["\n", "\r\n"] as $fileEol) {
        foreach (["\n", "\r\n"] as $beforeEol) {
            foreach (["\n", "\r\n"] as $afterEol) {
                $testOperation = $operation;
                $testOperation['hunks'][0] = [
                    'before' => str_replace("\n", $beforeEol, $old),
                    'after' => str_replace("\n", $afterEol, $new),
                ];
                file_put_contents($support . '/manifest.json', json_encode([
                    'dependencies' => [], 'files' => [$relative => $testOperation],
                ], JSON_THROW_ON_ERROR));
                $before = str_replace("\n", $fileEol, "<!-- custom before -->\n" . $old . "<!-- custom after -->\n");
                $expected = str_replace("\n", $fileEol, "<!-- custom before -->\n" . $new . "<!-- custom after -->\n");
                file_put_contents($fixture . '/' . $relative, $before);
                $plan = BackofficeProductionPatch::plan($fixture);
                check($plan[$relative]['changed'], 'Expected a planned change.');
                $receipt = BackofficeProductionPatch::apply($fixture, BackofficeProductionPatch::fingerprint($plan));
                check(file_get_contents($fixture . '/' . $relative) === $expected, 'Wrong bytes or custom content lost.');
                check(!is_file($fixture . '/var/sys/maintenance.lock'), 'Maintenance was not removed.');
                $repeat = BackofficeProductionPatch::plan($fixture);
                check(!$repeat[$relative]['changed'], 'Repeated application must be idempotent.');
                BackofficeProductionPatch::apply($fixture, BackofficeProductionPatch::fingerprint($repeat));
                BackofficeProductionPatch::restore($fixture, hash('sha256', json_encode($receipt, JSON_THROW_ON_ERROR)));
                check(file_get_contents($fixture . '/' . $relative) === $before, 'Restore must preserve exact original bytes.');
                $count++;
            }
        }
    }
    echo "PASS $count LF/CRLF combinations: plan, apply, idempotence, restore and custom content.\n";

    foreach ([$old . $old, str_replace('Admin-insights.html', 'Admin-custom.html', $old)] as $unknown) {
        file_put_contents($fixture . '/' . $relative, $unknown);
        $refused = false;
        try { BackofficeProductionPatch::plan($fixture); }
        catch (RuntimeException $error) { $refused = str_contains($error->getMessage(), 'Structure différente'); }
        check($refused, 'Ambiguous or unknown structure must be refused.');
        check(file_get_contents($fixture . '/' . $relative) === $unknown, 'A refused plan wrote the file.');
    }
    echo "PASS unknown and ambiguous structures are still refused without replacement.\n";

    file_put_contents($fixture . '/' . $relative, $old);
    $plan = BackofficeProductionPatch::plan($fixture);
    try {
        BackofficeProductionPatch::apply($fixture, BackofficeProductionPatch::fingerprint($plan), static function (): void {
            throw new RuntimeException('Simulated write failure');
        });
        throw new LogicException('Expected the simulated failure.');
    } catch (RuntimeException $error) {
        check($error->getMessage() === 'Simulated write failure', 'Unexpected exception.');
    }
    check(file_get_contents($fixture . '/' . $relative) === $old, 'Automatic rollback failed.');
    check(!is_file($fixture . '/var/sys/maintenance.lock'), 'Failure left maintenance active.');
    echo "PASS automatic rollback after a simulated failure.\n";
} finally {
    if (is_dir($fixture)) { removeFixture($fixture); }
}
