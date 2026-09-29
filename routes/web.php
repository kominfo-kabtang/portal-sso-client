<?php

use Illuminate\Support\Facades\Route;
use KominfoKabtang\PortalSso\Http\Controllers\SsoController;

Route::middleware(config('portal-sso.routes.middleware', ['web']))->group(function () {
    Route::get(config('portal-sso.routes.login', 'sso/login'), [SsoController::class, 'login'])
        ->name('portal-sso.login');

    Route::get(config('portal-sso.routes.callback', 'callback'), [SsoController::class, 'callback'])
        ->name('portal-sso.callback');

    Route::get(config('portal-sso.routes.callback_session', 'callback-session'), [SsoController::class, 'callbackSession'])
        ->name('portal-sso.callback-session');

    Route::post(config('portal-sso.routes.logout', 'sso/logout'), [SsoController::class, 'logout'])
        ->name('portal-sso.logout');
});
