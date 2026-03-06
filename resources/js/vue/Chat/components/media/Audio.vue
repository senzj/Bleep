<script setup>
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';
import LucideIcons from '../../../LucideIcons.vue';

const props = defineProps({
	src: { type: String, required: true },
	messageId: { type: [String, Number], default: null },
	mediaKey: { type: String, default: '' },
});

const showVolumeSlider = ref(false);
const showSpeedMenu = ref(false);
const volume = ref(100);
const lastVolume = ref(100);
const isMuted = ref(false);

const volumeIcon = computed(() => {
	if (volume.value === 0) return 'volume-x';
	if (volume.value <= 50) return 'volume-1';
	return 'volume-2';
});

const fileName = computed(() => {
	if (!props.src) return 'audio';
	const clean = props.src.split('?')[0];
	const parts = clean.split('/');
	return parts[parts.length - 1] || 'audio';
});

const audioId = computed(() => {
	const base = props.mediaKey || props.src || 'audio';
	const safeBase = base.replace(/[^a-zA-Z0-9_-]/g, '-').slice(-24);
	const idPart = props.messageId ? `chat-${props.messageId}` : 'chat';
	return `${idPart}-${safeBase}`;
});

const getAudioEl = () => document.getElementById(audioId.value);

function applyVolume(val) {
	const clamped = Math.min(100, Math.max(0, Number(val)));
	volume.value = clamped;
	isMuted.value = clamped === 0;
	const audio = getAudioEl();
	if (audio) {
		audio.volume = clamped / 100;
		if (clamped > 0) lastVolume.value = clamped;
		try { localStorage.setItem('bleepAudioVolume', String(clamped / 100)); } catch {}
	}
}

function onSliderInput(e) {
	applyVolume(Number(e.target.value));
}

function toggleMute() {
	if (isMuted.value) {
		applyVolume(lastVolume.value || 100);
	} else {
		lastVolume.value = volume.value;
		applyVolume(0);
	}
}

const ensureAudioScript = async () => {
	if (!window.initAudioPlayers) {
		await import('../../../../bleep/posts/media/audio.js');
	}
};

const initPlayers = async () => {
	await ensureAudioScript();
	await nextTick();

	try {
		const stored = parseFloat(localStorage.getItem('bleepAudioVolume'));
		if (Number.isFinite(stored)) {
			volume.value = Math.round(stored * 100);
			lastVolume.value = volume.value;
		}
	} catch {}

	if (window.initAudioPlayers) window.initAudioPlayers();
};

const handleOutsideClick = () => {
	showVolumeSlider.value = false;
	showSpeedMenu.value = false;
};

onMounted(() => {
	initPlayers();
	document.addEventListener('click', handleOutsideClick);
});

onUnmounted(() => {
	document.removeEventListener('click', handleOutsideClick);
});

watch(() => props.src, () => {
	if (props.src) initPlayers();
});
</script>

<template>
	<div class="rounded-xl border border-base-300 bg-base-200" data-bleep-media data-audio-player>
		<div class="p-3">
			<!-- Progress Bar -->
			<div class="relative bg-base-300 rounded-full overflow-hidden h-2 cursor-pointer group select-none mb-3" data-audio-progress-track>
				<div class="audio-buffered absolute left-0 top-0 h-full bg-base-content/10 rounded-full transition-all duration-300" style="width:0%"></div>
				<div class="audio-progress absolute left-0 top-0 h-full bg-primary rounded-full transition-all duration-300" style="width:0%"></div>
				<div class="audio-hover-progress absolute left-0 top-0 h-full bg-primary/30 rounded-full opacity-0 group-hover:opacity-100 transition-all duration-200 pointer-events-none" style="width:0%"></div>
			</div>

			<!-- Controls Row -->
			<div class="flex items-center justify-between gap-2">
				<!-- Left: Time -->
				<div class="text-xs text-base-content/60 min-w-16 tabular-nums font-mono">
					<span class="audio-current-time" :data-audio-id="audioId">0:00</span>
					<span class="mx-0.5">/</span>
					<span class="audio-total-time" :data-audio-id="audioId">0:00</span>
				</div>

				<!-- Center: Play/Pause -->
				<button type="button"
					class="audio-play-btn btn btn-primary btn-sm btn-circle shadow-md hover:scale-105 active:scale-95 transition-transform"
					:data-audio-id="audioId">
					<span class="play-icon pointer-events-none"><LucideIcons name="play" :size="18" class="pointer-events-none" /></span>
					<span class="pause-icon pointer-events-none" style="display:none;"><LucideIcons name="pause" :size="18" class="pointer-events-none" /></span>
					<span class="loading-icon pointer-events-none" style="display:none;"><span class="loading loading-spinner loading-sm"></span></span>
				</button>

				<!-- Right: Speed + Download + Volume -->
				<div class="flex items-center gap-2 min-w-16 justify-end">
					<!-- Speed -->
					<div class="relative" @click.stop>
						<button type="button"
							class="audio-speed-btn btn btn-ghost btn-xs"
							:data-audio-id="audioId"
							@click="showSpeedMenu = !showSpeedMenu; showVolumeSlider = false">
							<span class="audio-speed-label text-xs font-medium">1x</span>
						</button>
						<div v-if="showSpeedMenu"
							class="absolute bottom-full right-0 mb-2 bg-base-100 border border-base-300 rounded-lg shadow-lg p-1 w-20 z-50"
							@click.stop>
							<button v-for="speed in [0.5, 0.75, 1, 1.25, 1.5, 2]" :key="speed"
								type="button"
								class="audio-speed-option text-xs w-full text-left px-2 py-1 rounded hover:bg-base-200"
								:class="{ 'bg-primary/20 font-semibold': speed === 1 }"
								:data-speed="speed"
								@click="showSpeedMenu = false">
								{{ speed }}x
							</button>
						</div>
					</div>

					<!-- Download -->
					<a :href="src" :download="fileName"
						class="btn btn-ghost btn-xs btn-circle hidden sm:flex"
						title="Download" @click.stop>
						<LucideIcons name="download" :size="14" />
					</a>

					<!-- Volume -->
					<div class="relative" @click.stop>
						<button type="button"
							class="btn btn-ghost btn-xs btn-circle"
							:class="{ 'text-error': isMuted }"
							@click="showVolumeSlider = !showVolumeSlider; showSpeedMenu = false">
							<LucideIcons :name="volumeIcon" :size="14" class="pointer-events-none" />
						</button>
						<Transition
							enter-active-class="transition duration-150 ease-out"
							enter-from-class="opacity-0 scale-95"
							enter-to-class="opacity-100 scale-100"
							leave-active-class="transition duration-100 ease-in"
							leave-from-class="opacity-100 scale-100"
							leave-to-class="opacity-0 scale-95">
							<div v-if="showVolumeSlider"
								class="absolute bottom-full right-0 mb-2 z-50 bg-base-100 border border-base-300 rounded-xl shadow-lg flex items-center gap-2 px-3 py-2"
								style="min-width:160px;" @click.stop>
								<button type="button"
									class="shrink-0 transition-colors"
									:class="isMuted ? 'text-error hover:text-error/70' : 'text-base-content/60 hover:text-base-content'"
									@click="toggleMute">
									<LucideIcons :name="isMuted ? 'volume-x' : volumeIcon" :size="14" class="pointer-events-none" />
								</button>
								<input type="range"
									class="audio-volume-slider range range-xs range-primary flex-1"
									:data-audio-id="audioId"
									min="0" max="100" :value="volume"
									@input="onSliderInput" />
								<span class="text-xs text-base-content/60 w-8 text-right tabular-nums">{{ volume }}%</span>
							</div>
						</Transition>
					</div>
				</div>
			</div>

			<!-- Hidden audio element -->
			<audio class="hidden audio-element" :id="audioId" preload="none" :data-src="src"></audio>
		</div>
	</div>
</template>
