<?php

use Illuminate\Support\Facades\Route;
use Webkul\Paystack\Http\Controllers\PaystackController;

Route::controller(PaystackController::class)
    ->middleware('web')
    ->prefix('paystack')
    ->group(function () {
        Route::get('redirect', 'redirect')->name('paystack.standard.redirect');

        Route::post('init-inline', 'initInline')->name('paystack.init-inline');

        Route::post('verify-inline', 'verifyInline')->name('paystack.verify-inline');

        Route::get('callback', 'callback')->name('paystack.payment.callback');

        Route::get('cancel', 'cancel')->name('paystack.payment.cancel');

        Route::post('webhook', 'webhook')->name('paystack.webhook');
    });

Route::controller(PaystackController::class)
    ->middleware(['web', 'customer'])
    ->prefix('paystack')
    ->group(function () {
        Route::post('select-saved-card', 'selectSavedCard')->name('paystack.select-saved-card');
    });
