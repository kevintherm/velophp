<?php

namespace Veloquent\Core\Tests;

use Laravel\Ai\AiServiceProvider;
use Veloquent\Core\Support\Models\Tenant;
use Laravel\Sanctum\SanctumServiceProvider;
use Veloquent\Core\VeloquentServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Multitenancy\MultitenancyServiceProvider;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

abstract class TestCase extends OrchestraTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTenancy();
    }

    protected function setUpTenancy()
    {
        Tenant::withoutEvents(function () {
            $tenant = Tenant::create([
                'name' => 'test-tenant',
                'domain' => 'localhost',
                'database' => ':memory:',
            ]);

            $tenant->makeCurrent();
        });
    }

    protected function defineDatabaseMigrations()
    {
        $this->loadMigrationsFrom(realpath(__DIR__ . '/../database/migrations/landlord'));
        $this->loadMigrationsFrom(realpath(__DIR__ . '/../database/migrations/tenant'));
    }

    protected function getPackageProviders($app)
    {
        return [
            VeloquentServiceProvider::class,
            MultitenancyServiceProvider::class,
            SanctumServiceProvider::class,
            AiServiceProvider::class,
        ];
    }

    protected function defineRoutes($router)
    {
        $router->get('login', fn () => 'login')->name('login');
    }

    protected function getEnvironmentSetUp($app)
    {
        $storagePath = realpath(__DIR__ . '/../') . '/storage';
        $app->useStoragePath($storagePath);
        if (!is_dir($storagePath . '/logs')) {
            @mkdir($storagePath . '/logs', 0777, true);
        }

        $app['config']->set('app.key', 'base64:Xgs89rk/rM+8H/5X5A9U6fR/U0+5r/I/o/I/I/I/I/I=');
        $app['config']->set('app.url', 'http://localhost');
        $app['config']->set('database.default', 'testbench');
        $app['config']->set('database.connections.testbench', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ]);

        // For tests, we use the same connection for both to simplify
        $app['config']->set('multitenancy.tenant_model', Tenant::class);
        $app['config']->set('multitenancy.landlord_database_connection_name', 'testbench');
        $app['config']->set('multitenancy.tenant_database_connection_name', 'testbench');

        $app['config']->set('mail.default', 'array');
    }

    protected function tearDown(): void
    {
        if (app()->has('currentTenant')) {
            app()->forgetInstance('currentTenant');
            Tenant::forgetCurrent();
        }

        parent::tearDown();
    }
}
