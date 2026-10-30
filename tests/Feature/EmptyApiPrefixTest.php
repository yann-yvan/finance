<?php

namespace NYCorp\Finance\Tests\Feature;

use NYCorp\Finance\Tests\TestCase;

class EmptyApiPrefixTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('finance.api_prefix', '');
    }

    public function test_an_empty_api_prefix_serves_the_api_under_the_finance_prefix_only(): void
    {
        $this->postJson('/finance/dohone-sms-verify')->assertOk();
        $this->getJson('/api/finance/providers')->assertNotFound();
    }
}
