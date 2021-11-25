<?php

use Illuminate\Support\Facades\Route;
use NYCorp\Finance\Http\Controllers\FinanceController;


Route::group(['prefix' => 'deposit'], function () {
    Route::POST('/', [FinanceController::class, 'deposit'])->name('finance.wallet.deposit');
    Route::group(['prefix' => 'success'], function () {
        Route::POST('/dohone', [FinanceController::class, 'onDepositSuccessDohone'])->name('finance.wallet.deposit.success.dohone');
    });
});


Route::POST('/withdrawal', [FinanceController::class, 'withdrawal'])->name('finance.wallet.withdrawal');
Route::POST('/withdrawal/success', [FinanceController::class, 'onWithdrawalSuccess'])->name('finance.wallet.withdrawal.success');

Route::POST('/failure', [FinanceController::class, 'onFailureOrCancellation'])->name('finance.wallet.failure');


Route::POST('/dohone-sms-verify', [FinanceController::class, 'dohoneSmsVerification'])->name('finance.wallet.dohone.sms');
