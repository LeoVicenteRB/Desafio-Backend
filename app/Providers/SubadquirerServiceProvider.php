<?php

namespace App\Providers;

use App\Adapters\Contracts\SubadquirerInterface;
use App\Adapters\SubadqAAdapter;
use App\Adapters\SubadqBAdapter;
use Illuminate\Support\ServiceProvider;

class SubadquirerServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton('subadquirer.SubadqA', function ($app) {
            return new SubadqAAdapter(
                baseUrl: config('services.subadq_a.base_url', env('SUBADQA_BASE_URL', 'https://subadqa.mock')),
                apiKey: config('services.subadq_a.api_key'),
                apiSecret: config('services.subadq_a.api_secret'),
                timeout: config('services.subadq_a.timeout', 30)
            );
        });

        $this->app->singleton('subadquirer.SubadqB', function ($app) {
            return new SubadqBAdapter(
                baseUrl: config('services.subadq_b.base_url', env('SUBADQB_BASE_URL', 'https://subadqb.mock')),
                apiKey: config('services.subadq_b.api_key'),
                apiSecret: config('services.subadq_b.api_secret'),
                timeout: config('services.subadq_b.timeout', 30)
            );
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}

