import './bootstrap';

import './lucideicons';

import './chart';

import './alpine';

// theme toggle
import './theme';

// vue js
import { createApp } from 'vue';
import App from './vue/App/Chat.vue';

if (document.getElementById('chat-app')) {
    createApp(App).mount('#chat-app');
}
