<?php

declare(strict_types=1);

namespace App\Support\Localization;

use Illuminate\Contracts\Translation\Loader;
use Illuminate\Support\Arr;

final readonly class ValidationTranslationLoader implements Loader
{
    public function __construct(private Loader $loader) {}

    /** @return array<string, mixed> */
    public function load($locale, $group, $namespace = null): array
    {
        $lines = $this->loader->load($locale, $group, $namespace);

        if ($group !== 'validation' || ($namespace !== null && $namespace !== '*')) {
            return $lines;
        }

        $validation = [];
        foreach ($this->loader->load($locale, '*', '*') as $key => $value) {
            if (str_starts_with($key, 'validation.')) {
                $validation[substr($key, strlen('validation.'))] = $value;
            }
        }

        return array_replace_recursive($lines, Arr::undot($validation));
    }

    public function addNamespace($namespace, $hint): void
    {
        $this->loader->addNamespace($namespace, $hint);
    }

    public function addJsonPath($path): void
    {
        $this->loader->addJsonPath($path);
    }

    /** @return array<string, string> */
    public function namespaces(): array
    {
        return $this->loader->namespaces();
    }
}
