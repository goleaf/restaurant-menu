<?php

declare(strict_types=1);

use App\Http\Middleware\ProtectInvitationResponses;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

pest()->extend(TestCase::class);

test('credential paths protect responses even before a route is matched', function (string $uri): void {
    $request = Request::create($uri);
    $response = new Response('unchanged error body', 404, ['Cache-Control' => 'public, max-age=60']);

    expect(ProtectInvitationResponses::protect($request, $response))->toBe($response)
        ->and($response->getStatusCode())->toBe(404)
        ->and($response->getContent())->toBe('unchanged error body')
        ->and($response->headers->get('Cache-Control'))->toBe('no-store, private')
        ->and($response->headers->get('Referrer-Policy'))->toBe('no-referrer')
        ->and($response->headers->get('X-Robots-Tag'))->toBe('noindex, nofollow');
})->with([
    '/invite', '/invite/fixture', '/login', '/demo-login', '/demo-login/owner',
    '/local-login/fixture', '/forgot-password', '/reset-password', '/reset-password/fixture',
    '/two-factor-challenge', '/user/confirm-password', '/email/verify',
    '/login/?ignored=1', '/%6Cogin', '/reset-password%2Ffixture', '/invite/one/two',
    '/invite/fixture%0A',
]);

test('named authentication routes protect configured paths', function (string $name): void {
    $request = Request::create('/account/custom-entry');
    $route = (new Route('GET', 'account/custom-entry', static fn (): Response => new Response))->name($name);
    $request->setRouteResolver(static fn (): Route => $route);
    $response = ProtectInvitationResponses::protect($request, new Response);

    expect($response->headers->get('Cache-Control'))->toBe('no-store, private')
        ->and($response->headers->get('Referrer-Policy'))->toBe('no-referrer')
        ->and($response->headers->get('X-Robots-Tag'))->toBe('noindex, nofollow');
})->with([
    'login', 'login.store', 'logout', 'password.request', 'password.reset.form',
    'two-factor.login', 'two-factor.login.store', 'verification.verify',
    'invitations.pending', 'demo-login.store',
]);

test('unrelated requests retain their existing response headers', function (string $uri): void {
    $request = Request::create($uri);
    $response = new Response('public content', 200, [
        'Cache-Control' => 'public, max-age=60',
        'Referrer-Policy' => 'strict-origin',
        'X-Robots-Tag' => 'index, follow',
    ]);
    $headers = $response->headers->all();

    expect(ProtectInvitationResponses::protect($request, $response))->toBe($response)
        ->and($response->headers->all())->toBe($headers);
})->with([
    '/', '/restaurant/catalog', '/up', '/LOGIN', '/invited', '/login-help',
    '/account/login', '/restaurant?next=/login', '/reset-password%252Ffixture',
]);

test('Livewire responses are protected regardless of header value or path', function (string $value): void {
    $request = Request::create('/custom-update', 'POST', server: ['HTTP_X_LIVEWIRE' => $value]);
    $response = ProtectInvitationResponses::protect($request, new Response(status: 422));

    expect($response->getStatusCode())->toBe(422)
        ->and($response->headers->get('Cache-Control'))->toBe('no-store, private')
        ->and($response->headers->get('Referrer-Policy'))->toBe('no-referrer')
        ->and($response->headers->get('X-Robots-Tag'))->toBe('noindex, nofollow');
})->with(['', 'true']);

test('privacy middleware uses the route selected downstream', function (): void {
    $request = Request::create('/account/custom-entry');
    $response = new Response(status: 302);

    $result = (new ProtectInvitationResponses)->handle($request, function (Request $incoming) use ($request, $response): Response {
        expect($incoming)->toBe($request);
        $route = (new Route('GET', 'account/custom-entry', static fn (): Response => new Response))->name('login');
        $incoming->setRouteResolver(static fn (): Route => $route);

        return $response;
    });

    expect($result)->toBe($response)
        ->and($result->headers->get('Referrer-Policy'))->toBe('no-referrer');
});

test('privacy classification stays fresh across repeated calls', function (): void {
    $request = Request::create('/restaurant/catalog');

    expect(ProtectInvitationResponses::protect($request, new Response)->headers->has('Referrer-Policy'))->toBeFalse();
    $request->headers->set('X-Livewire', '');
    expect(ProtectInvitationResponses::protect($request, new Response)->headers->get('Referrer-Policy'))->toBe('no-referrer');
    $request->headers->remove('X-Livewire');
    expect(ProtectInvitationResponses::protect($request, new Response)->headers->has('Referrer-Policy'))->toBeFalse();
});

test('privacy path matching decodes an unrelated request only once', function (): void {
    $request = new class extends Request
    {
        public int $pathDecodes = 0;

        public function decodedPath(): string
        {
            $this->pathDecodes++;

            return parent::decodedPath();
        }
    };
    $request->initialize(server: ['REQUEST_URI' => '/restaurant/catalog', 'REQUEST_METHOD' => 'GET']);

    ProtectInvitationResponses::protect($request, new Response);

    expect($request->pathDecodes)->toBe(1);
});
