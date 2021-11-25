<?php


namespace NYCorp\Finance\Traits;


use NYCorp\Finance\Http\Payment\PaymentProviderGateway;
use NYCorp\Finance\Models\FinanceTransaction;

trait PaymentProviderTrait
{
    public abstract function deposit(FinanceTransaction $transaction): PaymentProviderGateway;

    public abstract function withdrawal(FinanceTransaction $transaction): PaymentProviderGateway;
}