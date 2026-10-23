<?php

namespace App\Providers;

use App\Helpers\StringHelpers;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class HelperServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        Str::macro('afterFirst', function ($subject, $search) {
            return StringHelpers::afterFirst($subject, $search);
        });

        Str::macro('beforeFirst', function ($subject, $search) {
            return StringHelpers::beforeFirst($subject, $search);
        });
    }
}
