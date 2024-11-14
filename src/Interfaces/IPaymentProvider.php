<?php


namespace NYCorp\Finance\Interfaces;


use Illuminate\Http\Request;
use NYCorp\Finance\Http\Payment\PaymentProviderGateway;
use NYCorp\Finance\Models\FinanceTransaction;

interface IPaymentProvider
{
    public static function getId(): string;

    public function deposit(FinanceTransaction $transaction): PaymentProviderGateway;

    public function withdrawal(FinanceTransaction $transaction): PaymentProviderGateway;

    public function onDepositSuccess(Request $request): PaymentProviderGateway;

    public function onWithdrawalSuccess(Request $request): PaymentProviderGateway;

    public static function getName(): string;

}