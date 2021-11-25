<?php


namespace NYCorp\Finance\Http\Payment;


use NYCorp\Finance\FinanceServiceProvider;
use NYCorp\Finance\Models\FinanceProvider;
use NYCorp\Finance\Models\FinanceTransaction;

class PaymentProviderGateway
{

    protected $successful = false;
    protected $message = "Oops something when wrong";
    protected $response;
    protected $transaction;
    protected $isWithdrawalRealTime = false;
    private $financeProvider;

    public function __construct()
    {
        $this->transaction = new FinanceTransaction();
    }

    public static function load($id = null): PaymentProviderGateway
    {
        $requestedProvider = new PaymentProviderGateway();
        $requestedProvider->financeProvider = new FinanceProvider();
        $providers = config(FinanceServiceProvider::FINANCE_CONFIG_NAME . ".payment_providers");
        foreach ($providers as $clazz) {
            try {
                $provider = new $clazz();
                if ($provider instanceof PaymentProviderGateway) {
                    $registeredProvider = FinanceProvider::firstOrCreate(
                        ["assigned_id" => $provider->getId()],
                        [
                            "name" => $provider->getName(),
                            "is_available" => $provider->isAvailable(),
                            "is_withdrawal_available" => $provider->isWithdrawalAvailable(),
                            "is_deposit_available" => $provider->isDepositAvailable(),
                        ]);

                    if ($id == $provider->getId()) {
                        $requestedProvider = $provider;
                        $requestedProvider->financeProvider = $registeredProvider;
                    }
                }
            } catch (\Exception $exception) {
                //TODO found a way to log this
            }
        }
        return $requestedProvider;
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

    /**
     * @return mixed
     */
    public function getTransaction()
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
    public function getResponse()
    {
        return $this->response;
    }

    /**
     * @param mixed $response
     */
    protected function setResponse($response): void
    {
        $this->response = $response;
    }

    /**
     * @return mixed
     */
    public function getFinanceProvider()
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
    protected function setExternalId($externalId): void
    {
        $this->transaction->external_id = $externalId;
    }
}