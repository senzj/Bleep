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
});
