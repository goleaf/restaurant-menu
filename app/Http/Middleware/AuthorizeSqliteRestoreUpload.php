<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\Backups\SqliteRestoreAuthorization;
use App\Support\Backups\SqliteBackupConstraints;
use Closure;
use Illuminate\Http\Request;
use Livewire\Features\SupportFileUploads\FileUploadConfiguration;
use Symfony\Component\HttpFoundation\Response;

final class AuthorizeSqliteRestoreUpload
{
    public function __construct(
        private readonly SqliteRestoreAuthorization $authorization,
        private readonly RequireRecentPasswordConfirmation $passwordConfirmation,
    ) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->routeIs('livewire.upload-file') || ! $request->query->has('restore_attempt')) {
            return $next($request);
        }

        abort_unless($request->hasValidSignature(), 401);
        $authorization = $this->authorization->handle($request);
        $attempt = $request->query('restore_attempt');
        abort_unless(is_string($attempt) && hash_equals(hash('sha256', $authorization['nonce']), $attempt), 403);

        return $this->passwordConfirmation->handle($request, function (Request $request) use ($next): Response {
            $originalRules = config('livewire.temporary_file_upload.rules');
            $rules = array_values(array_filter(FileUploadConfiguration::rules(), static fn (mixed $rule): bool => ! is_string($rule) || ! str_starts_with($rule, 'max:')));
            $rules[] = 'max:'.intdiv(SqliteBackupConstraints::MAXIMUM_BYTES, 1024);
            config()->set('livewire.temporary_file_upload.rules', $rules);

            try {
                return $next($request);
            } finally {
                config()->set('livewire.temporary_file_upload.rules', $originalRules);
            }
        });
    }
}
