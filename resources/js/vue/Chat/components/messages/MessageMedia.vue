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

const normalizedItems = computed(() => {
	if (Array.isArray(props.message.media_items) && props.message.media_items.length) {
		return props.message.media_items;
	}

	if (props.message.media_path || props.message.media_url) {
		return [{
			id: `legacy-${props.message.id}`,
			media_path: props.message.media_path,
			media_url: props.message.media_url,
			media_type: props.message.media_type,
			media_kind: props.message.media_kind,
			media_duration: props.message.media_duration,
		}];
	}

	return [];
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

const itemSrc = (item) => {
	if (!item) return '';

	if (!item.media_path) {
		return item.media_url || '';
	}

	const type = item.media_type || '';
	const isStreamType = type.startsWith('video/') || (type.startsWith('audio/') && item.media_kind !== 'voice');
    if (isStreamType) {
		return `/media/stream/${item.media_path}`;
	}

	return item.media_url || `/storage/${item.media_path}`;
};

const itemType = (item) => item?.media_type || '';
const itemIsImage = (item) => itemType(item).startsWith('image/');
const itemIsVideo = (item) => itemType(item).startsWith('video/');
const itemIsAudio = (item) => itemType(item).startsWith('audio/');
const itemIsVoice = (item) => item?.media_kind === 'voice';
</script>

<template>
	<div v-if="normalizedItems.length > 1" class="mt-2 grid grid-cols-2 gap-2">
		<div
			v-for="item in normalizedItems"
			:key="item.id || item.media_path"
			class="overflow-hidden rounded-lg"
		>
			<ImageViewer v-if="itemIsImage(item)" :src="itemSrc(item)" :alt="item.media_type" />
			<VoiceMessage v-else-if="itemIsVoice(item)" :src="itemSrc(item)" :duration="item.media_duration" />
			<VideoPlayer v-else-if="itemIsVideo(item)" :src="itemSrc(item)" :type="item.media_type" />
			<AudioPlayer
				v-else-if="itemIsAudio(item)"
				:src="itemSrc(item)"
				:message-id="`${message.id}-${item.id || item.media_path}`"
				:media-key="item.media_path"
			/>
			<a v-else :href="item.media_url || itemSrc(item)" target="_blank" rel="noopener" class="link link-primary text-sm">Open attachment</a>
		</div>
	</div>

	<div v-else-if="message.media_url || message.media_path" class="mt-2">
		<ImageViewer v-if="isImage" :src="mediaSrc" :alt="message.media_type" />
		<VoiceMessage v-else-if="isVoice" :src="mediaSrc" :duration="message.media_duration" />
		<VideoPlayer v-else-if="isVideo" :src="mediaSrc" :type="message.media_type" />
		<AudioPlayer v-else-if="isAudio" :src="mediaSrc" :message-id="message.id" :media-key="message.media_path" />
		<a v-else :href="message.media_url" target="_blank" rel="noopener" class="link link-primary text-sm">Open attachment</a>
	</div>

	<div v-else-if="normalizedItems.length === 1" class="mt-2">
		<ImageViewer v-if="itemIsImage(normalizedItems[0])" :src="itemSrc(normalizedItems[0])" :alt="normalizedItems[0].media_type" />
		<VoiceMessage v-else-if="itemIsVoice(normalizedItems[0])" :src="itemSrc(normalizedItems[0])" :duration="normalizedItems[0].media_duration" />
		<VideoPlayer v-else-if="itemIsVideo(normalizedItems[0])" :src="itemSrc(normalizedItems[0])" :type="normalizedItems[0].media_type" />
		<AudioPlayer
			v-else-if="itemIsAudio(normalizedItems[0])"
			:src="itemSrc(normalizedItems[0])"
			:message-id="message.id"
			:media-key="normalizedItems[0].media_path"
		/>
		<a v-else :href="normalizedItems[0].media_url || itemSrc(normalizedItems[0])" target="_blank" rel="noopener" class="link link-primary text-sm">Open attachment</a>
	</div>
</template>

