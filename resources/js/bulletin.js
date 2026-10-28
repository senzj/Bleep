import { createApp } from 'vue';
import BulletinBoard from './vue/Bulletin/BulletinBoard.vue';

const root = document.getElementById('bulletin-board');

if (root) {
    let initialPosts = [];

    try {
        const parsed = JSON.parse(root.dataset.posts ?? '[]');
        initialPosts = Array.isArray(parsed) ? parsed : [];
    } catch {
        initialPosts = [];
    }

    createApp(BulletinBoard, {
        initialPosts,
        nextCursor: root.dataset.nextCursor || null,
        hasMore: root.dataset.hasMore === '1',
        identity: root.dataset.identity ?? '',
        homeUrl: root.dataset.homeUrl || '/',
        endpoints: {
            posts: root.dataset.endpointPosts,
            store: root.dataset.endpointStore,
        },
    }).mount(root);
}
