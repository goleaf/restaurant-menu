<x-layouts::auth :title="$title">
    <div class="flex flex-col gap-6">
        <x-auth-header :title="$title" :description="$message" />

        <flux:button :href="$actionUrl" variant="primary" class="w-full">
            {{ $actionLabel }}
        </flux:button>
    </div>
</x-layouts::auth>
