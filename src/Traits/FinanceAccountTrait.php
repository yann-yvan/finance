<?php


namespace NYCorp\Finance\Traits;


use Exception;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use NYCorp\Finance\Http\Controllers\FinanceTransactionController;
use NYCorp\Finance\Http\Controllers\FinanceWalletController;
use NYCorp\Finance\Http\Core\ConfigReader;
use NYCorp\Finance\Http\Payment\PaymentProviderGateway;
use NYCorp\Finance\Models\FinanceAccount;
use NYCorp\Finance\Models\FinanceTransaction;
use Nycorp\LiteApi\Exceptions\LiteResponseException;
use Nycorp\LiteApi\Models\ResponseCode;
use Nycorp\LiteApi\Response\DefResponse;
use Nycorp\LiteApi\Traits\ApiResponseTrait;
use Throwable;

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
            ->where(FinanceAccount::LAST_VERIFICATION_AT, '>', now()->subHours(ConfigReader::getRefreshTtl()))
            ->where(FinanceAccount::OWNER_ID, $this->getKey())->first();

        //Check if is force or record has expired
        if ($account && !$always) {
            Log::debug("Balance reading");
            return $account->{FinanceAccount::CREDIBILITY};
        }

        return $this->calculator();
    }

    public function calculator(bool $verifyChecksum = false): float
    {
        $balance = 0;
        $active = true;
        $logs = [];


        if ($verifyChecksum) {
            Log::debug(__CLASS__ . " balance verification and calculation for " . $this->getKey());

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
                    #break;  // Stop further processing for invalid transactions
                }
            }

            if (!$active) {
                Log::critical("Wallet locked due to an invalid transaction checksum.", array_merge($logs, [
                    'transaction_id' => $transaction->id,
                    'owner_id' => $this->getKey(),
                    'owner_type' => __CLASS__,
                ]));
            }
        } else {
            Log::debug(__CLASS__ . " balance calculation for " . $this->getKey());

            $balances = FinanceTransaction::whereHas('wallet', function ($q) {
                $q->where('owner_id', $this->getKey())->where('owner_type', __CLASS__);
            })->select(FinanceTransaction::CURRENCY, DB::raw('SUM(amount) as total_balance'))
                ->groupBy(FinanceTransaction::CURRENCY)->pluck('total_balance', FinanceTransaction::CURRENCY)
                ->toArray();

            $balance = Arr::get($balances, $this->getCurrency(), 0);
        }

        FinanceAccount::updateOrCreate(
            [
                FinanceAccount::OWNER_TYPE => __CLASS__,
                FinanceAccount::OWNER_ID => $this->getKey(),
                FinanceAccount::CURRENCY => $this->getCurrency(),
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

    public function getCurrency()
    {
        return ConfigReader::getDefaultCurrency();
    }

    public function getBalancesAttribute(): float
    {
        return $this->balanceChecksum();
    }

    public function getClass(): string
    {
        return __CLASS__;
    }

    public function canMakeTransaction(): bool
    {
        if (empty($this->finance_account)) {
            $this->balanceChecksum();
            $this->refresh();
        }
        return $this->finance_account->{FinanceAccount::IS_ACCOUNT_ACTIVE};
    }

    public function canWithdraw(float $amount, bool $forceBalanceCalculation): bool
    {
        return $this->balanceChecksum($forceBalanceCalculation) - $amount >= ($this->finance_account->{FinanceAccount::THRESHOLD} ?? ConfigReader::getDefaultThreshold());
    }

    public function finance_account(): HasOne
    {
        return $this->hasOne(FinanceAccount::class, FinanceAccount::OWNER_ID)->where(FinanceAccount::OWNER_TYPE, __CLASS__);
    }

    public function deposit(string $providerId, float $amount, string $description): JsonResponse
    {
        return $this->makeTransaction($providerId, $amount, $description, FinanceTransaction::DEPOSIT_MOVEMENT);
    }

    protected function makeTransaction(string $providerId, float $amount, string $description, string $movement): JsonResponse
    {
        if ($amount === 0.0) {
            Log::warning("Useless transaction of amount $amount and $description");
            return self::liteResponse(ResponseCode::REQUEST_FAILURE, message: "Useless transaction of amount $amount");
        }

        $request = new Request(['provider_id' => $providerId, FinanceTransaction::AMOUNT => $amount, FinanceTransaction::CURRENCY => $this->getCurrency(), FinanceTransaction::DESCRIPTION => $description,]);

        try {
            if (!$this->exists) {
                throw new LiteResponseException(ResponseCode::REQUEST_NOT_AUTHORIZED, "This action can only be performed on an existing model.");
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
        } catch (Exception|Throwable $exception) {
            Log::error("Transaction $movement error occur with " . $exception->getMessage(), $exception->getTrace() ?? []);
            return self::liteResponse(ResponseCode::REQUEST_FAILURE, message: $exception->getMessage());
        }
    }

    public function withdrawal(string $providerId, float $amount, string $description): JsonResponse
    {
        return $this->makeTransaction($providerId, $amount, $description, FinanceTransaction::WITHDRAWAL_MOVEMENT);
    }

    public function setThreshold(float $minBalance): FinanceAccount
    {
        if (empty($this->finance_account)) {
            $this->balanceChecksum();
            $this->refresh();
        }
        $this->finance_account->update(
            [FinanceAccount::THRESHOLD => $minBalance]
        );

        return $this->finance_account;
    }
}