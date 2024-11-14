<?php


namespace NYCorp\Finance\Traits;


use Illuminate\Http\Request;
use NYCorp\Finance\Http\Payment\PaymentProviderGateway;
use NYCorp\Finance\Models\FinanceTransaction;

trait PaymentProviderTrait
{
    abstract public function deposit(FinanceTransaction $transaction): PaymentProviderGateway;

    abstract public function withdrawal(FinanceTransaction $transaction): PaymentProviderGateway;

    public function phoneVerification()
    {
    }

    abstract public function onDepositSuccess(Request $request): PaymentProviderGateway;

    abstract public function onWithdrawalSuccess(Request $request): PaymentProviderGateway;

    public function onFailureOrCancellation(Request $request)
    {
    }
}