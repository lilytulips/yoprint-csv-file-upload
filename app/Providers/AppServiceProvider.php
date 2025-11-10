<?php

namespace App\Providers;

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
        // Increase memory limit for large file processing
        // Note: This only affects memory_limit, not upload_max_filesize or post_max_size
        // Those must be changed in php.ini
        ini_set('memory_limit', '512M');
    }
}
