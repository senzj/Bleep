<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue';
import LucideIcons from '../../../LucideIcons.vue';
import MessageMedia from './MessageMedia.vue';
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
    seenAvatars: {
        type: Array,
        default: () => [],
    },
    showAvatar: {
        type: Boolean,
        default: true,
    },
});

const emit = defineEmits(['edit-message', 'delete-message']);
const store = useMessageStore();

const showSeenPopover = ref(false);
const showActionMenu = ref(false);
const editing = ref(false);
const editBody = ref('');
const editError = ref('');
const savingEdit = ref(false);
const editUploadProgress = ref(null);
const retainedMediaItems = ref([]);
const pendingMediaItems = ref([]);
let longPressTimer = null;

const visibleSeenBy = computed(() => props.seenAvatars.slice(0, 5));
const hiddenSeenBy = computed(() => props.seenAvatars.slice(5));
const canManageMessage = computed(() => props.mine && !props.message.is_deleted);
const hasEditMedia = computed(() => retainedMediaItems.value.length + pendingMediaItems.value.length > 0);

const normalizeMessageMedia = (message) => {
    if (Array.isArray(message?.media_items) && message.media_items.length) {
        return message.media_items.map((item) => ({
            id: item.id,
            media_path: item.media_path,
            media_url: item.media_url,
            media_type: item.media_type,
            media_kind: item.media_kind,
            media_duration: item.media_duration,
        }));
    }

    if (message?.media_path || message?.media_url) {
        return [{
            id: message.id ? `legacy-${message.id}` : null,
            media_path: message.media_path,
            media_url: message.media_url,
            media_type: message.media_type,
            media_kind: message.media_kind,
            media_duration: message.media_duration,
        }];
    }

    return [];
};

const revokePendingMediaPreviews = () => {
    pendingMediaItems.value.forEach((item) => {
        if (item.previewUrl) {
            URL.revokeObjectURL(item.previewUrl);
        }
    });
};

const resetInlineEditor = () => {
    editBody.value = props.message.body || '';
    editError.value = '';
    editUploadProgress.value = null;
    retainedMediaItems.value = normalizeMessageMedia(props.message);
    revokePendingMediaPreviews();
    pendingMediaItems.value = [];
};

const openInlineEditor = () => {
    if (!canManageMessage.value) return;

    closeActionMenu();
    editing.value = true;
    resetInlineEditor();
};

const cancelInlineEditor = () => {
    if (savingEdit.value) return;
    editing.value = false;
    resetInlineEditor();
};

const removeExistingMediaAt = (index) => {
    retainedMediaItems.value = retainedMediaItems.value.filter((_, i) => i !== index);
};

const removePendingMediaAt = (index) => {
    const item = pendingMediaItems.value[index];
    if (item?.previewUrl) {
        URL.revokeObjectURL(item.previewUrl);
    }

    pendingMediaItems.value = pendingMediaItems.value.filter((_, i) => i !== index);
};

const countImages = (items) => items.filter((item) => (item.media_type || item.file?.type || '').startsWith('image/')).length;
const countVideos = (items) => items.filter((item) => (item.media_type || item.file?.type || '').startsWith('video/')).length;

const pickEditMedia = () => {
    if (!editing.value || savingEdit.value) return;

    const input = document.createElement('input');
    input.type = 'file';
    input.accept = 'image/*,video/*,audio/*,.pdf';
    input.multiple = true;

    input.onchange = () => {
        const files = Array.from(input.files || []);
        if (!files.length) return;

        const nextItems = files.map((file) => ({
            file,
            mediaKind: file.type.startsWith('audio/') ? 'audio' : 'media',
            previewUrl: URL.createObjectURL(file),
            media_type: file.type,
        }));

        const existing = retainedMediaItems.value.map((item) => ({ media_type: item.media_type || '' }));
        const combined = [...existing, ...pendingMediaItems.value, ...nextItems];
        const imageCount = countImages(combined);
        const videoCount = countVideos(combined);

        if (imageCount > 10 || videoCount > 5) {
            nextItems.forEach((item) => {
                if (item.previewUrl) URL.revokeObjectURL(item.previewUrl);
            });
            editError.value = 'Maximum 10 images and 5 videos per message.';
            return;
        }

        editError.value = '';
        pendingMediaItems.value = [...pendingMediaItems.value, ...nextItems];
    };

    input.click();
};

const toggleActionMenu = () => {
    if (!canManageMessage.value) return;
    showActionMenu.value = !showActionMenu.value;
};

const closeActionMenu = () => {
    showActionMenu.value = false;
};

const onPointerDown = (event) => {
    // Long press for mobile only (skip if mouse device)
    if (event.pointerType === 'mouse') return;
    if (!canManageMessage.value) return;

    clearTimeout(longPressTimer);
    longPressTimer = setTimeout(() => {
        showActionMenu.value = true;
    }, 450);
};

const cancelLongPress = () => {
    clearTimeout(longPressTimer);
    longPressTimer = null;
};

const handleEdit = () => {
    openInlineEditor();
};

const handleDelete = () => {
    if (!window.confirm('Delete this message?')) return;
    emit('delete-message', props.message.id);
    closeActionMenu();
};

const onClickOutside = (event) => {
    if (!showActionMenu.value) return;
    // Close if click is outside the entire component
    if (!event.target.closest('.group\\/msg')) {
        closeActionMenu();
    }
};

const submitInlineEdit = async () => {
    if (savingEdit.value) return;

    const trimmedBody = String(editBody.value || '').trim();
    const retainedIds = retainedMediaItems.value
        .map((item) => Number(item.id))
        .filter((id) => Number.isFinite(id) && id > 0);

    if (!trimmedBody && !hasEditMedia.value) {
        editError.value = 'Message body or media is required.';
        return;
    }

    savingEdit.value = true;
    editError.value = '';

    try {
        const uploadedItems = [];
        const total = pendingMediaItems.value.length;

        for (let i = 0; i < total; i++) {
            const item = pendingMediaItems.value[i];
            const uploaded = await store.uploadMedia(item.file, item.mediaKind, (pct) => {
                editUploadProgress.value = Math.round(((i + pct / 100) / total) * 100);
            });
            uploadedItems.push(uploaded);
        }

        editUploadProgress.value = null;
        emit('edit-message', {
            messageId: props.message.id,
            body: trimmedBody || null,
            retainedMediaIds: retainedIds,
            newMediaItems: uploadedItems,
        });
        editing.value = false;
        resetInlineEditor();
    } catch {
        editError.value = 'Failed to update message.';
    } finally {
        savingEdit.value = false;
        editUploadProgress.value = null;
    }
};

onMounted(() => document.addEventListener('pointerdown', onClickOutside));
onUnmounted(() => {
    cancelLongPress();
    revokePendingMediaPreviews();
    document.removeEventListener('pointerdown', onClickOutside);
});
</script>

<template>
    <div
        class="relative group/msg"
        @pointerdown="onPointerDown"
        @pointerup="cancelLongPress"
        @pointerleave="cancelLongPress"
        @pointercancel="cancelLongPress"
    >
        <!-- Mobile long-press menu — appears above the bubble -->
        <div
            v-if="showActionMenu"
            class="absolute z-40 min-w-28 rounded-xl border border-base-300 bg-base-100 p-1 shadow-xl md:hidden"
            :class="mine ? 'right-4 bottom-full mb-1' : 'left-14 bottom-full mb-1'"
        >
            <button type="button" class="w-full rounded-lg px-2 py-1.5 text-left text-xs hover:bg-base-200" @click="handleEdit">Edit</button>
            <button type="button" class="w-full rounded-lg px-2 py-1.5 text-left text-xs text-error hover:bg-base-200" @click="handleDelete">Delete</button>
        </div>

        <!-- Chat bubble — completely untouched -->
        <div class="chat w-full" :class="mine ? 'chat-end' : 'chat-start'">
            <div class="chat-image avatar">
                <div class="h-10 w-10 rounded-full" :class="props.showAvatar ? '' : 'opacity-0'">
                    <img
                        v-if="props.showAvatar"
                        :src="message.sender?.profile_picture_url || '/images/avatar/default.jpg'"
                        :alt="`${message.sender?.username || 'user'} avatar`"
                    />
                </div>
            </div>

            <div class="chat-header">
                <span v-if="!mine && props.showAvatar" class="text-xs font-semibold">
                    {{ message.sender?.dname || message.sender?.username || 'User' }}
                </span>
                <time class="opacity-50" :class="!mine && props.showAvatar ? 'ml-2' : ''">
                    {{ new Date(message.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) }}
                </time>
            </div>

            <div class="chat-bubble relative" :class="mine ? 'chat-bubble-primary' : 'chat-bubble-neutral'">
                <!-- quick action menu -->
                <div
                    v-if="canManageMessage"
                    class="absolute top-1/2 -translate-y-1/2 z-30 hidden md:flex items-center opacity-0 group-hover/msg:opacity-100 transition-opacity duration-150 gap-1"
                    :class="mine ? 'right-full mr-1' : 'left-full ml-1'"
                >
                    
                    <!-- Elipsis -->
                    <div class="relative">
                        <button
                            type="button"
                            class="p-1 rounded-full bg-gray-600/20 hover:bg-gray-600/30 transition-colors cursor-pointer"
                            @click.stop="toggleActionMenu"
                        >
                            <LucideIcons name="ellipsis-vertical" class="h-4 w-4 opacity-60" />
                        </button>

                        <!-- Desktop dropdown -->
                        <div
                            v-if="showActionMenu"
                            class="absolute top-0 z-40 min-w-28 rounded-xl border border-base-300 bg-base-100 p-1 shadow-xl"
                            :class="mine ? 'right-full mr-1' : 'left-full ml-1'"
                        >
                            <button type="button" class="w-full rounded-lg px-2 py-1.5 text-left text-xs hover:bg-base-200" @click="handleEdit">Edit</button>
                            <button type="button" class="w-full rounded-lg px-2 py-1.5 text-left text-xs text-error hover:bg-base-200" @click="handleDelete">Delete</button>
                        </div>
                    </div>

                    <!-- Reply -->
                    <div class="relative">
                        <button
                            type="button"
                            class="p-1 rounded-full bg-gray-600/20 hover:bg-gray-600/30 transition-colors cursor-pointer"
                            @click.stop="$emit('reply', message)"
                        >
                            <LucideIcons name="reply" class="h-4 w-4 opacity-60" />
                        </button>
                    </div>

                    <!-- Reaction -->
                    <div class="relative">
                        <button
                            type="button"
                            class="p-1 rounded-full bg-gray-600/20 hover:bg-gray-600/30 transition-colors cursor-pointer"
                            @click.stop="$emit('react', message)"
                        >
                            <LucideIcons name="smile" class="h-4 w-4 opacity-60" />
                        </button>
                    </div>
                </div>

                <div v-if="editing" class="space-y-2">
                    <textarea
                        v-model="editBody"
                        class="textarea textarea-bordered w-full min-h-24"
                        maxlength="5000"
                        placeholder="Edit your message"
                        :disabled="savingEdit"
                    />

                    <div v-if="retainedMediaItems.length || pendingMediaItems.length" class="grid grid-cols-2 gap-2">
                        <div
                            v-for="(item, index) in retainedMediaItems"
                            :key="item.id || `${item.media_path}-${index}`"
                            class="relative overflow-hidden rounded-lg border border-base-300 bg-base-200 p-1"
                        >
                            <img
                                v-if="(item.media_type || '').startsWith('image/')"
                                :src="item.media_url || (item.media_path ? `/storage/${item.media_path}` : '')"
                                alt="Media"
                                class="h-24 w-full rounded object-cover"
                            >
                            <video
                                v-else-if="(item.media_type || '').startsWith('video/')"
                                :src="item.media_url || (item.media_path ? `/media/stream/${item.media_path}` : '')"
                                class="h-24 w-full rounded object-cover"
                                controls
                            />
                            <div v-else class="line-clamp-2 px-2 py-2 text-xs">{{ item.media_path || 'Attachment' }}</div>

                            <button
                                type="button"
                                class="btn btn-xs btn-circle btn-error absolute right-1 top-1"
                                :disabled="savingEdit"
                                @click="removeExistingMediaAt(index)"
                            >
                                <LucideIcons name="x" class="h-3 w-3" />
                            </button>
                        </div>

                        <div
                            v-for="(item, index) in pendingMediaItems"
                            :key="`pending-${item.file?.name}-${index}`"
                            class="relative overflow-hidden rounded-lg border border-base-300 bg-base-200 p-1"
                        >
                            <img
                                v-if="item.file?.type?.startsWith('image/')"
                                :src="item.previewUrl"
                                alt="Pending upload"
                                class="h-24 w-full rounded object-cover"
                            >
                            <video
                                v-else-if="item.file?.type?.startsWith('video/')"
                                :src="item.previewUrl"
                                class="h-24 w-full rounded object-cover"
                                controls
                            />
                            <div v-else class="line-clamp-2 px-2 py-2 text-xs">{{ item.file?.name || 'Attachment' }}</div>

                            <button
                                type="button"
                                class="btn btn-xs btn-circle btn-error absolute right-1 top-1"
                                :disabled="savingEdit"
                                @click="removePendingMediaAt(index)"
                            >
                                <LucideIcons name="x" class="h-3 w-3" />
                            </button>
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        <button type="button" class="btn btn-sm btn-outline" :disabled="savingEdit" @click="pickEditMedia">
                            <LucideIcons name="paperclip" class="h-4 w-4" />
                            Add media
                        </button>
                        <button type="button" class="btn btn-sm btn-primary" :disabled="savingEdit" @click="submitInlineEdit">
                            Save
                        </button>
                        <button type="button" class="btn btn-sm btn-ghost" :disabled="savingEdit" @click="cancelInlineEditor">
                            Cancel
                        </button>
                    </div>

                    <div v-if="editUploadProgress !== null" class="flex items-center gap-2">
                        <progress class="progress progress-primary flex-1" :value="editUploadProgress" max="100" />
                        <span class="text-xs font-medium">{{ editUploadProgress }}%</span>
                    </div>

                    <p v-if="editError" class="text-xs text-error">{{ editError }}</p>
                </div>

                <template v-else>
                    <p v-if="message.body" class="whitespace-pre-wrap">{{ message.body }}</p>
                    <MessageMedia :message="message" />
                </template>
            </div>

            <div class="chat-footer flex items-center gap-1 mt-1 opacity-50">
                <span class="text-[10px]">
                    {{ message.edited_at ? 'Edited' : '' }}
                </span>
            </div>
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
                        Seen at {{ new Date(person.last_read_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) }}
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
