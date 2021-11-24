<?php
use Illuminate\Support\Facades\Route;
use NYCorp\Finance\Http\Controllers\FinanceController;

Route::POST('/deposit', [FinanceController::class, 'deposit'])->name('finance.wallet.deposit');
Route::POST('/withdrawal', [FinanceController::class, 'withdrawal'])->name('finance.wallet.withdrawal');
