<?php

declare(strict_types=1);

namespace App\Providers;

use App\Repositories\Contracts\EmployeeRepositoryInterface;
use App\Repositories\EmployeeRepository;
use Illuminate\Support\ServiceProvider;

/**
 * Binds repository interfaces to their Eloquent implementations.
 *
 * This is the only place in the application where concrete Repository
 * classes are referenced outside of the Repositories namespace itself.
 * Swapping an implementation (e.g. to a Redis-backed cache repository)
 * only requires editing this file.
 *
 * Register this provider in config/app.php under 'providers'.
 */
class RepositoryServiceProvider extends ServiceProvider
{
    /**
     * Register repository bindings into the service container.
     *
     * @return void
     */
    public function register(): void
    {
        $this->app->bind(
            EmployeeRepositoryInterface::class,
            EmployeeRepository::class,
        );
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot(): void
    {
        // no bootstrapping required
    }
}
