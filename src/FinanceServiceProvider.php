<?php


namespace NYCorp\Finance;


use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use NYCorp\Finance\Console\ExpirePendingTransactionsCommand;
use NYCorp\Finance\Console\SealHistoryCommand;
use NYCorp\Finance\Console\VerifyIntegrityCommand;
use NYCorp\Finance\Http\Core\ConfigReader;

class FinanceServiceProvider extends ServiceProvider
{


    public function register()
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/config.php', ConfigReader::FINANCE_CONFIG);
    }

    /**
     * Migration files in execution order (published as 2024_11_13_1000NN_<name>.php)
     */
    public static function migrations(): array
    {
        return [
            'create_finance_providers_table',
            'create_finance_transactions_table',
            'create_finance_wallets_table',
            'create_finance_accounts_table',
            'add_signature_version_to_finance_transactions',
            'create_finance_ledgers_table',
            'create_finance_chain_entries_table',
            'create_finance_audit_logs_table',
            'add_display_to_finance_transactions',
            'create_finance_transaction_tags_table',
        ];
    }

    public function boot()
    {
        if ($this->app->runningInConsole()) {
            // Export the migration
            $migrations = [];
            foreach (self::migrations() as $key => $migrationName) {
                //Add class migration in list
                $migrations[__DIR__ . "/../database/migrations/$migrationName.php"] = database_path('migrations/' . sprintf('2024_11_13_%06d_%s.php', 100000 + $key, $migrationName));
            }

            $this->publishes($migrations, 'migrations');

            $this->commands([
                VerifyIntegrityCommand::class,
                SealHistoryCommand::class,
                ExpirePendingTransactionsCommand::class,
            ]);
        }

        $this->publishes([
            __DIR__ . '/../config/config.php' => config_path(ConfigReader::FINANCE_CONFIG . '.php'),
        ], 'config');

        $this->registerRoutes();

    }

    protected function registerRoutes()
    {
        Route::group($this->routeConfiguration(), function () {
            $this->loadRoutesFrom(__DIR__ . '/../routes/api.php');
        });

        Route::group(["prefix" => config(ConfigReader::FINANCE_CONFIG . ".prefix")], function () {
            $this->loadRoutesFrom(__DIR__ . '/../routes/web.php');
        });
    }

    protected function routeConfiguration(): array
    {
        return [
            'prefix' => "api/" . config(ConfigReader::FINANCE_CONFIG . ".prefix"),
            'middleware' => config(ConfigReader::FINANCE_CONFIG . '.middleware'),
        ];

    }
}