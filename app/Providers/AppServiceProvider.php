<?php

namespace App\Providers;

use App\Services\ZKTecoService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ZKTecoService::class, function ($app) {
            return new ZKTecoService();
        });
    }

    public function boot(): void
    {
        //
    }
}
