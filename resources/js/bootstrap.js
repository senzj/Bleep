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
	window.Echo = new Echo({
		broadcaster: 'reverb',
		key: reverbKey,
		wsHost: import.meta.env.VITE_REVERB_HOST || window.location.hostname,
		wsPort: Number(import.meta.env.VITE_REVERB_PORT || 80),
		wssPort: Number(import.meta.env.VITE_REVERB_PORT || 443),
		forceTLS: (import.meta.env.VITE_REVERB_SCHEME || 'https') === 'https',
		enabledTransports: ['ws', 'wss'],
		auth: {
			headers: {
				'X-CSRF-TOKEN': csrfToken || '',
			},
		},
	});
}
