<?php

namespace App\Http\Middleware;

use App\Actions\Localization\UpdateUserLocaleAction;
use App\Enums\SupportedLocale;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class SetInterfaceLocale
{
    public function __construct(
        private UpdateUserLocaleAction $updateUserLocale,
    ) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        App::setLocale($this->resolveLocale($request));

        return $next($request);
    }

    private function resolveLocale(Request $request): string
    {
        $queryLocale = $request->query('lang');

        if (is_string($queryLocale) && SupportedLocale::isSupported($queryLocale)) {
            $locale = SupportedLocale::normalize($queryLocale);
            $request->session()->put('interface_locale', $locale);

            $user = $request->user();

            if ($user instanceof User) {
                $this->updateUserLocale->handle($user, $locale);
            }

            return $locale;
        }

        $userLocale = $request->user()?->locale;

        if (SupportedLocale::isSupported(is_string($userLocale) ? $userLocale : null)) {
            return SupportedLocale::normalize($userLocale);
        }

        $sessionLocale = $request->session()->get('interface_locale');

        return SupportedLocale::normalize(
            is_string($sessionLocale) ? $sessionLocale : null,
            (string) config('app.locale', SupportedLocale::English->value),
        );
    }
}
