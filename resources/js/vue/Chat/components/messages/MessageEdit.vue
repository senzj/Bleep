<script setup>
import LucideIcons from '../../../LucideIcons.vue';

defineProps({
    editing: {
        type: Boolean,
        default: false,
    },
    editBody: {
        type: String,
        default: '',
    },
    savingEdit: {
        type: Boolean,
        default: false,
    },
    retainedMediaItems: {
        type: Array,
        default: () => [],
    },
    pendingMediaItems: {
        type: Array,
        default: () => [],
    },
    editError: {
        type: String,
        default: '',
    },
    editUploadProgress: {
        type: Number,
        default: null,
    },
});

const emit = defineEmits(['update:editBody', 'remove-existing-media', 'remove-pending-media', 'pick-media', 'save', 'cancel']);
</script>

<template>
    <div v-if="editing" class="space-y-2">
        <textarea
            :value="editBody"
            @input="emit('update:editBody', $event.target.value)"
            class="textarea textarea-bordered w-full min-h-12"
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
                <img v-if="(item.media_type || '').startsWith('image/')" :src="item.media_url || (item.media_path ? `/storage/${item.media_path}` : '')" alt="Media" class="h-24 w-full rounded object-cover">
                <video v-else-if="(item.media_type || '').startsWith('video/')" :src="item.media_url || (item.media_path ? `/media/stream/${item.media_path}` : '')" class="h-24 w-full rounded object-cover" controls />
                <div v-else class="line-clamp-2 px-2 py-2 text-xs">{{ item.media_path || 'Attachment' }}</div>
                <button type="button" class="btn btn-xs btn-circle btn-error absolute right-1 top-1" :disabled="savingEdit" @click="emit('remove-existing-media', index)">
                    <LucideIcons name="x" class="h-3 w-3" />
                </button>
            </div>
            <div
                v-for="(item, index) in pendingMediaItems"
                :key="`pending-${item.file?.name}-${index}`"
                class="relative overflow-hidden rounded-lg border border-base-300 bg-base-200 p-1"
            >
                <img v-if="item.file?.type?.startsWith('image/')" :src="item.previewUrl" alt="Pending upload" class="h-24 w-full rounded object-cover">
                <video v-else-if="item.file?.type?.startsWith('video/')" :src="item.previewUrl" class="h-24 w-full rounded object-cover" controls />
                <div v-else class="line-clamp-2 px-2 py-2 text-xs">{{ item.file?.name || 'Attachment' }}</div>
                <button type="button" class="btn btn-xs btn-circle btn-error absolute right-1 top-1" :disabled="savingEdit" @click="emit('remove-pending-media', index)">
                    <LucideIcons name="x" class="h-3 w-3" />
                </button>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <button type="button" class="btn btn-sm btn-outline" :disabled="savingEdit" @click="emit('pick-media')">
                <LucideIcons name="paperclip" class="h-4 w-4" />
            </button>
            <button type="button" class="btn btn-sm btn-error text-white" :disabled="savingEdit" @click="emit('cancel')">Cancel</button>
            <button type="button" class="btn btn-sm btn-secondary" :disabled="savingEdit" @click="emit('save')">Save</button>
        </div>

        <div v-if="editUploadProgress !== null" class="flex items-center gap-2">
            <progress class="progress progress-primary flex-1" :value="editUploadProgress" max="100" />
            <span class="text-xs font-medium">{{ editUploadProgress }}%</span>
        </div>
        <p v-if="editError" class="text-xs text-error">{{ editError }}</p>
    </div>
</template>