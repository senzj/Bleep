/**
 * Likes Functionality
 */
document.querySelectorAll('.like-form').forEach(form => {
    form.addEventListener('submit', async (e) => {
        e.preventDefault();

        const button = form.querySelector('.like-btn');
        const bleepId = button.dataset.bleepId;
        const csrfToken = form.querySelector('input[name="_token"]')?.value;

        if (!csrfToken) {
            console.error('CSRF token not found');
            return;
        }

        try {
            const response = await fetch(`/bleeps/${bleepId}/like`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrfToken,
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
                const errorData = await response.text();
                console.error('Response error:', errorData);
                return;
            }

            const data = await response.json();

            if (data.success) {
                // Toggle the red color
                button.classList.toggle('text-red-600');

                // Fetch updated like count
                const countResponse = await fetch(`/bleeps/${bleepId}/likes-count`);
                const countData = await countResponse.json();

                // Update mobile-only number and desktop text separately
                const mobileElem = form.querySelector('.like-count'); // mobile: show only number
                const desktopElem = form.querySelector('.like-text'); // desktop: show "N Like/Liked/Likes"
                const count = parseInt(countData.count ?? 0, 10);

                if (mobileElem) {
                    mobileElem.textContent = String(count);
                }

                if (desktopElem) {
                    const suffix = count === 1
                        ? (button.classList.contains('text-red-600') ? 'Liked' : 'Like')
                        : 'Likes';
                    desktopElem.textContent = `${count} ${suffix}`;
                }
            }
        } catch (error) {
            console.error('Error:', error);
        }
    });
});
