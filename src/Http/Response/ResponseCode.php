<?php

namespace NYCorp\Finance\Http\Response;

/**
 * Response codes of the finance API (same values as nycorp/lite-api 1.x).
 */
class ResponseCode
{
    public const REQUEST_SUCCESS = 1000;
    public const REQUEST_FAILURE = -1001;
    public const REQUEST_VALIDATION_ERROR = -1002;
    public const REQUEST_EXPIRED = -1003;
    public const REQUEST_TRYING_TO_INSERT_DUPLICATE = -1004;
    public const REQUEST_NOT_AUTHORIZED = -1005;
    public const REQUEST_EXCEPTION = -1006;
    public const REQUEST_NOT_FOUND = -1007;
    public const REQUEST_WRONG_JSON_FORMAT = -1008;
    public const REQUEST_SERVICE_NOT_AVAILABLE = -1009;
    public const REQUEST_EMERGENCY = -1010;

    private const MESSAGES = [
        self::REQUEST_SUCCESS => 'Request success.',
        self::REQUEST_FAILURE => 'Request failure.',
        self::REQUEST_VALIDATION_ERROR => 'Request validation error.',
        self::REQUEST_EXPIRED => 'Request expired.',
        self::REQUEST_TRYING_TO_INSERT_DUPLICATE => 'Request trying to insert duplicate.',
        self::REQUEST_NOT_AUTHORIZED => 'Request not authorized.',
        self::REQUEST_EXCEPTION => 'Request exception.',
        self::REQUEST_NOT_FOUND => 'Request not found.',
        self::REQUEST_WRONG_JSON_FORMAT => 'Request wrong json format.',
        self::REQUEST_SERVICE_NOT_AVAILABLE => 'Request service not available.',
        self::REQUEST_EMERGENCY => 'Request emergency.',
    ];

    public static function exists(int $code): bool
    {
        return isset(self::MESSAGES[-abs($code)]) || isset(self::MESSAGES[abs($code)]);
    }

    public static function defaultMessage(int $code): string
    {
        return self::MESSAGES[$code] ?? self::MESSAGES[-abs($code)] ?? self::MESSAGES[abs($code)] ?? '';
    }

    /**
     * Signed code from the absolute value carried by a response (success codes are positive)
     */
    public static function signed(int $code): int
    {
        return isset(self::MESSAGES[abs($code)]) ? abs($code) : -abs($code);
    }
}
