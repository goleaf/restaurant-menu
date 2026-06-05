<?php

namespace App\Actions\System;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class BuildProductionSafetyReportAction
{
    /**
     * @return array{
     *     environment: string,
     *     environment_label: string,
     *     is_production: bool,
     *     warnings: list<array{code: string, message: string, severity: string}>
     * }
     */
    public function handle(): array
    {
        $environment = trim((string) config('app.env', 'production')) ?: 'production';
        $normalizedEnvironment = strtolower($environment);
        $isProduction = $normalizedEnvironment === 'production';
        $warnings = [];

        if ($isProduction && config('app.debug') === true) {
            $warnings[] = [
                'code' => 'app_debug_enabled',
                'message' => 'APP_DEBUG is enabled in production. Set APP_DEBUG=false before serving traffic.',
                'severity' => 'danger',
            ];
        }

        if ($isProduction && ! $this->publicStoragePhpGuardExists()) {
            $warnings[] = [
                'code' => 'public_storage_php_guard_missing',
                'message' => 'Public storage is missing the PHP execution deny rule.',
                'severity' => 'warning',
            ];
        }

        return [
            'environment' => $environment,
            'environment_label' => $this->environmentLabel($environment),
            'is_production' => $isProduction,
            'warnings' => $warnings,
        ];
    }

    private function publicStoragePhpGuardExists(): bool
    {
        $path = storage_path('app/public/.htaccess');

        if (! File::exists($path)) {
            return false;
        }

        $rules = File::get($path);

        return Str::contains($rules, ['FilesMatch', 'php', 'Require all denied']);
    }

    private function environmentLabel(string $environment): string
    {
        return match (strtolower($environment)) {
            'production' => __('ui.actions.system.buildproductionsafetyreportaction.production'),
            'local' => __('ui.actions.system.buildproductionsafetyreportaction.local'),
            'staging' => __('ui.actions.system.buildproductionsafetyreportaction.staging'),
            'testing' => __('ui.actions.system.buildproductionsafetyreportaction.testing'),
            default => Str::of($environment)
                ->replace(['_', '-'], ' ')
                ->headline()
                ->toString(),
        };
    }
}
