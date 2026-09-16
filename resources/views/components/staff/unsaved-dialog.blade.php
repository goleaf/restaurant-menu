@props([])

<flux:modal name="staff-workspace-unsaved" class="w-full max-w-md" x-on:close="pendingNavigation = null">
    <div class="space-y-6">
        <div>
            <flux:heading size="lg">{{ __('staff.workspace.unsaved_title') }}</flux:heading>
            <flux:text class="mt-2">{{ __('staff.workspace.unsaved_description') }}</flux:text>
        </div>
        <div class="flex flex-wrap justify-end gap-3">
            <flux:button type="button" @click="cancelNavigation">{{ __('staff.workspace.keep_editing') }}</flux:button>
            <flux:button type="button" variant="primary" color="red" @click="discardAndNavigate" class="rm-action-danger">{{ __('staff.workspace.discard_continue') }}</flux:button>
        </div>
    </div>
</flux:modal>
