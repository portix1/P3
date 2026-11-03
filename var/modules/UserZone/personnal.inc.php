<?php
if (!empty($_SESSION['authenticated']) && isset($user) && $user->pseudo() !== '') {
    $template->setSlot('main', './var/modules/UserZone/templates/changeinfo.tpl');

    if (isset($_POST['name'])) {
        try {
        PortixSecurity::requirePostWithCsrf();
        $passwordChanged = false;
        $lastName = trim((string) $_POST['name']);
        if ($lastName === '') {
            $template->addRow('warning', array('message' => $_LANG['emptyname']));
            $lastName = $user->lastName();
        }

        $firstName = trim((string) ($_POST['firstname'] ?? ''));
        if ($firstName === '') {
            $template->addRow('warning', array('message' => $_LANG['emptyfirstname']));
            $firstName = $user->firstName();
        }

        $email = trim((string) ($_POST['mail'] ?? ''));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $template->addRow('warning', array('message' => $translator->text('profile_invalid_email')));
            $email = $user->email();
        }
        $repositories->users()->updateProfile($user->pseudo(), $lastName, $firstName, $email);

        if (!empty($_POST['pass1']) || !empty($_POST['pass2'])) {
            if (isset($_POST['oldpass']) && $user->verifyPassword((string) $_POST['oldpass'])) {
                if (isset($_POST['pass1'], $_POST['pass2'])
                    && hash_equals((string) $_POST['pass1'], (string) $_POST['pass2'])
                    && strlen((string) $_POST['pass2']) >= 12) {
                    $repositories->users()->updatePassword(
                        $user->pseudo(),
                        password_hash((string) $_POST['pass2'], PASSWORD_DEFAULT)
                    );
                    $passwordChanged = true;
                } else {
                    $template->addRow(
                        'warning',
                        array('message' => $translator->text('profile_password_requirements'))
                    );
                }
            } else {
                $template->addRow('warning', array('message' => $_LANG['badpass']));
            }
        }
        $user = $repositories->users()->find($user->pseudo()) ?? $user;
        if ($passwordChanged) {
            session_regenerate_id(true);
        }
        $_SESSION['auth_version'] = $user->authVersion();
        $template->set('usrmsg', $_LANG['updateok']);
        } catch (RuntimeException $error) {
            $template->addRow('warning', array('message' => $translator->exception($error)));
        }
    }

    $template->set('usrname', $user->lastName());
    $template->set('usrfirstname', $user->firstName());
    $template->set('usrmail', $user->email());
}
