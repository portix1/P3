<?php
if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Ce test doit être exécuté avec PHP CLI.\n");
    exit(1);
}

$stage = 'chargement des classes';
$root = '';
$status = 0;
try {
    require_once dirname(__DIR__) . '/includes/class/security.class.php';
    require_once dirname(__DIR__) . '/includes/class/settings.class.php';
    require_once dirname(__DIR__) . '/bootstrap/autoload.php';

    $stage = 'test CSRF';
    $_SESSION = array();
    if (PortixSecurity::csrfToken() !== '') {
        throw new RuntimeException('Un jeton CSRF ne doit pas créer implicitement une session publique.');
    }
    session_id('p3sec' . bin2hex(random_bytes(5)));
    if (!session_start()) {
        throw new RuntimeException('Impossible de démarrer la session de test CSRF.');
    }
    $token = PortixSecurity::csrfToken();
    if ($token === '' || !PortixSecurity::csrfIsValid($token) || PortixSecurity::csrfIsValid($token . 'x')) {
        throw new RuntimeException('Le jeton valide ou le jeton falsifié est mal évalué.');
    }
    $roles = new class {
        public function isAdmin(): bool { return true; }
        public function isRedactor(): bool { return true; }
        public function isModerator(): bool { return true; }
    };
    if (!PortixSecurity::hasRole($roles, 'admin')
        || !PortixSecurity::hasRole($roles, 'redactor')
        || PortixSecurity::hasRole($roles, 'moderator')) {
        throw new RuntimeException('La liste fermée des rôles administratifs est incorrecte.');
    }

    $stage = 'création du dossier temporaire';
    $root = sys_get_temp_dir() . DIRECTORY_SEPARATOR
        . 'portix2-security-' . bin2hex(random_bytes(5));
    if (!mkdir($root . DIRECTORY_SEPARATOR . 'var' . DIRECTORY_SEPARATOR . 'sys', 0770, true)) {
        throw new RuntimeException('Impossible de créer le dossier temporaire.');
    }
    $templateFile = $root . DIRECTORY_SEPARATOR . 'test.tpl';

    $stage = 'test des réglages JSON';
    PortixSettings::write('test', array('value' => '<ok>'), $root);
    $settings = PortixSettings::read('test', array(), $root);
    if (($settings['value'] ?? '') !== '<ok>') {
        throw new RuntimeException('La valeur JSON relue ne correspond pas.');
    }

    $stage = 'protection HTTP des schémas de modules';
    $projectRoot = dirname(__DIR__);
    foreach (array('.htaccess', 'web.config') as $serverRules) {
        $rules = (string) file_get_contents($projectRoot . DIRECTORY_SEPARATOR . $serverRules);
        if (!str_contains($rules, 'var/modules/[^/]+/database')) {
            throw new RuntimeException('Protection absente dans ' . $serverRules . '.');
        }
    }

    $stage = '404 P3 pour les URL PHP inconnues';
    $apacheRules = (string) file_get_contents($projectRoot . '/.htaccess');
    if (!str_contains($apacheRules, 'RewriteRule ^.*\\.(?:phar|phtml|php[0-9]?)$ index.php [L,QSA,NC]')
        || str_contains($apacheRules, 'RewriteRule ^.*\\.(?:phar|phtml|php[0-9]?)$ - [F,L,NC]')) {
        throw new RuntimeException('Les URL PHP inconnues ne sont pas routées vers la 404 P3.');
    }

    $stage = 'accès administrateur au gestionnaire de téléchargements';
    $downloadManifest = (string) file_get_contents($projectRoot . '/var/modules/download/desc.xml');
    $downloadConfig = (string) file_get_contents($projectRoot . '/var/modules/download/config.php');
    if (!str_contains($downloadManifest, '<AdminAccess>admin</AdminAccess>')
        || !str_contains($downloadConfig, "PortixSecurity::requireRole(\$user, 'admin')")) {
        throw new RuntimeException('Le gestionnaire de téléchargements n’est pas verrouillé au rôle admin.');
    }
    $redactorOnly = new class {
        public function isAdmin(): bool { return false; }
        public function isRedactor(): bool { return true; }
    };
    $denied = false;
    try {
        PortixSecurity::requireRole($redactorOnly, 'admin');
    } catch (RuntimeException) {
        $denied = true;
    }
    if (!$denied) {
        throw new RuntimeException('Un rédacteur peut franchir requireRole(admin).');
    }

    $stage = 'test d’échappement du template';
    file_put_contents($templateFile, '{{ escaped }}|{{ raw }}');
    $template = new \Portix\View\Template($templateFile);
    $template->set('escaped', '<script>alert(1)</script>');
    $template->set('raw', \Portix\View\Html::trusted('<strong>ok</strong>'));
    $rendered = $template->render();
    if (strpos($rendered, '<script>') !== false
        || strpos($rendered, '&lt;script&gt;') === false
        || strpos($rendered, '<strong>ok</strong>') === false) {
        throw new RuntimeException('L’échappement automatique ou le HTML explicite est incorrect.');
    }

    $stage = 'test de redéfinition d’une variable de template';
    file_put_contents($templateFile, '{{ state }}');
    $template = new \Portix\View\Template($templateFile);
    $template->set('state', 'ancienne valeur');
    $template->set('state', 'nouvelle valeur');
    if ($template->render() !== 'nouvelle valeur') {
        throw new RuntimeException('La dernière affectation d’une variable doit remplacer la précédente.');
    }

    $stage = 'test des conditions imbriquées du template';
    file_put_contents(
        $templateFile,
        '{% if show == "1" %}<main>{% for account in account %}<article>{{ account.name }}'
        . '{% if account.admin == "1" %}<b>ADMIN</b>{% endif %}'
        . '{% if account.action == "1" %}<button>GERER</button>{% endif %}'
        . '</article>{% endfor %}</main>{% endif %}'
    );
    $template = new \Portix\View\Template($templateFile);
    $template->set('show', '1');
    $template->addRow('account', ['name' => 'Marc', 'admin' => '1', 'action' => '1']);
    $template->addRow('account', ['name' => 'Alice', 'admin' => '0', 'action' => '1']);
    $rendered = $template->render();
    if (substr_count($rendered, '<article>') !== 2
        || substr_count($rendered, '<b>ADMIN</b>') !== 1
        || substr_count($rendered, '<button>GERER</button>') !== 2
        || str_contains($rendered, '{%')) {
        throw new RuntimeException('Les conditions imbriquées ou répétées sont mal évaluées.');
    }

    $stage = 'rendu public des téléchargements';
    $template = new \Portix\View\Template($projectRoot . '/var/modules/download/templates/index.tpl');
    $template->set('download', 'Téléchargements');
    $template->set('downloads_public_intro', 'Documents et fichiers mis à disposition par le site.');
    $template->set('downloads_listing', '0');
    $template->set('downloads_has_files', '1');
    $template->set('downloads_category_title', 'Guides');
    $template->set('downloads_category_description', 'Documentation');
    $template->set('downloads_all_categories', 'Tous les téléchargements');
    $template->set('downloads_no_files', 'Aucun fichier');
    $template->set('downloads_files_count', 'fichiers');
    $template->set('downloads_format', 'Format');
    $template->set('downloads_download', 'Télécharger');
    $template->set('downloaded', 'Téléchargé');
    $template->set('size', 'Taille');
    $template->set('times', 'fois');
    $template->addRow('downloadfile', [
        'id' => '7',
        'title' => 'Guide Portix',
        'description' => 'Guide de test',
        'mime_type' => 'application/pdf',
        'size' => '12 Kio',
        'checksum' => str_repeat('a', 64),
        'counter' => '2',
    ]);
    $rendered = $template->render();
    if (strpos($rendered, 'Guide Portix') === false
        || strpos($rendered, 'download.php?id=7') === false
        || str_contains($rendered, '{%')) {
        throw new RuntimeException('Un fichier publié ne figure pas dans sa catégorie publique.');
    }
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }
    echo "Sécurité, rôles, JSON et échappement des templates : OK\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'Test de sécurité : ÉCHEC (' . $stage . ') - '
        . get_class($error) . ': ' . $error->getMessage() . "\n"
        . $error->getTraceAsString() . "\n");
    $status = 1;
} finally {
    if ($root !== '' && is_dir($root)) {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iterator as $item) {
            $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
        }
        @rmdir($root);
    }
}
exit($status);
