<?php

declare(strict_types=1);

namespace App\Livewire\Auth;

use App\Support\Auth\LivewireAuthRedirectResponse;
use Closure;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

trait HandlesAuthResponses
{
    /** @param Closure(): void $operation */
    private function attempt(Closure $operation, string $errorField = 'form.email'): void
    {
        try {
            $operation();
        } catch (ValidationException $exception) {
            $messages = [];
            foreach ($exception->errors() as $field => $errors) {
                $messages[str_starts_with($field, 'form.') ? $field : 'form.'.$field] = array_map(
                    fn (string $message): string => match ($message) {
                        'auth.failed', __('auth.failed') => __('auth.failed'),
                        'The provided two factor authentication code was invalid.' => __('auth.two_factor_code'),
                        'The provided two factor recovery code was invalid.' => __('auth.two_factor_recovery_code'),
                        default => $message,
                    },
                    $errors,
                );
            }
            throw ValidationException::withMessages($messages);
        } catch (ThrottleRequestsException $exception) {
            $seconds = (int) ($exception->getHeaders()['Retry-After'] ?? 60);
            throw ValidationException::withMessages([$errorField => __('auth.throttle', [
                'seconds' => $seconds, 'minutes' => (int) ceil($seconds / 60),
            ])]);
        }
    }

    private function follow(Response $response): void
    {
        if ($response instanceof LivewireAuthRedirectResponse) {
            return;
        }

        if ($response->isRedirection() && is_string($location = $response->headers->get('Location'))) {
            $this->redirect($location, navigate: false);

            return;
        }

        abort($response);
    }
}
