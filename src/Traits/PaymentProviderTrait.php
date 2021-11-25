<?php


namespace NYCorp\Finance\Traits;


use Illuminate\Http\Request;
use NYCorp\Finance\Http\Payment\PaymentProviderGateway;
use NYCorp\Finance\Models\FinanceTransaction;

trait PaymentProviderTrait
{
    public abstract function deposit(FinanceTransaction $transaction): PaymentProviderGateway;

    public abstract function withdrawal(FinanceTransaction $transaction): PaymentProviderGateway;

    public function phoneVerification()
    {
    }

    public abstract function onDepositSuccess(Request $request): PaymentProviderGateway;

    public abstract function onWithdrawalSuccess(Request $request): PaymentProviderGateway;

    public function onFailureOrCancellation(Request $request)
    {
    }
}