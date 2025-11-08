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
                'resources/js/bleep/posts/comment.js',
                'resources/js/bleep/posts/like.js',
                'resources/js/bleep/posts/post.js',
                'resources/js/bleep/posts/repost.js',
                'resources/js/bleep/posts/share.js',
            ],
            refresh: true,
        }),
        tailwindcss(),
    ],

    build: {
        chunkSizeWarningLimit: 1600
    }
});
