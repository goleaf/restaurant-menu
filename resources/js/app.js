import { Livewire, Alpine } from '../../vendor/livewire/livewire/dist/livewire.esm.js';
import { registerAlpineComponents } from './alpine/register.js';

registerAlpineComponents(Alpine);

function releaseApplicationRoots() {
    document.querySelectorAll('[data-page-module]').forEach(root => {
        root.removeAttribute('x-ignore');
        root.removeAttribute('inert');
    });
    document.querySelectorAll('[data-page-module-status]').forEach(status => { status.hidden = true; });
}

// These two listeners belong to the single document bootstrap, not page instances.
document.addEventListener('alpine:init', releaseApplicationRoots, { once: true });
document.addEventListener('livewire:navigating', event => event.detail.onSwap(releaseApplicationRoots));

Livewire.start();
