# Current version baseline

Reverified on 2026-09-14: PHP 8.5.8; Laravel 13.26.1; Livewire 4.4.1; Flux UI Free 2.17.0; Tailwind CSS and `@tailwindcss/vite` 4.3.3; Laravel Vite plugin 3.2.0; Vite 8.2.2; Fortify 1.38.0; Pest 4.7.8 / PHPUnit 12.5.33; Pint 1.30.5; Larastan 3.10.0; Boost 2.5.5.

Composer requires PHP `>=8.5.0 <8.6.0`, Laravel `^13.0` and Livewire `^4.4`. Package locks are authoritative; stable Pest 4 remains intentional even though Pest 5 is a newer incompatible major test-style migration.

The repository-wide repair restored the prior lock files. All 178 Composer versions match the installed graph. A clean disposable install succeeded for Composer and npm with lifecycle scripts disabled; the workspace npm tree was aligned with the lock using `npm ci --ignore-scripts`, followed by a successful Vite production build. No dependency constraint or major version was changed.
