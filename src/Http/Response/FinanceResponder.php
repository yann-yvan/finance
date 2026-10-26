<?php

namespace NYCorp\Finance\Http\Response;

use Illuminate\Http\JsonResponse;
use InvalidArgumentException;

/**
 * Builds the JSON responses of the library, in the lite-api 1.x format:
 * {"success": bool, "message": string, "code": int (absolute), "body": mixed[, "token"][, "trace_id" on failure]}
 */
trait FinanceResponder
{
    public static function financeResponse(int $code, mixed $data = null, ?string $message = null, ?string $token = null): JsonResponse
    {
        if (!ResponseCode::exists($code)) {
            throw new InvalidArgumentException('Response code not found please refer to documentation');
        }

        $success = $code > 0;
        $reply = [
            'success' => $success,
            'message' => empty($message) ? ResponseCode::defaultMessage($code) : $message,
            'code' => abs($code),
            'body' => $data,
        ];
        if ($token !== null) {
            $reply['token'] = $token;
        }
        if (!$success) {
            $reply['trace_id'] = self::financeTraceId();
        }

        return response()->json($reply, 200, [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    private static function financeTraceId(): string
    {
        // Same trace id as the host's lite-api responses when it is there
        if (LiteApiBridge::enabled() && class_exists(\Nycorp\LiteApi\Logging\LoggerService::class)) {
            try {
                return (string)(\Nycorp\LiteApi\Logging\LoggerService::getLogger()->getActionId() ?? '');
            } catch (\Throwable) {
            }
        }
        return '';
    }
}
