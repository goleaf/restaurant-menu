<?php

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Testing\TestResponse;
use RuntimeException;
use Tests\TestCase;

final class InvitationPage
{
    public static function open(TestCase $test): string
    {
        $test->withCredentials()->withCookie(config('session.cookie'), session()->getId());
        $response = $test->get(route('invitations.pending'));
        preg_match_all('/wire:snapshot="([^"]+)"/', $response->getContent(), $matches);
        foreach ($matches[1] as $candidate) {
            $snapshot = html_entity_decode($candidate, ENT_QUOTES);
            if (json_decode($snapshot, true, flags: JSON_THROW_ON_ERROR)['memo']['name'] === 'invitations.show') {
                $test->withCredentials()->withCookie(config('session.cookie'), session()->getId());

                return $snapshot;
            }
        }

        throw new RuntimeException('Invitation page did not render its signed snapshot.');
    }

    /** @param array<string, mixed> $input */
    public static function call(TestCase $test, string $method, array $input = [], ?string $snapshot = null): TestResponse
    {
        $snapshot ??= self::open($test);
        $updates = [];
        foreach ($input as $key => $value) {
            $updates['form.'.$key] = $value;
        }

        return $test->postJson(route('default-livewire.update'), ['components' => [[
            'snapshot' => $snapshot, 'updates' => $updates, 'calls' => [['method' => $method, 'params' => []]],
        ]]], ['X-Livewire' => '']);
    }

    /** @return array<string, list<string>> */
    public static function errors(TestResponse $response): array
    {
        return json_decode($response->json('components.0.snapshot'), true, flags: JSON_THROW_ON_ERROR)['memo']['errors'];
    }

    /** @return array<string, mixed> */
    public static function form(TestResponse $response): array
    {
        return json_decode($response->json('components.0.snapshot'), true, flags: JSON_THROW_ON_ERROR)['data']['form'][0];
    }
}
