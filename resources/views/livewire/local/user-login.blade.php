<div>
    @if ($users !== null)
        <x-auth.local-user-directory :users="$users" />
    @endif
    <flux:error name="form.userId" />
</div>
