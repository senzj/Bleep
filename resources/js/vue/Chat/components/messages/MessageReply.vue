<script setup>
import { computed } from 'vue';
import LucideIcons from '../../../LucideIcons.vue';
import { useMessageStore } from '../../store/useMessageStore';

const props = defineProps({
    message: {
        type: Object,
        required: true,
    },
    mine: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['scroll-to-message']);
const store = useMessageStore();

const replyTo = computed(() => props.message.reply_to || null);
const replyToSenderName = computed(() => {
    if (!replyTo.value) return '';
    return replyTo.value.sender?.dname || replyTo.value.sender?.username || 'User';
});
const replyToSnippet = computed(() => {
    if (!replyTo.value) return '';
    if (replyTo.value.is_deleted) return '[deleted]';
    const body = replyTo.value.body || '';
    return body.length > 80 ? body.slice(0, 80) + '…' : body;
});

const replyMediaItems = computed(() => {
    if (!replyTo.value || replyTo.value.is_deleted) return [];
    const r = replyTo.value;
    if (Array.isArray(r.media_items) && r.media_items.length) return r.media_items;
    if (r.media_path || r.media_url) {
        return [{ media_path: r.media_path, media_url: r.media_url, media_type: r.media_type, media_kind: r.media_kind }];
    }
    return [];
});

const mediaSrc = (item) => {
    if (!item.media_path) return item.media_url || '';
    return item.media_url || `/storage/${item.media_path}`;
};

const isReplyToMine = computed(() => {
    if (!replyTo.value) return false;
    return Number(replyTo.value.sender_id) === Number(store.state.currentUserId);
});
</script>

<template>
    <div
        v-if="replyTo"
        class="cursor-pointer max-w-80"
        @click="emit('scroll-to-message', replyTo.id)"
    >
        <div
            class="rounded-lg py-1.5 px-2.5 text-xs opacity-80 hover:opacity-100 transition-opacity"
            :class="isReplyToMine ? 'bg-primary/20 text-primary-content border-l-4 border-primary/70' : 'bg-secondary/30 text-secondary-content border-l-4 border-secondary/70'"
        >
            <p class="font-semibold mb-0.5">{{ replyToSenderName }}</p>

            <p v-if="replyToSnippet" class="opacity-70 line-clamp-2 ml-1.5">{{ replyToSnippet }}</p>

            <!-- Media preview -->
            <div v-if="replyMediaItems.length" class="mb-1 flex flex-wrap gap-1 justify-center items-center">
                <template v-for="(item, i) in replyMediaItems.slice(0, 3)" :key="i">
                    <img
                        v-if="(item.media_type || '').startsWith('image/')"
                        :src="mediaSrc(item)"
                        class="h-12 w-12 rounded object-cover"
                    />
                    <div
                        v-else-if="(item.media_type || '').startsWith('video/')"
                        class="h-12 w-12 rounded bg-base-content/20 flex items-center justify-center"
                    >
                        <LucideIcons name="film" class="h-4 w-4 opacity-60" />
                    </div>
                    <div
                        v-else-if="item.media_kind === 'voice' || (item.media_type || '').startsWith('audio/')"
                        class="flex items-center gap-1 opacity-70"
                    >
                        <LucideIcons name="mic" class="h-3.5 w-3.5" />
                        <span>Voice message</span>
                    </div>
                    <div v-else class="flex items-center gap-1 opacity-70">
                        <LucideIcons name="paperclip" class="h-3.5 w-3.5" />
                        <span>Attachment</span>
                    </div>
                </template>
                <div v-if="replyMediaItems.length > 3" class="h-12 w-12 rounded bg-base-content/20 flex items-center justify-center text-xs font-bold">
                    +{{ replyMediaItems.length - 3 }}
                </div>
            </div>

            <p v-if="!replyToSnippet && !replyMediaItems.length" class="opacity-50 italic">[No content]</p>
        </div>
    </div>
</template>
