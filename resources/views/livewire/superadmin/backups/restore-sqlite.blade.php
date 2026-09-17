<div x-data="restoreUpload" x-on:livewire-upload-start="startUpload" x-on:livewire-upload-finish="finishUpload" x-on:livewire-upload-error="failUpload" x-on:livewire-upload-cancel="cancelUpload" x-on:online.window="goOnline" x-on:offline.window="goOffline">
    <section class="mx-auto flex w-full max-w-3xl flex-col gap-6">
        <header class="flex flex-col gap-2">
            <p class="text-sm font-medium text-red-700 dark:text-red-300">{{ __('ui.superadmin.backup_restore.critical_operation') }}</p>
            <h1 class="text-2xl font-semibold text-zinc-950 dark:text-white">{{ __('ui.superadmin.backup_restore.title') }}</h1>
            <p class="max-w-2xl text-sm leading-6 text-zinc-600 dark:text-zinc-300">
                {{ __('ui.superadmin.backup_restore.description') }}
            </p>
        </header>

        <flux:callout
            variant="danger"
            :heading="__('ui.superadmin.backup_restore.warning_title')"
         icon="x-circle" role="status" class="callout-contrast content-safe">
            <flux:callout.text>
                {{ __('ui.superadmin.backup_restore.warning') }}
            </flux:callout.text>
        </flux:callout>

        <form
            wire:submit="preview"
            class="grid gap-5 rounded-card border border-border-subtle bg-surface p-5 shadow-card sm:p-6"
        >

            <flux:file-upload wire:model="upload.backup" accept=".sqlite,.sqlite3,.db,application/vnd.sqlite3" :label="__('ui.superadmin.backup_restore.choose_file')">
                <flux:file-upload.dropzone :heading="__('ui.superadmin.backup_restore.choose_file')" :text="__('ui.superadmin.backup_restore.file_help', ['size' => $maximumSizeMegabytes])" />
            </flux:file-upload>
            <flux:text>{{ __('ui.superadmin.backup_restore.schema_help') }}</flux:text>
            <flux:error name="upload.backup" />

            <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                <flux:button :href="route('superadmin.dashboard')" icon="arrow-left" wire:navigate>
                    {{ __('ui.actions.cancel') }}
                </flux:button>
                <flux:button type="submit" wire:loading.attr="disabled" wire:target="preview,upload.backup" wire:offline.attr="disabled" x-bind:disabled="uploading || offline || submitting" variant="primary" color="red" icon="arrow-path" class="rm-action-danger">
                    {{ __('ui.superadmin.backup_restore.preview') }}
                </flux:button>
            </div>
        </form>
        @if ($candidate !== null)
            <flux:text class="content-safe">{{ $candidate['name'] }}</flux:text>
            <flux:text class="content-safe">{{ $restoreReason }}</flux:text>
            <flux:callout class="content-safe" variant="warning" :heading="__('ui.superadmin.backup_restore.ready')">
                <flux:callout.text>{{ __('ui.superadmin.backup_restore.verified_file', ['bytes' => $candidate['bytes'], 'sha256' => $candidate['sha256']]) }}</flux:callout.text>
            </flux:callout>
            <form method="POST" action="{{ route('superadmin.backups.sqlite.restore.store') }}" x-on:submit="submit($event)">
                @csrf
                <input type="hidden" name="grant" value="{{ $grant }}">
                <flux:button type="submit" wire:loading.attr="disabled" wire:target="preview,upload.backup" wire:offline.attr="disabled" x-bind:disabled="uploading || offline || submitting" variant="primary" color="red" icon="arrow-path" class="rm-action-danger rm-backup-restore-submit">
                    {{ __('ui.superadmin.backup_restore.submit') }}
                </flux:button>
            </form>
        @endif
    </section>
</div>
