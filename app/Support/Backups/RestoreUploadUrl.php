<?php

declare(strict_types=1);

namespace App\Support\Backups;

use App\Services\Backups\SqliteRestoreAuthorization;
use Illuminate\Auth\Middleware\RequirePassword;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

final readonly class RestoreUploadUrl
{
    /**
     * Create a new class instance.
     */
    public function __construct(private SqliteRestoreAuthorization $authorization, private RequirePassword $password) {}

    public function issue(Request $request): string
    {
        $authorization = $this->authorization->handle($request);
        $confirmation = $this->password->handle($request, static fn (): Response => new Response(status: 204));
        abort_unless($confirmation instanceof Response && $confirmation->getStatusCode() === 204, 423);

        return URL::temporarySignedRoute('livewire.upload-file', now()->addMinutes(5), [
            'restore_attempt' => hash('sha256', $authorization['nonce']),
        ]);
    }
}
