<?php


namespace NYCorp\Finance;


use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use NYCorp\Finance\Http\Core\ConfigReader;

class FinanceServiceProvider extends ServiceProvider
{


    public function register()
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/config.php', ConfigReader::FINANCE_CONFIG);
    }

    public function boot()
    {
        if ($this->app->runningInConsole()) {
            // Export the migration

            $migrationClasses = [
                'CreateFinanceProvidersTable',
                'CreateFinanceTransactionsTable',
                'CreateFinanceWalletsTable',
                'CreateFinanceAccountsTable',
                'AddSignatureVersionToFinanceTransactions',
            ];

            $migrations = [];

            foreach ($migrationClasses as $key=>$class) {
                if (!class_exists($class, true)) {
                    //Extract word from first upper case letter
                    $words = preg_split('/(?=[A-Z])/', $class);

                    //Remove first element of the array because it is empty
                    unset($words[0]);

                    //change first upper case letter to lower case
                    for ($i = 1, $iMax = count($words); $i <= $iMax; $i++) {
                        $words[$i] = lcfirst($words[$i]);
                    }

                    //join them to match migration name convention on Laravel
                    $migrationName = implode("_", $words);

                    //Add class migration in list
                    $migrations[__DIR__ . "/../database/migrations/$migrationName.php"] = database_path('migrations/' . "2024_11_13_10000{$key}_$migrationName.php");
                }
            }


            $this->publishes($migrations, 'migrations');
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