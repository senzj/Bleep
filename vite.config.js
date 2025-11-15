import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/js/init.js',
                'resources/css/app.css',
                'resources/css/profile-crop.css',
                'resources/js/app.js',
                'resources/js/admin/dashboard.js',
                'resources/js/admin/devices.js',
                'resources/js/admin/reports.js',
                'resources/js/admin/users.js',
                'resources/js/auth/register.js',
                'resources/js/bleep/modals/mediamodal.js',
                'resources/js/bleep/modals/posts/edit.js',
                'resources/js/bleep/modals/posts/reports.js',
                'resources/js/bleep/posts/comment.js',
                'resources/js/bleep/posts/infinitescroll.js',
                'resources/js/bleep/posts/like.js',
                'resources/js/bleep/posts/media.js',
                'resources/js/bleep/posts/post.js',
                'resources/js/bleep/posts/repost.js',
                'resources/js/bleep/posts/share.js',
                'resources/js/bleep/users/follow.js',
                'resources/js/bleep/users/profile-lazyload.js',
                'resources/js/bleep/users/profile.js',
                'resources/js/post/lazyload.js',
                'resources/js/profile/profile-crop.js',
                'resources/js/settings/password.js',
                'resources/js/settings/device.js',
            ],
            refresh: true,
        }),
        tailwindcss(),
    ],

    build: {
        chunkSizeWarningLimit: 1600
    }
});
