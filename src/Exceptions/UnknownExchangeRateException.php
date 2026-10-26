<?php

namespace NYCorp\Finance\Exceptions;

use Nycorp\LiteApi\Exceptions\LiteResponseException;
use Nycorp\LiteApi\Models\ResponseCode;

class UnknownExchangeRateException extends LiteResponseException
{
    public function __construct(string $from, string $to)
    {
        parent::__construct(ResponseCode::REQUEST_VALIDATION_ERROR, "No exchange rate available from $from to $to");
    }
}
