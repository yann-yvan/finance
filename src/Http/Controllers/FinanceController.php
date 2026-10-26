<?php


namespace NYCorp\Finance\Http\Controllers;


use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use NYCorp\Finance\Http\Payment\DohonePaymentProvider;
use NYCorp\Finance\Http\Payment\PaymentProviderGateway;
use NYCorp\Finance\Integrity\AuditTrail;
use NYCorp\Finance\Models\FinanceAuditLog;
use NYCorp\Finance\Models\FinanceTransaction;
use NYCorp\Finance\Exceptions\FinanceException;
use NYCorp\Finance\Http\Response\ResponseCode;
use NYCorp\Finance\Http\Response\FinanceResponder;
use OpenApi\Annotations as OA;
use Throwable;

class FinanceController
{
    use FinanceResponder;

    public function depositNotification(string $provider, Request $request): JsonResponse
    {
        return $this->settle($provider, $request, FinanceTransaction::DEPOSIT_MOVEMENT);
    }

    public function withdrawalNotification(string $provider, Request $request): JsonResponse
    {
        return $this->settle($provider, $request, FinanceTransaction::WITHDRAWAL_MOVEMENT);
    }

    /**
     * Apply a provider callback. Only the provider's verdict changes the transaction: an unauthenticated callback
     * leaves it untouched (the genuine one can still complete it), and a callback for a finalized transaction
     * is acknowledged without error.
     */
    private function settle(string $provider, Request $request, string $movement): JsonResponse
    {
        // Keys only: the payload holds personal data
        Log::info(ucfirst($movement) . " notification received from $provider", ['keys' => array_keys($request->all())]);

        try {
            $gateway = PaymentProviderGateway::load($provider);
        } catch (FinanceException $exception) {
            return $exception->toResponse();
        }

        try {
            $gateway = $movement === FinanceTransaction::DEPOSIT_MOVEMENT ? $gateway->onDepositSuccess($request) : $gateway->onWithdrawalSuccess($request);
        } catch (Exception|Throwable $exception) {
            Log::error("Finance: $provider $movement callback failed: " . get_class($exception) . ' ' . $exception->getMessage());
            return self::financeResponse(ResponseCode::REQUEST_FAILURE, message: 'Callback could not be processed');
        }

        $transaction = $gateway->getTransaction();
        if (!$transaction->exists) {
            return self::financeResponse(ResponseCode::REQUEST_NOT_FOUND, message: 'Transaction not found');
        }

        $rejection = $gateway->ownsTransaction() ? $gateway->getRejectionReason() : 'transaction belongs to another provider';
        if ($rejection !== null) {
            Log::warning("Finance: $provider callback rejected for transaction {$transaction->id}: $rejection");
            AuditTrail::record(FinanceAuditLog::EVENT_CALLBACK_REJECTED, $transaction, null, [
                'provider' => $provider,
                'movement' => $movement,
                'reason' => $rejection,
            ]);
            return self::financeResponse(ResponseCode::REQUEST_NOT_AUTHORIZED, self::summary($transaction), 'Callback rejected');
        }

        if ($transaction->getOriginal(FinanceTransaction::IS_LOCKED)) {
            if ($gateway->successful() && $transaction->getOriginal(FinanceTransaction::STATE) !== FinanceTransaction::STATE_COMPLETED) {
                // Paid after being failed/canceled: money received but not credited, needs a human
                Log::critical("Finance: $provider reports a success for finalized transaction {$transaction->id} ({$transaction->getOriginal(FinanceTransaction::STATE)})");
                AuditTrail::record(FinanceAuditLog::EVENT_CALLBACK_REJECTED, $transaction, null, [
                    'provider' => $provider,
                    'movement' => $movement,
                    'reason' => 'success_on_finalized_transaction',
                ]);
            }
            return self::financeResponse(ResponseCode::REQUEST_SUCCESS, self::summary($transaction), 'Transaction already finalized');
        }

        $state = $gateway->resolveState();
        if ($state !== null) {
            FinanceTransactionController::close($transaction, $state, ['provider_message' => $gateway->getMessage()]);
            $transaction->refresh();
        }

        return self::financeResponse(ResponseCode::REQUEST_SUCCESS, self::summary($transaction), $state === null ? 'Transaction still pending' : null);
    }

    private static function summary(FinanceTransaction $transaction): array
    {
        return [
            FinanceTransaction::ID => $transaction->id,
            FinanceTransaction::STATE => $transaction->state,
        ];
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
        $validator = Validator::make($request->all(), [
            'code' => ['required', 'string', 'max:20'],
            'phone' => ['required', 'string', 'max:20'],
        ]);
        if ($validator->fails()) {
            return self::financeResponse(ResponseCode::REQUEST_VALIDATION_ERROR, message: $validator->errors()->first());
        }

        return response()->json((new DohonePaymentProvider())->SMSConfirmation($request->code, $request->phone));
    }
}