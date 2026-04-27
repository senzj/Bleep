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

// Custom directive to render lucide icons in Vue components
const lucideDirective = {
	mounted(el) {
		if (window.lucide && window.lucide.replace) {
			window.lucide.replace({ nodes: el.querySelectorAll('[data-lucide]') });
		}
	},
	updated(el) {
		if (window.lucide && window.lucide.replace) {
			window.lucide.replace({ nodes: el.querySelectorAll('[data-lucide]') });
		}
	},
};

// Lucide Vue Icon
const app = createApp(lucideicons);
app.component('LucideIcon', lucideicons);
app.directive('lucide', lucideDirective);

// Chat App
if (document.getElementById('chat-app')) {
	const app = createApp(ChatApp);
	app.directive('lucide', lucideDirective);
	app.mount('#chat-app');
}


// Comments Layout
const mountCommentLayout = (containerId, initialPayload = null) => {
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

    // If no bleep data but we have a bleep ID or anonymous mode is enabled, create a fallback bleep object
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
        initialComments: initialPayload?.comments || [],
        initialCurrentPage: Number(initialPayload?.currentPage || 1),
        initialHasMore: Boolean(initialPayload?.hasMore),
    });

	app.directive('lucide', lucideDirective);

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

function setModalLoadingCurtain(visible, title = 'Loading comments...') {
    const curtain = document.getElementById('comments-modal-loading-curtain');
    const titleEl = document.getElementById('comments-modal-loading-title');
    if (!curtain) return;

    curtain.classList.toggle('hidden', !visible);
    if (titleEl) {
        titleEl.textContent = title;
    }
}

document.addEventListener('DOMContentLoaded', () => {

    // Post page — mount immediately as usual
    if (document.getElementById('comments-container-layout')) {
        mountCommentLayout('comments-container-layout');
    }

    // Modal — same mountCommentLayout function, just triggered on click instead of page load
    let modalApp = null;
    let modalIsLoading = false;
    let activeModalBleepId = null;
    let modalRequestSeq = 0;
    let modalAbortController = null;

    window.addEventListener('open-comments', async (e) => {
        const bleepId = String(e.detail.bleepId ?? '').trim();
        if (!bleepId) return;

        // Anti-spam: if the same modal is already open or loading, ignore repeated clicks.
        if ((modalApp || modalIsLoading) && String(activeModalBleepId) === bleepId) {
            return;
        }

        const container = document.getElementById('comments-container-layout-modal');
        if (!container) return;

        // Keep current modal content in place and cover with loading curtain.
        setModalLoadingCurtain(true);

        const requestId = ++modalRequestSeq;
        modalIsLoading = true;
        activeModalBleepId = bleepId;

        if (modalAbortController) {
            modalAbortController.abort();
        }
        modalAbortController = new AbortController();
        const { signal } = modalAbortController;

        if (modalApp) {
            modalApp.unmount();
            modalApp = null;
        }

        // Fetch bleep + first comments page in parallel so modal renders with real data immediately.
        let bleep = { id: bleepId, user: { id: null, username: null } };
        let initialComments = [];
        let initialCurrentPage = 1;
        let initialHasMore = false;

        try {
            const [bleepRes, commentsRes] = await Promise.allSettled([
                fetch(`/bleeps/${bleepId}/data`, {
                    headers: { 'Accept': 'application/json' },
                    signal,
                }),
                fetch(`/bleeps/comments/${bleepId}/comments?page=1`, {
                    headers: { 'Accept': 'application/json' },
                    signal,
                })
            ]);

            if (requestId !== modalRequestSeq) {
                return;
            }

            if (bleepRes.status === 'fulfilled' && bleepRes.value.ok) {
                bleep = await bleepRes.value.json();
            }

            if (commentsRes.status === 'fulfilled' && commentsRes.value.ok) {
                const commentsJson = await commentsRes.value.json();
                initialComments = Array.isArray(commentsJson.comments) ? commentsJson.comments : [];
                initialCurrentPage = Number(commentsJson.current_page || 1);
                initialHasMore = Boolean(commentsJson.has_more);
            }

            // Set both attributes before mounting
            container.setAttribute('data-bleep-id', bleepId);
            container.setAttribute('data-bleep', JSON.stringify(bleep));

            if (requestId !== modalRequestSeq) {
                return;
            }

            modalApp = mountCommentLayout('comments-container-layout-modal', {
                comments: initialComments,
                currentPage: initialCurrentPage,
                hasMore: initialHasMore,
            });
            setModalLoadingCurtain(false);
        } catch (error) {
            if (error?.name === 'AbortError') {
                return;
            }
            console.warn('Comments modal prefetch failed, using fallback payload.');

            container.setAttribute('data-bleep-id', bleepId);
            container.setAttribute('data-bleep', JSON.stringify(bleep));

            if (requestId !== modalRequestSeq) {
                return;
            }

            modalApp = mountCommentLayout('comments-container-layout-modal', {
                comments: initialComments,
                currentPage: initialCurrentPage,
                hasMore: initialHasMore,
            });
            setModalLoadingCurtain(false);
        } finally {
            if (requestId === modalRequestSeq) {
                modalIsLoading = false;
                modalAbortController = null;
            }
        }
    });

    window.addEventListener('close-comments', () => {
        modalRequestSeq += 1;
        modalIsLoading = false;
        activeModalBleepId = null;
        if (modalAbortController) {
            modalAbortController.abort();
            modalAbortController = null;
        }

        if (modalApp) {
            modalApp.unmount();
            modalApp = null;
        }

        setModalLoadingCurtain(false);
    });
});
