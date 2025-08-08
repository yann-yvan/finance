<?php

namespace NYCorp\Finance\Http\Controllers;


use Carbon\Carbon;
use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use NYCorp\Finance\Events\FinanceTransactionSuccessEvent;
use NYCorp\Finance\Http\Core\ConfigReader;
use NYCorp\Finance\Http\Payment\PaymentProviderGateway;
use NYCorp\Finance\Models\FinanceAccount;
use NYCorp\Finance\Models\FinanceProvider;
use NYCorp\Finance\Models\FinanceTransaction;
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
     *
     * @return JsonResponse
     * @throws Exception
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
        $rawAmount = $request->get("amount");
        $currency = $request->get("currency", ConfigReader::getDefaultCurrency());
        $description = $request->get("description");

        try {
            if (!$provider->isAvailable()) {
                throw new LiteResponseException(ResponseCode::REQUEST_NOT_FOUND, "Provider unavailable");
            }

            if (($financeMovement === FinanceTransaction::DEPOSIT_MOVEMENT && !$provider->isDepositAvailable()) || ($financeMovement === FinanceTransaction::WITHDRAWAL_MOVEMENT && !$provider->isWithdrawalAvailable())) {
                throw new LiteResponseException(ResponseCode::REQUEST_NOT_FOUND, "Provider $financeMovement Service Unavailable");
            }

            $amount = $financeMovement === FinanceTransaction::DEPOSIT_MOVEMENT ? abs($rawAmount) : -abs($rawAmount);

            //Check balance with the absolute value of the desire amount to withdrawal
            if (($financeMovement === FinanceTransaction::WITHDRAWAL_MOVEMENT) && !$this->hasEnoughBalance(abs($rawAmount))) {
                throw new LiteResponseException(ResponseCode::REQUEST_VALIDATION_ERROR, "Not enough balance please make a deposit !!");
            }

            $data = [
                FinanceTransaction::AMOUNT => $amount,
                FinanceTransaction::CURRENCY => $currency,
                FinanceTransaction::DESCRIPTION => $description,
                FinanceTransaction::START_LOG => self::getHttpLog($request),
                FinanceTransaction::FINANCE_PROVIDER_ID => $provider->id,
            ];
            return $this->save($data);
        } catch (LiteResponseException $exception) {
            return $exception->toResponse();
        } catch (Exception   $exception) {
            Log::error('Error during transaction creation', $exception->getTrace() ?? []);
            return self::liteResponse(ResponseCode::REQUEST_FAILURE, message: 'Unable to init transaction');
        }
    }

    private function hasEnoughBalance($amount): bool
    {
        $force = ConfigReader::getMinAmountCheckForce() >= $amount;
        return $this->accountable->canWithdraw($amount, $force);
    }

    private static function getHttpLog(Request $request): array
    {
        return [
            "parameters" => $request->all(),
            "hosts" => $request->getHost(),
            "ips" => $request->ips(),
        ];
    }

    /**
     * Verify the transaction.
     *
     * @param FinanceTransaction $transaction
     *
     * @return void
     */
    public static function close(FinanceTransaction $transaction): void
    {
        self::checksum($transaction);

        //Launch custom action after success
        if ($transaction->state === FinanceTransaction::STATE_COMPLETED) {
            try {
                event(new FinanceTransactionSuccessEvent($transaction->wallet->owner, $transaction));
            }catch (Exception|\Throwable $exception){
                Log::error("Finance success Event " .$exception->getMessage() ,$exception->getTrace());
            }
        }
    }

    private static function checksum(FinanceTransaction $transaction): void
    {
        //Check first if the transaction integrity
        if (!empty($transaction->external_id)) {
            $transaction->state = FinanceTransaction::STATE_COMPLETED;
        } else {
            $transaction->state = FinanceTransaction::STATE_FAILED;
        }

        $transaction->end_log = self::getHttpLog(\request());
        $transaction->verify_at = Carbon::now();
        $transaction->save();

        if (empty($transaction->wallet->owner->finance_account)) {
            $balance = $transaction->wallet->owner->balance;
        } else {
            $balance = $transaction->wallet->owner->finance_account->{FinanceAccount::CREDIBILITY} + $transaction->{FinanceTransaction::AMOUNT};
            $transaction->wallet->owner->finance_account->update([
                FinanceAccount::CREDIBILITY => $balance
            ]);
        }

        Log::info("New Balance " . $balance);
    }

    public function getModel(): Model
    {
        return new FinanceTransaction;
    }

    public function addRule(): array
    {
        return [
            'amount' => ['required', 'numeric'],
            'description' => ['required', 'string', 'max:255'],
            'start_log' => ['required'],
            'finance_provider_id' => ['required', "exists:finance_providers,id"],
        ];
    }

    public function updateRule(mixed $modelId): array
    {
        return [];
    }
}
