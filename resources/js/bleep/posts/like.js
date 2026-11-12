/**
 * Likes functionality with an instant optimistic UI.
 *
 * The click handler paints the new state synchronously and only then starts
 * the request. There is no disabled state and no await before painting, so the
 * heart and count react on the same frame as the tap.
 */

const canHover = window.matchMedia && window.matchMedia('(hover: hover)').matches;
const likesInFlight = new Set();
const LIKED_CLASS = 'text-red-600';

const likeButtonsFor = (bleepId) =>
    document.querySelectorAll(`.like-btn[data-bleep-id="${bleepId}"]`);

/**
 * A liked heart is filled and turns into a "broken" heart while hovered, so a
 * click clearly reads as "unlike". The icon is only re-rendered when the name
 * actually changes, keeping the optimistic click on the same frame.
 */
const syncLikeIcon = (button) => {
    const heart = button.querySelector('.heart-icon');
    if (!heart) return;

    const liked = button.classList.contains(LIKED_CLASS);
    const hovering = canHover && button.matches(':hover');
    const nextIcon = liked && hovering ? 'heart-crack' : 'heart';

    if (heart.getAttribute('data-lucide') === nextIcon) return;

    heart.setAttribute('data-lucide', nextIcon);
    window.createLucideIcons?.(button);
};

const paintLikeState = (bleepId, liked, count) => {
    likeButtonsFor(bleepId).forEach((button) => {
        button.classList.toggle(LIKED_CLASS, liked);
        button.setAttribute('aria-pressed', liked ? 'true' : 'false');

        syncLikeIcon(button);

        const mobile = button.querySelector('.like-count');
        if (mobile) mobile.textContent = String(count);

        const desktop = button.querySelector('.like-text');
        if (desktop) {
            const suffix = count === 1
                ? (liked ? 'Liked' : 'Like')
                : 'Likes';
            desktop.textContent = `${count} ${suffix}`;
        }
    });

    // A bleep can appear more than once (reposts); keep every copy in sync.
    document.querySelectorAll(`.like-count[data-bleep-id="${bleepId}"]`).forEach((el) => {
        el.textContent = String(count);
    });
    document.querySelectorAll(`.like-text[data-bleep-id="${bleepId}"]`).forEach((el) => {
        const suffix = count === 1 ? (liked ? 'Liked' : 'Like') : 'Likes';
        el.textContent = `${count} ${suffix}`;
    });
};

// Hover feedback for liked buttons (pointer devices only).
if (canHover) {
    document.addEventListener('pointerover', (e) => {
        const button = e.target.closest('.like-btn');
        if (!button) return;

        syncLikeIcon(button);
    }, true);

    document.addEventListener('pointerout', (e) => {
        const button = e.target.closest('.like-btn');
        if (!button) return;

        syncLikeIcon(button);
    }, true);
}

const toggleLike = (button) => {
    const bleepId = button?.dataset.bleepId;
    if (!bleepId) return;

    // Guests are sent to sign in instead of getting a fake optimistic like.
    if (document.body.dataset.authenticated !== '1') {
        window.location.href = '/login';
        return;
    }

    if (likesInFlight.has(bleepId)) return;

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    if (!csrfToken) return;

    const wasLiked = button.classList.contains(LIKED_CLASS);
    const countElem = button.querySelector('.like-count')
        || document.querySelector(`.like-count[data-bleep-id="${bleepId}"]`);
    const currentCount = parseInt(countElem?.textContent || '0', 10) || 0;

    const nextLiked = !wasLiked;
    const nextCount = Math.max(0, currentCount + (nextLiked ? 1 : -1));

    // Paint first, request second.
    likesInFlight.add(bleepId);
    paintLikeState(bleepId, nextLiked, nextCount);

    button.classList.add('like-animate');
    window.setTimeout(() => button.classList.remove('like-animate'), 650);

    fetch(`/bleeps/${bleepId}/like`, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': csrfToken,
            'X-Requested-With': 'XMLHttpRequest',
            'Content-Type': 'application/json',
            'Accept': 'application/json',
        },
        body: '{}',
        keepalive: true,
    })
        .then(async (response) => {
            if (response.status === 401) {
                window.location.href = '/login';
                return null;
            }

            if (!response.ok) throw new Error('Like request failed');

            return response.json();
        })
        .then((data) => {
            if (!data) return;

            // Server state wins once it arrives.
            const serverLiked = Boolean(data.liked);
            const serverCount = Number.isFinite(Number(data.count))
                ? Number(data.count)
                : nextCount;

            paintLikeState(bleepId, serverLiked, serverCount);
        })
        .catch(() => {
            paintLikeState(bleepId, wasLiked, currentCount);
        })
        .finally(() => {
            likesInFlight.delete(bleepId);
        });
};

// Immediate click handling (works for buttons inserted later too).
document.addEventListener('click', (e) => {
    const button = e.target.closest('.like-btn');
    if (!button) return;

    e.preventDefault();
    toggleLike(button);
}, true);

// Keyboard / no-JS fallback: still intercept the form submit.
document.addEventListener('submit', (e) => {
    const form = e.target.closest('.like-form');
    if (!form) return;

    e.preventDefault();
    toggleLike(form.querySelector('.like-btn'));
});
