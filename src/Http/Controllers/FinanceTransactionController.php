<?php

namespace NYCorp\Finance\Http\Controllers;


use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use NYCorp\Finance\Http\Payment\PaymentProviderGateway;
use NYCorp\Finance\Models\FinanceProvider;
use NYCorp\Finance\Models\FinanceTransaction;

class FinanceTransactionController extends Controller
{
    const DEPOSIT = 0;
    const WITHDRAWAL = 1;

    /**
     * Display a listing of the resource.
     *
     * @param Request $request
     *
     * @return \Illuminate\Http\Response
     */
    public static function deposit(Request $request)
    {
        return (new FinanceTransactionController())->store($request, $request->get("amount"), $request->get("description"), PaymentProviderGateway::load($request->get("finance_provider_id"))->getFinanceProvider(), self::DEPOSIT);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @param                          $amount
     * @param                          $description
     * @param FinanceProvider          $provider
     * @param int                      $financeMovement
     *
     * @return array|\Illuminate\Http\JsonResponse|\Illuminate\Http\Response
     */
    private function store(Request $request, $amount, $description, FinanceProvider $provider, $financeMovement = self::DEPOSIT)
    {
        try {
            if (!$provider->exist() or !$provider->isAvailable()) {
                throw new Exception("Provider unavailable");
            }

            if (($financeMovement == self::DEPOSIT && !$provider->isDepositAvailable()) || ($financeMovement == self::WITHDRAWAL && !$provider->isWithdrawalAvailable())) {
                throw new Exception("Provider Service unavailable");
            }

            $amount = $financeMovement == self::DEPOSIT ? abs($amount) : -abs($amount);

            $data = [
                'id' => strtoupper(Carbon::now()->shortMonthName) . time(),
                'amount' => $amount,
                'description' => $description,
                'state' => FinanceTransaction::STATE_PENDING,
                'start_log' => json_encode($request->all()),
                'finance_provider_id' => $provider->id,
            ];
            $data["start_signature"] = $this->getStartSignature($data);
            return $this->save($data);
        } catch (Exception   $exception) {
            return $this->respondError($exception);
        }
    }

    /**
     * Display the specified resource.
     *
     * @param $data
     *
     * @return string
     */
    private function getStartSignature($data): string
    {
        return md5($data["amount"] . $data["finance_provider_id"] . $data["state"] . $data["id"]);
    }

    /**
     * Verify the transaction.
     *
     * @param FinanceTransaction $transaction
     *
     * @return void
     */
    public static function close(FinanceTransaction $transaction)
    {
        (new FinanceTransactionController())->checksum($transaction);
    }

    private function checksum(FinanceTransaction $transaction)
    {
        //Check first if the transaction integrity
        if (!empty($transaction->external_id) and $this->isStartSignature($transaction)) {
            $transaction->state = FinanceTransaction::STATE_SUCCESS;
        } else
            $transaction->state = FinanceTransaction::STATE_FAILED;

        $transaction->end_log = json_encode(\request()->all());
        $transaction->verify_at = Carbon::now();

        //Set in last position to make sure it consider all updated value
        $transaction->end_signature = $this->getEndSignature($transaction);

        $transaction->save();
    }

    /**
     * Display the specified resource.
     *
     * @param FinanceTransaction $transaction
     *
     * @return bool
     */
    private function isStartSignature(FinanceTransaction $transaction): bool
    {
        return strcmp($this->getStartSignature(["amount" => $transaction->amount, "finance_provider_id" => $transaction->finance_provider_id, "state" => $transaction->state, "id" => $transaction->id]), $transaction->start_signature) == 0;
    }

    /**
     * Display the specified resource.
     *
     * @param FinanceTransaction $transaction
     *
     * @return string
     */
    private function getEndSignature(FinanceTransaction $transaction): string
    {
        return md5($transaction->start_signature . $transaction->state . $transaction->verify_at . $transaction->external_id);
    }

    /**
     * Display a listing of the resource.
     *
     * @param Request $request
     *
     * @return \Illuminate\Http\Response
     */
    public static function withdrawal(Request $request, $user)
    {
        return (new FinanceTransactionController())->store($request, $request->get("amount"), $request->get("description"), PaymentProviderGateway::load($request->get("finance_provider_id"))->getFinanceProvider(), self::WITHDRAWAL);
    }

    /**
     * @param array $data
     *
     * @return \Illuminate\Http\Response|null
     */
    public function create(array $data)
    {
        return FinanceTransaction::create($data);
    }

    protected function validator(&$data, array $rules = [])
    {
        return Validator::make($data, [
            'id' => ['required', 'string', "min:8"],
            'amount' => ['required', 'numeric'],
            'description' => ['required', 'string', 'max:255'],
            'state' => ['required', Rule::in(FinanceTransaction::getStates())],
            'start_log' => ['required'],
            'start_signature' => ['required'],
            'finance_provider_id' => ['required', "exists:finance_providers,id"],
        ]);
    }

    /**
     * Display the specified resource.
     *
     * @param FinanceTransaction $transaction
     *
     * @return bool
     */
    private function isEndSignature(FinanceTransaction $transaction): bool
    {
        return strcmp($this->getEndSignature($transaction), $transaction->end_signature) == 0;
    }

}
