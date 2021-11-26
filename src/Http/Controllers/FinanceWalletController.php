<?php

namespace NYCorp\Finance\Http\Controllers;

use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use NYCorp\Finance\FinanceServiceProvider;
use NYCorp\Finance\Http\Core\Finance;
use NYCorp\Finance\Models\FinanceWallet;


class FinanceWalletController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @param $userAccount
     * @param $transaction
     * @return array|\Illuminate\Http\JsonResponse|\Illuminate\Http\Response|void
     */
    public static function build($transaction)
    {
        return (new FinanceWalletController())->deposit($transaction);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @param array $data
     * @return \Illuminate\Http\Response
     */
    protected function create(array $data)
    {
        return FinanceWallet::create($data);
    }

    protected function validator(&$data, array $rules = [])
    {
        return Validator::make($data, [
            'id' => 'required',
            'credit_wallet_id' => ['required'],
            'owner_id' => ['required', 'exists:users,id'],
            'finance_transaction_id' => ['required', 'exists:finance_transactions,id'],
        ]);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @param $userAccount
     * @param $transaction
     * @return array|\Illuminate\Http\JsonResponse|void
     */
    private function deposit($transaction)
    {
        try {
            $data = [
                'id' => strtoupper(Carbon::now()->shortMonthName) . time(),
                'credit_wallet_id' => strtoupper(Carbon::now()->shortMonthName) . time(),
                'owner_id' => Finance::getFinanceAccount()->id,
                'finance_transaction_id' => $transaction["id"],
            ];
            return $this->save($data);
        } catch (Exception   $exception) {
            return $this->liteResponse(config(Finance::FINANCE_CONFIG_NAME . '-code.request.FAILURE'), $exception, $exception->getMessage());
        }
    }

}
