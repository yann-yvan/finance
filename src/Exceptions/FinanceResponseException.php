<?php

namespace NYCorp\Finance\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * Carries an error response out of a database transaction, so that the transaction is rolled back.
 */
class FinanceResponseException extends RuntimeException
{
    public function __construct(private readonly JsonResponse $response)
    {
        parent::__construct('Finance operation failed');
    }

    public function getResponse(): JsonResponse
    {
        return $this->response;
    }
}
