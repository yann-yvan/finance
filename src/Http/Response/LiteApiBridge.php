<?php

namespace NYCorp\Finance\Http\Response;

use Composer\InstalledVersions;

/**
 * When nycorp/lite-api 1.x is installed, the finance responses and exceptions extend the lite-api ones, so host code
 * typed against lite-api keeps working. With no lite-api, or another major version, they stand alone.
 * FINANCE_LITE_API_BRIDGE=0 turns the bridge off.
 */
final class LiteApiBridge
{
    private static ?bool $enabled = null;

    public static function enabled(): bool
    {
        if (self::$enabled !== null) {
            return self::$enabled;
        }

        $flag = $_SERVER['FINANCE_LITE_API_BRIDGE'] ?? $_ENV['FINANCE_LITE_API_BRIDGE'] ?? getenv('FINANCE_LITE_API_BRIDGE');
        if ($flag !== false && $flag !== null && in_array(strtolower((string)$flag), ['0', 'false', 'off'], true)) {
            return self::$enabled = false;
        }

        try {
            $version = class_exists(InstalledVersions::class) && InstalledVersions::isInstalled('nycorp/lite-api')
                ? (string)InstalledVersions::getVersion('nycorp/lite-api')
                : '';
        } catch (\Throwable) {
            $version = '';
        }

        return self::$enabled = str_starts_with($version, '1.')
            && class_exists(\Nycorp\LiteApi\Response\DefResponse::class)
            && class_exists(\Nycorp\LiteApi\Exceptions\LiteResponseException::class);
    }
}
