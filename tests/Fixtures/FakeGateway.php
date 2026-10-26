<?php

namespace NYCorp\Finance\Tests\Fixtures;

use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use NYCorp\Finance\Http\Payment\PaymentProviderGateway;
use NYCorp\Finance\Models\FinanceProviderGatewayResponse;
use NYCorp\Finance\Models\FinanceTransaction;

/**
 * Asynchronous provider: deposit()/withdrawal() leave the transaction pending, the callback settles it.
 * Callback parameters: ref (transaction id), signature ("good" is authentic), status, reference.
 */
class FakeGateway extends PaymentProviderGateway
{
    public static bool $acceptStart = true;
    public static bool $throwOnStart = false;

    public static function reset(): void
    {
        self::$acceptStart = true;
        self::$throwOnStart = false;
    }

    public static function getId(): string
    {
        return 'FAKE';
    }

    public static function getName(): string
    {
        return 'Fake';
    }

    public static function getCurrency(): string
    {
        return 'USD';
    }

    protected function isWithdrawalAvailable(): bool
    {
        return true;
    }

    public function deposit(FinanceTransaction $transaction): PaymentProviderGateway
    {
        return $this->start($transaction);
    }

    public function withdrawal(FinanceTransaction $transaction): PaymentProviderGateway
    {
        return $this->start($transaction);
    }

    private function start(FinanceTransaction $transaction): PaymentProviderGateway
    {
        if (self::$throwOnStart) {
            throw new \RuntimeException('provider down');
        }
        $this->successful = self::$acceptStart;
        $this->message = self::$acceptStart ? 'started' : 'refused';
        $this->response = new FinanceProviderGatewayResponse($transaction, $this->getWallet($transaction)->id);
        return $this;
    }

    public function onDepositSuccess(Request $request): PaymentProviderGateway
    {
        if ($this->findTransaction($request, 'ref') === null) {
            return $this;
        }

        if ($request->get('signature') !== 'good') {
            $this->rejectCallback('bad signature');
            return $this;
        }

        // Like CinetPay: the reference is always set, whatever the status
        $this->setExternalId((string)Arr::get($request->all(), 'reference', 'ext-' . $this->transaction->id));
        match ($request->get('status')) {
            'SUCCESS' => $this->successful = true,
            'FAILED' => $this->markDefinitiveFailure(),
            default => $this->successful = false,
        };
        return $this;
    }

    public function onWithdrawalSuccess(Request $request): PaymentProviderGateway
    {
        return $this->onDepositSuccess($request);
    }
}
