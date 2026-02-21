import { createRouter, createWebHistory } from 'vue-router';
import Chat from '../vue/Chat/App.vue';

const routes = [
    {
        path: '/chat',
        name: 'Chat',
        component: Chat,
    },
];

export default createRouter({
    history: createWebHistory(),
    routes,
});
