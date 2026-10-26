<?php

namespace NYCorp\Finance\Tests\Fixtures;

use NYCorp\Finance\Http\Payment\DohonePaymentProvider;
use NYCorp\Finance\Http\Payment\PaymentProviderGateway;
use NYCorp\Finance\Models\FinanceProviderGatewayResponse;
use NYCorp\Finance\Models\FinanceTransaction;

/**
 * Dohone provider whose deposit start does not call the Dohone API; callbacks use the real code.
 */
class OfflineDohoneGateway extends DohonePaymentProvider
{
    public static function getCurrency(): string
    {
        return 'USD';
    }

    public function deposit(FinanceTransaction $transaction): PaymentProviderGateway
    {
        $this->successful = true;
        $this->response = new FinanceProviderGatewayResponse($transaction, $this->getWallet($transaction)->id);
        return $this;
    }
}
