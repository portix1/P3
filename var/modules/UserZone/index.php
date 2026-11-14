<?php

declare(strict_types=1);

use Portix\Domain\AnonymousUser;
use Portix\Mail\NativeMailTransport;
use Portix\Observability\EventLogger;

require_once './var/modules/UserZone/src/PasswordResetRepository.php';

$portixEventLogger = new EventLogger($database->connection());
$passwordResetRepository = new \PortixModule\UserZone\PasswordResetRepository($database->connection());

// Le template de connexion teste toujours cette variable, même sans erreur.
$template->set('login_message', '');

$Isadmin = false;
$authRoute = isset($argument[0]) ? (string) $argument[0] : '';

if ($authRoute === 'disconnect') {
    $disconnectActor = (string) ($_SESSION['user_nick'] ?? 'anonymous');
    try {
        PortixSecurity::requirePostWithCsrf();
    } catch (RuntimeException $error) {
        http_response_code(400);
        header('Content-Type: text/plain; charset=UTF-8');
        exit($translator->exception($error));
    }
    $portixEventLogger->audit($disconnectActor, 'user.logout', 'session', [], $requestId);
    $_SESSION = array();
    if (ini_get('session.use_cookies')) {
        $parameters = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $parameters['path'],
            $parameters['domain'],
            $parameters['secure'],
            $parameters['httponly']
        );
    }
    session_destroy();
    header('Location: ./');
    exit;
}

$authenticated = false;
$userPseudo = '';

if (isset($_POST['pseudo'], $_POST['pass'])) {
    $candidatePseudo = trim((string) $_POST['pseudo']);
    if (!PortixSecurity::csrfIsValid()) {
        $template->set('login_message', $translator->text('login_form_expired'));
    } elseif (($remaining = PortixSecurity::loginDelayRemaining($candidatePseudo)) > 0) {
        $template->set(
            'login_message',
            $translator->text('login_wait_prefix') . $remaining . $translator->text('login_wait_suffix')
        );
    } else {
        $candidate = $repositories->users()->find($candidatePseudo);
        if ($candidate !== null && $candidate->verifyPassword((string) $_POST['pass'])) {
            if (!session_regenerate_id(true)) {
                throw new RuntimeException('Impossible de renouveler la session.');
            }
            $template->set('csrf_token', PortixSecurity::rotateCsrfToken());
            \Portix\Preview\SessionPreviewStore::clear();
            if ($candidate->passwordNeedsUpgrade()) {
                $repositories->users()->updatePassword(
                    $candidatePseudo,
                    password_hash((string) $_POST['pass'], PASSWORD_DEFAULT)
                );
                $candidate = $repositories->users()->find($candidatePseudo);
            }
            $_SESSION['user_nick'] = $candidatePseudo;
            $_SESSION['authenticated'] = true;
            $_SESSION['auth_version'] = $candidate->authVersion();
            unset($_SESSION['pass']);
            $authenticated = true;
            $userPseudo = $candidatePseudo;
            PortixSecurity::clearLoginFailures($candidatePseudo);
            $portixEventLogger->audit($candidatePseudo, 'user.login', 'session', [], $requestId);
            if ($authRoute === 'login') {
                header('Location: ./');
                exit;
            }
        } else {
            \Portix\Preview\SessionPreviewStore::clear();
            $template->set('csrf_token', PortixSecurity::rotateCsrfToken());
            unset($_SESSION['user_nick'], $_SESSION['authenticated'], $_SESSION['auth_version'], $_SESSION['pass']);
            PortixSecurity::recordLoginFailure($candidatePseudo);
            $template->set('login_message', $translator->text('login_invalid_credentials'));
            $portixEventLogger->audit('anonymous', 'user.login_failed', $candidatePseudo, [], $requestId);
        }
    }
} elseif (!empty($_SESSION['authenticated']) && isset($_SESSION['user_nick'])) {
    $userPseudo = trim((string) $_SESSION['user_nick']);
    $authenticated = $userPseudo !== '';
}

$user = $authenticated ? $repositories->users()->find($userPseudo) : null;
if ($authenticated && $user !== null) {
    if (!isset($_SESSION['auth_version'])
        || (int) $_SESSION['auth_version'] !== $user->authVersion()) {
        $authenticated = false;
        $template->set('csrf_token', PortixSecurity::rotateCsrfToken());
        \Portix\Preview\SessionPreviewStore::clear();
        unset($_SESSION['user_nick'], $_SESSION['authenticated'], $_SESSION['auth_version'], $_SESSION['pass']);
        $user = null;
    }
}
if ($authenticated && $user !== null) {
    $template->set('username', ucfirst($userPseudo));
    $template->setSlot('users', './var/modules/UserZone/templates/personnal.tpl');
    $Isadmin = $user->isAdmin();
} else {
    if ($authenticated) {
        $template->set('csrf_token', PortixSecurity::rotateCsrfToken());
    }
    $authenticated = false;
    \Portix\Preview\SessionPreviewStore::clear();
        unset($_SESSION['user_nick'], $_SESSION['authenticated'], $_SESSION['auth_version'], $_SESSION['pass']);
    $user = new AnonymousUser();
    $template->setSlot('users', './var/modules/UserZone/templates/users.tpl');
}

if (in_array($authRoute, ['forgot-password', 'reset-password'], true) && $authenticated) {
    header('Location: ./');
    exit;
}

if ($authRoute === 'forgot-password') {
    $template->set('password_forgot_message', '');
    $template->set('password_forgot_error', '');

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        if (!PortixSecurity::csrfIsValid()) {
            $template->set('password_forgot_error', $translator->text('login_form_expired'));
        } else {
            $email = trim((string) ($_POST['email'] ?? ''));
            if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                $template->set('password_forgot_error', $translator->text('password_forgot_invalid_email'));
            } else {
                $template->set('password_forgot_message', $translator->text('password_forgot_confirmation'));
                $remaining = PortixSecurity::consumePasswordResetRateLimit($email);
                if ($remaining === 0) {
                    $candidate = $repositories->users()->findUniqueByEmail($email);
                    if ($candidate !== null) {
                        $siteUrl = rtrim(trim((string) ($CONFIG['site_url'] ?? '')), '/');
                        $scheme = strtolower((string) parse_url($siteUrl, PHP_URL_SCHEME));
                        if ($siteUrl === ''
                            || filter_var($siteUrl, FILTER_VALIDATE_URL) === false
                            || !in_array($scheme, ['http', 'https'], true)) {
                            $siteUrl = '';
                        }

                        $senderName = trim((string) ($CONFIG['mail_sender_name'] ?? ''));
                        $senderEmail = trim((string) ($CONFIG['mail_sender_email'] ?? ''));
                        if ($senderName === '') {
                            $senderName = (string) ($CONFIG['title'] ?? 'P3');
                        }
                        if ($siteUrl !== '' && $senderEmail !== '') {
                            $token = bin2hex(random_bytes(32));
                            $tokenHash = hash('sha256', $token);
                            $passwordResetRepository->issue($candidate->pseudo(), $tokenHash, 3600);
                            $resetUrl = $siteUrl . '/reset-password.html?token=' . rawurlencode($token);
                            $body = $translator->text('password_reset_mail_intro') . "\n\n"
                                . $translator->text('password_reset_mail_instruction') . "\n"
                                . $resetUrl . "\n\n"
                                . $translator->text('password_reset_mail_ignore');
                            $sent = (new NativeMailTransport())->send(
                                $candidate->email(),
                                (string) ($CONFIG['title'] ?? 'P3') . ' — '
                                    . $translator->text('password_reset_mail_subject'),
                                $body,
                                $senderName,
                                $senderEmail
                            );
                            if ($sent) {
                                $portixEventLogger->audit(
                                    'anonymous',
                                    'user.password_reset_requested',
                                    $candidate->pseudo(),
                                    [],
                                    $requestId
                                );
                            } else {
                                $passwordResetRepository->revoke($candidate->pseudo());
                                $portixEventLogger->application(
                                    'warning',
                                    'user.password_reset_mail_failed',
                                    'A password reset request was accepted but the email could not be sent.',
                                    ['pseudo' => $candidate->pseudo()],
                                    $requestId
                                );
                            }
                        } else {
                            $portixEventLogger->application(
                                'warning',
                                'user.password_reset_mail_unconfigured',
                                'A password reset request was accepted but the public site URL or sender email is not configured.',
                                ['pseudo' => $candidate->pseudo()],
                                $requestId
                            );
                        }
                    }
                }
            }
        }
    }

    $template->setSlot('main', './var/modules/UserZone/templates/forgot-password.tpl');
    $pageMetadata->define(
        $translator->text('password_forgot_title'),
        $translator->text('password_forgot_intro'),
        'forgot-password.html'
    );
}

if ($authRoute === 'reset-password') {
    $template->set('password_reset_success_message', '');
    $template->set('password_reset_error', '');
    $template->set('password_reset_token_valid', '0');
    $template->set('password_reset_token', '');

    $token = trim((string) ($_POST['reset_token'] ?? $_GET['token'] ?? ''));
    $tokenIsFormatted = preg_match('/^[a-f0-9]{64}$/', $token) === 1;
    $tokenHash = $tokenIsFormatted ? hash('sha256', $token) : '';

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        if (!PortixSecurity::csrfIsValid()) {
            $template->set('password_reset_error', $translator->text('login_form_expired'));
            if ($tokenIsFormatted && $passwordResetRepository->isValid($tokenHash)) {
                $template->set('password_reset_token_valid', '1');
                $template->set('password_reset_token', $token);
            }
        } else {
            $pass1 = (string) ($_POST['pass1'] ?? '');
            $pass2 = (string) ($_POST['pass2'] ?? '');
            if (!$tokenIsFormatted || !$passwordResetRepository->isValid($tokenHash)) {
                $template->set('password_reset_error', $translator->text('password_reset_invalid'));
            } elseif (!hash_equals($pass1, $pass2) || strlen($pass1) < 12 || strlen($pass1) > 4096) {
                $template->set('password_reset_error', $translator->text('password_reset_requirements'));
                $template->set('password_reset_token_valid', '1');
                $template->set('password_reset_token', $token);
            } else {
                $pseudo = $passwordResetRepository->resetPassword(
                    $tokenHash,
                    password_hash($pass1, PASSWORD_DEFAULT)
                );
                if ($pseudo === null) {
                    $template->set('password_reset_error', $translator->text('password_reset_invalid'));
                } else {
                    if (!session_regenerate_id(true)) {
                throw new RuntimeException('Impossible de renouveler la session.');
            }
            $template->set('csrf_token', PortixSecurity::rotateCsrfToken());
                    \Portix\Preview\SessionPreviewStore::clear();
                    unset(
                        $_SESSION['user_nick'],
                        $_SESSION['authenticated'],
                        $_SESSION['auth_version'],
                        $_SESSION['pass']
                    );
                    $template->set('password_reset_success_message', $translator->text('password_reset_success'));
                    $portixEventLogger->audit(
                        $pseudo,
                        'user.password_reset',
                        'account',
                        [],
                        $requestId
                    );
                }
            }
        }
    } elseif ($tokenIsFormatted && $passwordResetRepository->isValid($tokenHash)) {
        $template->set('password_reset_token_valid', '1');
        $template->set('password_reset_token', $token);
    } else {
        $template->set('password_reset_error', $translator->text('password_reset_invalid'));
    }

    $template->setSlot('main', './var/modules/UserZone/templates/reset-password.tpl');
    $pageMetadata->define(
        $translator->text('password_reset_title'),
        $translator->text('password_reset_intro'),
        'reset-password.html'
    );
}

if ($authRoute === 'login') {
    if ($authenticated) {
        header('Location: ./');
        exit;
    }
    $template->setSlot('main', './var/modules/UserZone/templates/login.tpl');
    $pageMetadata->define($translator->text('login_title'), $translator->text('login_introduction'), 'login.html');
}

switch ($authRoute) {
    case 'personnal':
        include './var/modules/UserZone/personnal.inc.php';
        break;
}
