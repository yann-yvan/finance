<?php


namespace NYCorp\Finance;


use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class FinanceServiceProvider extends ServiceProvider
{
    const FINANCE_CONFIG_NAME = "finance";

    public function register()
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/config.php', self::FINANCE_CONFIG_NAME);
        $this->mergeConfigFrom(__DIR__ . '/../config/code.php', self::FINANCE_CONFIG_NAME . '-code');

    }

    public function boot()
    {
        if ($this->app->runningInConsole()) {
            // Export the migration

            $migrationClasses = [
                'CreateFinanceProvidersTable',
                'CreateFinanceTransactionsTable',
                'CreateFinanceWalletsTable',
            ];

            $migrations = [];

            foreach ($migrationClasses as $class) {
                if (!class_exists($class, true)) {
                    //Extract word from first upper case letter
                    $words = preg_split('/(?=[A-Z])/', $class);

                    //Remove first element of the array because it is empty
                    unset($words[0]);

                    //change first upper case letter to lower case
                    for ($i = 1; $i <= count($words); $i++) {
                        $words[$i] = lcfirst($words[$i]);
                    }

                    //join them to match migration name convention on Laravel
                    $migrationName = join("_", $words);

                    //create delay if one second
                    sleep(1);

                    //Add class migration in list
                    $migrations[__DIR__ . "/../database/migrations/$migrationName.php.stub"] = database_path('migrations/' . date('Y_m_d_His', time()) . "_$migrationName.php");
                }
            }


            $this->publishes($migrations, 'migrations');
        }

        $this->publishes([
            __DIR__ . '/../config/config.php' => config_path(self::FINANCE_CONFIG_NAME . '.php'),
            __DIR__ . '/../config/code.php' => config_path(self::FINANCE_CONFIG_NAME . '-code.php'),
        ], 'config');

        $this->registerRoutes();

    }

    protected function registerRoutes()
    {
        Route::group($this->routeConfiguration(), function () {
            $this->loadRoutesFrom(__DIR__ . '/../routes/api.php');
        });

        Route::group(["prefix" => config(self::FINANCE_CONFIG_NAME . ".prefix")], function () {
            $this->loadRoutesFrom(__DIR__ . '/../routes/web.php');
        });
    }

    protected function routeConfiguration(): array
    {
        return [
            'prefix' => "api/" . config(self::FINANCE_CONFIG_NAME . ".prefix"),
            'middleware' => config(self::FINANCE_CONFIG_NAME . '.middleware'),
        ];

    }
}