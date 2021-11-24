<?php


namespace NYCorp\Finance\Http\Payment;


use NYCorp\Finance\FinanceServiceProvider;
use NYCorp\Finance\Models\FinanceProvider;

class PaymentProviderGateway
{

    private $financeProvider;

    public static function load($id = null): PaymentProviderGateway
    {
        $requestedProvider = new PaymentProviderGateway();
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
                        $requestedProvider->financeProvider = $registeredProvider;
                    }
                }
            } catch (\Exception $exception) {
                //TODO found a way to log this
            }
        }
        return $requestedProvider;
    }

    /**
     * @return mixed
     */
    public function getFinanceProvider()
    {
        return $this->financeProvider;
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
}