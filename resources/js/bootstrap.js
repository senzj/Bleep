import axios from 'axios';
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
if (csrfToken) {
	window.axios.defaults.headers.common['X-CSRF-TOKEN'] = csrfToken;
}

window.Pusher = Pusher;

const reverbKey = import.meta.env.VITE_REVERB_APP_KEY;

if (reverbKey) {
	// Reverb is often configured with 0.0.0.0 so the server listens on every
	// interface. A browser cannot connect to 0.0.0.0, so resolve wildcard and
	// loopback hosts to the hostname the page was actually served from.
	const envHost = String(import.meta.env.VITE_REVERB_HOST || '')
		.split('#')[0]
		.trim()
		.replace(/^https?:\/\//i, '')
		.replace(/\/.*$/, '')
		.replace(/:\d+$/, '');

	const pageHost = window.location.hostname;
	const wildcardHosts = ['', '0.0.0.0', '127.0.0.1', '::', '[::]', 'localhost'];
	const wsHost = wildcardHosts.includes(envHost.toLowerCase())
		? (pageHost || 'localhost')
		: envHost;

	const pageIsHttps = window.location.protocol === 'https:';
	const envScheme = String(import.meta.env.VITE_REVERB_SCHEME || '')
		.split('#')[0]
		.trim()
		.toLowerCase();
	const forceTLS = pageIsHttps || envScheme === 'https';

	// Only trust an explicitly configured port. Otherwise use the standard
	// secure/non-secure port instead of hard-coding 80 (which produced the
	// ws://0.0.0.0:8080 failure).
	const configuredPort = Number(
		String(import.meta.env.VITE_REVERB_PORT || '').split('#')[0].trim()
	);
	const port = Number.isFinite(configuredPort) && configuredPort > 0
		? configuredPort
		: (forceTLS ? 443 : 8080);

	window.Echo = new Echo({
		broadcaster: 'reverb',
		key: reverbKey,
		wsHost,
		wsPort: port,
		wssPort: port,
		forceTLS,
		enabledTransports: forceTLS ? ['wss'] : ['ws'],
		auth: {
			headers: {
				'X-CSRF-TOKEN': csrfToken || '',
			},
		},
	});
}
