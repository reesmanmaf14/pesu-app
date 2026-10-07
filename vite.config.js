import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css',
    'resources/js/app.js',
    'resources/css/aac.css',
    'resources/js/aac/app.js',
    'resources/js/aac/recordings.js',],
            refresh: true,
        }),
    ],
});
