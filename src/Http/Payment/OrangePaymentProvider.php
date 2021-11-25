<?php


namespace NYCorp\Finance\Http\Payment;


use NYCorp\Finance\Models\FinanceTransaction;
use NYCorp\Finance\Traits\FinanceProviderTrait;
use NYCorp\Finance\Traits\PaymentProviderTrait;

class OrangePaymentProvider extends PaymentProviderGateway
{
    use PaymentProviderTrait;
    use FinanceProviderTrait;

    public function getId(): int
    {
        return 2;
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
}