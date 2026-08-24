<?php

declare(strict_types=1);

namespace App\Support\DemoLogin;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Request;

final class DemoEnvironment
{
    public function __construct(private readonly Application $application) {}

    public function allowsRequest(Request $request): bool
    {
        return $this->isEnabled() && $this->allowsHost($request->getHost());
    }

    public function shouldSeedDatabase(): bool
    {
        $host = parse_url((string) config('app.url'), PHP_URL_HOST);

        return $this->isEnabled()
            && is_string($host)
            && $this->allowsHost($host);
    }

    public function isEnabled(): bool
    {
        return ! $this->application->isProduction()
            && strtolower((string) config('app.env')) !== 'production'
            && config('demo-login.enabled') === true;
    }

    private function allowsHost(string $host): bool
    {
        return in_array(
            strtolower(rtrim($host, '.')),
            $this->allowedHosts(),
            true,
        );
    }

    /**
     * @return list<string>
     */
    private function allowedHosts(): array
    {
        $configuredHosts = config('demo-login.allowed_hosts', []);

        if (! is_array($configuredHosts)) {
            return [];
        }

        return collect($configuredHosts)
            ->filter(fn (mixed $host): bool => is_string($host))
            ->map(fn (string $host): string => strtolower(rtrim(trim($host), '.')))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
}
