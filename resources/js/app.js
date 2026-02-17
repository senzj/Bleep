import './bootstrap';

import './lucideicons';

import './chart';

import './alpine';

// theme toggle
import './theme';

// vue js
import { createApp } from 'vue';
import App from './vue/App/Message.vue';

if (document.getElementById('vue-message-app')) {
    createApp(App).mount('#vue-message-app');
}
