<?php

declare(strict_types=1);

namespace Revita\Crm\Helpers;

final class Mail
{
    /**
     * Envio simples via mail() da hospedagem.
     * Em produção pode ser trocado por SMTP sem alterar os controllers.
     */
    public static function sendPlain(string $to, string $subject, string $body, string $fromEmail = ''): bool
    {
        $to = trim($to);
        if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return false;
        }
        $fromEmail = $fromEmail !== '' ? $fromEmail : 'noreply@localhost';
        $fromName = 'Revita CMS';
        $headers = [];
        $headers[] = 'MIME-Version: 1.0';
        $headers[] = 'Content-Type: text/plain; charset=UTF-8';
        $headers[] = 'From: ' . self::encodeHeader($fromName) . ' <' . $fromEmail . '>';
        $headers[] = 'X-Mailer: Revita-CMS';

        return @mail($to, self::encodeSubject($subject), $body, implode("\r\n", $headers));
    }

    private static function encodeSubject(string $subject): string
    {
        if (preg_match('/[^\x20-\x7E]/', $subject)) {
            return '=?UTF-8?B?' . base64_encode($subject) . '?=';
        }
        return $subject;
    }

    private static function encodeHeader(string $name): string
    {
        if (preg_match('/[^\x20-\x7E]/', $name)) {
            return '=?UTF-8?B?' . base64_encode($name) . '?=';
        }
        return $name;
    }
}
