<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use NYCorp\Finance\Models\FinanceProvider;

class FinanceProviderTableSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        FinanceProvider::create([
            "name" => "App provider",
            "is_withdrawal_available" => false,
        ]);
    }
}
