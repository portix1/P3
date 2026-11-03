<?php
declare(strict_types=1);

namespace Portix\Mail;

interface MailTransport
{
    public function send(
        string $recipient,
        string $subject,
        string $body,
        string $senderName,
        string $senderEmail
    ): bool;
}
