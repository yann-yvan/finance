<?php

namespace NYCorp\Finance\Http\Controllers;

use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use NYCorp\Finance\FinanceServiceProvider;
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
    public static function build($userAccount,$transaction)
    {
        return (new FinanceWalletController())->deposit($userAccount,$transaction);
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
    public function deposit($userAccount,$transaction)
    {
        try {
            $data = [
                'id' => strtoupper(Carbon::now()->shortMonthName) . time(),
                'credit_wallet_id' => strtoupper(Carbon::now()->shortMonthName) . time(),
                'owner_id' => 1,
                'finance_transaction_id' => $transaction["id"],
            ];
            return $this->save($data);
        } catch (Exception   $exception) {
            return $this->liteResponse(config(FinanceServiceProvider::FINANCE_CONFIG_NAME . '-code.request.FAILURE'), $exception, $exception->getMessage());
        }
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function withdrawal()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function transfer()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\FinanceWallet  $financeWallet
     * @return \Illuminate\Http\Response
     */
    public function show(FinanceWallet $financeWallet)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\FinanceWallet  $financeWallet
     * @return \Illuminate\Http\Response
     */
    public function edit(FinanceWallet $financeWallet)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\FinanceWallet  $financeWallet
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, FinanceWallet $financeWallet)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\FinanceWallet  $financeWallet
     * @return \Illuminate\Http\Response
     */
    public function destroy(FinanceWallet $financeWallet)
    {
        //
    }
}
