<flux:field class="rm-image-upload-field">
    <input
        type="file"
        accept="{{ $acceptedMimeTypes }}"
        aria-label="{{ $ariaLabel }}"
        {{ $controlAttributes->class('rm-image-upload') }}
    >
    <flux:description :id="$helpId" class="text-text-muted!">{{ $helpText }}</flux:description>
    @if ($fieldName)
        <flux:error :name="$fieldName" :id="$errorId" :deep="false" class="text-danger!" />
    @endif
</flux:field>
