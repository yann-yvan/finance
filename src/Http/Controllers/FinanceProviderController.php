<?php


namespace NYCorp\Finance\Http\Controllers;


use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use NYCorp\Finance\Http\Payment\PaymentProviderGateway;
use NYCorp\Finance\Models\FinanceProvider;
use Nycorp\LiteApi\Models\ResponseCode;
use Nycorp\LiteApi\Traits\ApiResponseTrait;

class FinanceProviderController
{
    use ApiResponseTrait;

    public function providers(): JsonResponse
    {
        try {
            PaymentProviderGateway::load();
            return self::liteResponse(code: ResponseCode::REQUEST_SUCCESS, data: FinanceProvider::where(FinanceProvider::IS_PUBLIC, true)->get([
                "assigned_id as provider_id",
                FinanceProvider::NAME,
                FinanceProvider::COLOR,
                FinanceProvider::LOGO,
                FinanceProvider::IS_AVAILABLE,
                FinanceProvider::IS_DEPOSIT_AVAILABLE,
                FinanceProvider::IS_WITHDRAWAL_AVAILABLE,
            ]));
        } catch (\Exception $exception) {
            Log::error('*Loading Payment Providers* with ' . $exception->getMessage(), $exception->getTrace() ?? []);
            return self::liteResponse(ResponseCode::REQUEST_FAILURE, message: $exception->getMessage());
        }
    }
}