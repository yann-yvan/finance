<?php

namespace NYCorp\Finance\Http\Response;

use Illuminate\Http\JsonResponse;

// Parent chosen once, when the class is first loaded (see LiteApiBridge). The bridged class adds and overrides nothing,
// so it stays compatible with every lite-api 1.x release.
if (LiteApiBridge::enabled()) {
    abstract class DefResponseBase extends \Nycorp\LiteApi\Response\DefResponse
    {
    }
} else {
    abstract class DefResponseBase
    {
        private mixed $payload;

        private JsonResponse $jsonResponse;

        public function __construct(JsonResponse $response)
        {
            $this->jsonResponse = $response;
            $this->payload = $response->getData(true);
        }

        public static function parse($data): static
        {
            if (is_array($data)) {
                $data = new JsonResponse($data);
            }
            return new static($data);
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

        /**
         * Signed code (success codes positive, errors negative)
         */
        public function getCodeKey(): int
        {
            return ResponseCode::signed($this->getCode());
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
}
