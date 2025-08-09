<?php


namespace NYCorp\Finance\Http\Controllers;


use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use NYCorp\Finance\Http\Payment\DohonePaymentProvider;
use NYCorp\Finance\Http\Payment\PaymentProviderGateway;
use Nycorp\LiteApi\Models\ResponseCode;
use Nycorp\LiteApi\Traits\ApiResponseTrait;
use OpenApi\Annotations as OA;

class FinanceController
{
    use ApiResponseTrait;

    public function depositNotification(string $provider, Request $request): JsonResponse
    {
        Log::info("Deposit Notification Received", $request->all());
        FinanceTransactionController::close((PaymentProviderGateway::load($provider))->onDepositSuccess($request)->getTransaction());
        return self::liteResponse(ResponseCode::REQUEST_SUCCESS);
    }

    public function withdrawalNotification(string $provider, Request $request): JsonResponse
    {
        Log::info("Withdrawal Notification Received", $request->all());
        FinanceTransactionController::close((PaymentProviderGateway::load($provider))->onWithdrawalSuccess($request)->getTransaction());
        return self::liteResponse(ResponseCode::REQUEST_SUCCESS);
    }

    /**
     * @OA\Post(
     *    path="/api/finance/dohone-sms-verify",
     *   tags={"Wallet"},
     *   summary="MObile money sms verification",
     *   description="",
     *   operationId="MomoVerify",
     *   @OA\Parameter(
     *         name="code",
     *         in="query",
     *         description="sms code",
     *         required=true,
     *         @OA\Schema(
     *         type="string"
     *         ),
     *         style="form"
     *     ),
     *   @OA\Parameter(
     *         name="phone",
     *         in="query",
     *         description="sms code receiver number",
     *         required=true,
     *         @OA\Schema(
     *         type="string"
     *         ),
     *         style="form"
     *     ),
     *     @OA\Response(
     *     response=200,
     *     description="successful operation",
     *     @OA\Schema(type="json"),
     *
     *   ),
     * )
     * @param Request $request
     * @return JsonResponse
     * @deprecated
     */
    public function dohoneSmsVerification(Request $request): JsonResponse
    {
        return response()->json((new DohonePaymentProvider())->SMSConfirmation($request->code, $request->phone));
    }
}