<?php

namespace NYCorp\Finance\Http\Controllers;


use Carbon\Carbon;
use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use NYCorp\Finance\Events\FinanceTransactionSuccessEvent;
use NYCorp\Finance\Http\Core\ConfigReader;
use NYCorp\Finance\Http\Core\ExchangeRate;
use NYCorp\Finance\Http\Core\HttpLog;
use NYCorp\Finance\Http\Payment\PaymentProviderGateway;
use NYCorp\Finance\Models\FinanceAccount;
use NYCorp\Finance\Models\FinanceProvider;
use NYCorp\Finance\Models\FinanceTransaction;
use NYCorp\Finance\Models\FinanceWallet;
use NYCorp\Finance\Scope\InvalidWalletScope;
use Nycorp\LiteApi\Exceptions\LiteResponseException;
use Nycorp\LiteApi\Models\ResponseCode;

class FinanceTransactionController extends Controller
{
    private Model $accountable;

    /**
     * @param Model $accountable
     */
    public function __construct(Model $accountable)
    {
        parent::__construct();
        $this->accountable = $accountable;
    }


    /**
     * Display a listing of the resource.
     *
     * @param Request $request
     * @param Model $accountable
     * @param string $movement
     * @return JsonResponse
     * @throws LiteResponseException
     */
    public static function init(Request $request, Model $accountable, string $movement): JsonResponse
    {
        return (new FinanceTransactionController($accountable))->store($request, PaymentProviderGateway::load($request->get("provider_id"))->getFinanceProvider(), $movement);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param Request $request
     * @param FinanceProvider $provider
     * @param string $financeMovement
     *
     * @return JsonResponse
     * @throws Exception
     */
    private function store(Request $request, FinanceProvider $provider, string $financeMovement = FinanceTransaction::DEPOSIT_MOVEMENT): JsonResponse
    {
        $rawAmount        = $request->get("amount");
        $currency         = $request->get("currency") ?? ConfigReader::getDefaultCurrency();
        $providerCurrency = $request->get("provider_currency") ?? $provider->toGateway()::getCurrency();
        $description      = $request->get("description");

        try {
            if (!$provider->isAvailable()) {
                throw new LiteResponseException(ResponseCode::REQUEST_NOT_FOUND, "Provider unavailable");
            }

            if (($financeMovement === FinanceTransaction::DEPOSIT_MOVEMENT && !$provider->isDepositAvailable()) || ($financeMovement === FinanceTransaction::WITHDRAWAL_MOVEMENT && !$provider->isWithdrawalAvailable())) {
                throw new LiteResponseException(ResponseCode::REQUEST_NOT_FOUND, "Provider $financeMovement Service Unavailable");
            }

            $amount = $financeMovement === FinanceTransaction::DEPOSIT_MOVEMENT ? abs($rawAmount) : -abs($rawAmount);

            //Check balance with the absolute value of the desire amount to withdrawal
            if (($financeMovement === FinanceTransaction::WITHDRAWAL_MOVEMENT) && !$this->hasEnoughBalance(abs($amount), $currency)) {
                $positiveAmount = abs($amount);
                throw new LiteResponseException(ResponseCode::REQUEST_VALIDATION_ERROR, "Not enough balance to withdraw $positiveAmount $currency please make a deposit !!");
            }

            # include exchange rate in logs for later use
            $request = $request->merge([
                'exchange_rate' => [
                    'from'  => $currency,
                    'to'    => $providerCurrency,
                    'value' => ExchangeRate::make($currency)->getRate($providerCurrency)
                ]
            ]);

            $data = [
                FinanceTransaction::AMOUNT              => $amount,
                FinanceTransaction::CURRENCY            => $currency,
                FinanceTransaction::DESCRIPTION         => $description,
                FinanceTransaction::START_LOG           => self::getHttpLog($request),
                FinanceTransaction::FINANCE_PROVIDER_ID => $provider->id,
            ];
            return $this->save($data);
        } catch (LiteResponseException $exception) {
            return $exception->toResponse();
        } catch (Exception   $exception) {
            Log::error('Error during transaction creation: ' . get_class($exception) . ' ' . $exception->getMessage());
            return self::liteResponse(ResponseCode::REQUEST_FAILURE, message: 'Unable to init transaction');
        }
    }

    /**
     * Always a new row: lite-api's default firstOrCreate() would return an identical earlier transaction
     */
    public function create(array $data): Model
    {
        return $this->getModel()::create($data);
    }

    /**
     * Must run inside a database transaction (see FinanceAccountTrait::makeTransaction): the account row stays locked
     * until the new debit is recorded, and recent pending debits are reserved, so two debits cannot spend the same money.
     */
    private function hasEnoughBalance($amount, $currency): bool
    {
        FinanceAccount::where(FinanceAccount::OWNER_TYPE, $this->accountable->getClass())
            ->where(FinanceAccount::OWNER_ID, $this->accountable->getKey())
            ->where(FinanceAccount::CURRENCY, $currency)
            ->lockForUpdate()
            ->first();

        $force = $amount >= ConfigReader::getMinAmountCheckForce();
        return $this->accountable->canWithdraw($amount + self::reservedAmount($this->accountable, $currency), $force, $currency);
    }

    /**
     * Total of the recent pending debits of the owner (positive number)
     */
    public static function reservedAmount(Model $accountable, string $currency): float
    {
        $ttl = ConfigReader::getPendingReservationTtl();
        if ($ttl <= 0) {
            return 0;
        }

        $reserved = FinanceTransaction::where(FinanceTransaction::STATE, FinanceTransaction::STATE_PENDING)
            ->where(FinanceTransaction::CURRENCY, $currency)
            ->where(FinanceTransaction::AMOUNT, '<', 0)
            ->where('created_at', '>', now()->subMinutes($ttl))
            ->whereExists(static function ($query) use ($accountable) {
                $query->selectRaw(1)
                    ->from('finance_wallets')
                    ->whereColumn('finance_wallets.finance_transaction_id', 'finance_transactions.id')
                    ->where('finance_wallets.owner_type', $accountable->getClass())
                    ->where('finance_wallets.owner_id', $accountable->getKey());
            })
            ->sum(FinanceTransaction::AMOUNT);

        return abs((float)$reserved);
    }

    private static function getHttpLog(Request $request): array
    {
        return HttpLog::capture($request);
    }

    /**
     * Finalize the transaction, atomically: state + checksum + lock, hash chain entry, audit row and balance increment.
     *
     * Idempotent: a transaction already finalized (by a previous call or a concurrent callback) is left as is.
     *
     * @param FinanceTransaction|null $transaction
     * @param string|null $state COMPLETED, FAILED or CANCELED. Null keeps the legacy rule (COMPLETED when there is an
     *                           external id): pass the provider's verdict instead.
     * @param array $context extra data stored in end_log (no personal data)
     *
     * @return bool true when this call finalized the transaction
     */
    public static function close(?FinanceTransaction $transaction, ?string $state = null, array $context = []): bool
    {
        if ($transaction === null || !$transaction->exists) {
            Log::warning('Finance: close() called without a persisted transaction');
            return false;
        }

        $state ??= empty($transaction->external_id) ? FinanceTransaction::STATE_FAILED : FinanceTransaction::STATE_COMPLETED;
        if (!in_array($state, [FinanceTransaction::STATE_COMPLETED, FinanceTransaction::STATE_FAILED, FinanceTransaction::STATE_CANCELED], true)) {
            throw new \InvalidArgumentException("Invalid final state $state");
        }
        if ($state === FinanceTransaction::STATE_COMPLETED && empty($transaction->external_id)) {
            // A completed transaction without provider reference would never be counted (InvalidWalletScope)
            Log::error("Finance: transaction {$transaction->id} cannot complete without an external id");
            $state = FinanceTransaction::STATE_FAILED;
        }

        $completed = DB::transaction(static function () use ($transaction, $state, $context) {
            $locked = FinanceTransaction::whereKey($transaction->getKey())->lockForUpdate()->value(FinanceTransaction::IS_LOCKED);
            if ($locked) {
                Log::info("Finance: transaction {$transaction->id} already finalized");
                $transaction->refresh();
                return false;
            }

            $wallet = FinanceWallet::withoutGlobalScope(InvalidWalletScope::class)
                ->where(FinanceWallet::FINANCE_TRANSACTION_ID, $transaction->getKey())
                ->first();

            // Same lock order as a withdrawal (account, then chain) to avoid deadlocks
            $account = null;
            if ($wallet !== null && $state === FinanceTransaction::STATE_COMPLETED) {
                $account = FinanceAccount::where(FinanceAccount::OWNER_TYPE, $wallet->owner_type)
                    ->where(FinanceAccount::OWNER_ID, $wallet->owner_id)
                    ->where(FinanceAccount::CURRENCY, $transaction->currency)
                    ->lockForUpdate()
                    ->first();
            }

            $endLog = self::getHttpLog(\request());
            if (!empty($context)) {
                $endLog['context'] = HttpLog::redact($context);
            }

            $transaction->state = $state;
            $transaction->end_log = $endLog;
            $transaction->verify_at = Carbon::now();
            $transaction->save();

            if ($state !== FinanceTransaction::STATE_COMPLETED || $wallet === null) {
                return false;
            }

            # Update balance: atomic increment, never read-then-write
            if ($account !== null) {
                FinanceAccount::whereKey($account->getKey())->increment(FinanceAccount::CREDIBILITY, (float)$transaction->amount);
                Log::info("Finance: balance of account {$account->id} moved by {$transaction->amount}");
            } else {
                $wallet->owner?->balanceChecksum(false, $transaction->currency);
            }

            return true;
        });

        //Launch custom action after success
        if ($completed) {
            try {
                event(new FinanceTransactionSuccessEvent($transaction->wallet->owner, $transaction));
            } catch (Exception|\Throwable $exception) {
                Log::error("Finance success Event " . $exception->getMessage());
            }
        }

        return $completed;
    }

    public function getModel(): Model
    {
        return new FinanceTransaction;
    }

    public function addRule(): array
    {
        return [
            'amount'              => ['required', 'numeric'],
            'description'         => ['required', 'string', 'max:255'],
            'start_log'           => ['required'],
            'finance_provider_id' => ['required', "exists:finance_providers,id"],
        ];
    }

    public function updateRule(mixed $modelId): array
    {
        return [];
    }
}
