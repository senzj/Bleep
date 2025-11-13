(() => {
    const DEFAULT = 'lofi';
    let t = localStorage.getItem('theme') || DEFAULT;
    if (t === 'system') {
        try {
            t = matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
        } catch (e) {}
    }
    document.documentElement.setAttribute('data-theme', t);
})();
