<?php
declare(strict_types=1);

use Portix\Mail\NativeMailTransport;
use Portix\Observability\EventLogger;

header('Cache-Control: private, no-store, max-age=0');

$settings = PortixSettings::read('contact', [
    'recipient_name' => $translator->text('contact_default_recipient'),
    'notification_email' => '',
    'retention_days' => 365,
]);
$systemSenderName = trim((string) ($CONFIG['mail_sender_name'] ?? ''));
if ($systemSenderName === '') {
    $systemSenderName = (string) ($CONFIG['title'] ?? 'Portix 3');
}
$systemSenderEmail = trim((string) ($CONFIG['mail_sender_email'] ?? ''));
$pageMetadata->define($translator->text('contact'), $translator->text('contact_public_intro'), 'contact.html');
// A private continuation link keeps public replies attached to their conversation.
if (($argument[1] ?? '') === 'discussion') {
    $token = (string) ($argument[2] ?? '');
    $conversation = $repositories->contact()->findPublicConversation($token);
    $pageMetadata->noIndexNoFollow();
    header('Referrer-Policy: no-referrer');
    header('X-Robots-Tag: noindex, nofollow, noarchive');
    if ($conversation === null) {
        http_response_code(404);
        $template->setSlot('main', 'var/modules/contact/templates/unavailable.tpl');
        return;
    }
    $threadError = '';
    $threadNotice = '';
    if (isset($_POST['contact_thread_reply'])) {
        try {
            PortixSecurity::requirePostWithCsrf();
            if (trim((string) ($_POST['website'] ?? '')) !== '') { throw new RuntimeException($translator->text('contact_invalid_message')); }
            $body = trim(strip_tags((string) ($_POST['message'] ?? '')));
            if ($body === '' || mb_strlen($body) > 20000) { throw new RuntimeException($translator->text('contact_invalid_message')); }
            if (PortixSecurity::consumeContactRateLimit() > 0) { throw new RuntimeException($translator->text('contact_wait_before_send')); }
            $repositories->contact()->addMessage((int) $conversation['id'], 'incoming', $body, (string) $conversation['visitor_name'], 'received');
            $threadNotice = $translator->text('contact_received_message');
            $notificationEmail = (string) ($settings['notification_email'] ?? '');
            if (filter_var($notificationEmail, FILTER_VALIDATE_EMAIL) && filter_var($systemSenderEmail, FILTER_VALIDATE_EMAIL)) {
                (new NativeMailTransport())->send($notificationEmail, $translator->text('contact_notification_subject') . ' — ' . $conversation['subject'], $translator->text('contact_notification_body'), $systemSenderName, $systemSenderEmail);
            }
            if (PHP_SAPI !== 'cli') {
                header('Location: contact-discussion-' . $token . '.html?received=1', true, 303);
                return;
            }
        } catch (Throwable $error) { $threadError = $translator->exception($error); }
    }
    $template->setSlot('main', 'var/modules/contact/templates/thread.tpl');
    $template->set('contact_thread_subject', (string) $conversation['subject']);
    $template->set('contact_thread_token', $token);
    $template->set('contact_thread_error', $threadError);
    $template->set('contact_thread_notice', isset($_GET['received']) ? $translator->text('contact_received_message') : $threadNotice);
    foreach ($repositories->contact()->messages((int) $conversation['id']) as $row) {
        $template->addRow('publicmessage', ['body' => (string) $row['body'], 'date' => (string) $row['sent_at'], 'author' => $row['direction'] === 'incoming' ? $translator->text('contact_your_message') : (string) $settings['recipient_name']]);
    }
    return;
}

$errorMessage = '';

if (isset($_POST['contact_action'])) {
    try {
        PortixSecurity::requirePostWithCsrf();
        if (trim((string) ($_POST['website'] ?? '')) !== '') {
            throw new RuntimeException($translator->text('contact_invalid_message'));
        }
        $name = trim((string) ($_POST['name'] ?? ''));
        $email = trim((string) ($_POST['email'] ?? ''));
        $subject = trim((string) preg_replace('/\s+/u', ' ', strip_tags((string) ($_POST['subject'] ?? ''))));
        $body = trim(strip_tags((string) ($_POST['message'] ?? '')));
        if ($name === '' || mb_strlen($name) > 120
            || filter_var($email, FILTER_VALIDATE_EMAIL) === false
            || $subject === '' || mb_strlen($subject) > 200
            || $body === '' || mb_strlen($body) > 20000) {
            throw new RuntimeException($translator->text('contact_invalid_message'));
        }
        if (PortixSecurity::consumeContactRateLimit() > 0) {
            throw new RuntimeException($translator->text('contact_wait_before_send'));
        }

        $conversationId = $repositories->contact()->createConversation($name, $email, $subject, $body);
        $notificationEmail = (string) ($settings['notification_email'] ?? '');
        $notificationSent = false;
        if (filter_var($notificationEmail, FILTER_VALIDATE_EMAIL)
            && filter_var($systemSenderEmail, FILTER_VALIDATE_EMAIL)) {
            $siteUrl = rtrim((string) ($CONFIG['site_url'] ?? ''), '/');
            $adminUrl = ($siteUrl !== '' ? $siteUrl : $request->basePath())
                . '/Admin-contact-view-' . $conversationId . '.html';
            $notificationSent = (new NativeMailTransport())->send(
                $notificationEmail,
                $translator->text('contact_notification_subject') . ' — ' . $subject,
                $translator->text('contact_notification_body') . "\n\n"
                    . $translator->text('contact_from') . ': ' . $name . "\n"
                    . $translator->text('contact_subject') . ': ' . $subject . "\n\n"
                    . $adminUrl,
                $systemSenderName,
                $systemSenderEmail
            );
        }
        if (!$notificationSent) {
            (new EventLogger($database->connection()))->application(
                'warning',
                'contact.notification_failed',
                'A contact message was stored but its notification could not be sent.',
                ['conversation_id' => $conversationId],
                $requestId
            );
        }
        $template->setSlot('main', 'var/modules/contact/templates/sent.tpl');
        return;
    } catch (Throwable $error) {
        $errorMessage = $translator->exception($error);
    }
}

$displayName = trim($user->firstName() . ' ' . $user->lastName());
if ($displayName === '') {
    $displayName = $user->pseudo();
}
$template->set('contact_error', $errorMessage);
$template->set('contactname', $displayName);
$template->set('contactmail', $user->email());
$template->set('contactto', (string) ($settings['recipient_name'] ?? $translator->text('contact_default_recipient')));
$template->setSlot('main', 'var/modules/contact/templates/index.tpl');
