<?php

namespace NYCorp\Finance\Exceptions;

use NYCorp\Finance\Http\Response\ResponseCode;

class UnknownExchangeRateException extends FinanceException
{
    public function __construct(string $from, string $to)
    {
        parent::__construct(ResponseCode::REQUEST_VALIDATION_ERROR, "No exchange rate available from $from to $to");
    }
}
