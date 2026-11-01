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
            identity: root.dataset.endpointIdentity,
        },
        nameChangesLeft: Number(root.dataset.nameChangesLeft || 0),
        nameCooldown: Number(root.dataset.nameCooldown || 0),
    }).mount(root);
}
