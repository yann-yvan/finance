<?php

namespace NYCorp\Finance\Http\Core;

use Illuminate\Http\Request;

/**
 * Request snapshot stored in start_log / end_log, without personal data.
 */
class HttpLog
{
    public const REDACTED = '[REDACTED]';

    public static function capture(Request $request): array
    {
        return [
            "parameters" => self::redact($request->all()),
            "hosts" => $request->getHost(),
            "ips" => array_map([self::class, 'ip'], $request->ips()),
        ];
    }

    public static function redact(array $data): array
    {
        $keys = array_map('strtolower', ConfigReader::getRedactedLogKeys());

        foreach ($data as $key => $value) {
            // GatewayPayload stores its fields with a "____" prefix
            $normalized = strtolower(ltrim((string)$key, '_'));
            if (in_array($normalized, $keys, true)) {
                $data[$key] = $value === null || $value === '' ? $value : self::REDACTED;
            } elseif (is_array($value)) {
                $data[$key] = self::redact($value);
            }
        }

        return $data;
    }

    public static function ip(?string $ip): ?string
    {
        if ($ip === null || !ConfigReader::shouldAnonymizeIp()) {
            return $ip;
        }

        $packed = @inet_pton($ip);
        if ($packed === false) {
            return null;
        }

        // IPv4: drop the last octet, IPv6: keep the /48 prefix
        $keep = strlen($packed) === 4 ? 3 : 6;
        return inet_ntop(substr($packed, 0, $keep) . str_repeat("\0", strlen($packed) - $keep));
    }
}
