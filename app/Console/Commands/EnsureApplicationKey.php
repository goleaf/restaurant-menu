<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Dotenv\Dotenv;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

final class EnsureApplicationKey extends Command
{
    protected $signature = 'app:ensure-key';

    protected $description = 'Generate an application key only when no key is already configured';

    public function handle(): int
    {
        $path = $this->laravel->environmentFilePath();
        $environment = File::exists($path) ? Dotenv::parse(File::get($path)) : [];

        if (filled($environment['APP_KEY'] ?? null) || filled(config('app.key'))) {
            $this->components->info(__('setup.key_preserved'));

            return self::SUCCESS;
        }

        return $this->call('key:generate', ['--no-interaction' => true]);
    }
}
