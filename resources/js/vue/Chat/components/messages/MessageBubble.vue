<script setup>
import { computed, ref } from 'vue';
import MessageMedia from './MessageMedia.vue';

const props = defineProps({
	message: {
		type: Object,
		required: true,
	},
	mine: {
		type: Boolean,
		default: false,
	},
	seenAvatars: {
		type: Array,
		default: () => [],
	},
});

const showSeenPopover = ref(false);

const visibleSeenBy = computed(() => props.seenAvatars.slice(0, 5));
const hiddenSeenBy = computed(() => props.seenAvatars.slice(5));

const messageStatusText = computed(() => {
	if (!props.mine || props.seenAvatars.length > 0) return '';

	switch (props.message.status) {
		case 'sending':
			return 'Sending';
		case 'sent':
			return 'Sent';
		case 'delivered':
			return 'Received';
		case 'seen':
			return 'Seen';
		default:
			return 'Sent';
	}
});
</script>

<template>
    <div class="chat w-full" :class="mine ? 'chat-end' : 'chat-start'">
        <div class="chat-image avatar">
            <div class="w-10 rounded-full">
                <img
                    :src="message.sender?.profile_picture_url || '/images/avatar/default.jpg'"
                    :alt="`${message.sender?.username || 'user'} avatar`"
                />
            </div>
        </div>

        <div v-if="!mine" class="chat-header opacity-70 text-xs font-semibold">
            {{ message.sender?.dname || message.sender?.username || 'User' }}
        </div>

        <div class="chat-bubble" :class="mine ? 'chat-bubble-primary' : 'chat-bubble-neutral'">
            <p v-if="message.body" class="whitespace-pre-wrap">{{ message.body }}</p>
            <MessageMedia :message="message" />
        </div>

        <div class="chat-footer flex items-center gap-1 opacity-50">
            <time class="text-xs">
                {{ new Date(message.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' }) }}
            </time>
        </div>
    </div>

    <!-- Seen avatars -->
    <div
        v-if="seenAvatars.length > 0"
        class="flex items-center px-12 mb-1"
        :class="mine ? 'justify-end' : 'justify-end mr-10'"
    >
        <div
            v-for="(person, index) in visibleSeenBy"
            :key="person.id"
            class="relative group"
            :class="index > 0 ? 'ml-0.5' : ''"
        >
            <img
                :src="person.profile_picture_url || '/images/avatar/default.jpg'"
                :alt="person.dname || person.username"
                class="h-4 w-4 rounded-full object-cover ring-2 ring-base-200 cursor-default"
            />

            <!-- Custom tooltip -->
            <div class="absolute bottom-full mb-1.5 left-1/2 -translate-x-1/2 z-50 pointer-events-none
                        opacity-0 group-hover:opacity-100 transition-opacity duration-150 whitespace-nowrap">
                <div class="bg-base-content text-base-100 text-[10px] rounded-lg px-2 py-1 shadow-lg">
                    <p class="font-semibold">{{ person.dname || person.username }}</p>
                    <p v-if="person.last_read_at" class="opacity-70">
                        Seen at {{ new Date(person.last_read_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' }) }}
                    </p>
                </div>
                <!-- Tooltip arrow -->
                <div class="absolute top-full left-1/2 -translate-x-1/2 h-0 w-0
                            border-l-4 border-l-transparent border-r-4 border-r-transparent
                            border-t-4 border-t-base-content" />
            </div>
        </div>

        <!-- +N more popover (unchanged structure, but add seen time inside) -->
        <div v-if="hiddenSeenBy.length" class="relative -ml-0.5">
            <button
                class="h-4 w-4 rounded-full bg-base-300 text-[8px] font-bold flex items-center justify-center ring-2 ring-base-200 cursor-pointer hover:bg-base-200"
                @click.stop="showSeenPopover = !showSeenPopover"
            >
                +{{ hiddenSeenBy.length }}
            </button>

            <Teleport to="body">
                <div v-if="showSeenPopover" class="fixed inset-0 z-40" @click="showSeenPopover = false" />
            </Teleport>

            <div
                v-if="showSeenPopover"
                class="absolute bottom-full mb-3 min-w-44 rounded-2xl border border-base-300 bg-base-100 p-2 shadow-xl z-50"
                :class="mine ? 'right-0' : 'left-0'"
            >
                <div class="absolute -bottom-2 h-0 w-0 border-l-[7px] border-l-transparent border-r-[7px] border-r-transparent border-t-8 border-t-base-300" :class="mine ? 'right-2' : 'left-2'" />
                <div class="absolute -bottom-2 h-0 w-0 border-l-[6px] border-l-transparent border-r-[6px] border-r-transparent border-t-[7px] border-t-base-100" :class="mine ? 'right-2' : 'left-2'" />

                <p class="mb-1 px-1 text-[10px] font-semibold opacity-50">Seen by</p>
                <div
                    v-for="person in hiddenSeenBy"
                    :key="person.id"
                    class="flex items-center gap-2 rounded-lg px-1 py-1 hover:bg-base-200"
                >
                    <img :src="person.profile_picture_url || '/images/avatar/default.jpg'" class="h-5 w-5 shrink-0 rounded-full object-cover" />
                    <div class="flex flex-col min-w-0">
                        <span class="max-w-28 truncate text-xs font-medium">{{ person.dname || person.username }}</span>
                        <span v-if="person.last_read_at" class="text-[10px] opacity-60">
                            {{ new Date(person.last_read_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) }}
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
