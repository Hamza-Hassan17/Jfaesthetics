<?php

namespace App\Providers;

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        // Some hosts still run MySQL/MariaDB with utf8mb4 but without
        // innodb_large_prefix, capping index length under 767-1000 bytes.
        // A default VARCHAR(255) primary/unique key (like sessions.id)
        // exceeds that under utf8mb4 (4 bytes/char). 191 chars keeps every
        // such key under the limit regardless of host config.
        Schema::defaultStringLength(191);
    }
}
