<?php


namespace NYCorp\Finance\Traits;


use NYCorp\Finance\Http\Controllers\FinanceTransactionController;
use NYCorp\Finance\Models\FinanceTransaction;

trait FinanceAccount
{
    //('finance_transactions', 'finance_transactions.id', 'finance_wallets.finance_transaction_id')->where('finance_wallets.owner_id', $this->getKey())
    public function getBalanceAttribute()
    {
        return $this->getBalance(true);
    }

    public function getBalance($force = false)
    {
        $balance = 0;

        //Check if is force or record has expired
        if ($force) {
            error_log("Balance calculation");
            foreach (FinanceTransaction::whereHas('wallet', function ($q) {
                $q->where('owner_id', $this->getKey());
            })->get() as $transaction) {
                if (FinanceTransactionController::isTrue($transaction)) {
                    $balance = $balance + $transaction->amount;
                }
            }
        } else {
            error_log("Balance reading");
            //TODO this action should be optimized by reading balance from database record if not expired or generate new one like below so add database reading
        }
        return $balance;
    }
}