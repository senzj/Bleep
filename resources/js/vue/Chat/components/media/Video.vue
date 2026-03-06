<script setup>
import { onUnmounted, ref, nextTick } from 'vue';
import LucideIcons from '../../../LucideIcons.vue';

const props = defineProps({
	src: { type: String, required: true },
	type: { type: String, default: 'video/mp4' },
});

const videoRef = ref(null);
const isPlaying = ref(false);
const wrapperRef = ref(null);

const openMediaModal = async () => {
	const v = videoRef.value;
	if (v) v.pause();

	// Click the hidden data-media-index element to trigger media modal
	const mediaIndexEl = wrapperRef.value?.querySelector('[data-media-index]');
	if (mediaIndexEl) {
		mediaIndexEl.click();
	}
};

// 1st click → play inline, 2nd click (while playing) → open modal
const handleVideoClick = () => {
	const v = videoRef.value;
	if (!v) return;

	if (!isPlaying.value) {
		v.play().catch(() => {});
	} else {
		openMediaModal();
	}
};

const onPlay = () => { isPlaying.value = true; };
const onPause = () => { isPlaying.value = false; };
const onEnded = () => { isPlaying.value = false; };

onUnmounted(() => videoRef.value?.pause());
</script>

<template>
	<div ref="wrapperRef" data-bleep-media>
		<!-- Hidden element for media modal to pick up -->
		<div
			class="hidden"
			data-media-index="0"
			data-media-type="video"
			:data-media-src="src"
			:data-media-mime="type"
		></div>

		<!-- Visible video player -->
		<div
			class="relative inline-block max-h-64 cursor-pointer overflow-hidden rounded-lg"
			@click="handleVideoClick"
		>
			<video
				ref="videoRef"
				class="block max-h-64 rounded-lg"
				:src="src"
				preload="metadata"
				playsinline
				@play="onPlay"
				@pause="onPause"
				@ended="onEnded"
			/>

			<!-- Play overlay (only shown when paused) -->
			<Transition name="overlay-fade">
				<div
					v-if="!isPlaying"
					class="absolute inset-0 flex items-center justify-center bg-black/30 hover:bg-black/40 transition-colors"
				>
					<div class="flex h-14 w-14 items-center justify-center rounded-full bg-white/40 shadow-xl">
						<LucideIcons name="play" class="h-5 w-5 text-white ml-0.5" />
					</div>
				</div>
			</Transition>

			<!-- Expand hint (shown when playing) -->
			<Transition name="overlay-fade">
				<div
					v-if="isPlaying"
					class="absolute bottom-2 right-2 flex items-center justify-center rounded-full bg-black/40 p-1"
					title="Tap to open fullscreen"
				>
					<LucideIcons name="expand" class="h-3 w-3 text-white" />
				</div>
			</Transition>
		</div>
	</div>
</template>

<style scoped>
.overlay-fade-enter-active,
.overlay-fade-leave-active {
	transition: opacity 0.15s ease;
}
.overlay-fade-enter-from,
.overlay-fade-leave-to {
	opacity: 0;
}
</style>
