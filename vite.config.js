import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';
import { applicationStyles } from './resources/build/styles.js';

export default defineConfig({
    plugins: [
        applicationStyles(),
        laravel({
            input: [
                'resources/css/app.css',
                'resources/scss/app.scss',
                'resources/scss/team.scss',
                'resources/scss/availability.scss',
                'resources/scss/restaurant-center.scss',
                'resources/scss/qr-print.scss',
                'resources/scss/preparation.scss',
                'resources/scss/preparation-print.scss',
                'resources/js/app.js',
            ],
            refresh: true,

        }),
        tailwindcss(),
    ],
    server: {
        cors: true,
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
