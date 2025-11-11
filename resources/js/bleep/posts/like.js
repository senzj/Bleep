/**
 * Likes Functionality
 */
// Use event delegation so forms added later also work
document.addEventListener('submit', async (e) => {
    const form = e.target.closest('.like-form');
    if (!form) return;

    e.preventDefault();

    const button = form.querySelector('.like-btn');
    const bleepId = button?.dataset.bleepId;
    const csrfToken = form.querySelector('input[name="_token"]')?.value
        || document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

    if (!bleepId || !csrfToken) return;

    try {
        const response = await fetch(`/bleeps/${bleepId}/like`, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': csrfToken,
            'X-Requested-With': 'XMLHttpRequest',
            'Content-Type': 'application/json',
            'Accept': 'application/json',
        },
        body: JSON.stringify({})
        });

        if (response.status === 401) {
        window.location.href = '/login';
        return;
        }
        if (!response.ok) {
        // optional: console.warn(await response.text());
        return;
        }

        const data = await response.json();
        if (!data?.success) return;

        // Toggle color
        button.classList.toggle('text-red-600');

        // Refresh count
        const countResponse = await fetch(`/bleeps/${bleepId}/likes-count`, {
        headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
        });
        const countData = await countResponse.json();
        const count = parseInt(countData.count ?? 0, 10);

        const mobileElem = form.querySelector('.like-count');
        const desktopElem = form.querySelector('.like-text');

        if (mobileElem) mobileElem.textContent = String(count);
        if (desktopElem) {
        const suffix = count === 1
            ? (button.classList.contains('text-red-600') ? 'Liked' : 'Like')
            : 'Likes';
        desktopElem.textContent = `${count} ${suffix}`;
        }
    } catch (error) {
        console.error('Error liking bleep:', error);
    }
});
