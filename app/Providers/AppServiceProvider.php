<?php

namespace App\Providers;

use App\Models\ProductionJob;
use App\Models\User;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Route::bind('job', function (string $value): ProductionJob {
            $user = request()->user();

            if (! $user instanceof User) {
                throw new AuthenticationException;
            }

            return ProductionJob::visibleTo($user)->findOrFail($value);
        });
    }
}
