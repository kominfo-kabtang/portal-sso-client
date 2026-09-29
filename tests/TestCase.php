<?php

namespace KominfoKabtang\PortalSso\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as Orchestra;
use KominfoKabtang\PortalSso\PortalSsoServiceProvider;
use KominfoKabtang\PortalSso\Tests\Fixtures\User;

abstract class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('users', function (Blueprint $table) {
            $table->increments('id');
            $table->string('nip')->unique();
            $table->string('name')->nullable();
            $table->string('password')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        Route::middleware('web')->group(function () {
            Route::get('login', function () {
                return 'halaman login';
            })->middleware('portal-sso.required')->name('login');

            Route::get('dashboard', function () {
                return 'dashboard';
            })->middleware('auth');
        });
    }

    protected function getPackageProviders($app)
    {
        return [PortalSsoServiceProvider::class];
    }

    protected function getEnvironmentSetUp($app)
    {
        $app['config']->set('app.key', 'base64:' . base64_encode(str_repeat('a', 32)));
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        $app['config']->set('auth.providers.users.model', User::class);

        $app['config']->set('portal-sso.enabled', true);
        $app['config']->set('portal-sso.host', 'http://10.0.0.1');
        $app['config']->set('portal-sso.host_domain', 'https://portal.test');
        $app['config']->set('portal-sso.client_id', '7');
        $app['config']->set('portal-sso.client_secret', 'rahasia');
        $app['config']->set('portal-sso.redirect_after_login', '/dashboard');
    }
}
