<?php

namespace App\Providers;

use App\Services\Document\DocxToPdfConverter;
use App\Services\Document\LibreOfficeDocxToPdfConverter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(DocxToPdfConverter::class, LibreOfficeDocxToPdfConverter::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
