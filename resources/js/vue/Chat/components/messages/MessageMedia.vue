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
	// Voice notes stay in the coloured bubble; every other attachment is a
	// standalone card so it can use the full width of the message column.
	filter: {
		type: String,
		default: 'all',
	},
});

const allItems = computed(() => {
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

const isVoiceItem = (item) => item?.media_kind === 'voice';

const normalizedItems = computed(() => {
	if (props.filter === 'voice') {
		return allItems.value.filter(isVoiceItem);
	}

	if (props.filter === 'nonVoice') {
		return allItems.value.filter((item) => !isVoiceItem(item));
	}

	return allItems.value;
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
</script>

<template>
	<div v-if="normalizedItems.length > 1" class="grid w-full grid-cols-2 gap-1.5">
		<div
			v-for="item in normalizedItems"
			:key="item.id || item.media_path"
			class="min-w-0 overflow-hidden rounded-xl"
		>
			<ImageViewer v-if="itemIsImage(item)" :src="itemSrc(item)" :alt="item.media_type" />
			<VoiceMessage v-else-if="isVoiceItem(item)" :src="itemSrc(item)" :duration="item.media_duration" />
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

	<div v-else-if="normalizedItems.length === 1" class="w-full">
		<ImageViewer v-if="itemIsImage(normalizedItems[0])" :src="itemSrc(normalizedItems[0])" :alt="normalizedItems[0].media_type" />
		<VoiceMessage v-else-if="isVoiceItem(normalizedItems[0])" :src="itemSrc(normalizedItems[0])" :duration="normalizedItems[0].media_duration" />
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
