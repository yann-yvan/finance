<?php


namespace NYCorp\Finance\Traits;


use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use NYCorp\Finance\Exceptions\FinanceResponseException;
use NYCorp\Finance\Http\Controllers\FinanceTransactionController;
use NYCorp\Finance\Http\Controllers\FinanceWalletController;
use NYCorp\Finance\Http\Core\ConfigReader;
use NYCorp\Finance\Http\Core\ExchangeRate;
use NYCorp\Finance\Http\Payment\PaymentProviderGateway;
use NYCorp\Finance\Integrity\AuditTrail;
use NYCorp\Finance\Models\FinanceAccount;
use NYCorp\Finance\Models\FinanceAuditLog;
use NYCorp\Finance\Models\FinanceTransaction;
use NYCorp\Finance\Models\GatewayPayload;
use NYCorp\Finance\Exceptions\FinanceException;
use NYCorp\Finance\Http\Response\ResponseCode;
use NYCorp\Finance\Http\Response\DefResponse;
use NYCorp\Finance\Http\Response\FinanceResponder;
use Throwable;

trait FinanceAccountTrait
{
    use FinanceResponder;

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
        $account = FinanceAccount::where(FinanceAccount::OWNER_TYPE, $this->getMorphClass())
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
                $this->persistBalance($cur, $balance, $logs, false);
            }

            $validBalance = Arr::get($balances, $currency, 0);
        }

        if (!empty($logs)) {
            Log::critical(static::class . " Wallet {$this->getKey()} balance $invalidBalance locked due to an invalid transaction checksum. ", $logs);
        }

        return $validBalance;
    }

    /**
     * @param bool $verified true when the balance comes from a checksum verification: only then may it (re)activate
     *                       or deactivate the account, a plain recalculation must not reactivate a locked account
     */
    private function persistBalance(string $currency, float $balance, array $logs, bool $verified = true): void
    {
        $account = FinanceAccount::firstOrNew([
            FinanceAccount::OWNER_TYPE => __CLASS__,
            FinanceAccount::OWNER_ID => $this->getKey(),
            FinanceAccount::CURRENCY => $currency,
        ]);
        $old = $account->exists ? [
            FinanceAccount::CREDIBILITY => self::formatAmount($account->{FinanceAccount::CREDIBILITY}),
            FinanceAccount::IS_ACCOUNT_ACTIVE => (bool)$account->{FinanceAccount::IS_ACCOUNT_ACTIVE},
        ] : null;

        if ($verified || !$account->exists) {
            $account->{FinanceAccount::IS_ACCOUNT_ACTIVE} = empty($logs);
            $account->{FinanceAccount::ACCOUNT_LOGS} = $logs;
        }
        $account->{FinanceAccount::CREDIBILITY} = $balance;
        $account->{FinanceAccount::LAST_VERIFICATION_AT} = now();
        $account->save();

        $new = [
            FinanceAccount::CREDIBILITY => self::formatAmount($balance),
            FinanceAccount::IS_ACCOUNT_ACTIVE => (bool)$account->{FinanceAccount::IS_ACCOUNT_ACTIVE},
        ];
        if ($old !== $new) {
            AuditTrail::record(FinanceAuditLog::EVENT_ACCOUNT_RECALCULATED, $account, $old, $new + ['verified' => $verified, 'invalid_transactions' => count($logs)]);
        }
    }

    private static function formatAmount(mixed $amount): string
    {
        return number_format((float)$amount, 5, '.', '');
    }

    public function getCurrency(): string
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
            $this->balanceChecksum(true, $this->getCurrency());
            $this->refresh();
        }
        return $this->finance_account->{FinanceAccount::IS_ACCOUNT_ACTIVE};
    }

    public function canWithdraw(float $amount, bool $forceBalanceCalculation, string $currency): bool
    {
        $balance = $this->balanceChecksum($forceBalanceCalculation, $currency);
        # Use arbitrary precision math (bcmath) if you need exact decimal math (like for money)
        # because of this case 0.81558 - 0.81558; // -1.1102230246252E-16
        return bcsub(self::formatAmount($balance), self::formatAmount($amount), 5) >= ($this->finance_account->{FinanceAccount::THRESHOLD} ?? ConfigReader::getDefaultThreshold());
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
     * @deprecated Use credit() instead
     */
    public function deposit(string $providerId, float $amount, string $description, ?string $currency = null, bool $allowZeroAmount = false): JsonResponse
    {
        return $this->makeTransaction($providerId, $amount, $description, $currency, FinanceTransaction::DEPOSIT_MOVEMENT, allowZeroAmount: $allowZeroAmount);
    }

    /**
     * @param bool $allowZeroAmount a 0 amount is refused unless explicitly allowed, e.g. to record a plan granted for free
     */
    protected function makeTransaction(string $providerId, float $amount, string $description, ?string $currency, string $movement, ?string $fromWalletId = null, ?string $providerCurrency = null, bool $allowZeroAmount = false): JsonResponse
    {
        $amount = ExchangeRate::round($amount); # This to avoid tempered with in transaction in start signature
        if ($amount == 0 && !$allowZeroAmount) {
            Log::warning("Useless transaction of amount $amount");
            return self::financeResponse(ResponseCode::REQUEST_VALIDATION_ERROR, message: "Useless transaction of amount $amount");
        }

        $request = new Request([
            'provider_id' => $providerId,
            'provider_currency' => $providerCurrency,
            FinanceTransaction::AMOUNT => $amount,
            FinanceTransaction::CURRENCY => $currency ?? $this->getCurrency(),
            FinanceTransaction::DESCRIPTION => $description,
        ]);

        try {
            if (!$this->exists) {
                throw new FinanceException(ResponseCode::REQUEST_NOT_AUTHORIZED, "This action can only be performed on an existing record.");
            }

            Log::debug("starting a $movement");

            // Transaction + wallet are created together (no orphan), under the account lock taken by the balance check
            $transaction = DB::transaction(function () use ($request, $movement, $fromWalletId) {
                $transactionResponse = new DefResponse(FinanceTransactionController::init($request, $this, $movement));
                if (!$transactionResponse->isSuccess()) {
                    throw new FinanceResponseException($transactionResponse->getResponse());
                }

                $walletResponse = new DefResponse(FinanceWalletController::persist($transactionResponse->getData(), $this, $fromWalletId));
                if (!$walletResponse->isSuccess()) {
                    throw new FinanceResponseException($walletResponse->getResponse());
                }

                return FinanceTransaction::findOrFail($transactionResponse->getData()["id"]);
            });

            try {
                $gateway = PaymentProviderGateway::load($transaction->finance_provider_id)->{$movement}($transaction);
            } catch (Exception|Throwable $exception) {
                FinanceTransactionController::close($transaction->fresh(), FinanceTransaction::STATE_FAILED, ['reason' => 'provider_error', 'message' => $exception->getMessage()]);
                throw $exception;
            }

            if (!$gateway->successful()) {
                // The provider refused to start: do not leave an orphan PENDING transaction behind
                FinanceTransactionController::close($transaction->fresh(), FinanceTransaction::STATE_FAILED, ['reason' => 'provider_refused', 'message' => $gateway->getMessage()]);
            }

            return self::financeResponse($gateway->successful() ? ResponseCode::REQUEST_SUCCESS : ResponseCode::REQUEST_FAILURE, $gateway->getResponse()->toArray(), $gateway->getMessage());
        } catch (FinanceResponseException $exception) {
            return $exception->getResponse();
        } catch (Exception|Throwable $exception) {
            Log::error("Transaction $movement error occur with " . get_class($exception) . ': ' . $exception->getMessage());
            return self::financeResponse(ResponseCode::REQUEST_FAILURE, message: $exception->getMessage());
        }
    }

    /**
     * @param string $providerId
     * @param float $amount
     * @param string $description
     * @param string|null $currency
     * @return JsonResponse
     * @deprecated Use debit() instead
     */
    public function withdrawal(string $providerId, float $amount, string $description, ?string $currency = null, bool $allowZeroAmount = false): JsonResponse
    {
        return $this->makeTransaction($providerId, $amount, $description, $currency, FinanceTransaction::WITHDRAWAL_MOVEMENT, allowZeroAmount: $allowZeroAmount);
    }

    public function transfer(Model $beneficiary, string $providerId, float $amount, string $description, ?string $currency = null): DefResponse
    {
        if (!in_array(FinanceAccountTrait::class, class_uses($beneficiary), true)) {
            return DefResponse::parse(self::financeResponse(ResponseCode::REQUEST_NOT_AUTHORIZED, message: "Beneficiary not using FinanceAccountTrait"));
        }

        try {
            Log::debug("starting a transfer");
            return DB::transaction(function () use ($beneficiary, $currency, $description, $amount, $providerId) {
                $debit = $this->debit($providerId, $amount, $description, $currency);
                if ($debit->isSuccess()) {
                    $credit = $beneficiary->credit($providerId, $amount, $description, $currency, Arr::get($debit->getBody(), 'wallet'));
                    if (!$credit->isSuccess()) {
                        throw new FinanceException(ResponseCode::REQUEST_EXCEPTION, $credit->getMessage());
                    }
                    return $credit;
                }
                return $debit;
            });
        } catch (Exception|Throwable $exception) {
            return DefResponse::parse(self::financeResponse(ResponseCode::REQUEST_EXCEPTION, message: $exception->getMessage()));
        }
    }

    public function debit(string $providerId, float $amount, string $description, ?string $currency = null, ?string $providerCurrency = null, bool $allowZeroAmount = false): DefResponse
    {
        return DefResponse::parse($this->makeTransaction($providerId, $amount, $description, $currency, FinanceTransaction::WITHDRAWAL_MOVEMENT, null, $providerCurrency, $allowZeroAmount));
    }

    public function credit(string $providerId, float $amount, string $description, ?string $currency = null, ?string $sourceWallet = null, ?string $providerCurrency = null, bool $allowZeroAmount = false): DefResponse
    {
        return DefResponse::parse($this->makeTransaction($providerId, $amount, $description, $currency, FinanceTransaction::DEPOSIT_MOVEMENT, $sourceWallet, $providerCurrency, $allowZeroAmount));
    }

    public function setThreshold(float $minBalance): FinanceAccount
    {
        if (empty($this->finance_account)) {
            $this->balanceChecksum(false, $this->getCurrency());
            $this->refresh();
        }
        $old = $this->finance_account->{FinanceAccount::THRESHOLD};
        $this->finance_account->update(
            [FinanceAccount::THRESHOLD => $minBalance]
        );
        AuditTrail::record(
            FinanceAuditLog::EVENT_ACCOUNT_THRESHOLD_CHANGED,
            $this->finance_account,
            [FinanceAccount::THRESHOLD => $old === null ? null : self::formatAmount($old)],
            [FinanceAccount::THRESHOLD => self::formatAmount($minBalance)],
        );

        return $this->finance_account;
    }

    public function setGatewayPayload(GatewayPayload $payload): static
    {
        $payload->store();
        return $this;
    }
}