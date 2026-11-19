<script setup>
import { computed } from 'vue';
import MessageMedia from './MessageMedia.vue';

const props = defineProps({
    message: {
        type: Object,
        required: true,
    },
});

// A shared bleep's body is the /s/{token} link. The card below already opens
// the post, so the raw link stays hidden.
const displayBody = computed(() => Boolean(props.message.body) && !props.message.shared_bleep);
</script>

<template>
    <!-- Deleted message placeholder -->
    <div v-if="message.is_deleted" class="flex justify-center text-xs text-gray-400 italic py-1">
        This message was deleted
    </div>

    <!-- Normal message content -->
    <template v-else>
        <p v-if="displayBody" class="text-md wrap-break-word">{{ message.body }}</p>
        <MessageMedia :message="message" filter="voice" />
    </template>
</template>
