<script setup>
import { computed } from 'vue';

const props = defineProps({
	message: {
		type: Object,
		required: true,
	},
});

const isImage = computed(() => (props.message.media_type || '').startsWith('image/'));
const isVideo = computed(() => (props.message.media_type || '').startsWith('video/'));
const isAudio = computed(() => (props.message.media_type || '').startsWith('audio/'));
</script>

<template>
	<div v-if="message.media_url" class="mt-2">
		<img v-if="isImage" :src="message.media_url" alt="media" class="max-h-64 rounded-lg object-cover">

		<video v-else-if="isVideo" controls class="max-h-64 rounded-lg">
			<source :src="message.media_url" :type="message.media_type">
		</video>

		<div v-else-if="isAudio" class="space-y-1">
			<p class="text-xs font-medium">
				{{ message.media_kind === 'voice' ? 'Voice message' : 'Audio file' }}
			</p>
			<audio controls class="w-full">
				<source :src="message.media_url" :type="message.media_type">
			</audio>
		</div>

		<a v-else :href="message.media_url" target="_blank" rel="noopener" class="link link-primary text-sm">Open attachment</a>
	</div>
</template>
