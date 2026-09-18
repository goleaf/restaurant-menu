<?php

declare(strict_types=1);

namespace Tests\Support;

use Closure;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Pest\Browser\Api\PendingAwaitablePage;
use Symfony\Component\HttpFoundation\Response;

final class BrowserMenuImageUpload
{
    public static function attachPng(PendingAwaitablePage $page, string $selector, string $name): void
    {
        $page->assertEnabled($selector);
        $encodedSelector = json_encode($selector, JSON_THROW_ON_ERROR);
        $encodedName = json_encode($name, JSON_THROW_ON_ERROR);
        $encodedBytes = json_encode(base64_encode(UploadedFile::fake()->image($name, 800, 400)->getContent()), JSON_THROW_ON_ERROR);
        $attached = $page->script(<<<JAVASCRIPT
            (() => {
                const input = document.querySelector({$encodedSelector});
                if (!(input instanceof HTMLInputElement)) return false;
                const bytes = Uint8Array.from(atob({$encodedBytes}), character => character.charCodeAt(0));
                const transfer = new DataTransfer();
                transfer.items.add(new File([bytes], {$encodedName}, { type: 'image/png' }));
                input.files = transfer.files;
                input.dispatchEvent(new Event('change', { bubbles: true }));
                return true;
            })()
        JAVASCRIPT);
        expect($attached)->toBeTrue();
    }

    public static function enableMultipartFixtures(): void
    {
        $middleware = new class
        {
            public function handle(Request $request, Closure $next): Response
            {
                $type = $request->header('Content-Type', '');
                if (! str_starts_with($type, 'multipart/form-data') || ! str_contains($request->path(), 'livewire')) {
                    return $next($request);
                }

                expect(preg_match('/boundary="?([^";]+)"?/', $type, $matches))->toBe(1);
                $body = $request->getContent();
                expect(strlen($body))->toBeLessThan(100_000);
                $files = [];
                foreach (explode('--'.$matches[1], $body) as $part) {
                    if (! str_contains($part, 'name="files[]"')) {
                        continue;
                    }
                    [$headers, $content] = explode("\r\n\r\n", $part, 2);
                    expect(preg_match('/filename="([^"]+)"/', $headers, $filename))->toBe(1);
                    expect($filename[1])->toBeIn(['first.png', 'second.png', 'third.png', 'catalog-fixture.csv']);
                    $files[] = UploadedFile::fake()->createWithContent($filename[1], substr($content, 0, -2));
                }
                expect($files)->toHaveCount(1);
                $request->files->set('files', $files);

                return $next($request);
            }
        };
        app()->instance('browser.multipart-fixtures', $middleware);
        app(Kernel::class)->prependMiddleware('browser.multipart-fixtures');
    }
}
