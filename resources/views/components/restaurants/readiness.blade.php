@props(['readiness'])
<section class="rm-restaurant-center__readiness" aria-label="{{ __('dashboard.control.readiness.title') }}">
    <flux:callout :heading="$readiness['ordering']['label']" :text="$readiness['ordering']['detail']" />
    <x-dashboard.readiness :readiness="$readiness" />
</section>
