<?php

namespace Tests;

use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Routing\Redirector;
use Illuminate\Testing\TestResponse;
use Laravel\Fortify\Features;
use Laravel\Fortify\FortifyServiceProvider;
use Livewire\Features\SupportRedirects\Redirector as LivewireRedirector;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function call(mixed $method, mixed $uri, mixed $parameters = [], mixed $cookies = [], mixed $files = [], mixed $server = [], mixed $content = null): TestResponse
    {
        // Aborted Livewire requests can leave their temporary redirector in the reused test container.
        // PHP-FPM starts the next HTTP request with Laravel's native redirector.
        if ($this->app->make('redirect') instanceof LivewireRedirector) {
            $redirector = new Redirector($this->app->make('url'));
            $redirector->setSession($this->app->make('session.store'));
            $this->app->instance('redirect', $redirector);
            $this->app->forgetInstance(ResponseFactory::class);
        }

        return parent::call($method, $uri, $parameters, $cookies, $files, $server, $content);
    }

    protected function skipUnlessFortifyHas(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }

    /** @param list<string> $features */
    protected function enableFortifyFeatures(array $features): void
    {
        config(['fortify.features' => array_values(array_unique([
            ...config('fortify.features', []),
            ...$features,
        ]))]);

        $this->app->getProvider(FortifyServiceProvider::class)->boot();
        require base_path('routes/auth.php');
        $this->app['router']->getRoutes()->refreshNameLookups();
        $this->app['router']->getRoutes()->refreshActionLookups();
    }
}
