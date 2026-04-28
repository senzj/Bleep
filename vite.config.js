import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';

export default defineConfig({
    plugins: [
        tailwindcss(),
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
                'resources/js/auth/random-username-generator.js',
                'resources/js/bleep/comments/commentSheet.js',
                'resources/js/bleep/modals/mediamodal.js',
                'resources/js/bleep/modals/posts/edit.js',
                'resources/js/bleep/modals/posts/reports.js',
                'resources/js/bleep/posts/media/audio.js',
                'resources/js/bleep/posts/media/lazyload.js',
                'resources/js/bleep/posts/media/video.js',
                'resources/js/bleep/posts/media/visibility.js',
                'resources/js/bleep/posts/infinitescroll.js',
                'resources/js/bleep/posts/like.js',
                'resources/js/bleep/posts/media.js',
                'resources/js/bleep/posts/nsfw.js',
                'resources/js/bleep/posts/post.js',
                'resources/js/bleep/posts/repost.js',
                'resources/js/bleep/posts/send_notif.js',
                'resources/js/bleep/posts/share.js',
                'resources/js/bleep/users/follow-requests.js',
                'resources/js/bleep/users/follow.js',
                'resources/js/bleep/users/profile-lazyload.js',
                'resources/js/bleep/users/profile.js',
                'resources/js/post/lazyload.js',
                'resources/js/profile/profile-crop.js',
                'resources/js/settings/device.js',
                'resources/js/settings/logs.js',
                'resources/js/settings/password.js',
                'resources/js/settings/preference.js',
                'resources/js/social/blockuser.js',
                'resources/js/social/follow-relationships.js',
                'resources/js/social/people.js',
                'resources/js/ui/mobile.js',
            ],
            refresh: true,
        }),
    ],

    build: {
        chunkSizeWarningLimit: 1600
    }
});
