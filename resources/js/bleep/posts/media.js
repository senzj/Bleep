document.addEventListener('DOMContentLoaded', () => {
    const input = document.getElementById('bleep-media-input');
    const openBtn = document.getElementById('open-media-picker');
    const preview = document.getElementById('bleep-media-preview');
    const countBadge = document.getElementById('bleep-media-count');
    if (!input || !openBtn || !preview) return;

    const maxFiles = 4;
    // Keep an accumulator so multiple selections add up to maxFiles
    let dt = new DataTransfer();

    const updateCount = () => {
        const n = dt.files.length;
        if (!countBadge) return;
        if (n > 0) {
        countBadge.textContent = `${n}/${maxFiles}`;
        countBadge.classList.remove('hidden');
        } else {
        countBadge.classList.add('hidden');
        }
        // disable add button when max reached
        openBtn.disabled = n >= maxFiles;
    };

    const render = () => {
        preview.innerHTML = '';
        [...dt.files].forEach((file, idx) => {
        const wrapper = document.createElement('div');
        wrapper.className = 'relative rounded-lg overflow-hidden border border-base-300';

        let mediaEl;
        if (file.type.startsWith('image/')) {
            mediaEl = document.createElement('img');
            mediaEl.className = 'w-full h-28 object-cover';
        } else {
            mediaEl = document.createElement('video');
            mediaEl.className = 'w-full h-28 object-cover';
            mediaEl.muted = true;
            mediaEl.controls = true;
        }
        mediaEl.src = URL.createObjectURL(file);

        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'btn btn-xs btn-circle absolute top-1 right-1';
        btn.innerHTML = '<i data-lucide="x" class="w-3 h-3"></i>';
        btn.addEventListener('click', () => {
            const next = new DataTransfer();
            [...dt.files].forEach((f, i) => { if (i !== idx) next.items.add(f); });
            dt = next;
            input.files = dt.files;
            render();
            updateCount();
            if (window.lucide) window.lucide.createIcons();
        });

        wrapper.appendChild(mediaEl);
        wrapper.appendChild(btn);
        preview.appendChild(wrapper);
        });
        if (window.lucide) window.lucide.createIcons();
    };

    openBtn.addEventListener('click', () => {
        // clear input so picking same file again still triggers change
        input.value = '';
        input.click();
    });

    input.addEventListener('change', () => {
        if (!input.files || input.files.length === 0) return;

        const currently = [...dt.files];
        const incoming = [...input.files];

        // add while respecting maxFiles
        for (const f of incoming) {
        if (currently.length + dt.items.length >= maxFiles) break;
        // Prevent duplicates by name+size+lastModified
        const dup = [...dt.files].some(
            (x) => x.name === f.name && x.size === f.size && x.lastModified === f.lastModified
        );
        if (!dup) dt.items.add(f);
        }

        input.files = dt.files;
        render();
        updateCount();
    });

    // initial state
    updateCount();
});
