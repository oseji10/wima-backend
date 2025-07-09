<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

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
    

public function boot()
{
    // if (!file_exists(public_path('storage'))) {
    //     try {
    //         Artisan::call('storage:link');
    //     } catch (\Throwable $e) {
    //         Log::warning('Storage link failed (probably due to exec() being disabled): ' . $e->getMessage());
    //     }
    // }
}

}
