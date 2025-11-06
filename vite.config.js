import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                'resources/js/bleep/modals/posts/edit.js',
                'resources/js/bleep/posts/comments.js',
                'resources/js/bleep/posts/likes.js',
                'resources/js/bleep/posts/posts.js',
            ],
            refresh: true,
        }),
        tailwindcss(),
    ],

    build: {
        chunkSizeWarningLimit: 1600
    }
});
