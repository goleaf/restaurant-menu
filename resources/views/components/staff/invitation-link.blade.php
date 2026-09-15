@props(['value'])

<div x-data="invitationClipboard" x-id="['invitation-link']" data-invitation-clipboard class="space-y-3">
    <flux:field>
        <flux:label x-bind:for="$id('invitation-link')">{{ __('staff.link.label') }}</flux:label>
        <input x-ref="link" x-bind:id="$id('invitation-link')" data-invitation-link value="{{ $value }}" readonly autocomplete="off" spellcheck="false"
            class="min-h-11 w-full min-w-0 rounded-control border border-border-strong bg-surface px-3 py-2 text-sm text-text-primary focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand"
            @focus="$event.target.select()" />
        <flux:description>{{ __('staff.link.once') }}</flux:description>
    </flux:field>
    <div class="flex flex-wrap items-center gap-3">
        <flux:button type="button" @click="copy" x-bind:disabled="busy" data-copy-invitation>{{ __('ui.actions.copy_to_clipboard') }}</flux:button>
        <span x-cloak x-show="copied" role="status" class="text-sm text-success">{{ __('staff.link.copied') }}</span>
    </div>
    <p x-cloak x-show="failed" role="alert" class="text-sm text-warning">{{ __('staff.link.fallback') }}</p>
</div>
