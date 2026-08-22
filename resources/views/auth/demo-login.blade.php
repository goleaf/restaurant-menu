<x-layouts::auth.simple :title="__('demo_login.title')" wide>
    <div class="flex flex-col gap-6">
        <x-auth-header :title="__('demo_login.title')" :description="__('demo_login.description')" />

        <div class="rounded-card border border-warning bg-warning-surface p-4 text-sm leading-6 text-text-primary" role="note">
            <div class="flex gap-3">
                <flux:icon name="exclamation-triangle" class="mt-0.5 size-5 shrink-0 text-warning" aria-hidden="true" />
                <p>{{ __('demo_login.warning') }}</p>
            </div>
        </div>

        @error('demo_login')
            <div class="rounded-card border border-danger bg-danger-surface p-4 text-sm leading-6 text-text-primary" role="alert">
                <div class="flex gap-3">
                    <flux:icon name="exclamation-circle" class="mt-0.5 size-5 shrink-0 text-danger" aria-hidden="true" />
                    <p>{{ $message }}</p>
                </div>
            </div>
        @enderror

        <ul class="grid gap-3 sm:grid-cols-2">
            @forelse ($accounts as $account)
                <li class="min-w-0 rounded-card border border-border-subtle bg-surface p-4">
                    <div class="flex h-full min-w-0 flex-col gap-4">
                        <div class="flex min-w-0 items-start gap-3">
                            <flux:icon
                                :name="$account['available'] ? 'check-circle' : 'exclamation-circle'"
                                @class([
                                    'mt-0.5 size-5 shrink-0',
                                    'text-success' => $account['available'],
                                    'text-warning' => ! $account['available'],
                                ])
                                aria-hidden="true"
                            />

                            <div class="min-w-0">
                                <h2 class="text-base font-semibold text-text-primary">{{ $account['label'] }}</h2>
                                <p class="break-all text-sm text-text-muted">{{ $account['email'] }}</p>
                            </div>
                        </div>

                        <div class="flex flex-1 flex-col gap-2 text-sm leading-5">
                            @if ($account['available'])
                                <p class="font-medium text-success">{{ __('demo_login.available') }}</p>
                            @else
                                <p class="font-medium text-warning">{{ __('demo_login.unavailable') }}</p>
                                <p class="text-text-muted">{{ __('demo_login.unavailable_hint') }}</p>
                            @endif
                        </div>

                        <form method="POST" action="{{ route('demo-login.authenticate', ['role' => $account['role']]) }}">
                            @csrf

                            <flux:button
                                type="submit"
                                variant="primary"
                                class="h-auto! min-h-touch w-full whitespace-normal! py-2"
                                :disabled="! $account['available']"
                            >
                                {{ __('demo_login.login_as', ['role' => $account['label']]) }}
                            </flux:button>
                        </form>
                    </div>
                </li>
            @empty
                <li class="rounded-card border border-border-subtle bg-surface p-4 text-center text-sm text-text-muted sm:col-span-2">
                    {{ __('demo_login.empty') }}
                </li>
            @endforelse
        </ul>
    </div>
</x-layouts::auth.simple>
