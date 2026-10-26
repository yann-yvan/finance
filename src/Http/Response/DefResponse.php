<?php

namespace NYCorp\Finance\Http\Response;

use Illuminate\Http\JsonResponse;

/**
 * Parsed finance response. Same methods as nycorp/lite-api's DefResponse (and an instance of it when the bridge is on).
 */
class DefResponse extends DefResponseBase
{
    private mixed $payload;

    private JsonResponse $jsonResponse;

    public function __construct(JsonResponse $response)
    {
        $this->jsonResponse = $response;
        $this->payload = $response->getData(true);
    }

    public static function parse($data): DefResponse
    {
        if (is_array($data)) {
            $data = new JsonResponse($data);
        }
        return new DefResponse($data);
    }

    public function getData(): mixed
    {
        return $this->payload['body'] ?? null;
    }

    public function getBody()
    {
        return $this->payload['body'] ?? null;
    }

    public function isSuccess(): bool
    {
        return (bool)($this->payload['success'] ?? false);
    }

    public function getMessage(): string
    {
        return (string)($this->payload['message'] ?? '');
    }

    public function getCode(): int
    {
        return (int)($this->payload['code'] ?? 0);
    }

    public function getCodeKey(): string
    {
        return (string)ResponseCode::signed($this->getCode());
    }

    public function isCodeSame(int $code): bool
    {
        return $this->getCode() === abs($code);
    }

    public function getResponse(): JsonResponse
    {
        return $this->jsonResponse;
    }
}
