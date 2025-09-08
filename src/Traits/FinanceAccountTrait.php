<?php


namespace NYCorp\Finance\Traits;


use Exception;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
        return $this->balanceChecksum(false, $this->getCurrency());
    }

    /**
     * @param bool $verifyChecksum
     * @param string $currency
     * @return float
     */
    public function balanceChecksum(bool $verifyChecksum, string $currency): float
    {
        $account = FinanceAccount::where(FinanceAccount::OWNER_TYPE, __CLASS__)
            ->where(FinanceAccount::CURRENCY, $currency)
            ->where(FinanceAccount::LAST_VERIFICATION_AT, '>', now()->subMinutes(ConfigReader::getRefreshTtl()))
            ->where(FinanceAccount::OWNER_ID, $this->getKey())->first();

        //Check if is force or record has expired
        if ($account && !$verifyChecksum) {
            #Log::debug("Balance reading");
            return $account->{FinanceAccount::CREDIBILITY};
        }

        return $this->calculator($verifyChecksum, $currency);
    }

    private function calculator(bool $verifyChecksum, string $currency): float
    {
        $validBalance = 0;
        $invalidBalance = 0;
        $logs = [];

        if ($verifyChecksum) {
            Log::debug(__CLASS__ . " balance verification and calculation for " . $this->getKey());

            FinanceTransaction::whereHas('wallet', function ($q) {
                $q->where('owner_id', $this->getKey())->where('owner_type', __CLASS__);
            })->where('currency', $currency)->chunk(1000,
                function ($transactions) use (&$validBalance, &$invalidBalance, &$logs) {
                    foreach ($transactions as $transaction) {
                        // Verify the checksum for each transaction
                        if ($transaction->verifyChecksum()) {
                            // If checksum is valid, add the transaction amount to the balance
                            $validBalance += $transaction->amount;
                        } else {
                            $invalidBalance += $transaction->amount;
                            $log = ['reason' => 'Corrupted transaction id ', 'id' => $transaction->id];
                            $logs[] = $log;
                            // If checksum is invalid, lock the account and log the issue
                            #break;  // Stop further processing for invalid transactions
                            Log::critical("Finance", $log);
                        }
                    }
                },
            );

            $this->persistBalance($currency, $validBalance, $logs);

        } else {
            Log::debug(__CLASS__ . " balance calculation for " . $this->getKey());

            $balances = FinanceTransaction::whereHas('wallet', function ($q) {
                $q->where('owner_id', $this->getKey())
                    ->where('owner_type', __CLASS__);
            })->select(FinanceTransaction::CURRENCY, DB::raw('SUM(amount) as balance'))
                ->groupBy(FinanceTransaction::CURRENCY)
                ->pluck('balance', FinanceTransaction::CURRENCY)
                ->toArray();

            foreach ($balances as $cur => $balance) {
                $this->persistBalance($cur, $balance, $logs);
            }

            $validBalance = Arr::get($balances, $currency, 0);
        }

        if (!empty($logs)) {
            Log::critical(static::class . " Wallet {$this->getKey()} balance $invalidBalance locked due to an invalid transaction checksum. ", $logs);
        }

        return $validBalance;
    }

    private function persistBalance(string $currency, float $balance, array $logs): void
    {
        FinanceAccount::updateOrCreate(
            [
                FinanceAccount::OWNER_TYPE => __CLASS__,
                FinanceAccount::OWNER_ID => $this->getKey(),
                FinanceAccount::CURRENCY => $currency,
            ],
            [
                FinanceAccount::IS_ACCOUNT_ACTIVE => empty($logs),
                FinanceAccount::ACCOUNT_LOGS => $logs,
                FinanceAccount::CREDIBILITY => $balance,
                FinanceAccount::LAST_VERIFICATION_AT => now(),
            ]
        );
    }

    public function getCurrency()
    {
        return ConfigReader::getDefaultCurrency();
    }

    public function getBalancesAttribute(): float
    {
        return $this->balanceChecksum(false, $this->getCurrency());
    }

    public function getClass(): string
    {
        return __CLASS__;
    }

    public function canMakeTransaction(): bool
    {
        if (empty($this->finance_account)) {
            $this->balanceChecksum(false, $this->getCurrency());
            $this->refresh();
        }
        return $this->finance_account->{FinanceAccount::IS_ACCOUNT_ACTIVE};
    }

    public function canWithdraw(float $amount, bool $forceBalanceCalculation, string $currency): bool
    {
        $balance = $this->balanceChecksum($forceBalanceCalculation, $currency);
        # Use arbitrary precision math (bcmath) if you need exact decimal math (like for money)
        # because of this case 0.81558 - 0.81558; // -1.1102230246252E-16
        return bcsub($balance, $amount, 5) >= ($this->finance_account->{FinanceAccount::THRESHOLD} ?? ConfigReader::getDefaultThreshold());
    }

    public function finance_account(): HasOne
    {
        return $this->hasOne(FinanceAccount::class, FinanceAccount::OWNER_ID)
            ->where(FinanceAccount::OWNER_TYPE, __CLASS__)
            ->where(FinanceAccount::CURRENCY, $this->getCurrency());
    }

    public function finance_accounts(): HasMany
    {
        return $this->hasMany(FinanceAccount::class, FinanceAccount::OWNER_ID)->where(FinanceAccount::OWNER_TYPE, __CLASS__);
    }

    /**
     * @param string $providerId
     * @param float $amount
     * @param string $description
     * @param string|null $currency
     * @return JsonResponse
     * @deprecated
     */
    public function deposit(string $providerId, float $amount, string $description, ?string $currency = null): JsonResponse
    {
        return $this->makeTransaction($providerId, $amount, $description, $currency, FinanceTransaction::DEPOSIT_MOVEMENT);
    }

    protected function makeTransaction(string $providerId, float $amount, string $description, ?string $currency, string $movement): JsonResponse
    {
        if ($amount === 0.0) {
            Log::warning("Useless transaction of amount $amount for $description");
            return self::liteResponse(ResponseCode::REQUEST_VALIDATION_ERROR, message: "Useless transaction of amount $amount");
        }

        $request = new Request([
            'provider_id' => $providerId,
            FinanceTransaction::AMOUNT => $amount,
            FinanceTransaction::CURRENCY => $currency ?? $this->getCurrency(),
            FinanceTransaction::DESCRIPTION => $description,
        ]);

        try {
            if (!$this->exists) {
                throw new LiteResponseException(ResponseCode::REQUEST_NOT_AUTHORIZED, "This action can only be performed on an existing record.");
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

    public function credit(string $providerId, float $amount, string $description, ?string $currency = null): JsonResponse
    {
        return $this->makeTransaction($providerId, $amount, $description, $currency, FinanceTransaction::DEPOSIT_MOVEMENT);
    }

    /**
     * @param string $providerId
     * @param float $amount
     * @param string $description
     * @param string|null $currency
     * @return JsonResponse
     * @deprecated
     */
    public function withdrawal(string $providerId, float $amount, string $description, ?string $currency = null): JsonResponse
    {
        return $this->makeTransaction($providerId, $amount, $description, $currency, FinanceTransaction::WITHDRAWAL_MOVEMENT);
    }

    public function debit(string $providerId, float $amount, string $description, ?string $currency = null): JsonResponse
    {
        return $this->makeTransaction($providerId, $amount, $description, $currency, FinanceTransaction::WITHDRAWAL_MOVEMENT);
    }

    public function setThreshold(float $minBalance): FinanceAccount
    {
        if (empty($this->finance_account)) {
            $this->balanceChecksum(false, $this->getCurrency());
            $this->refresh();
        }
        $this->finance_account->update(
            [FinanceAccount::THRESHOLD => $minBalance]
        );

        return $this->finance_account;
    }
}