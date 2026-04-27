<script setup>
import PostLayout from './layout/PostLayout.vue';
import ModalLayout from './layout/ModalLayout.vue';

const props = defineProps({
    bleepId: {
        type: [String, Number],
        required: true,
    },
    bleep: {
        type: Object,
        required: true,
        validator: (obj) => {
            return obj && typeof obj === 'object' && !Array.isArray(obj);
        }
    },
    mode: {
        type: String,
        enum: ['post', 'modal'],
        default: 'post',
    },
    authUser: {
        type: Object,
        default: null,
    },
    userAvatar: {
        type: String,
        default: '/images/avatar/default.jpg',
    },
    isAuthenticated: {
        type: String,
        default: "false",
    },
    isAnonymousEnabled: {
        type: [Boolean, String],
        default: false,
    },
    initialComments: {
        type: Array,
        default: () => [],
    },
    initialCurrentPage: {
        type: Number,
        default: 1,
    },
    initialHasMore: {
        type: Boolean,
        default: false,
    },

});

const emit = defineEmits(['close']);

</script>

<template>
    <PostLayout
        v-if="mode === 'post'"
        :bleep-id="bleepId"
        :bleep="bleep"
        :auth-user="authUser"
        :user-avatar="userAvatar"
        :is-authenticated="isAuthenticated"
        :is-anonymous-enabled="isAnonymousEnabled"
        @close="emit('close')"
    />

    <ModalLayout
        v-else-if="mode === 'modal'"
        :bleep-id="bleepId"
        :bleep="bleep"
        :auth-user="authUser"
        :user-avatar="userAvatar"
        :is-authenticated="isAuthenticated"
        :is-anonymous-enabled="isAnonymousEnabled"
        :initial-comments="initialComments"
        :initial-current-page="initialCurrentPage"
        :initial-has-more="initialHasMore"
        @close="emit('close')"
    />
</template>

<style scoped>
</style>
