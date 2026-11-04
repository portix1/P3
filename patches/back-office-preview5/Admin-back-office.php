<?php
declare(strict_types=1);

$root = __DIR__;
if (!is_file($root . '/bootstrap/app.php') || !is_file($root . '/var/sys/config.json')) {
    http_response_code(400);
    exit('Placer les fichiers du correctif à la racine de P3.');
}
if (!is_file($root . '/VERSION') || trim((string) file_get_contents($root . '/VERSION')) !== '0.8.01-beta1-preview5') {
    http_response_code(400);
    exit('Ce correctif est réservé à P3 0.8.01-beta1-preview5.');
}
$app = require $root . '/bootstrap/app.php';
PortixSecurity::sendHeaders();
header('Cache-Control: private, no-store');
header('Content-Type: text/html; charset=UTF-8');
$user = null;
if (!empty($_SESSION['authenticated']) && isset($_SESSION['user_nick'], $_SESSION['auth_version'])) {
    $user = (new Portix\Repository\UserRepository($app->database()->connection()))->find((string) $_SESSION['user_nick']);
}
if ($user === null || !$user->isAdmin() || $user->authVersion() !== (int) ($_SESSION['auth_version'] ?? -1)) {
    http_response_code(403);
    exit('Connectez-vous comme administrateur sur ce site, puis ouvrez de nouveau cette page.');
}
require_once $root . '/tools/backoffice-production/Patch.php';
$error = '';
$message = '';
$plan = null;
try {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        PortixSecurity::requirePostWithCsrf();
        $action = (string) ($_POST['patch_action'] ?? '');
        if ($action === 'apply') {
            $expected = (string) ($_SESSION['backoffice_patch_plan'] ?? '');
            if ($expected === '') { throw new RuntimeException('Contrôle expiré. Recharger cette page.'); }
            BackofficeProductionPatch::apply($root, $expected);
            $message = 'Le back-office a été réorganisé. Vous pouvez ouvrir le tableau de bord.';
        } elseif ($action === 'restore') {
            $expected = (string) ($_SESSION['backoffice_patch_receipt'] ?? '');
            BackofficeProductionPatch::restore($root, $expected);
            $message = 'Les fichiers précédents ont été restaurés.';
        } else { throw new RuntimeException('Action inconnue.'); }
        unset($_SESSION['backoffice_patch_plan'], $_SESSION['backoffice_patch_receipt']);
    } elseif (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
        throw new RuntimeException('Méthode non autorisée.');
    }
    $plan = BackofficeProductionPatch::plan($root);
    $_SESSION['backoffice_patch_plan'] = BackofficeProductionPatch::fingerprint($plan);
} catch (Throwable $exception) {
    http_response_code(400);
    $error = $exception->getMessage();
}
$receipt = null;
try {
    $receipt = BackofficeProductionPatch::receipt($root);
    if ($receipt !== null) { $_SESSION['backoffice_patch_receipt'] = hash('sha256', json_encode($receipt, JSON_THROW_ON_ERROR)); }
} catch (Throwable $exception) { $error = $exception->getMessage(); }
$changed = $plan === null ? [] : array_filter($plan, static fn(array $entry): bool => $entry['changed']);
$canRestore = $receipt !== null && in_array($receipt['state'], ['applied', 'applying'], true);
$escape = static fn(string $text): string => htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?><!doctype html>
<html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Réorganisation du back-office P3</title>
<style>body{font:16px/1.5 system-ui,sans-serif;background:#f4f6fa;color:#17243a;margin:0}main{max-width:850px;margin:2rem auto;padding:1.5rem;background:white;border:1px solid #d5deea;border-radius:12px}button,.button{padding:.7rem 1rem;border:1px solid #718bac;border-radius:6px;color:#174b85;background:#edf5ff;cursor:pointer;text-decoration:none;display:inline-block}code{overflow-wrap:anywhere}.error{color:#8b1616}form{margin:1rem 0}</style></head>
<body><main>
<h1>Réorganisation du back-office</h1>
<p>Correctif pour P3 0.8.01-beta1-preview5. Il ajoute les trois espaces Rédaction, Administration et Configuration.</p>
<?php if ($error !== ''): ?><p class="error" role="alert"><?= $escape($error) ?></p><?php endif; ?>
<?php if ($message !== ''): ?><p role="status"><?= $escape($message) ?></p><?php endif; ?>
<?php if ($plan !== null && $changed !== []): ?>
<p><?= count($changed) ?> fichiers seront adaptés. Une sauvegarde privée sera créée avant application. Le site affichera brièvement la page de maintenance pendant le remplacement.</p>
<details><summary>Fichiers concernés</summary><ul><?php foreach ($changed as $relative => $entry): ?><li><code><?= $escape($relative) ?></code></li><?php endforeach; ?></ul></details>
<form method="post"><input type="hidden" name="csrf_token" value="<?= $escape(PortixSecurity::csrfToken()) ?>"><button type="submit" name="patch_action" value="apply">Appliquer la réorganisation du back-office</button></form>
<?php elseif ($plan !== null): ?><p>La réorganisation du back-office est déjà appliquée.</p><?php endif; ?>
<?php if ($canRestore): ?>
<p>Sauvegarde des fichiers précédents dans <code><?= $escape((string) $receipt['backup']) ?></code>.</p>
<form method="post"><input type="hidden" name="csrf_token" value="<?= $escape(PortixSecurity::csrfToken()) ?>"><button type="submit" name="patch_action" value="restore">Restaurer les fichiers précédents</button></form>
<?php endif; ?>
<p><a class="button" href="Admin.html">Ouvrir le tableau de bord</a></p>
<p>Après vérification, vous pouvez retirer ce script et le dossier <code>tools/backoffice-production</code>. Conservez la sauvegarde privée si vous souhaitez pouvoir revenir aux fichiers précédents.</p>
</main></body></html>
