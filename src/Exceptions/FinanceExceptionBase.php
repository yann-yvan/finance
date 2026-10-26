<?php

namespace NYCorp\Finance\Exceptions;

use Illuminate\Http\JsonResponse;
use NYCorp\Finance\Http\Response\DefResponse;
use NYCorp\Finance\Http\Response\FinanceResponder;
use NYCorp\Finance\Http\Response\LiteApiBridge;
use NYCorp\Finance\Http\Response\ResponseCode;

// Parent chosen once, when the class is first loaded (see LiteApiBridge)
if (LiteApiBridge::enabled()) {
    abstract class FinanceExceptionBase extends \Nycorp\LiteApi\Exceptions\LiteResponseException
    {
        public function __construct(int $code, string $message, mixed $data = null)
        {
            \Exception::__construct($message, $code);
            $this->data = $data;
        }
    }
} else {
    abstract class FinanceExceptionBase extends \Exception
    {
        use FinanceResponder;

        protected mixed $data;

        public function __construct(int $code, string $message, mixed $data = null)
        {
            parent::__construct($message, $code);
            $this->data = $data;
        }

        public static function parse(DefResponse $response): static
        {
            return new static((int)$response->getCodeKey(), $response->getMessage(), $response->getData());
        }

        public function getData(): mixed
        {
            return $this->data;
        }

        public function toResponse(): JsonResponse
        {
            return self::financeResponse(ResponseCode::exists($this->code) ? $this->code : ResponseCode::REQUEST_FAILURE, $this->data, $this->message);
        }

        public function isCodeSame(int $code): bool
        {
            return abs($this->getCode()) === abs($code);
        }
    }
}
