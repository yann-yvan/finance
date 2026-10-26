<?php

namespace NYCorp\Finance\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use NYCorp\Finance\FinanceServiceProvider;
use NYCorp\Finance\Http\Payment\DefaultPaymentProvider;
use NYCorp\Finance\Http\Payment\DohonePaymentProvider;
use NYCorp\Finance\Integrity\FinanceSchema;
use NYCorp\Finance\Tests\Fixtures\FakeGateway;
use NYCorp\Finance\Tests\Fixtures\Partner;
use NYCorp\Finance\Tests\Fixtures\User;
use Nycorp\LiteApi\Providers\LiteApiServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    public const KEY = 'base64:q2b3sV0Y0p4m4o1v6zq3d0nJ8y5qkq2S0qF5lJ6m8x4=';

    protected function setUp(): void
    {
        FinanceSchema::flush();
        FakeGateway::reset();
        parent::setUp();
        Http::preventStrayRequests();
        Http::fake();
    }

    protected function getPackageProviders($app): array
    {
        return [LiteApiServiceProvider::class, FinanceServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        // FINANCE_TEST_DB=mysql runs the suite on a real (throw-away!) MySQL database to check the triggers
        if (env('FINANCE_TEST_DB') === 'mysql') {
            $app['config']->set('database.default', 'mysql');
            $app['config']->set('database.connections.mysql', [
                'driver' => 'mysql',
                'host' => env('FINANCE_TEST_DB_HOST', '127.0.0.1'),
                'port' => env('FINANCE_TEST_DB_PORT', '3306'),
                'database' => env('FINANCE_TEST_DB_DATABASE', 'finance_test'),
                'username' => env('FINANCE_TEST_DB_USERNAME', 'root'),
                'password' => env('FINANCE_TEST_DB_PASSWORD', ''),
                'charset' => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
                'prefix' => '',
            ]);
        } else {
            $app['config']->set('database.default', 'sqlite');
            $app['config']->set('database.connections.sqlite', [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
                'foreign_key_constraints' => true,
            ]);
        }
        $app['config']->set('finance.default_currency', 'USD');
        $app['config']->set('finance.payment_providers', [
            DefaultPaymentProvider::class,
            FakeGateway::class,
            DohonePaymentProvider::class,
        ]);
        $app['config']->set('finance.signature.key_id', 'k1');
        $app['config']->set('finance.signature.key', self::KEY);
        $app['config']->set('dohone.payOutHashCode', 'dohone-secret');
    }

    protected function defineDatabaseMigrations(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            Schema::dropAllTables();
        }

        Schema::create('users', static function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });
        Schema::create('partners', static function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        foreach (FinanceServiceProvider::migrations() as $migration) {
            (include __DIR__ . "/../database/migrations/$migration.php")->up();
        }
    }

    protected function user(string $name = 'alice'): User
    {
        return User::create(['name' => $name]);
    }

    protected function partner(string $name = 'acme'): Partner
    {
        return Partner::create(['name' => $name]);
    }
}
