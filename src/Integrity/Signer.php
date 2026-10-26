<?php

namespace NYCorp\Finance\Integrity;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use NYCorp\Finance\Http\Core\ConfigReader;

/**
 * HMAC-SHA256 signer used by the v3 signatures, the hash chain and the audit log.
 *
 * A signature is stored as "v3:{keyId}:{hex}" so that it carries the key that produced it:
 * keys can be rotated by moving the old one to `finance.signature.previous_keys`.
 */
class Signer
{
    public const PREFIX = 'v3';

    private static bool $warned = false;

    /**
     * True when a dedicated signing key is configured.
     */
    public static function isEnabled(): bool
    {
        return self::currentKey() !== null;
    }

    public static function currentKeyId(): ?string
    {
        return self::isEnabled() ? (string)ConfigReader::getSignatureKeyId() : null;
    }

    /**
     * Sign the payload with the current key. Falls back to an unkeyed SHA-256 when no key is configured.
     */
    public static function sign(array $payload): string
    {
        $key = self::currentKey();
        if ($key === null) {
            self::warnOnce();
            return hash('sha256', self::canonical($payload));
        }

        $keyId = (string)ConfigReader::getSignatureKeyId();
        return self::PREFIX . ':' . $keyId . ':' . hash_hmac('sha256', self::canonical($payload), $key);
    }

    /**
     * Verify a value produced by sign(), with whichever known key it names.
     */
    public static function verify(array $payload, ?string $signature): bool
    {
        if (empty($signature)) {
            return false;
        }

        if (!self::isSigned($signature)) {
            return hash_equals($signature, hash('sha256', self::canonical($payload)));
        }

        [, $keyId, $mac] = explode(':', $signature, 3);
        $key = self::keys()[$keyId] ?? null;
        if ($key === null) {
            return false;
        }

        return hash_equals(hash_hmac('sha256', self::canonical($payload), $key), $mac);
    }

    public static function isSigned(?string $value): bool
    {
        return is_string($value) && Str::startsWith($value, self::PREFIX . ':') && substr_count($value, ':') >= 2;
    }

    public static function keyIdOf(?string $value): ?string
    {
        return self::isSigned($value) ? explode(':', $value, 3)[1] : null;
    }

    /**
     * Canonical serialisation: every scalar becomes a string (int/string ids from different drivers compare equal),
     * arrays are encoded once, and json_encode removes any ambiguity of a separator.
     */
    public static function canonical(array $payload): string
    {
        $normalized = array_map(static function ($value) {
            if ($value === null) {
                return null;
            }
            if (is_bool($value)) {
                return $value ? '1' : '0';
            }
            if (is_array($value)) {
                return json_encode($value);
            }
            if ($value instanceof \DateTimeInterface) {
                return $value->format('Y-m-d H:i:s');
            }
            return (string)$value;
        }, array_values($payload));

        return json_encode($normalized, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * All usable keys indexed by key id (current first, then previous ones).
     */
    public static function keys(): array
    {
        $keys = [];
        $current = self::currentKey();
        if ($current !== null) {
            $keys[(string)ConfigReader::getSignatureKeyId()] = $current;
        }

        $previous = ConfigReader::getSignaturePreviousKeys();
        if (is_string($previous)) {
            $parsed = [];
            foreach (array_filter(array_map('trim', explode(',', $previous))) as $item) {
                if (str_contains($item, ':')) {
                    [$id, $value] = explode(':', $item, 2);
                    $parsed[$id] = $value;
                }
            }
            $previous = $parsed;
        }

        foreach ((array)$previous as $id => $value) {
            $decoded = self::decodeKey($value);
            if ($decoded !== null && !isset($keys[$id])) {
                $keys[(string)$id] = $decoded;
            }
        }

        return $keys;
    }

    private static function currentKey(): ?string
    {
        $keyId = ConfigReader::getSignatureKeyId();
        if (empty($keyId) || str_contains((string)$keyId, ':')) {
            return null;
        }
        return self::decodeKey(ConfigReader::getSignatureKey());
    }

    private static function decodeKey(mixed $value): ?string
    {
        if (!is_string($value) || $value === '') {
            return null;
        }
        if (Str::startsWith($value, 'base64:')) {
            $decoded = base64_decode(substr($value, 7), true);
            return $decoded === false || $decoded === '' ? null : $decoded;
        }
        return $value;
    }

    private static function warnOnce(): void
    {
        if (!self::$warned) {
            self::$warned = true;
            Log::warning('Finance: no FINANCE_SIGNATURE_KEY configured, integrity hashes are not keyed (legacy mode).');
        }
    }
}
