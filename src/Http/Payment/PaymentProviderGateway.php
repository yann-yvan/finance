<?php


namespace NYCorp\Finance\Http\Payment;


use NYCorp\Finance\FinanceServiceProvider;
use NYCorp\Finance\Models\FinanceProvider;

class PaymentProviderGateway
{

    protected $successful = false;
    protected $response;
    private $financeProvider;

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
     * @return bool
     */
    public function successful(): bool
    {
        return $this->successful;
    }

    /**
     * @return mixed
     */
    public function getResponse()
    {
        return $this->response;
    }

    /**
     * @return mixed
     */
    public function getFinanceProvider()
    {
        return $this->financeProvider;
    }
}