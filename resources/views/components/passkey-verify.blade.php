@props([
    'optionsRoute' => 'passkey.login-options',
    'submitRoute' => 'passkey.login',
    'label' => __('ui.components.passkey_verify.sign_in_with_a_passkey'),
    'loadingLabel' => __('ui.components.passkey_verify.authenticating'),
    'separator' => __('ui.components.passkey_verify.or_continue_with_email'),
])

<div
    x-data="passkeyVerification" data-failure="{{ __('auth.passkey_failed') }}" data-options-url="{{ route($optionsRoute) }}" data-submit-url="{{ route($submitRoute) }}" data-fallback-url="{{ route('dashboard') }}"
>
    <template x-if="supported">
        <div>
            <div class="grid gap-2">
                <flux:button
                    variant="outline"
                    icon="finger-print"
                    class="w-full"
                    x-on:click="verify()"
                    x-bind:disabled="loading"
                >
                    <span x-show="!loading">{{ $label }}</span>
                    <span x-show="loading" x-cloak>{{ $loadingLabel }}</span>
                </flux:button>
                <p role="alert" x-show="error" x-text="error" x-cloak
                   class="text-sm text-center text-red-600 dark:text-red-400"></p>
            </div>

            <div class="relative my-6">
                <div class="absolute inset-0 flex items-center">
                    <div class="w-full border-t border-zinc-200 dark:border-zinc-700"></div>
                </div>
                <div class="relative flex justify-center text-xs uppercase">
                    <span class="px-2 text-zinc-500 dark:text-zinc-400 bg-white dark:bg-zinc-900">
                        {{ $separator }}
                    </span>
                </div>
            </div>
        </div>
    </template>
</div>
