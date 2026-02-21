import './bootstrap';
import './lucideicons';
import './chart';
import './alpine';
import './theme';

// vue js
import { createApp } from 'vue';
import router from './router';

// Import Vue Components
import ChatApp from './vue/Chat/App.vue';
import CommentLayout from './vue/Bleeps/comments/CommentLayout.vue';

// Import Lucide Vue Icon Component
import lucideicons from './vue/LucideIcons.vue';


// Lucide Vue Icon
const app = createApp(lucideicons);
app.component('LucideIcon', lucideicons);


// Chat App
if (document.getElementById('chat-app')) {
    const app = createApp(ChatApp);
    app.use(router);
    app.mount('#chat-app');
}


// Comments Layout
const mountCommentLayout = (containerId) => {
    const container = document.getElementById(containerId);
    if (!container) return null;

    const bleepIdAttr = container.getAttribute('data-bleep-id');
    const bleepDataStr = container.getAttribute('data-bleep');
    const modeAttr = container.getAttribute('data-mode');
    const authuserStr = container.getAttribute('data-auth-user');
    const userAvatarStr = container.getAttribute('data-user-avatar');
    const isAuthStr = container.getAttribute('data-is-authenticated');
    const isAnon = container.getAttribute('data-anonymous');

    let bleep = {};
    try {
        const parsedBleep = JSON.parse(bleepDataStr);
        bleep = Array.isArray(parsedBleep) ? {} : parsedBleep;
    } catch (error) {
        bleep = {};
        console.warn('Failed to parse bleep data:', error);
    }

    let authUser = {};
    try {
        const parsedAuthUser = JSON.parse(authuserStr);
        authUser = Array.isArray(parsedAuthUser) ? {} : parsedAuthUser;
    } catch (error) {
        authUser = {};
        console.warn('Failed to parse auth user data:', error);
    }

    // If no bleep data but we have bleepId, create minimal bleep object
    if ((!bleep || !bleep.id) && bleepIdAttr) {
        bleep = { id: bleepIdAttr, user: { id: null, username: null } };
    }

    const app = createApp(CommentLayout, {
        bleepId: bleepIdAttr,
        bleep,
        mode: modeAttr,
        authUser,
        userAvatar: userAvatarStr,
        isAuthenticated: isAuthStr,
        isAnonymousEnabled: isAnon,
    });

    // console.log('Mounting CommentLayout with props:', {
    //     bleepId: bleepIdAttr,
    //     bleep,
    //     mode: modeAttr,
    //     authUser,
    //     userAvatar: userAvatarStr,
    //     isAuthenticated: isAuthStr,
    //     isAnonymousEnabled: isAnon,
    // });

    app.mount(container);
    return app;
};

document.addEventListener('DOMContentLoaded', () => {

    // Post page — mount immediately as usual
    if (document.getElementById('comments-container-layout')) {
        mountCommentLayout('comments-container-layout');
    }

    // Modal — same mountCommentLayout function, just triggered on click instead of page load
    let modalApp = null;

    window.addEventListener('open-comments', async (e) => {
        const bleepId = String(e.detail.bleepId ?? '').trim();
        if (!bleepId) return;

        const container = document.getElementById('comments-container-layout-modal');
        if (!container) return;

        if (modalApp) {
            modalApp.unmount();
            modalApp = null;
            container.innerHTML = '';
        }

        // Fetch fresh bleep data before mounting
        let bleep = { id: bleepId, user: { id: null, username: null } };
        try {
            const res = await fetch(`/bleeps/${bleepId}/data`, {
                headers: { 'Accept': 'application/json' }
            });
            if (res.ok) bleep = await res.json();
        } catch {
            console.warn('Bleep fetch failed, using fallback');
        }

        // Set both attributes before mounting
        container.setAttribute('data-bleep-id', bleepId);
        container.setAttribute('data-bleep', JSON.stringify(bleep));

        modalApp = mountCommentLayout('comments-container-layout-modal');
    });

    window.addEventListener('close-comments', () => {
        if (modalApp) {
            modalApp.unmount();
            modalApp = null;
            const container = document.getElementById('comments-container-layout-modal');
            if (container) container.innerHTML = '';
        }
    });
});
