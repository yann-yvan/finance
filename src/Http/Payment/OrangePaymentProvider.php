<?php


namespace NYCorp\Finance\Http\Payment;


use Illuminate\Http\Client\Request;
use NYCorp\Finance\Models\FinanceTransaction;
use NYCorp\Finance\Traits\FinanceProviderTrait;
use NYCorp\Finance\Traits\PaymentProviderTrait;

class OrangePaymentProvider extends PaymentProviderGateway
{
    use PaymentProviderTrait;
    use FinanceProviderTrait;

    public function getId(): string
    {
        return "2";
    }

    public function getName(): string
    {
        return "Orange";
    }

    public function deposit(FinanceTransaction $transaction): PaymentProviderGateway
    {
        // TODO: Implement deposit() method.
    }

    public function withdrawal(FinanceTransaction $transaction): PaymentProviderGateway
    {
        // TODO: Implement withdrawal() method.
    }

    public function onDepositSuccess(Request $request): PaymentProviderGateway
    {
        // TODO: Implement onDepositSuccess() method.
    }

    public function onWithdrawalSuccess(Request $request): PaymentProviderGateway
    {
        // TODO: Implement onWithdrawalSuccess() method.
    }
}