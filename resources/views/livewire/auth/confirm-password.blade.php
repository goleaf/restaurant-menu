<x-layouts::auth :title="__('ui.auth.confirm_password.confirm_password')">
    <div class="flex flex-col gap-6">
        <x-auth-header
            :title="__('ui.auth.confirm_password.confirm_password')"
            :description="__('ui.auth.confirm_password.this_is_a_secure_area_of_the_application_please_co')"
        />

        <x-auth-session-status class="text-center" :status="session('status')" />

        <form method="POST" action="{{ route('password.confirm.store') }}" class="flex flex-col gap-6">
            @csrf

            <flux:input
                name="password"
                :label="__('ui.auth.confirm_password.password')"
                type="password"
                required
                autocomplete="current-password"
                :placeholder="__('ui.auth.confirm_password.password')"
                viewable
            />

            <flux:button variant="primary" type="submit" class="w-full" data-test="confirm-password-button">
                {{ __('ui.actions.confirm') }}
            </flux:button>
        </form>
    </div>
</x-layouts::auth>
