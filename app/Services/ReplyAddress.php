<?php

namespace App\Services;

/**
 * The address a notification email can be answered at: reply+TOKEN@your-inbound-domain.
 * The token names the person and the conversation and is signed, so it cannot be forged or edited.
 */
class ReplyAddress
{
    public static function enabled(): bool
    {
        return filled(config('workora.inbound_domain')) && filled(config('workora.inbound_secret'));
    }

    public static function make(string $userId, string $clientId, ?string $projectId): ?string
    {
        if (! self::enabled()) {
            return null;
        }
        $payload = rtrim(strtr(base64_encode(implode('|', [$userId, $clientId, $projectId ?? '-'])), '+/', '-_'), '=');

        return 'reply+'.$payload.'.'.self::sign($payload).'@'.config('workora.inbound_domain');
    }

    /** @return array{0: string, 1: string, 2: ?string}|null user id, client id, project id */
    public static function parse(string $address): ?array
    {
        if (! preg_match('/reply\+([A-Za-z0-9_-]+)\.([a-f0-9]{16})@/i', $address, $m) || ! hash_equals(self::sign($m[1]), strtolower($m[2]))) {
            return null;
        }
        $parts = explode('|', (string) base64_decode(strtr($m[1], '-_', '+/')));

        return count($parts) === 3 ? [$parts[0], $parts[1], $parts[2] === '-' ? null : $parts[2]] : null;
    }

    private static function sign(string $payload): string
    {
        return substr(hash_hmac('sha256', $payload, (string) config('workora.inbound_secret').config('app.key')), 0, 16);
    }

    /** Drop the quoted earlier message and signature that mail apps add under a reply. */
    public static function stripQuoted(string $text): string
    {
        $lines = preg_split('/\r\n|\r|\n/', $text);
        $keep = [];
        foreach ($lines as $i => $line) {
            if (preg_match('/^\s*>/', $line) || preg_match('/^\s*On .{5,200}wrote:\s*$/i', $line) || preg_match('/^\s*-{2,}\s*Original Message\s*-{2,}/i', $line) || preg_match('/^--\s*$/', $line)) {
                break;
            }
            $keep[] = $line;
        }

        return trim(implode("\n", $keep));
    }
}
