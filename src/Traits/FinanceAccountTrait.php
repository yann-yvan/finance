<?php


namespace NYCorp\Finance\Traits;


use Exception;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use NYCorp\Finance\Http\Controllers\FinanceTransactionController;
use NYCorp\Finance\Http\Controllers\FinanceWalletController;
use NYCorp\Finance\Http\Payment\PaymentProviderGateway;
use NYCorp\Finance\Models\FinanceAccount;
use NYCorp\Finance\Models\FinanceTransaction;
use Nycorp\LiteApi\Models\ResponseCode;
use Nycorp\LiteApi\Response\DefResponse;
use Nycorp\LiteApi\Traits\ApiResponseTrait;

trait FinanceAccountTrait
{
    use ApiResponseTrait;

    public function getBalanceAttribute(): float
    {
        return $this->balanceChecksum();
    }

    /**
     * @param bool $always
     * @return float
     */
    public function balanceChecksum(bool $always = false): float
    {
        $account = FinanceAccount::where(FinanceAccount::OWNER_TYPE, __CLASS__)
            ->where(FinanceAccount::LAST_VERIFICATION_AT, '>', now()->subHour())
            ->where(FinanceAccount::OWNER_ID, $this->getKey())->first();

        //Check if is force or record has expired
        if ($account && !$always) {
            Log::debug("Balance reading");
            return $account->{FinanceAccount::CREDIBILITY};
        }

        return $this->calculator();
    }

    public function getClass(): string
    {
        return __CLASS__;
    }

    public function calculator(): float
    {
        $balance = 0;
        $active = true;
        $logs = [];

        Log::debug("Forced balance calculation");
        $transactions = FinanceTransaction::whereHas('wallet', function ($q) {
            $q->where('owner_id', $this->getKey())->where('owner_type', __CLASS__);
        })->get();

        foreach ($transactions as $transaction) {
            // Verify the checksum for each transaction
            if ($active = $transaction->verifyChecksum()) {
                // If checksum is valid, add the transaction amount to the balance
                $balance += $transaction->amount;
            } else {
                $logs[] = ['reason' => 'Corrupted transaction id ', 'id' => $transaction->id];
                // If checksum is invalid, lock the account and log the issue
                break;  // Stop further processing for invalid transactions
            }
        }

        if (!$active) {
            Log::critical("Wallet locked due to an invalid transaction checksum.", array_merge($logs, [
                'transaction_id' => $transaction->id,
                'owner_id' => $this->getKey(),
                'owner_type' => __CLASS__,
            ]));
        }

        FinanceAccount::updateOrCreate(
            [
                FinanceAccount::OWNER_TYPE => __CLASS__,
                FinanceAccount::OWNER_ID => $this->getKey(),
            ],
            [
                FinanceAccount::IS_ACCOUNT_ACTIVE => $active,
                FinanceAccount::ACCOUNT_LOGS => $logs,
                FinanceAccount::CREDIBILITY => $balance,
                FinanceAccount::LAST_VERIFICATION_AT => now(),
            ]
        );

        return $balance;
    }

    public function canMakeTransaction(): bool
    {
        return empty($this->finance_account) || $this->finance_account->{FinanceAccount::IS_ACCOUNT_ACTIVE};
    }

    public function canWithdraw(float $amount, bool $forceBalanceCalculation): bool
    {
        return $this->balanceChecksum($forceBalanceCalculation) - $amount >= ($this->finance_account->{FinanceAccount::THRESHOLD} ?? 0);
    }

    public function finance_account(): HasOne
    {
        return $this->hasOne(FinanceAccount::class, FinanceAccount::OWNER_ID)->where(FinanceAccount::OWNER_TYPE, __CLASS__);
    }

    public function deposit(Request $request): JsonResponse
    {
        return $this->makeTransaction($request, FinanceTransaction::DEPOSIT_MOVEMENT);
    }

    protected function makeTransaction(Request $request, string $movement): JsonResponse
    {
        try {
            if (!$this->exists) {
                throw new Exception("This action can only be performed on an existing model.");
            }

            Log::debug("starting a $movement");
            $transactionResponse = new DefResponse(FinanceTransactionController::init($request, $this, $movement));
            if ($transactionResponse->isSuccess()) {
                $walletResponse = new DefResponse(FinanceWalletController::persist($transactionResponse->getData(), $this));
                if (!$walletResponse->isSuccess()) {
                    return $walletResponse->getResponse();
                }
                $gateway = PaymentProviderGateway::load($transactionResponse->getData()["finance_provider_id"])->{$movement}(FinanceTransaction::find($transactionResponse->getData()["id"]));
                return self::liteResponse($gateway->successful() ? ResponseCode::REQUEST_SUCCESS : ResponseCode::REQUEST_FAILURE, $gateway->getResponse()->toArray(), $gateway->getMessage());
            }

            return $transactionResponse->getResponse();
        } catch (\Exception|\Throwable $exception) {
            Log::error("Transaction $movement error occur with " . $exception->getMessage(), $exception->getTrace() ?? []);
            return self::liteResponse(ResponseCode::REQUEST_FAILURE, message: $exception->getMessage());
        }
    }

    public function withdrawal(Request $request): JsonResponse
    {
        return $this->makeTransaction($request, FinanceTransaction::WITHDRAWAL_MOVEMENT);
    }
}