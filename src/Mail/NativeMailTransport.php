<?php
declare(strict_types=1);

namespace Portix\Mail;

final class NativeMailTransport implements MailTransport
{
    public function send(
        string $recipient,
        string $subject,
        string $body,
        string $senderName,
        string $senderEmail
    ): bool {
        if (!function_exists('mail')
            || filter_var($recipient, FILTER_VALIDATE_EMAIL) === false
            || filter_var($senderEmail, FILTER_VALIDATE_EMAIL) === false) {
            return false;
        }
        $subject = trim(str_replace(["\r", "\n"], ' ', $subject));
        $senderName = trim(str_replace(["\r", "\n", '"'], ['', '', ''], $senderName));
        $headers = [
            'From: "' . $senderName . '" <' . $senderEmail . '>',
            'Reply-To: ' . $senderEmail,
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: 8bit',
            'X-Mailer: Portix 3',
        ];
        return mail($recipient, $subject, $body, implode("\r\n", $headers));
    }
}
