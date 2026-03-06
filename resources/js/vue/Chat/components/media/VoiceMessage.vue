<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue';
import LucideIcons from '../../../LucideIcons.vue';

const props = defineProps({
	src: { type: String, required: true },
	duration: { type: Number, default: 0 },
});

const audioRef = ref(null);
const isPlaying = ref(false);
const isLoading = ref(false);
const resolvedDuration = ref(0);

// Use server-provided duration first; fall back to audio metadata
const displayDuration = computed(() => props.duration || resolvedDuration.value || 0);

const formatDuration = (s) => {
	if (!s || !isFinite(s)) return '0"';
	const m = Math.floor(s / 60);
	const sec = Math.round(s % 60);
	return m > 0 ? `${m}:${sec.toString().padStart(2, '0')}"` : `${sec}"`;
};

const bubbleWidth = computed(() => {
	const dur = displayDuration.value;
	if (!dur || !isFinite(dur)) return 80;
	return Math.min(80 + Math.floor(dur) * 2, 320);
});

// WebM seek trick — force browser to resolve real duration
const resolveWebmDuration = () => {
	const audio = audioRef.value;
	if (!audio) return;
	if (isFinite(audio.duration) && audio.duration > 0) {
		resolvedDuration.value = audio.duration;
		return;
	}
	const handler = () => {
		if (isFinite(audio.duration) && audio.duration > 0) {
			resolvedDuration.value = audio.duration;
			audio.removeEventListener('timeupdate', handler);
			audio.currentTime = 0;
		}
	};
	audio.addEventListener('timeupdate', handler);
	audio.currentTime = 1e10;
};

// For old messages without server duration, load metadata to discover it
onMounted(() => {
	if (props.duration > 0) return;
	const audio = audioRef.value;
	if (!audio) return;
	const onLoaded = () => {
		audio.removeEventListener('loadedmetadata', onLoaded);
		resolveWebmDuration();
	};
	audio.addEventListener('loadedmetadata', onLoaded);
	audio.preload = 'metadata';
	audio.load();
});

const togglePlay = async () => {
	const audio = audioRef.value;
	if (!audio) return;

	if (window.pauseAllAudio) window.pauseAllAudio();

	if (isPlaying.value) {
		audio.pause();
	} else {
		isLoading.value = true;
		try { await audio.play(); } finally { isLoading.value = false; }
	}
};

const onPlay = () => { isPlaying.value = true; isLoading.value = false; };
const onPause = () => { isPlaying.value = false; };
const onEnded = () => {
	isPlaying.value = false;
	if (audioRef.value) audioRef.value.currentTime = 0;
};

const onDurationChange = () => {
	const d = audioRef.value?.duration;
	if (d && isFinite(d) && d > 0) {
		resolvedDuration.value = d;
	}
};

onUnmounted(() => audioRef.value?.pause());
</script>

<template>
	<div
		class="inline-flex items-center gap-2 rounded-full cursor-pointer select-none transition-all"
		:style="{ width: `${bubbleWidth}px` }"
		@click="togglePlay"
	>
		<div class="flex shrink-0 items-center justify-center">
			<span v-if="isLoading" class="loading loading-spinner loading-sm text-white" />
			<LucideIcons v-else-if="isPlaying" name="volume-2" :size="18" class="text-white" />
			<LucideIcons v-else name="mic" :size="18" class="text-white" />
		</div>

		<span class="text-sm font-medium text-white tabular-nums shrink-0">
			{{ formatDuration(displayDuration) }}
		</span>

		<audio
			ref="audioRef"
			:src="src"
			preload="none"
			class="hidden"
			@play="onPlay"
			@pause="onPause"
			@ended="onEnded"
			@durationchange="onDurationChange"
		/>
	</div>
</template>
