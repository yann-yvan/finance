<?php

use Illuminate\Support\Facades\Route;
use NYCorp\Finance\Http\Controllers\FinanceProviderController;

Route::ANY('/providers', [FinanceProviderController::class, 'providers']);
