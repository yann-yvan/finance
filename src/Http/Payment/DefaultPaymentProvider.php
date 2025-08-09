<?php


namespace NYCorp\Finance\Http\Payment;


use Illuminate\Http\Request;
use Illuminate\Support\Str;
use NYCorp\Finance\Http\Controllers\FinanceTransactionController;
use NYCorp\Finance\Http\Core\ConfigReader;
use NYCorp\Finance\Interfaces\InternalProvider;
use NYCorp\Finance\Models\FinanceProviderGatewayResponse;
use NYCorp\Finance\Models\FinanceTransaction;

class DefaultPaymentProvider extends PaymentProviderGateway implements InternalProvider
{
    protected bool $successful = true;
    protected string $message = "Great good job";

    public static function getId(): string
    {
        return ConfigReader::getDefaultPaymentProviderId();
    }

    public static function getName(): string
    {
        return ConfigReader::getDefaultPaymentProviderName();
    }

    public function isPublic(): bool
    {
        return false;
    }

    public function deposit(FinanceTransaction $transaction): PaymentProviderGateway
    {
        $walletId = $this->getWallet($transaction)->id;
        $this->response = new FinanceProviderGatewayResponse($transaction, $walletId, [], false, null);
        $transaction->{FinanceTransaction::EXTERNAL_ID} = $walletId;
        FinanceTransactionController::close($transaction);
        return $this;
    }

    public function withdrawal(FinanceTransaction $transaction): PaymentProviderGateway
    {
        $walletId = $this->getWallet($transaction)->id;
        $this->response = new FinanceProviderGatewayResponse($transaction, $walletId, [], false, null);
        $transaction->{FinanceTransaction::EXTERNAL_ID} = $walletId;
        FinanceTransactionController::close($transaction);
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

    protected function isDepositAvailable(): bool
    {
        return false;
    }

    protected function isAvailable(): bool
    {
        return false;
    }

    public static function getCurrency(): string
    {
        return ConfigReader::getDefaultCurrency();
    }
}