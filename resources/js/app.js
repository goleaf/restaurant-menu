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

// These listeners belong to the single document bootstrap, not page instances.
document.addEventListener('alpine:init', releaseApplicationRoots, { once: true });
document.addEventListener('livewire:navigating', event => event.detail.onSwap(releaseApplicationRoots));
document.addEventListener('livewire:navigated', () => {
    // Cached HTML can retain wire:offline styles and disabled attributes from before reconnecting.
    window.dispatchEvent(new Event(navigator.onLine ? 'online' : 'offline'));
});

// A response already received before disconnecting can replace offline attributes.
Livewire.interceptMessage(({ onSuccess }) => {
    onSuccess(({ onRender }) => {
        onRender(() => {
            if (!navigator.onLine) window.dispatchEvent(new Event('offline'));
        });
    });
});

Livewire.start();
