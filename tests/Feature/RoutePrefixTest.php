<?php

namespace NYCorp\Finance\Tests\Feature;

use NYCorp\Finance\Http\Payment\DefaultPaymentProvider;
use NYCorp\Finance\Http\Payment\PaymentProviderGateway;
use NYCorp\Finance\Tests\TestCase;

class RoutePrefixTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('finance.api_prefix', 'v2/');
        $app['config']->set('finance.prefix', '/wallet');
    }

    public function test_routes_follow_the_configured_prefixes(): void
    {
        $this->getJson('/v2/wallet/providers')->assertOk()->assertJsonPath('success', true);
        $this->getJson('/wallet/providers')->assertOk();
        $this->getJson('/api/finance/providers')->assertNotFound();

        $this->assertStringEndsWith('/v2/wallet/deposit/notification/' . DefaultPaymentProvider::getId(),
            PaymentProviderGateway::load(DefaultPaymentProvider::getId())->depositNotificationUrl());
    }
}
