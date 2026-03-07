<script setup>
import MessageMedia from './MessageMedia.vue';
import LucideIcons from '../../../LucideIcons.vue';

defineProps({
    message: {
        type: Object,
        required: true,
    },
});
</script>

<template>
    <!-- Deleted message placeholder -->
    <div v-if="message.is_deleted" class="flex justify-center text-xs text-gray-400 italic py-1">
        This message was deleted
    </div>

    <!-- Normal message content -->
    <template v-else>
        <p v-if="message.body" class="text-md wrap-break-word">{{ message.body }}</p>
        <MessageMedia :message="message" />
    </template>
</template>