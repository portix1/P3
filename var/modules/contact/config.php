<?php
declare(strict_types=1);

use Portix\Mail\NativeMailTransport;
use Portix\Observability\EventLogger;

if ((!$user->isAdmin() && !$user->isRedactor())
    || (!$user->isAdmin() && (($argument[2] ?? '') === 'settings'
        || in_array($_POST['contact_admin_action'] ?? '', ['settings', 'purge'], true)))) {
    http_response_code(403);
    $template->setSlot('main', './var/admin/templates/access-denied.tpl');
    return;
}
$settings = PortixSettings::read('contact', [
    'recipient_name' => $translator->text('contact_default_recipient'),
    'notification_email' => '',
    'retention_days' => 365,
    'trash_retention_days' => 30,
]);
$systemSenderName = trim((string) ($CONFIG['mail_sender_name'] ?? ''));
if ($systemSenderName === '') {
    $systemSenderName = (string) ($CONFIG['title'] ?? 'P3');
}
$systemSenderEmail = trim((string) ($CONFIG['mail_sender_email'] ?? ''));
$view = (string) ($argument[2] ?? 'inbox');
header('Cache-Control: private, no-store, max-age=0');
$folder = (string) ($_POST['folder'] ?? $_GET['folder'] ?? ($_GET['status'] ?? 'inbox'));
if (!in_array($folder, ['inbox', 'archives', 'closed', 'trash', 'all'], true)) { $folder = 'inbox'; }
$message = (string) ($_SESSION['contact_flash'] ?? '');
unset($_SESSION['contact_flash']);
// Expiration happens on authenticated administration access, without cron.
$repositories->contact()->purgeTrash((int) $settings['trash_retention_days']);
$logger = new EventLogger($database->connection());

if (isset($_POST['contact_admin_action'])) {
    try {
        PortixSecurity::requirePostWithCsrf();
        $action = (string) $_POST['contact_admin_action'];
        if ($action === 'settings') {
            PortixSecurity::requireRole($user, 'admin');
            $recipientName = trim((string) ($_POST['recipient_name'] ?? ''));
            $notificationEmail = trim((string) ($_POST['notification_email'] ?? ''));
            if ($recipientName === '' || mb_strlen($recipientName) > 120
                || filter_var($notificationEmail, FILTER_VALIDATE_EMAIL) === false) {
                throw new RuntimeException($translator->text('contact_invalid_settings'));
            }
            $settings = [
                'recipient_name' => $recipientName,
                'notification_email' => $notificationEmail,
                'retention_days' => (int) ($settings['retention_days'] ?? 365),
                'trash_retention_days' => max(1, min(3650, (int) ($_POST['trash_retention_days'] ?? 30))),
            ];
            PortixSettings::write('contact', $settings);
            $logger->audit($user->pseudo(), 'contact.settings', 'contact', [], $requestId);
            $message = $translator->text('contact_settings_saved');
        } elseif ($action === 'reply') {
            $id = filter_var($_POST['conversation_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            $reply = trim((string) ($_POST['reply'] ?? ''));
            $conversation = $id === false ? null : $repositories->contact()->findConversation((int) $id);
            if ($conversation === null || $conversation['deleted_at'] !== null || $conversation['status'] === 'closed' || $reply === '' || mb_strlen($reply) > 20000) {
                throw new RuntimeException($translator->text('contact_invalid_reply'));
            }
            $messageId = $repositories->contact()->addMessage(
                (int) $id,
                'outgoing',
                $reply,
                $user->pseudo(),
                'pending'
            );
            $publicBase = \Portix\Seo\PublicUrl::resolve((string) ($CONFIG['site_url'] ?? ''), $request);
            $mailBody = $reply;
            if ($publicBase !== '') {
                $mailBody .= "\n\n" . $translator->text('contact_continue') . ': ' . $publicBase . '/contact-discussion-' . $conversation['public_token'] . '.html';
            }
            $sent = (new NativeMailTransport())->send(
                (string) $conversation['visitor_email'],
                $translator->text('contact_reply_subject') . ' — ' . (string) $conversation['subject'],
                $mailBody,
                $systemSenderName,
                $systemSenderEmail
            );
            $repositories->contact()->updateMessageDelivery($messageId, $sent ? 'sent' : 'failed');
            $logger->audit(
                $user->pseudo(),
                'contact.reply',
                'conversation:' . $id,
                ['delivery' => $sent ? 'sent' : 'failed'],
                $requestId
            );
            if (!$sent) {
                throw new RuntimeException($translator->text('contact_reply_failed'));
            }
            $message = $translator->text('contact_reply_sent');
            $view = 'view';
            $argument[3] = (string) $id;
        } elseif (in_array($action, ['archive', 'unarchive', 'close', 'reopen', 'trash', 'restore', 'read', 'unread'], true)) {
            if ($action === 'trash' && ($_POST['confirm_trash'] ?? '') !== 'yes') { throw new RuntimeException($translator->text('contact_trash_confirm')); }
            $rawIds = $_POST['ids'] ?? [$_POST['conversation_id'] ?? 0];
            if (!is_array($rawIds) || count($rawIds) > 1000 || $rawIds === []) { throw new RuntimeException($translator->text('contact_select_required')); }
            $ids = [];
            foreach ($rawIds as $rawId) {
                $id = filter_var($rawId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
                if ($id === false) { throw new RuntimeException($translator->text('contact_conversation_not_found')); }
                $ids[] = (int) $id;
            }
            $count = $repositories->contact()->applyAction($ids, $action);
            $logger->audit($user->pseudo(), 'contact.' . $action, 'contact', ['ids' => $ids, 'count' => $count], $requestId);
            $message = $translator->text('contact_action_done') . ' ' . $count;
            $view = 'inbox';
        } elseif (in_array($action, ['message_trash', 'message_restore'], true)) {
            $id = filter_var($_POST['conversation_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            $messageId = filter_var($_POST['message_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($id === false || $messageId === false) { throw new RuntimeException($translator->text('contact_conversation_not_found')); }
            if ($action === 'message_trash' && ($_POST['confirm_trash'] ?? '') !== 'yes') { throw new RuntimeException($translator->text('contact_trash_confirm')); }
            $repositories->contact()->messageAction((int) $id, (int) $messageId, $action === 'message_restore');
            $logger->audit($user->pseudo(), 'contact.' . $action, 'message:' . $messageId, ['conversation_id' => $id], $requestId);
            $view = 'view';
            $argument[3] = (string) $id;
        } elseif ($action === 'purge') {
            PortixSecurity::requireRole($user, 'admin');
            if (($_POST['confirm_empty'] ?? '') !== 'yes') { throw new RuntimeException($translator->text('contact_confirm_required')); }
            $deleted = $repositories->contact()->purgeTrash((int) $settings['trash_retention_days'], true);
            $logger->audit($user->pseudo(), 'contact.purge', 'contact', ['deleted' => $deleted], $requestId);
            $message = $translator->text('contact_purged') . ' ' . $deleted;
        }
        if (PHP_SAPI !== 'cli') {
            $_SESSION['contact_flash'] = $message;
            $destination = $view === 'settings' ? 'Admin-contact-settings.html' : ($view === 'view' ? 'Admin-contact-view-' . (int) $argument[3] . '.html' : 'Admin-contact.html?folder=' . rawurlencode($folder));
            header('Location: ' . $destination, true, 303);
            exit;
        }
    } catch (Throwable $error) {
        $message = $translator->exception($error);
    }
}

$template->set('contact_admin_message', $message);
$template->set('contact_inbox_current', $view === 'inbox' ? 'is-current' : '');
$template->set('contact_settings_current', $view === 'settings' ? 'is-current' : '');
$template->set('contact_can_configure', $user->isAdmin() ? '1' : '0');

if ($view === 'settings') {
    PortixSecurity::requireRole($user, 'admin');
    $template->setSlot('main', 'var/modules/contact/templates/settings.tpl');
    $template->set('contact_recipient_name', (string) $settings['recipient_name']);
    $template->set('contact_notification_email', (string) $settings['notification_email']);
    $template->set('contact_retention_days', (string) $settings['trash_retention_days']);
    return;
}

if ($view === 'view' && isset($argument[3]) && ctype_digit((string) $argument[3])) {
    $conversation = $repositories->contact()->findConversation((int) $argument[3]);
    if ($conversation === null) {
        throw new RuntimeException($translator->text('contact_conversation_not_found'));
    }
    $template->setSlot('main', 'var/modules/contact/templates/conversation.tpl');
    foreach (['id', 'visitor_name', 'visitor_email', 'subject', 'status', 'created_at', 'updated_at'] as $key) {
        $template->set('contact_' . $key, (string) $conversation[$key]);
    }
    $template->set('contact_conversation_subject', (string) $conversation['subject']);
    $template->set('contact_is_open', $conversation['status'] !== 'closed' ? '1' : '0');
    $repositories->contact()->markRead((int) $conversation['id']);
    $template->set('contact_conversation_live', $conversation['deleted_at'] === null ? '1' : '0');
    $template->set('contact_can_reply', $conversation['status'] !== 'closed' && $conversation['deleted_at'] === null ? '1' : '0');
    foreach ($repositories->contact()->messages((int) $conversation['id']) as $row) {
        $template->addRow('contactmessage', [
            'id' => (string) $row['id'],
            'direction' => (string) $row['direction'],
            'direction_label' => $translator->text('contact_direction_' . (string) $row['direction']),
            'author' => (string) $row['author_name'],
            'date' => (string) $row['sent_at'],
            'body' => (string) $row['body'],
            'delivery' => $translator->text('contact_delivery_' . (string) $row['delivery_status']),
        ]);
    }
    foreach ($repositories->contact()->messages((int) $conversation['id'], true) as $row) {
        $template->addRow('trashedmessage', ['id' => (string) $row['id'], 'body' => (string) $row['body'], 'author' => (string) $row['author_name']]);
    }
    return;
}

$template->setSlot('main', 'var/modules/contact/templates/inbox.tpl');
$template->set('contact_folder', $folder);
$template->set('contact_is_trash', $folder === 'trash' ? '1' : '0');
foreach (['inbox', 'archives', 'closed', 'trash', 'all'] as $tab) {
    $template->addRow('contactfolder', ['key' => $tab, 'label' => $translator->text('contact_folder_' . $tab), 'current' => $folder === $tab ? 'is-current' : '']);
}
foreach ($repositories->contact()->conversations($folder) as $row) {
    $template->addRow('conversation', [
        'id' => (string) $row['id'],
        'unread' => (int) $row['unread'] === 1 ? 'is-unread' : '',
        'name' => (string) $row['visitor_name'],
        'subject' => (string) $row['subject'],
        'status' => (string) $row['status'],
        'status_label' => $translator->text('contact_status_' . (string) $row['status']),
        'updated_at' => (string) $row['updated_at'],
        'message_count' => (string) $row['message_count'],
    ]);
}
