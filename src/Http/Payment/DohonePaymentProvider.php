<?php


namespace NYCorp\Finance\Http\Payment;


use NYCorp\Finance\Models\FinanceTransaction;
use NYCorp\Finance\Traits\FinanceProviderTrait;
use NYCorp\Finance\Traits\PaymentProviderTrait;

class DohonePaymentProvider extends PaymentProviderGateway
{
    use PaymentProviderTrait;
    use FinanceProviderTrait;

    public function getId(): int
    {
        return 3;
    }

    public function getName(): string
    {
        return "Dohone";
    }

    public function deposit(FinanceTransaction $transaction)
    {
        // TODO: Implement deposit() method.
    }

    public function withdrawal(FinanceTransaction $transaction)
    {
        // TODO: Implement withdrawal() method.
    }
}