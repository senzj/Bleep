<script setup>
import { computed, nextTick, onMounted, watch } from 'vue';
import CommentAudio from '../media/audio.vue';
import LucideIcons from '../../../LucideIcons.vue';

const props = defineProps({
	path: {
		type: String,
		default: '',
	},
	alt: {
		type: String,
		default: '',
	},
	isReply: {
		type: Boolean,
		default: false,
	},
	commentId: {
		type: [String, Number],
		default: null,
	},
});

const mediaUrl = computed(() => {
    if (!props.path) return '';
    if (isVideo.value) return `/media/stream/${props.path}`;
    return `/storage/${props.path}`;
})

const isImage = computed(() => /\.(jpg|jpeg|png|gif|webp)$/i.test(props.path || ''));
const isVideo = computed(() => /\.(mp4|mov|webm)$/i.test(props.path || ''));
const isAudio = computed(() => /\.(mp3|wav|ogg|m4a|aac|flac)$/i.test(props.path || ''));
const mediaType = computed(() => (isImage.value ? 'image' : (isVideo.value ? 'video' : 'file')));

const notifyMediaHydrated = async () => {
	await nextTick();
	document.dispatchEvent(new Event('bleeps:media:hydrated'));
};

onMounted(() => {
	if (props.path) notifyMediaHydrated();
});

watch(() => props.path, () => {
	if (props.path) notifyMediaHydrated();
});
</script>

<template>
	<div v-if="path" class="mb-3" :data-comment-media-wrapper="commentId" data-bleep-media>
		<CommentAudio
			v-if="isAudio"
			:src="mediaUrl"
			:comment-id="commentId"
			:media-key="path"
		/>

		<div
            v-else
            class="inline-flex max-w-xs rounded-lg overflow-hidden bg-base-300 shadow cursor-pointer"
            data-media-index="0"
            :data-media-type="mediaType"
            :data-media-src="mediaUrl"
            :data-media-alt="alt"
            :data-media-mime="isVideo ? 'video/mp4' : ''"
        >
            <img
                v-if="isImage"
                :src="mediaUrl"
                :alt="alt"
                :class="isReply ? 'max-h-40' : 'h-45'"
                class="max-w-full object-cover"
            />

            <!-- No controls, just a thumbnail with play icon overlay -->
            <div v-else class="relative">
                <video
                    :class="isReply ? 'max-h-40' : ''"
                    class="max-w-full h-50 object-cover pointer-events-none"
                >
                    <source :src="mediaUrl" />
                </video>

                <div class="absolute inset-0 flex items-center justify-center bg-black/10">
                    <div class="w-8 h-8 rounded-full bg-base-100/70 flex items-center justify-center">
                        <LucideIcons name="play" size="18" class="text-gray-800" />
                    </div>
                </div>
            </div>
        </div>
	</div>
</template>
