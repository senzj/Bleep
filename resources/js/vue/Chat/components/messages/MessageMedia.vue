<script setup>
import { computed } from 'vue';
import AudioPlayer from '../media/Audio.vue';
import ImageViewer from '../media/Image.vue';
import VideoPlayer from '../media/Video.vue';
import VoiceMessage from '../media/VoiceMessage.vue';

const props = defineProps({
	message: {
		type: Object,
		required: true,
	},
});

const isImage = computed(() => (props.message.media_type || '').startsWith('image/'));
const isVideo = computed(() => (props.message.media_type || '').startsWith('video/'));
const isAudio = computed(() => (props.message.media_type || '').startsWith('audio/'));
const isVoice = computed(() => props.message.media_kind === 'voice');

// Use streaming URL for video/audio, direct URL for images
const mediaSrc = computed(() => {
	if (!props.message.media_path) return props.message.media_url || '';
	if (isVideo.value || (isAudio.value && !isVoice.value)) {
		return `/media/stream/${props.message.media_path}`;
	}
	return props.message.media_url || `/storage/${props.message.media_path}`;
});
</script>

<template>
	<div v-if="message.media_url || message.media_path" class="mt-2">
		<ImageViewer v-if="isImage" :src="mediaSrc" :alt="message.media_type" />
		<VoiceMessage v-else-if="isVoice" :src="mediaSrc" :duration="message.media_duration" />
		<VideoPlayer v-else-if="isVideo" :src="mediaSrc" :type="message.media_type" />
		<AudioPlayer v-else-if="isAudio" :src="mediaSrc" :message-id="message.id" :media-key="message.media_path" />
		<a v-else :href="message.media_url" target="_blank" rel="noopener" class="link link-primary text-sm">Open attachment</a>
	</div>
</template>

