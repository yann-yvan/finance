<?php

use Illuminate\Support\Facades\Route;
use NYCorp\Finance\Http\Controllers\FinanceController;
use NYCorp\Finance\Http\Controllers\FinanceProviderController;

Route::ANY('/providers', [FinanceProviderController::class, 'providers'])->name('finance.payment.provider');

Route::group(['prefix' => 'deposit'], static function () {
    Route::ANY('/notification/{provider}', [FinanceController::class, 'depositNotification'])->name('finance.wallet.deposit.notification');


    Route::group(['prefix' => 'success'], function () {
        Route::ANY('/dohone', [FinanceController::class, 'onDepositSuccessDohone'])->name('finance.wallet.deposit.success.dohone');
    });
});

Route::group(['prefix' => 'withdrawal'], static function () {
    Route::ANY('/notification/{provider}', [FinanceController::class, 'withdrawalNotification'])->name('finance.wallet.withdrawal.notification');
});


Route::POST('/withdrawal', [FinanceController::class, 'withdrawal'])->name('finance.wallet.withdrawal');



Route::POST('/dohone-sms-verify', [FinanceController::class, 'dohoneSmsVerification'])->name('finance.wallet.dohone.sms');
