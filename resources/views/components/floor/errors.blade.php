@if ($errors->any())
    <div role="alert" tabindex="-1" class="rm-floor__errors">
        @forelse ($errors->all() as $error)
            <p>{{ $error }}</p>
        @empty
        @endforelse
    </div>
@endif
