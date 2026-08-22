<x-layouts::auth :title="$title">
    <div class="flex flex-col gap-6">
        <x-auth-header
            :title="$title"
            :description="__('invitations.description')"
        />

        <dl class="grid gap-4 rounded-xl border border-zinc-200 bg-white p-5 text-sm shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
            <div class="grid gap-1">
                <dt class="font-medium text-zinc-600 dark:text-zinc-300">{{ __('invitations.organization') }}</dt>
                <dd class="text-zinc-950 dark:text-white">{{ $organizationName }}</dd>
            </div>

            @if ($branchName)
                <div class="grid gap-1">
                    <dt class="font-medium text-zinc-600 dark:text-zinc-300">{{ __('invitations.branch') }}</dt>
                    <dd class="text-zinc-950 dark:text-white">{{ $branchName }}</dd>
                </div>
            @endif

            <div class="grid gap-1">
                <dt class="font-medium text-zinc-600 dark:text-zinc-300">{{ __('invitations.role') }}</dt>
                <dd class="text-zinc-950 dark:text-white">{{ $roleName }}</dd>
            </div>

            <div class="grid gap-1">
                <dt class="font-medium text-zinc-600 dark:text-zinc-300">{{ __('invitations.expires_at') }}</dt>
                <dd class="text-zinc-950 dark:text-white">{{ $expiresAt }}</dd>
            </div>
        </dl>

        <form method="POST" action="{{ $acceptUrl }}">
            @csrf

            <flux:button type="submit" variant="primary" class="w-full">
                {{ __('invitations.actions.accept') }}
            </flux:button>
        </form>
    </div>
</x-layouts::auth>
