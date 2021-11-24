<?php
use Illuminate\Support\Facades\Route;
use NYCorp\Finance\Http\Controllers\FinanceController;

Route::get('/deposit', [FinanceController::class, 'deposit'])->name('finance.wallet.depot');
