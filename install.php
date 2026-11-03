<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap/autoload.php';
require_once __DIR__ . '/includes/class/security.class.php';

PortixSecurity::sendHeaders();

$preflight = (new Portix\Installation\EnvironmentPreflight(__DIR__))->run();
$error = '';
$installed = false;
$application = null;

if ($preflight['ok']) {
    /** @var Portix\Core\Application $application */
    $application = require __DIR__ . '/bootstrap/app.php';

    if ($application->configuration()->isInstalled()) {
        exit('<p>P3 est déjà configuré. <a href="./">Ouvrir le site</a></p>');
    }

    if (empty($_SESSION['install_token'])) {
        $_SESSION['install_token'] = bin2hex(random_bytes(32));
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        try {
            $preflight = (new Portix\Installation\EnvironmentPreflight(__DIR__))->run();
            if (!$preflight['ok']) {
                throw new RuntimeException(
                    'L’environnement du serveur ne permet plus l’installation. Corrigez les éléments signalés puis réessayez.'
                );
            }
            if (!isset($_POST['token'])
                || !hash_equals((string) $_SESSION['install_token'], (string) $_POST['token'])) {
                throw new RuntimeException('Jeton d’installation invalide. Rechargez la page.');
            }

            $title = trim((string) ($_POST['title'] ?? ''));
            $pseudo = trim((string) ($_POST['admin_pseudo'] ?? ''));
            $email = trim((string) ($_POST['admin_email'] ?? ''));
            $password = (string) ($_POST['admin_password'] ?? '');

            if ($title === '' || strlen($title) > 150 || !preg_match('/^[A-Za-z0-9_.-]{2,64}$/', $pseudo)) {
                throw new RuntimeException('Titre ou pseudonyme administrateur invalide.');
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 12) {
                throw new RuntimeException(
                    'Une adresse e-mail valide et un mot de passe de 12 caractères minimum sont requis.'
                );
            }

            if (is_file('./database/portix2.sqlite')) {
                $existingDatabase = $application->database()->connection();
                $hasCurrentUsersTable = $existingDatabase->query(
                    "SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = 'users'"
                )->fetchColumn() !== false;
                if (!$hasCurrentUsersTable
                    || (int) $existingDatabase->query('SELECT COUNT(*) FROM users')->fetchColumn() !== 0) {
                    throw new RuntimeException(
                        'Une base SQLite existe déjà. Restaurez sa configuration ou déplacez cette base avant une installation neuve.'
                    );
                }
            }

            $application->migrate();
            (new Portix\Module\BundledModuleSynchronizer(
                $application->root(),
                $application->database()
            ))->synchronize();

            $repositories = $application->repositories();
            $application->database()->transaction(function (PDO $pdo) use (
                $repositories,
                $pseudo,
                $password,
                $email
            ): void {
                $repositories->users()->create(
                    $pseudo,
                    password_hash($password, PASSWORD_DEFAULT),
                    $email,
                    $pseudo,
                    '',
                    true,
                    true
                );
                $repositories->menu()->create('Accueil', 'Page d’accueil', 'accueil.html');
                $repositories->menu()->create('Contact', 'Nous contacter', 'contact.html');
                $repositories->menu()->create('Téléchargements', 'Fichiers disponibles', 'dwl.html');
                $now = gmdate('Y-m-d H:i:s');
                (new Portix\Installation\DefaultContentSeeder())->seed($pdo, $pseudo, $now);
            });

            $application->configuration()->replace([
                'lang' => 'fr',
                'template' => 'modern',
                'defaultpage' => 'news',
                'title' => $title,
                'site_url' => '',
                'site_description' => '',
                'mail_sender_name' => $title,
                'mail_sender_email' => '',
                'debug' => false,
            ]);
            PortixSettings::write('contact', [
                'recipient_name' => $pseudo,
                'notification_email' => $email,
                'sender_name' => $title,
                'sender_email' => '',
                'retention_days' => 365,
            ]);
            PortixSettings::write('insights', [
                'mode' => 'minimal',
                'analytics_retention_days' => 730,
                'application_retention_days' => 30,
                'audit_retention_days' => 365,
                'last_purge_day' => '',
                'secret' => bin2hex(random_bytes(32)),
            ]);
            Portix\Maintenance\UpdateSafety::markRelease(
                __DIR__,
                trim((string) file_get_contents(__DIR__ . '/VERSION')),
                'install'
            );

            unset($_SESSION['install_token']);
            $installed = true;
        } catch (Throwable $exception) {
            $error = $exception->getMessage();
        }
    }
}

function installEscape(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** @param array{label:string, ok:bool, detail:string} $check */
function installCheckRow(array $check): string
{
    $class = $check['ok'] ? 'check-ok' : 'check-fail';
    $status = $check['ok'] ? 'OK' : 'Échec';
    $symbol = $check['ok'] ? '✓' : '✗';

    return '<li class="' . $class . '"><strong>' . $symbol . ' ' . installEscape($check['label'])
        . '</strong><span>' . $status . ' — ' . installEscape($check['detail']) . '</span></li>';
}
?>
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Installation de P3</title>
<style>
:root{color-scheme:light}body{font:16px/1.55 system-ui,sans-serif;max-width:52rem;margin:3rem auto;padding:0 1rem;color:#17202a;background:#fff}h1,h2{line-height:1.2}fieldset{border:1px solid #778493;border-radius:.6rem;margin:1rem 0;padding:1rem}label{display:block;margin:.8rem 0}input{display:block;width:100%;box-sizing:border-box;padding:.7rem;margin-top:.25rem;border:1px solid #657383;border-radius:.35rem;font:inherit}button,.button{display:inline-block;min-height:44px;box-sizing:border-box;padding:.7rem 1rem;border:1px solid #315f86;border-radius:.4rem;background:#e9f3fb;color:#173c5b;font:inherit;font-weight:700;text-decoration:none;cursor:pointer}.error{background:#fdecec;color:#7a1616;border:1px solid #bd5656;padding:1rem;border-radius:.5rem}.success{background:#eaf7ed;color:#174d25;border:1px solid #5e946b;padding:1rem;border-radius:.5rem}.preflight{margin:1.5rem 0;padding:1rem;border:1px solid #aeb8c2;border-radius:.6rem;background:#f7f9fb}.check-list{list-style:none;padding:0;margin:0;display:grid;gap:.55rem}.check-list li{padding:.65rem .75rem;border-radius:.4rem;border:1px solid transparent}.check-list strong,.check-list span{display:block}.check-list span{font-size:.92rem;margin-top:.15rem}.check-ok{background:#edf8ef;color:#174d25;border-color:#82ad8b!important}.check-fail{background:#fdecec;color:#7a1616;border-color:#c87070!important}.blocked{font-weight:700;color:#7a1616}.actions{display:flex;gap:.75rem;flex-wrap:wrap;margin-top:1rem}@media(max-width:36rem){body{margin:1.5rem auto}.actions>*{width:100%;text-align:center}}
</style>
</head>
<body>
<h1>Installation de P3</h1>

<section class="preflight" aria-labelledby="environment-title">
<h2 id="environment-title">Vérification de l’environnement</h2>
<p>P3 prépare automatiquement ses dossiers de travail et vérifie que PHP peut y créer puis supprimer un fichier.</p>

<h3>PHP</h3>
<ul class="check-list">
<?php foreach ($preflight['checks'] as $check): ?>
<?= installCheckRow($check) ?>
<?php endforeach; ?>
</ul>

<h3>Dossiers et permissions</h3>
<ul class="check-list">
<?php foreach ($preflight['directories'] as $check): ?>
<?= installCheckRow($check) ?>
<?php endforeach; ?>
</ul>
</section>

<?php if ($error !== ''): ?>
<p class="error" role="alert"><?= installEscape($error) ?></p>
<?php endif; ?>

<?php if ($installed): ?>
<div class="success" role="status">
<p><strong>Installation terminée.</strong></p>
<p>La base SQLite, la configuration et les dossiers d’écriture ont été contrôlés.</p>
<p><a class="button" href="./accueil.html">Ouvrir P3</a></p>
</div>
<?php elseif (!$preflight['ok']): ?>
<p class="blocked">L’installation est bloquée tant que tous les prérequis indispensables ne sont pas validés.</p>
<p>Si P3 ne peut pas corriger automatiquement un dossier, donnez à l’utilisateur du serveur Web les droits d’écriture sur ce dossier puis relancez le contrôle.</p>
<div class="actions"><a class="button" href="./install.php">Réessayer les vérifications</a></div>
<?php else: ?>
<p>Cette procédure crée une installation neuve avec une base SQLite et un premier compte administrateur.</p>
<form method="post">
<input type="hidden" name="token" value="<?= installEscape($_SESSION['install_token']) ?>">
<fieldset>
<legend>Site</legend>
<label>Titre
<input name="title" required maxlength="150" value="<?= installEscape($_POST['title'] ?? 'P3') ?>">
</label>
</fieldset>
<fieldset>
<legend>Administrateur</legend>
<label>Pseudonyme
<input name="admin_pseudo" required pattern="[A-Za-z0-9_.-]{2,64}" autocomplete="username" value="<?= installEscape($_POST['admin_pseudo'] ?? '') ?>">
</label>
<label>E-mail
<input name="admin_email" type="email" required autocomplete="email" value="<?= installEscape($_POST['admin_email'] ?? '') ?>">
</label>
<label>Mot de passe (12 caractères minimum)
<input name="admin_password" type="password" minlength="12" required autocomplete="new-password">
</label>
</fieldset>
<button type="submit">Installer P3</button>
</form>
<?php endif; ?>
</body>
</html>
