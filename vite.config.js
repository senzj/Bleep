import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';

export default defineConfig({
    plugins: [
        vue(),
        laravel({
            input: [
                'resources/js/init.js',
                'resources/css/app.css',
                'resources/css/profile-crop.css',
                'resources/css/profile.css',
                'resources/js/app.js',
                'resources/js/admin/dashboard.js',
                'resources/js/admin/devices.js',
                'resources/js/admin/reports.js',
                'resources/js/admin/users.js',
                'resources/js/admin/visits.js',
                'resources/js/auth/register.js',
                'resources/js/bleep/modals/mediamodal.js',
                'resources/js/bleep/modals/posts/edit.js',
                'resources/js/bleep/modals/posts/reports.js',
                'resources/js/bleep/posts/comment/replies.js',
                'resources/js/bleep/posts/comment/likes.js',
                'resources/js/bleep/posts/media/audio.js',
                'resources/js/bleep/posts/media/video.js',
                'resources/js/bleep/posts/media/visibility.js',
                'resources/js/bleep/posts/comment.js',
                'resources/js/bleep/posts/infinitescroll.js',
                'resources/js/bleep/posts/like.js',
                'resources/js/bleep/posts/media.js',
                'resources/js/bleep/posts/nsfw.js',
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
                'resources/js/settings/preferences.js',
                'resources/js/settings/logs.js',
                'resources/js/social/people.js',
                'resources/js/ui/mobile.js',
            ],
            refresh: true,
        }),
        tailwindcss(),
    ],

    build: {
        chunkSizeWarningLimit: 1600
    }
});
