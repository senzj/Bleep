<script setup>
import { computed, ref } from 'vue';

const props = defineProps({
    message: {
        type: Object,
        required: true,
    },
    seenAvatars: {
        type: Array,
        default: () => [],
    },
    mine: {
        type: Boolean,
        default: false,
    },
});

const showSeenPopover = ref(false);
const visibleSeenBy = computed(() => props.seenAvatars.slice(0, 5));
const hiddenSeenBy = computed(() => props.seenAvatars.slice(4));

const messageStatus = computed(() => {
    if (!props.mine) return null;
    if (props.seenAvatars.length >= 1) return 'seen';
    if (props.message.status === 'delivered') return 'delivered';
    if (props.message.status === 'seen') return 'seen';
    return 'sent';
});

const statusLabel = computed(() => {
    if (!props.mine) return '';
    if (messageStatus.value === 'seen') {
        return;
    }
    if (messageStatus.value === 'delivered') return 'Delivered';
    if (messageStatus.value === 'sent') return 'Sent';
    return '';
});
</script>

<template>
    <div
        v-if="(mine && statusLabel) || seenAvatars.length >= 1"
        class="flex items-center px-12 mb-1"
        :class="mine ? 'justify-end' : 'justify-end ml-5'"
    >

        <span v-if="statusLabel" class="text-[10px] opacity-50 mr-1.5">
            {{ statusLabel }}
        </span>

        <div v-for="(person, index) in visibleSeenBy"
            :key="person.id"
            class="relative group"
            :class="index > 0 ? 'ml-0.5' : ''">
            
            <img :src="person.profile_picture_url || '/images/avatar/default.jpg'"
                :alt="person.dname || person.username"
                class="h-4.5 w-4.5 rounded-full object-cover ring-2 ring-base-200 cursor-default"
            />

            <div class="absolute bottom-full mb-1.5 left-1/2 -translate-x-1/2 z-50 pointer-events-none opacity-0 group-hover:opacity-100 transition-opacity duration-150 whitespace-nowrap">

                <div class="bg-base-content text-base-100 text-[10px] rounded-lg px-2 py-1 shadow-lg">
                    <p class="font-semibold">
                        {{ person.dname || person.username }}
                        <template v-if="person.last_read_at">
                            <span class="opacity-70"> seen at {{ new Date(person.last_read_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) }}</span>
                        </template>
                    </p>
                </div>

                <div class="absolute top-full left-1/2 -translate-x-1/2 h-0 w-0 border-l-4 border-l-transparent border-r-4 border-r-transparent border-t-4 border-t-base-content"></div>
            </div>
        </div>

        <div v-if="hiddenSeenBy.length" class="relative -ml-0.5">
            <button
                class="h-4.5 w-4.5 rounded-full bg-base-300 text-[8px] font-bold flex items-center justify-center ring-2 ring-base-200 cursor-pointer hover:bg-base-200"
                @click.stop="showSeenPopover = !showSeenPopover"
            >
                +{{ hiddenSeenBy.length }}
            </button>

            <Teleport to="body">
                <div v-if="showSeenPopover" class="fixed inset-0 z-40" @click="showSeenPopover = false"></div>
            </Teleport>

            <div
                v-if="showSeenPopover"
                class="absolute bottom-full mb-3 min-w-44 rounded-2xl border border-base-300 bg-base-100 p-2 shadow-xl z-50"
                :class="mine ? 'right-0' : 'left-0'"
            >
                <div class="absolute -bottom-2 h-0 w-0 border-l-[7px] border-l-transparent border-r-[7px] border-r-transparent border-t-8 border-t-base-300" :class="mine ? 'right-2' : 'left-2'" />
                <div class="absolute -bottom-2 h-0 w-0 border-l-[6px] border-l-transparent border-r-[6px] border-r-transparent border-t-[7px] border-t-base-100" :class="mine ? 'right-2' : 'left-2'" />
                
                <p class="mb-1 px-1 text-[10px] font-semibold opacity-50">
                    Seen by
                </p>
                
                <div v-for="person in hiddenSeenBy"
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