<x-layouts::auth :title="$title">
    <div class="flex flex-col gap-6">
        <x-auth-header :title="$title" :description="$message" />

        @if ($switchAccountUrl)
            <form method="POST" action="{{ $switchAccountUrl }}">
                @csrf
                <flux:button type="submit" variant="primary" class="w-full">{{ __('invitations.actions.switch_account') }}</flux:button>
            </form>
        @else
            <flux:button :href="$actionUrl" variant="primary" class="w-full">
                {{ $actionLabel }}
            </flux:button>
        @endif
    </div>
</x-layouts::auth>
