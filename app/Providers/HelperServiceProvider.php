<?php

namespace App\Providers;

use App\Helpers\StringHelpers;
use Illuminate\Support\Str;
use Illuminate\Support\ServiceProvider;

class HelperServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     *
     * @return void
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
