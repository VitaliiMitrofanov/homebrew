<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Psr7\HttpFactory;
use SoftCreatR\PerplexityAI\PerplexityAI;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(PerplexityAI::class, function () {
        $apiKey = config('services.perplexity.key');

        $httpClient     = new GuzzleClient();
        $httpFactory    = new HttpFactory();

        return new PerplexityAI(
            $httpFactory, // RequestFactory
            $httpFactory, // StreamFactory
            $httpFactory, // UriFactory
            $httpClient,
            $apiKey
        );
    });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
