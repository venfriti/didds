<?php

use Illuminate\Support\Facades\Route;
use Webkul\Analytics\Http\Controllers\Shop\PageViewController;

Route::post('api/analytics/page-views', [PageViewController::class, 'store'])
    ->middleware('web')
    ->name('shop.api.analytics.page_views.store');
