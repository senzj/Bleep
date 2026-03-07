<script setup>
import { computed } from 'vue';
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



const store = useMessageStore();

const groupedReactions = computed(() => {
    const reactions = props.message.reactions || [];
    const groups = {};
    reactions.forEach((r) => {
        if (!groups[r.emoji]) {
            groups[r.emoji] = { emoji: r.emoji, count: 0, users: [], userReacted: false };
        }
        groups[r.emoji].count++;
        groups[r.emoji].users.push(r.user || { id: r.user_id });
        if (Number(r.user_id) === Number(store.state.currentUserId)) {
            groups[r.emoji].userReacted = true;
        }
    });
    return Object.values(groups);
});

</script>

<template>
    <!-- Other people's messages: time | edited (gap) {reactions} -->
    <div v-if="!mine" class="flex flex-wrap items-center gap-1.5 justify-start">
        <!-- Time and edited -->
        <div class="flex items-center gap-1 flex-nowrap">
            <time class="text-[11px] opacity-70 shrink-0">
                {{ new Date(message.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) }}
            </time>
            <span v-if="message.edited_at" class="text-[11px] opacity-50 shrink-0">|</span>
            <span v-if="message.edited_at" class="text-[11px] opacity-50 shrink-0">edited</span>
        </div>

        <!-- Reactions -->
        <button
            v-for="group in groupedReactions"
            :key="group.emoji"
            type="button"
            class="inline-flex items-center gap-0.5 rounded-full border px-1.5 py-0.5 text-xs transition-colors"
            :class="group.userReacted
                ? 'border-primary bg-primary/10 text-primary'
                : 'border-base-300 bg-base-100 hover:bg-base-200'"
            @click="store.toggleReaction(message.id, group.emoji)"
        >
            <span>{{ group.emoji }}</span>
            <span class="text-xs font-medium">{{ group.count }}</span>
        </button>
    </div>

    <!-- My messages: {reactions} (gap) edited | time -->
    <div v-else class="flex flex-wrap items-center gap-1.5 justify-end">
        <!-- Reactions -->
        <button
            v-for="group in groupedReactions"
            :key="group.emoji"
            type="button"
            class="inline-flex items-center gap-0.5 rounded-full border px-1.5 py-0.5 text-xs transition-colors"
            :class="group.userReacted
                ? 'border-primary bg-primary/10 text-primary'
                : 'border-base-300 bg-base-100 hover:bg-base-200'"
            @click="store.toggleReaction(message.id, group.emoji)"
        >
            <span>{{ group.emoji }}</span>
            <span class="text-xs font-medium">{{ group.count }}</span>
        </button>

        <!-- Edited and time -->
        <div class="flex items-center gap-1 flex-nowrap">
            <span v-if="message.edited_at" class="text-[11px] opacity-50 shrink-0">edited</span>
            <span v-if="message.edited_at" class="text-[11px] opacity-50 shrink-0">|</span>
            <time class="text-[11px] opacity-70 shrink-0">
                {{ new Date(message.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) }}
            </time>
        </div>
    </div>
</template>
