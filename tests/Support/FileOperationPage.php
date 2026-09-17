<?php

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Livewire\Livewire;
use RuntimeException;
use Tests\TestCase;

final class FileOperationPage
{
    public static function open(TestCase $test, string $url, string $name): string
    {
        $test->withCredentials()->withCookie(config('session.cookie'), session()->getId());
        $response = $test->get($url)->assertOk();
        preg_match_all('/wire:snapshot="([^"]+)"/', $response->getContent(), $matches);
        foreach ($matches[1] as $candidate) {
            $snapshot = html_entity_decode($candidate, ENT_QUOTES);
            if (json_decode($snapshot, true, flags: JSON_THROW_ON_ERROR)['memo']['name'] === $name) {
                return $snapshot;
            }
        }
        throw new RuntimeException('The file operation page did not render its signed snapshot.');
    }

    /** @param array<string, mixed> $updates
     * @param  list<mixed>  $params
     */
    public static function call(TestCase $test, string $snapshot, string $method, array $updates = [], array $params = []): TestResponse
    {
        $test->withCredentials()->withCookie(config('session.cookie'), session()->getId());

        return $test->postJson(Livewire::getUpdateUri(), ['components' => [[
            'snapshot' => $snapshot, 'updates' => $updates, 'calls' => [['method' => $method, 'params' => $params]],
        ]]], ['X-Livewire' => '', 'X-CSRF-TOKEN' => csrf_token()]);
    }

    public static function upload(TestCase $test, string $snapshot, UploadedFile $file): string
    {
        Storage::fake('tmp-for-tests');
        $start = self::call($test, $snapshot, '_startUpload', params: ['upload.backup', [['name' => $file->getClientOriginalName(), 'size' => $file->getSize(), 'type' => $file->getMimeType()]], false])->assertOk();
        $url = collect($start->json('components.0.effects.dispatches'))->firstWhere('name', 'upload:generatedSignedUrl')['params']['url'];
        $test->withCredentials()->withCookie(config('session.cookie'), session()->getId());
        $upload = $test->post($url, ['files' => [$file]], ['Accept' => 'application/json', 'X-CSRF-TOKEN' => csrf_token()])->assertOk();
        $finish = self::call($test, $start->json('components.0.snapshot'), '_finishUpload', params: ['upload.backup', $upload->json('paths'), false])->assertOk();

        return $finish->json('components.0.snapshot');
    }
}
