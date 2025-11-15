// Post form anonymous toggle (shows icon only when checked)
const postToggle = document.getElementById('post-anonymous-toggle');
const postIndicator = document.getElementById('post-toggle-indicator');

if (postToggle && postIndicator) {
    const updatePostToggleUI = () => {
        if (postToggle.checked) {
            postIndicator.style.backgroundImage = 'none';
            postIndicator.style.backgroundColor = '#1f2937';
            postIndicator.innerHTML = `<i data-lucide="hat-glasses" class="w-4 h-4 text-white"></i>`;
            // also update small-screen label icon bg
            const anonIcon = document.getElementById('post-anonymous-icon');
            if (anonIcon) anonIcon.style.backgroundColor = '#1f2937';
            if (window.createLucideIcons) window.createLucideIcons();
        } else {
            postIndicator.innerHTML = '';
            postIndicator.style.backgroundColor = 'transparent';
            postIndicator.style.backgroundImage = 'none';
            const anonIcon = document.getElementById('post-anonymous-icon');
            if (anonIcon) anonIcon.style.backgroundColor = 'transparent';
        }
    };

    postToggle.addEventListener('change', updatePostToggleUI);
    updatePostToggleUI();
}

// NSFW toggle UI (mobile icon + indicator color)
const postNsfwToggle = document.getElementById('post-nsfw-toggle');
const postNsfwIndicator = document.getElementById('post-nsfw-toggle-indicator');
const postNsfwIcon = document.getElementById('post-nsfw-icon');

if (postNsfwToggle && postNsfwIndicator) {
    const updateNsfwToggleUI = () => {
        if (postNsfwToggle.checked) {
            postNsfwIndicator.style.backgroundImage = 'none';
            // purple-ish to match "secondary" toggle
            postNsfwIndicator.style.backgroundColor = '#7c3aed';
            postNsfwIndicator.innerHTML = `<i data-lucide="eye-off" class="w-4 h-4 text-white"></i>`;
            if (postNsfwIcon) postNsfwIcon.style.backgroundColor = '#7c3aed';
            if (window.createLucideIcons) window.createLucideIcons();
        } else {
            postNsfwIndicator.innerHTML = '';
            postNsfwIndicator.style.backgroundColor = 'transparent';
            postNsfwIndicator.style.backgroundImage = 'none';
            if (postNsfwIcon) postNsfwIcon.style.backgroundColor = 'transparent';
        }
    };

    postNsfwToggle.addEventListener('change', updateNsfwToggleUI);
    updateNsfwToggleUI();
}

document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('bleep-form');
    if (!form) return;

    const submitBtn = document.getElementById('post-submit-btn');
    const mediaBtn = document.getElementById('open-media-picker');

    const progressWrap = document.getElementById('upload-progress');
    const progressBar = document.getElementById('upload-progress-bar');
    const progressPercent = document.getElementById('upload-progress-percent');
    const statusText = document.getElementById('upload-status');

    form.addEventListener('submit', (e) => {
        // Only use AJAX + show loading indicator when there's media uploaded.
        const fileInputs = Array.from(form.querySelectorAll('input[type="file"]'));
        const hasFiles = fileInputs.some(input => input.files && input.files.length > 0);

        if (!hasFiles) {
        // No media -> let the form submit normally (no preventDefault)
        return;
        }

        // Media present -> handle via XHR and show progress UI
        e.preventDefault();
        if (form.dataset.uploading === '1') return;

        const formData = new FormData(form); // build before disabling inputs

        const xhr = new XMLHttpRequest();
        xhr.open('POST', form.action, true);
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');

        const tokenInput = form.querySelector('input[name="_token"]');
        if (tokenInput) xhr.setRequestHeader('X-CSRF-TOKEN', tokenInput.value);

        startUploadUI();

        xhr.upload.addEventListener('progress', (ev) => {
        if (!ev.lengthComputable) return;
        const pct = Math.min(99, Math.round((ev.loaded / ev.total) * 100));
        updateProgress(pct, 'Uploading media...');
        });

        xhr.onreadystatechange = () => {
        if (xhr.readyState !== 4) return;

        if (xhr.status >= 200 && xhr.status < 400) {
            updateProgress(100, 'Processing...');
            // Let server redirect complete by reloading
            setTimeout(() => window.location.reload(), 200);
        } else {
            failUploadUI();
        }
        };

        xhr.onerror = failUploadUI;
        xhr.send(formData);
    });

    function startUploadUI() {
        form.dataset.uploading = '1';
        progressWrap.classList.remove('hidden');
        updateProgress(0, 'Starting upload...');

        submitBtn?.setAttribute('disabled', 'true');
        mediaBtn?.setAttribute('disabled', 'true');

        // Prevent edits during upload (after FormData was built)
        form.querySelectorAll('input, textarea, select, button').forEach((el) => {
        if (el !== submitBtn && el !== mediaBtn) el.setAttribute('disabled', 'true');
        });
    }

    function updateProgress(val, text) {
        progressBar.value = val;
        progressPercent.textContent = `${val}%`;
        if (text) statusText.textContent = text;
    }

    function failUploadUI() {
        statusText.textContent = 'Upload failed. Please try again.';
        form.dataset.uploading = '0';

        submitBtn?.removeAttribute('disabled');
        mediaBtn?.removeAttribute('disabled');
        form.querySelectorAll('input, textarea, select, button').forEach((el) => {
        el.removeAttribute('disabled');
        });
    }

    // replace/insert toggle UI logic with the following:
    const anonIconBtn = document.getElementById('post-anonymous-icon');
    const anonCheckbox = document.getElementById('post-anonymous-toggle');
    const nsfwIconBtn = document.getElementById('post-nsfw-icon');
    const nsfwCheckbox = document.getElementById('post-nsfw-toggle');

    function setIconState(btn, checked, onClasses = ['bg-primary','text-white','shadow'], offClasses = ['bg-transparent']) {
        if (!btn) return;
        if (checked) {
            btn.classList.add(...onClasses);
            offClasses.forEach(c => btn.classList.remove(c));
            btn.setAttribute('aria-pressed', 'true');
            btn.setAttribute('aria-checked', 'true');
        } else {
            onClasses.forEach(c => btn.classList.remove(c));
            btn.classList.add(...offClasses);
            btn.setAttribute('aria-pressed', 'false');
            btn.setAttribute('aria-checked', 'false');
        }
    }

    if (anonIconBtn && anonCheckbox) {
        // label click will toggle checkbox automatically; react to changes only
        anonCheckbox.addEventListener('change', () => {
            setIconState(anonIconBtn, anonCheckbox.checked, ['bg-primary','text-white','shadow'], ['bg-transparent']);
        });
        setIconState(anonIconBtn, anonCheckbox.checked, ['bg-primary','text-white','shadow'], ['bg-transparent']);
    }

    if (nsfwIconBtn && nsfwCheckbox) {
        // label click will toggle checkbox automatically; react to changes only
        nsfwCheckbox.addEventListener('change', () => {
            setIconState(nsfwIconBtn, nsfwCheckbox.checked, ['bg-secondary','text-white','shadow'], ['bg-transparent']);
        });
        setIconState(nsfwIconBtn, nsfwCheckbox.checked, ['bg-secondary','text-white','shadow'], ['bg-transparent']);
    }

    // Toggle icons highlight logic (icon is label/indicator, input is the control)
    const anonIcon = document.getElementById('post-anonymous-icon');
    const anonToggle = document.getElementById('post-anonymous-toggle');

    const nsfwIcon = document.getElementById('post-nsfw-icon');
    const nsfwToggle = document.getElementById('post-nsfw-toggle');

    function setIconState(iconEl, checked, onClass = 'bg-primary', offClass = 'bg-transparent') {
        if (!iconEl) return;
        iconEl.classList.remove(onClass, offClass, 'text-white', 'shadow');
        if (checked) {
            iconEl.classList.add(onClass, 'text-white', 'shadow');
            iconEl.setAttribute('aria-pressed', 'true');
        } else {
            iconEl.classList.add(offClass);
            iconEl.setAttribute('aria-pressed', 'false');
        }
    }

    if (anonToggle && anonIcon) {
        // init
        setIconState(anonIcon, anonToggle.checked, 'bg-primary');
        // update when user toggles control
        anonToggle.addEventListener('change', () => setIconState(anonIcon, anonToggle.checked, 'bg-primary'));
        // clicking icon (label) will toggle the input automatically via `for`
    }

    if (nsfwToggle && nsfwIcon) {
        setIconState(nsfwIcon, nsfwToggle.checked, 'bg-secondary');
        nsfwToggle.addEventListener('change', () => setIconState(nsfwIcon, nsfwToggle.checked, 'bg-secondary'));
    }
});
