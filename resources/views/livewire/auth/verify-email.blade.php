<x-layouts::auth :title="__('ui.auth.verify_email.email_verification')">
    <div class="mt-4 flex flex-col gap-6">
        <flux:text class="text-center">
            {{ __('ui.auth.verify_email.please_verify_your_email_address_by_clicking_on_the_li') }}
        </flux:text>

        @if ($verificationLinkSent)
            <flux:text class="text-center font-medium !dark:text-green-400 !text-green-600">
                {{ __('ui.auth.verify_email.a_new_verification_link_has_been_sent_to_the_email_add') }}
            </flux:text>
        @endif

        <div class="flex flex-col items-center justify-between space-y-3">
            <form method="POST" action="{{ route('verification.send') }}">
                @csrf
                <flux:button type="submit" variant="primary" class="w-full">
                    {{ __('ui.auth.verify_email.resend_verification_email') }}
                </flux:button>
            </form>

            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <flux:button variant="ghost" type="submit" class="text-sm cursor-pointer" data-test="logout-button">
                    {{ __('navigation.logout') }}
                </flux:button>
            </form>
        </div>
    </div>
</x-layouts::auth>
