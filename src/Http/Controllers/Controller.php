<?php


namespace NYCorp\Finance\Http\Controllers;


use Illuminate\Http\JsonResponse;
use NYCorp\Finance\Http\Payment\PaymentProviderGateway;
use Nycorp\LiteApi\Http\Controllers\Core\CoreController;
use Nycorp\LiteApi\Models\ResponseCode;

abstract class Controller extends CoreController
{
    /**
     * @throws \Exception
     */
    protected function reply(PaymentProviderGateway $gateway): JsonResponse
    {
        return self::liteResponse($gateway->successful() ? ResponseCode::REQUEST_SUCCESS : ResponseCode::REQUEST_FAILURE, $gateway->getResponse()->toArray(), $gateway->getMessage());
    }
}