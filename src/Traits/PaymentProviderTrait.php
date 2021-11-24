<?php


namespace NYCorp\Finance\Traits;


use NYCorp\Finance\Models\FinanceTransaction;

trait PaymentProviderTrait
{
    public abstract function deposit(FinanceTransaction $transaction);

    public abstract function withdrawal(FinanceTransaction $transaction);
}