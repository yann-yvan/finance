<?php


namespace NYCorp\Finance\Http\Payment;


use Illuminate\Http\Request;
use NYCorp\Finance\Models\FinanceTransaction;

class OrangePaymentProvider extends PaymentProviderGateway
{
    public static function getId(): string
    {
        return "ORANGE_CM";
    }

    public static function getName(): string
    {
        return "Orange";
    }

    public function deposit(FinanceTransaction $transaction): PaymentProviderGateway
    {
        return $this;
    }

    public function withdrawal(FinanceTransaction $transaction): PaymentProviderGateway
    {
        return $this;
    }

    public function onDepositSuccess(Request $request): PaymentProviderGateway
    {
        return $this;
    }

    public function onWithdrawalSuccess(Request $request): PaymentProviderGateway
    {
        return $this;
    }
}