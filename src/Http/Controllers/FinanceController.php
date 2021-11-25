<?php


namespace NYCorp\Finance\Http\Controllers;


use Illuminate\Http\Request;
use NYCorp\Finance\Http\Payment\DohonePaymentProvider;
use NYCorp\Finance\Http\Payment\PaymentProviderGateway;
use NYCorp\Finance\Http\ResponseParser\DefResponse;
use NYCorp\Finance\Models\FinanceTransaction;

class FinanceController extends Controller
{
    /**
     * * @OA\Post(
     *     path="/api/user/wallet/cash-in",
     *   tags={"Wallet"},
     *   summary="Solidarity",
     *   description="",
     *   operationId="myWalletCashIn",
     *    @OA\Parameter(
     *         name="otp",
     *         in="query",
     *         description="otp from orange get by ussd #150*4*4# required when provider is MOBILE Money and mode is
     *         Orange", required=false,
     *         @OA\Schema(
     *         type="string"
     *         ),
     *         style="form"
     *     ),
     *    @OA\Parameter(
     *         name="mode",
     *         in="query",
     *         description="Mobile money paiment type 1=MTN, 2=Orange, 10=Dohone",
     *         required=false,
     *         @OA\Schema(
     *         type="string"
     *         ),
     *         style="form"
     *     ),
     *    @OA\Parameter(
     *         name="phone",
     *         in="query",
     *         description="the buyer phone SET USER PHONE BY DEFAULT",
     *         required=true,
     *         @OA\Schema(
     *         type="integer"
     *         ),
     *         style="form"
     *     ),
     *    @OA\Parameter(
     *         name="card_no",
     *         in="query",
     *         description="card number  for stripe",
     *         required=true,
     *         @OA\Schema(
     *         type="string"
     *         ),
     *         style="form"
     *     ),
     *    @OA\Parameter(
     *         name="ccExpiryMonth",
     *         in="query",
     *         description="card expiration month for stripe",
     *         required=true,
     *         @OA\Schema(
     *         type="string"
     *         ),
     *         style="form"
     *     ),
     *    @OA\Parameter(
     *         name="ccExpiryYear",
     *         in="query",
     *         description="card expiration Year for stripe",
     *         required=true,
     *         @OA\Schema(
     *         type="string"
     *         ),
     *         style="form"
     *     ),
     *    @OA\Parameter(
     *         name="cvvNumber",
     *         in="query",
     *         description="card cvv for stripe",
     *         required=true,
     *         @OA\Schema(
     *         type="string"
     *         ),
     *         style="form"
     *     ),
     *    @OA\Parameter(
     *         name="provider_id",
     *         in="query",
     *         description="the channel you want to use 1=PAYPAL 2=STRIPE  3=MOBILE MONEY",
     *         required=true,
     *         @OA\Schema(
     *         type="integer"
     *         ),
     *         style="form"
     *     ),
     *    @OA\Parameter(
     *         name="luggage_request_id",
     *         in="query",
     *         description="the id of the request to pay",
     *         required=true,
     *         @OA\Schema(
     *         type="integer"
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
     * Call when the payment has been validate in the transaction table
     *
     * @param Request $request
     *
     * @return array|\Illuminate\Http\JsonResponse
     */
    public function deposit(Request $request)
    {
        try {
            $transactionResponse = new DefResponse(FinanceTransactionController::deposit($request));
            if ($transactionResponse->isSuccess()) {
                $walletResponse = new DefResponse(FinanceWalletController::build(auth()->user(), $transactionResponse->getData()));
                if (!$walletResponse->isSuccess()) {
                    return $walletResponse->getResponse();
                }
                $result = PaymentProviderGateway::load($transactionResponse->getData()["finance_provider_id"])->deposit(FinanceTransaction::find($transactionResponse->getData()["id"]));
                return $this->reply($result);
            }
            return $transactionResponse->getResponse();
        } catch (\Exception | \Throwable $exception) {
            error_log($exception->getMessage());
            return $this->respondError($exception);
        }
    }

    public function withdrawal(Request $request)
    {
        try {
            $transactionResponse = new DefResponse(FinanceTransactionController::withdrawal($request));
            if ($transactionResponse->isSuccess()) {
                $walletResponse = new DefResponse(FinanceWalletController::build(auth()->user(), $transactionResponse->getData()));
                if (!$walletResponse->isSuccess()) {
                    return $walletResponse->getResponse();
                }
                $gateway = PaymentProviderGateway::load($transactionResponse->getData()["finance_provider_id"])->withdrawal(FinanceTransaction::find($transactionResponse->getData()["id"]));

                if ($gateway->successful() and $gateway->isWithdrawalRealTime()) {
                    FinanceTransactionController::close($gateway->getTransaction());
                }

                return $this->reply($gateway);
            }
            return $transactionResponse->getResponse();
        } catch (\Exception | \Throwable $exception) {
            return $this->respondError($exception);
        }
    }

    public function onDepositSuccessDohone(Request $request)
    {
        FinanceTransactionController::close((new DohonePaymentProvider())->onDepositSuccess($request)->getTransaction());
    }


    public function onFailureOrCancellation(Request $request)
    {

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
     *
     * @return array|JsonResponse
     */
    public function dohoneSmsVerification(Request $request)
    {
        return $this->reply((new DohonePaymentProvider())->SMSConfirmation($request->code, $request->phone));
    }

}