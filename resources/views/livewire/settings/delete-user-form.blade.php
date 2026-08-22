<section class="mt-10 space-y-6">
    <div class="relative mb-5">
        <flux:heading>{{ __('ui.settings.delete_user_form.delete_account') }}</flux:heading>
        <flux:subheading>{{ __('ui.settings.delete_user_form.delete_your_account_and_all_of_its_resources') }}</flux:subheading>
    </div>

    <flux:modal.trigger name="confirm-user-deletion">
        <flux:button variant="danger" x-data="" x-on:click.prevent="$dispatch('open-modal', 'confirm-user-deletion')">
            {{ __('ui.settings.delete_user_form.delete_account') }}
        </flux:button>
    </flux:modal.trigger>

    <flux:modal name="confirm-user-deletion" :show="$errors->isNotEmpty()" focusable class="max-w-lg" :closable="false">
        <x-modal-close-button />

        <form method="POST" wire:submit="deleteUser" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('ui.settings.delete_user_form.are_you_sure_you_want_to_delete_your_account') }}</flux:heading>

                <flux:subheading>
                    {{ __('ui.settings.delete_user_form.once_your_account_is_deleted_all_of_its_resour') }}
                </flux:subheading>
            </div>

            <flux:input wire:model="password" :label="__('ui.auth.confirm_password.password')" type="password" viewable />

            <div class="flex justify-end space-x-2 rtl:space-x-reverse">
                <flux:modal.close>
                    <flux:button variant="filled">{{ __('ui.actions.cancel') }}</flux:button>
                </flux:modal.close>

                <flux:button variant="danger" type="submit">{{ __('ui.settings.delete_user_form.delete_account') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</section>
