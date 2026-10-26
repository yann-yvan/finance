<?php

namespace NYCorp\Finance\Tests\Feature;

use NYCorp\Finance\Exceptions\FinanceException;
use NYCorp\Finance\Exceptions\LockedTransactionException;
use NYCorp\Finance\Http\Payment\DefaultPaymentProvider;
use NYCorp\Finance\Http\Response\DefResponse;
use NYCorp\Finance\Http\Response\FinanceResponder;
use NYCorp\Finance\Http\Response\LiteApiBridge;
use NYCorp\Finance\Http\Response\ResponseCode;
use NYCorp\Finance\Tests\TestCase;

class ResponseFormatTest extends TestCase
{
    use FinanceResponder;

    public function test_success_and_failure_keep_the_lite_api_format(): void
    {
        $user = $this->user();

        $success = $user->credit(DefaultPaymentProvider::getId(), 10, 'top up');
        $this->assertInstanceOf(DefResponse::class, $success);
        $this->assertSame(['success', 'message', 'code', 'body'], array_keys($success->getResponse()->getData(true)));
        $this->assertSame(1000, $success->getCode());
        $this->assertSame(['transaction', 'wallet', 'errors', 'smsVerificationRequired', 'paymentUrl'], array_keys($success->getBody()));

        $failure = $user->debit(DefaultPaymentProvider::getId(), 1000, 'too much');
        $this->assertSame(['success', 'message', 'code', 'body', 'trace_id'], array_keys($failure->getResponse()->getData(true)));
        $this->assertFalse($failure->isSuccess());
        $this->assertSame(1002, $failure->getCode());
        $this->assertSame('-1002', $failure->getCodeKey());
        $this->assertTrue($failure->isCodeSame(ResponseCode::REQUEST_VALIDATION_ERROR));
    }

    public function test_default_message_matches_lite_api(): void
    {
        $this->assertSame('Request not found.', DefResponse::parse(self::financeResponse(ResponseCode::REQUEST_NOT_FOUND))->getMessage());
        $this->assertSame('Request success.', DefResponse::parse(self::financeResponse(ResponseCode::REQUEST_SUCCESS))->getMessage());
    }

    public function test_bridge_keeps_lite_api_types_for_hosts(): void
    {
        if (!LiteApiBridge::enabled()) {
            $this->assertFalse(is_subclass_of(DefResponse::class, 'Nycorp\LiteApi\Response\DefResponse'));
            $this->assertFalse(is_subclass_of(FinanceException::class, 'Nycorp\LiteApi\Exceptions\LiteResponseException'));
            return;
        }

        // Host code typed against lite-api (e.g. a method returning Nycorp\LiteApi\Response\DefResponse) keeps working
        $this->assertInstanceOf(\Nycorp\LiteApi\Response\DefResponse::class, $this->user()->credit(DefaultPaymentProvider::getId(), 1, 'x'));
        $this->assertInstanceOf(\Nycorp\LiteApi\Exceptions\LiteResponseException::class, new LockedTransactionException(ResponseCode::REQUEST_NOT_AUTHORIZED, 'locked'));

        // Byte-for-byte the same JSON as lite-api
        $liteJson = (new class { use \Nycorp\LiteApi\Traits\ApiResponseTrait; })::liteResponse(ResponseCode::REQUEST_SUCCESS, ['a' => 1])->getContent();
        $this->assertSame($liteJson, self::financeResponse(ResponseCode::REQUEST_SUCCESS, ['a' => 1])->getContent());
    }

    public function test_exceptions_render_responses(): void
    {
        $exception = new FinanceException(ResponseCode::REQUEST_NOT_FOUND, 'Gateway Not Found');
        $response = DefResponse::parse($exception->toResponse());

        $this->assertFalse($response->isSuccess());
        $this->assertSame('Gateway Not Found', $response->getMessage());
        $this->assertTrue($exception->isCodeSame(ResponseCode::REQUEST_NOT_FOUND));
    }
}
