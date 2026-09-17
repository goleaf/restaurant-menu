<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Fortify\Features;
use Laravel\Fortify\FortifyServiceProvider;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
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
