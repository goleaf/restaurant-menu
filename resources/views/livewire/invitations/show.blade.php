<div>
    @if ($isPending)
        @include('invitations.show')
    @else
        @include('invitations.status')
    @endif
</div>
