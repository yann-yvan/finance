<?php


namespace NYCorp\Finance\Http\Payment;


use Illuminate\Support\Facades\Log;
use NYCorp\Finance\Http\Core\ConfigReader;
use NYCorp\Finance\Interfaces\InternalProvider;
use NYCorp\Finance\Interfaces\IPaymentProvider;
use NYCorp\Finance\Models\FinanceProvider;
use NYCorp\Finance\Models\FinanceProviderGatewayResponse;
use NYCorp\Finance\Models\FinanceTransaction;
use NYCorp\Finance\Models\FinanceWallet;
use NYCorp\Finance\Scope\InvalidWalletScope;
use Nycorp\LiteApi\Exceptions\LiteResponseException;
use Nycorp\LiteApi\Models\ResponseCode;

abstract class PaymentProviderGateway implements IPaymentProvider
{
    protected bool $successful = false;
    protected string $message = "Oops something when wrong";
    protected FinanceProviderGatewayResponse $response;
    protected FinanceTransaction $transaction;
    protected bool $isWithdrawalRealTime = false;
    private FinanceProvider $financeProvider;

    public function __construct()
    {
        $this->transaction = new FinanceTransaction();
    }

    public static function load($id = null): PaymentProviderGateway
    {
        $requestedGatewayProvider = null;
        foreach (ConfigReader::getPaymentProviders() as $clazz) {
            try {
                $gatewayProvider = new $clazz();
                if ($gatewayProvider instanceof self) {
                    $registeredProvider = FinanceProvider::firstOrCreate(
                        [FinanceProvider::ASSIGNED_ID => $gatewayProvider::getId()],
                        [
                            FinanceProvider::NAME => $gatewayProvider::getName(),
                            FinanceProvider::IS_AVAILABLE => $gatewayProvider->isAvailable(),
                            FinanceProvider::IS_WITHDRAWAL_AVAILABLE => $gatewayProvider->isWithdrawalAvailable(),
                            FinanceProvider::IS_DEPOSIT_AVAILABLE => $gatewayProvider->isDepositAvailable(),
                            FinanceProvider::IS_PUBLIC => $gatewayProvider->isPublic(),
                        ]
                    );

                    //Id can be either provider id, or assigned_id
                    if ($id === $registeredProvider->{FinanceProvider::ASSIGNED_ID} || $id === $registeredProvider->id) {
                        $requestedGatewayProvider = $gatewayProvider;

                        if ($gatewayProvider instanceof InternalProvider) {
                            // Bypass restrictions for the internal provider
                            $registeredProvider->is_deposit_available = true;
                            $registeredProvider->is_withdrawal_available = true;
                            $registeredProvider->is_available = true;
                        }

                        $requestedGatewayProvider->financeProvider = $registeredProvider;
                    }
                }
            } catch (\Exception|\Throwable $exception) {
                Log::error('Loading Payment Provider Gateway with ' . $exception->getMessage(), $exception->getTrace() ?? []);
            }
        }

        if ($requestedGatewayProvider === null) {
            throw new LiteResponseException(ResponseCode::REQUEST_NOT_FOUND, message: 'Gateway Not Found');
        }

        return $requestedGatewayProvider;
    }

    protected function isAvailable(): bool
    {
        return true;
    }

    protected function isWithdrawalAvailable(): bool
    {
        return false;
    }

    protected function isDepositAvailable(): bool
    {
        return true;
    }

    public function isPublic(): bool
    {
        return true;
    }

    /**
     * @return string
     */
    public function getMessage(): string
    {
        return $this->message;
    }

    /**
     * @param string $message
     */
    protected function setMessage(string $message): void
    {
        $this->message = $message;
    }

    public static function depositNotificationUrl(string $providerId): string
    {
        return route('finance.wallet.deposit.notification', $providerId);
    }

    public static function withdrawalNotificationUrl(string $providerId): string
    {
        return route('finance.wallet.withdrawal.notification', $providerId);
    }

    /**
     * @return mixed
     */
    public function getTransaction(): FinanceTransaction
    {
        return $this->transaction;
    }

    /**
     * @param FinanceTransaction $transaction
     */
    protected function setTransaction(FinanceTransaction $transaction): void
    {
        $this->transaction = $transaction;
    }

    /**
     * @return bool
     */
    public function successful(): bool
    {
        return $this->successful;
    }

    /**
     * @return bool
     */
    public function isWithdrawalRealTime(): bool
    {
        return $this->isWithdrawalRealTime;
    }

    /**
     * @return mixed
     */
    public function getResponse(): FinanceProviderGatewayResponse
    {
        return $this->response;
    }

    /**
     * @param mixed $response
     */
    protected function setResponse(FinanceProviderGatewayResponse $response): void
    {
        $this->response = $response;
    }

    /**
     * @return mixed
     */
    public function getFinanceProvider(): FinanceProvider
    {
        return $this->financeProvider;
    }

    /**
     * @param bool $successful
     */
    protected function setSuccessful(bool $successful): void
    {
        $this->successful = $successful;
    }

    /**
     * @param mixed $externalId
     */
    protected function setExternalId(string $externalId): void
    {
        $this->transaction->external_id = $externalId;
    }

    protected function getWallet(FinanceTransaction $transaction): FinanceWallet
    {
        $wallet = FinanceWallet::withoutGlobalScope(InvalidWalletScope::class)->where(FinanceWallet::FINANCE_TRANSACTION_ID, $transaction->id)->first();
        if (empty($wallet)) {
            $wallet = new FinanceWallet();
        }
        return $wallet;
    }
}