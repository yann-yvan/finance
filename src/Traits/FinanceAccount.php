<?php


namespace NYCorp\Finance\Traits;


use NYCorp\Finance\Models\FinanceWallet;

trait FinanceAccount
{
    public function getBalanceAttribute()
    {
        return FinanceWallet::join('finance_transactions', 'finance_transactions.id', 'finance_wallets.finance_transaction_id')->where('finance_wallets.owner_id', $this->getKey())->sum('finance_transactions.amount');
    }
}