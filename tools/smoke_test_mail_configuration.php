<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Ce test doit être exécuté en ligne de commande.\n");
    exit(1);
}

$root = dirname(__DIR__);
$status = 0;

try {
    $contactPublic = (string) file_get_contents($root . '/var/modules/contact/index.php');
    $contactAdmin = (string) file_get_contents($root . '/var/modules/contact/config.php');
    $contactSettings = (string) file_get_contents($root . '/var/modules/contact/templates/settings.tpl');
    $passwordReset = (string) file_get_contents($root . '/var/modules/UserZone/index.php');
    $generalSettings = (string) file_get_contents($root . '/var/admin/templates/config-mail.tpl');

    mailAssertTrue(
        str_contains($contactPublic, "\$CONFIG['mail_sender_email']")
            && str_contains($contactPublic, "\$CONFIG['mail_sender_name']"),
        'le formulaire Contact utilise l’expéditeur système'
    );
    mailAssertTrue(
        str_contains($contactAdmin, "\$CONFIG['mail_sender_email']")
            && str_contains($contactAdmin, "\$CONFIG['mail_sender_name']"),
        'les réponses Contact utilisent l’expéditeur système'
    );
    mailAssertTrue(
        !str_contains($contactPublic, "\$settings['sender_email']")
            && !str_contains($contactAdmin, "\$settings['sender_email']")
            && !str_contains($contactAdmin, "\$settings['sender_name']"),
        'le module Contact ne possède plus d’expéditeur parallèle'
    );
    mailAssertTrue(
        !str_contains($contactSettings, 'name="sender_email"')
            && !str_contains($contactSettings, 'name="sender_name"'),
        'les champs d’expéditeur ont disparu des réglages Contact'
    );
    mailAssertTrue(
        !str_contains($passwordReset, "PortixSettings::read('contact'"),
        'la réinitialisation de mot de passe n’utilise plus le fallback Contact'
    );
    mailAssertTrue(
        str_contains($generalSettings, 'name="mail_sender_name"')
            && str_contains($generalSettings, 'name="mail_sender_email"')
            && str_contains($generalSettings, '{{ config_mail_sender_name }}')
            && str_contains($generalSettings, '{{ config_mail_sender_email }}'),
        'les champs d’expéditeur système sont présents dans Configuration / Messagerie du site'
    );

    echo "Expéditeur e-mail système unifié : OK\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'Test configuration e-mail : ÉCHEC - ' . $error->getMessage() . "\n");
    $status = 1;
}

exit($status);

function mailAssertTrue(bool $condition, string $label): void
{
    if (!$condition) {
        throw new RuntimeException($label . ' : échec.');
    }
}
