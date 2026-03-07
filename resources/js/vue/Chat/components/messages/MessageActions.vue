<script setup>
import LucideIcons from '../../../LucideIcons.vue';

defineProps({
    isDeleted: {
        type: Boolean,
        default: false,
    },
    mine: {
        type: Boolean,
        default: false,
    },
    canManageMessage: {
        type: Boolean,
        default: false,
    },
    showEmojiPicker: {
        type: Boolean,
        default: false,
    },
    showActionMenu: {
        type: Boolean,
        default: false,
    },
    quickEmojis: {
        type: Array,
        default: () => ['👍', '❤️', '😂', '😮', '😢', '🔥'],
    },
});

const emit = defineEmits(['toggle-emoji-picker', 'react', 'reply', 'toggle-action-menu', 'edit', 'delete']);
</script>

<template>
    <div
        v-if="!isDeleted"
        class="absolute top-1/2 -translate-y-1/2 z-30 hidden md:flex items-center opacity-0 group-hover/msg:opacity-100 transition-opacity duration-150 gap-1 align-middle"
        :class="mine ? 'right-full mr-1' : 'left-full ml-1'"
    >
        <!-- Reaction -->
        <div class="relative">
            <button
                type="button"
                class="p-1 rounded-full bg-gray-600/20 hover:bg-gray-600/30 transition-colors cursor-pointer"
                @click.stop="emit('toggle-emoji-picker')"
            >
                <LucideIcons name="smile" class="h-4 w-4 opacity-60" />
            </button>
            <!-- Desktop emoji picker dropdown -->
            <div
                v-if="showEmojiPicker"
                class="absolute bottom-full mb-1 z-50 flex items-center gap-1 rounded-xl border border-base-300 bg-base-100 p-1.5 shadow-xl"
                :class="mine ? 'right-0' : 'left-0'"
            >
                <button
                    v-for="emoji in quickEmojis"
                    :key="emoji"
                    type="button"
                    class="text-lg hover:scale-125 transition-transform px-0.5"
                    @click="emit('react', emoji)"
                >{{ emoji }}</button>
            </div>
        </div>

        <!-- Reply -->
        <button
            type="button"
            class="p-1 rounded-full bg-gray-600/20 hover:bg-gray-600/30 transition-colors cursor-pointer"
            @click.stop="emit('reply')"
        >
            <LucideIcons name="reply" class="h-4 w-4 opacity-60" />
        </button>

        <!-- Ellipsis (Edit/Delete) — owner only -->
        <div v-if="canManageMessage" class="relative">
            <button
                type="button"
                class="p-1 rounded-full bg-gray-600/20 hover:bg-gray-600/30 transition-colors cursor-pointer"
                @click.stop="emit('toggle-action-menu')"
            >
                <LucideIcons name="ellipsis-vertical" class="h-4 w-4 opacity-60" />
            </button>
            <div
                v-if="showActionMenu"
                class="absolute top-0 z-40 min-w-28 rounded-xl border border-base-300 bg-base-100 p-1 shadow-xl"
                :class="mine ? 'right-full mr-1' : 'left-full ml-1'"
            >
                <button type="button" class="w-full rounded-lg px-2 py-1.5 text-left text-xs hover:bg-base-200" @click="emit('edit')">Edit</button>
                <button type="button" class="w-full rounded-lg px-2 py-1.5 text-left text-xs text-error hover:bg-base-200" @click="emit('delete')">Delete</button>
            </div>
        </div>
    </div>
</template>
