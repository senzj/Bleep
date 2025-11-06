 // Post form anonymous toggle (shows icon only when checked)
const postToggle = document.getElementById('post-anonymous-toggle');
const postIndicator = document.getElementById('post-toggle-indicator');

if (postToggle && postIndicator) {
    const updatePostToggleUI = () => {
        if (postToggle.checked) {
            postIndicator.style.backgroundImage = 'none';
            postIndicator.style.backgroundColor = '#1f2937';
            postIndicator.innerHTML = `<i data-lucide="hat-glasses" class="w-4 h-4 text-white"></i>`;
            if (window.createLucideIcons) window.createLucideIcons();
        } else {
            postIndicator.innerHTML = '';
            postIndicator.style.backgroundColor = 'transparent';
            postIndicator.style.backgroundImage = 'none';
        }
    };

    postToggle.addEventListener('change', updatePostToggleUI);
    updatePostToggleUI();
}
