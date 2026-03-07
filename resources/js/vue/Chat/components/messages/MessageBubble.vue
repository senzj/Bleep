<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue';
import LucideIcons from '../../../LucideIcons.vue';
import MessageReply from './MessageReply.vue';
import MessageContent from './MessageContent.vue';
import MessageActions from './MessageActions.vue';
import MessageEdit from './MessageEdit.vue';
import MessageSeen from './MessageSeen.vue';
import { useMessageStore } from '../../store/useMessageStore';
import MessageFooter from './MessageFooter.vue';

const QUICK_EMOJIS = ['👍', '❤️', '😂', '😮', '😢', '🔥'];

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
    highlighted: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['edit-message', 'delete-message', 'reply', 'react', 'scroll-to-message']);
const store = useMessageStore();

const showActionMenu = ref(false);
const showEmojiPicker = ref(false);
const editing = ref(false);
const editBody = ref('');
const editError = ref('');
const savingEdit = ref(false);
const editUploadProgress = ref(null);
const retainedMediaItems = ref([]);
const pendingMediaItems = ref([]);
let longPressTimer = null;

// ── Swipe-to-reply state ──
const swipeOffsetX = ref(0);
const isSwiping = ref(false);
let swipeStartX = 0;
let swipeStartY = 0;
let swipeDirection = null; // null | 'horizontal' | 'vertical'

const SWIPE_THRESHOLD = 60;
const MAX_SWIPE = 80;

const canManageMessage = computed(() => props.mine && !props.message.is_deleted);
const isDeleted = computed(() => props.message.is_deleted);
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
    showActionMenu.value = !showActionMenu.value;
    if (!showActionMenu.value) showEmojiPicker.value = false;
};

const closeActionMenu = () => {
    showActionMenu.value = false;
    showEmojiPicker.value = false;
};

const onPointerDown = (event) => {
    // Long press for mobile only (skip if mouse device)
    if (event.pointerType === 'mouse') return;
    if (isDeleted.value) return;

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

const handleReply = () => {
    emit('reply', props.message);
    closeActionMenu();
};

const handleQuickReact = async (emoji) => {
    closeActionMenu();
    await store.toggleReaction(props.message.id, emoji);
};

const toggleEmojiPicker = () => {
    showEmojiPicker.value = !showEmojiPicker.value;
};

const onClickOutside = (event) => {
    if (!showActionMenu.value && !showEmojiPicker.value) return;
    // Close if click is outside the entire component
    if (!event.target.closest('.group\\/msg')) {
        closeActionMenu();
    }
};

// ── Swipe-to-reply (touch only) ──
const onTouchStart = (event) => {
    if (isDeleted.value || editing.value) return;
    const touch = event.touches[0];
    swipeStartX = touch.clientX;
    swipeStartY = touch.clientY;
    swipeDirection = null;
    isSwiping.value = false;
};

const onTouchMove = (event) => {
    if (isDeleted.value || editing.value) return;
    const touch = event.touches[0];
    const dx = touch.clientX - swipeStartX;
    const dy = touch.clientY - swipeStartY;

    if (swipeDirection === null) {
        if (Math.abs(dx) > 8 || Math.abs(dy) > 8) {
            swipeDirection = Math.abs(dx) > Math.abs(dy) ? 'horizontal' : 'vertical';
        }
        return;
    }

    if (swipeDirection !== 'horizontal') return;

    // For mine: allow negative (right-to-left), for others: allow positive (left-to-right)
    if (props.mine) {
        const clamped = Math.max(-MAX_SWIPE, Math.min(0, dx));
        swipeOffsetX.value = clamped;
    } else {
        const clamped = Math.max(0, Math.min(MAX_SWIPE, dx));
        swipeOffsetX.value = clamped;
    }

    if (Math.abs(swipeOffsetX.value) > 10) {
        isSwiping.value = true;
    }
};

const onTouchEnd = () => {
    if (Math.abs(swipeOffsetX.value) >= SWIPE_THRESHOLD) {
        emit('reply', props.message);
    }
    swipeOffsetX.value = 0;
    isSwiping.value = false;
    swipeDirection = null;
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
        class="relative group/msg w-full"
        :class="{ 'highlighted-message': highlighted }"
        @pointerdown="onPointerDown"
        @pointerup="cancelLongPress"
        @pointerleave="cancelLongPress"
        @pointercancel="cancelLongPress"
        @touchstart.passive="onTouchStart"
        @touchmove.passive="onTouchMove"
        @touchend.passive="onTouchEnd"
    >
        <!-- Mobile long-press menu — appears above the bubble -->
        <div
            v-if="showActionMenu"
            class="absolute z-40 rounded-xl border border-base-300 bg-base-100 p-1 shadow-xl md:hidden"
            :class="mine ? 'right-4 bottom-full mb-1' : 'left-14 bottom-full mb-1'"
        >
            <!-- Quick emoji row -->
            <div class="flex items-center gap-1 px-1 py-1 border-b border-base-300 mb-1">
                <button
                    v-for="emoji in QUICK_EMOJIS"
                    :key="emoji"
                    type="button"
                    class="text-lg hover:scale-125 transition-transform px-0.5"
                    @click="handleQuickReact(emoji)"
                >{{ emoji }}</button>
            </div>
            <button type="button" class="w-full rounded-lg px-2 py-1.5 text-left text-xs hover:bg-base-200 flex items-center gap-2" @click="handleReply">
                <LucideIcons name="reply" class="h-3.5 w-3.5" /> Reply
            </button>
            <button v-if="canManageMessage" type="button" class="w-full rounded-lg px-2 py-1.5 text-left text-xs hover:bg-base-200 flex items-center gap-2" @click="handleEdit">
                <LucideIcons name="pencil" class="h-3.5 w-3.5" /> Edit
            </button>
            <button v-if="canManageMessage" type="button" class="w-full rounded-lg px-2 py-1.5 text-left text-xs text-error hover:bg-base-200 flex items-center gap-2" @click="handleDelete">
                <LucideIcons name="trash-2" class="h-3.5 w-3.5" /> Delete
            </button>
        </div>

        <!-- Swipe wrapper for touch gestures -->
        <div
            :style="{ transform: `translateX(${swipeOffsetX}px)`, transition: isSwiping ? 'none' : 'transform 0.2s ease' }"
        >
            <!-- Chat bubble -->
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

                <div class="chat-header gap-3 flex items-center">
                    <span v-if="!mine && props.showAvatar" class="text-sm font-semibold">
                        {{ message.sender?.dname || 'User' }}
                    </span>
                </div>

                <!-- Reply + Message bubbles wrapper (flex column to stack vertically) -->
                <div class="flex flex-col gap-1">
                    <!-- Reply preview above the message bubble (hidden if deleted) -->
                    <MessageReply
                        v-if="!isDeleted"
                        :message="message"
                        :mine="mine"
                        @scroll-to-message="(id) => emit('scroll-to-message', id)"
                    />

                    <div class="chat-bubble relative items-center min-w-30 max-w-80 gap-1" :class="[
                        isDeleted ? 'chat-bubble-ghost' : (mine ? 'chat-bubble-primary' : 'chat-bubble-secondary'),
                        highlighted ? 'ring-2 ring-primary ring-offset-1 ring-offset-base-100' : '',
                    ]">
                        <!-- Desktop hover actions — beside the bubble -->
                        <MessageActions
                            :is-deleted="isDeleted"
                            :mine="mine"
                            :can-manage-message="canManageMessage"
                            :show-emoji-picker="showEmojiPicker"
                            :show-action-menu="showActionMenu"
                            :quick-emojis="QUICK_EMOJIS"
                            @toggle-emoji-picker="toggleEmojiPicker"
                            @react="handleQuickReact"
                            @reply="handleReply"
                            @toggle-action-menu="toggleActionMenu"
                            @edit="handleEdit"
                            @delete="handleDelete"
                        />

                        <!-- Inline editor -->
                        <MessageEdit
                            v-if="editing"
                            :editing="editing"
                            :edit-body="editBody"
                            :saving-edit="savingEdit"
                            :retained-media-items="retainedMediaItems"
                            :pending-media-items="pendingMediaItems"
                            :edit-error="editError"
                            :edit-upload-progress="editUploadProgress"
                            @update:edit-body="editBody = $event"
                            @remove-existing-media="removeExistingMediaAt"
                            @remove-pending-media="removePendingMediaAt"
                            @pick-media="pickEditMedia"
                            @save="submitInlineEdit"
                            @cancel="cancelInlineEditor"
                        />

                        <!-- Normal message content -->
                        <MessageContent
                            v-else
                            :message="message"
                        />
                    </div>
                </div>

                <div class="chat-footer mt-1 justify-end">
                    <!-- Reactions (hidden for deleted messages) -->
                    <MessageFooter
                        v-if="!isDeleted"
                        :message="message"
                        :mine="mine"
                    />
                </div>
            </div>
        </div>

        <!-- Swipe reply indicator -->
        <div
            v-if="Math.abs(swipeOffsetX) > 10"
            class="absolute top-1/2 -translate-y-1/2 transition-opacity"
            :class="mine ? 'right-2' : 'left-2'"
            :style="{ opacity: Math.min(1, Math.abs(swipeOffsetX) / SWIPE_THRESHOLD) }"
        >
            <LucideIcons name="reply" class="h-5 w-5 text-primary" />
        </div>

        <!-- Seen avatars (hidden for deleted messages) -->
        <MessageSeen v-if="!isDeleted" :message="message" :seenAvatars="seenAvatars" :mine="mine" />
    </div>
</template>
