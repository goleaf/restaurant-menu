<div class="mt-4 flex flex-col gap-6">
    <flux:text class="text-center">
        {{ __('ui.auth.verify_email.please_verify_your_email_address_by_clicking_on_the_li') }}
    </flux:text>

    @if ($verificationLinkSent)
        <flux:text class="text-center font-medium !dark:text-green-400 !text-green-600">
            {{ __('ui.auth.verify_email.a_new_verification_link_has_been_sent_to_the_email_add') }}
        </flux:text>
    @endif

    <flux:error name="verificationLinkSent" />

    <div class="flex flex-col items-center justify-between space-y-3">
        <form wire:submit="resend" novalidate>
                <flux:button type="submit" wire:loading.attr="disabled" wire:offline.attr="disabled" variant="primary" class="w-full">
                {{ __('ui.auth.verify_email.resend_verification_email') }}
            </flux:button>
        </form>

        <livewire:auth.logout />
    </div>
</div>
