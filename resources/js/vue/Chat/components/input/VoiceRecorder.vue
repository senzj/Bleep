<script setup>
import { computed, onBeforeUnmount, ref } from 'vue';
import LucideIcons from '../../../LucideIcons.vue';

const props = defineProps({
	disabled: {
		type: Boolean,
		default: false,
	},
});

const viewportHeight = ref(window.innerHeight);

const emit = defineEmits(['voice-recorded']);

const MAX_SECONDS = 60;

const holdButtonRef = ref(null);
const cancelTargetRef = ref(null);
const pointerId = ref(null);
const recorder = ref(null);
const stream = ref(null);
const chunks = ref([]);
const timerId = ref(null);
const startAt = ref(0);

const isRecording = ref(false);
const isPreparing = ref(false);
const isCancelling = ref(false);
const elapsedMs = ref(0);

const elapsedSeconds = computed(() => Math.floor(elapsedMs.value / 1000));

const isLocked = computed(() => props.disabled || isPreparing.value);

const bubbleWidthPx = computed(() => {
    const maxMs = MAX_SECONDS * 1000;
    const progress = Math.min(elapsedMs.value / maxMs, 1);
    return 80 + progress * 160; // grows from 80px to 240px
});

const buttonWidthPx = computed(() => {
	const baseWidth = 100;
	const extraWidth = elapsedSeconds.value * 2;
	return baseWidth + extraWidth;
});

const bubblePosition = computed(() => {
	if (!holdButtonRef.value) return { top: 0, left: 0 };
	const rect = holdButtonRef.value.getBoundingClientRect();
	return {
		top: rect.top,
		left: rect.left + rect.width / 2,
	};
});

const stopMeter = () => {
	if (timerId.value) {
		window.clearInterval(timerId.value);
		timerId.value = null;
	}
};

const releaseMic = () => {
	stream.value?.getTracks().forEach((track) => track.stop());
	stream.value = null;
};

const cleanupPointerCapture = () => {
	if (holdButtonRef.value && pointerId.value !== null && holdButtonRef.value.hasPointerCapture(pointerId.value)) {
		holdButtonRef.value.releasePointerCapture(pointerId.value);
	}
	pointerId.value = null;
};

const resetSessionUi = () => {
	isRecording.value = false;
	isPreparing.value = false;
	isCancelling.value = false;
	elapsedMs.value = 0;
	startAt.value = 0;
	stopMeter();
	cleanupPointerCapture();
};

const isWithinCancelArea = (x, y) => {
	const el = cancelTargetRef.value;
	if (!el) return false;
	const r = el.getBoundingClientRect();
	return x >= r.left && x <= r.right && y >= r.top && y <= r.bottom;
};

const startRecording = async () => {
	if (!navigator.mediaDevices?.getUserMedia || !window.MediaRecorder) {
		window.alert('Voice recording is not supported in this browser.');
		return false;
	}

	isPreparing.value = true;
	try {
		stream.value = await navigator.mediaDevices.getUserMedia({ audio: true });
		recorder.value = new MediaRecorder(stream.value);
		chunks.value = [];

		recorder.value.ondataavailable = (event) => {
			if (event.data?.size) chunks.value.push(event.data);
		};

		recorder.value.start(250);
		startAt.value = Date.now();
		elapsedMs.value = 0;
		timerId.value = window.setInterval(() => {
			elapsedMs.value = Date.now() - startAt.value;
			if (elapsedSeconds.value >= MAX_SECONDS) {
				finishRecording(false);
			}
		}, 100);

		isRecording.value = true;
		isPreparing.value = false;
		return true;
	} catch {
		isPreparing.value = false;
		releaseMic();
		window.alert('Microphone permission is required to send voice messages.');
		return false;
	}
};

const finishRecording = (cancelled) => {
	const activeRecorder = recorder.value;
	if (!activeRecorder || activeRecorder.state === 'inactive') {
		releaseMic();
		resetSessionUi();
		return;
	}

	const shouldEmit = !cancelled;

	activeRecorder.onstop = () => {
		const mime = activeRecorder.mimeType || 'audio/webm';
		const blob = new Blob(chunks.value, { type: mime });
		const minDurationMs = 350;

		if (shouldEmit && blob.size > 0 && elapsedMs.value >= minDurationMs) {
			emit('voice-recorded', blob, Math.round(elapsedMs.value / 1000));
		}

		recorder.value = null;
		chunks.value = [];
		releaseMic();
		resetSessionUi();
	};

	activeRecorder.stop();
	stopMeter();
	isRecording.value = false;
	isPreparing.value = false;
};

const onPointerDown = async (event) => {
	if (isLocked.value || event.button !== 0) return;

	pointerId.value = event.pointerId;
	holdButtonRef.value?.setPointerCapture(event.pointerId);

	const started = await startRecording();
	if (!started) {
		cleanupPointerCapture();
	}
};

const onPointerMove = (event) => {
	if (!isRecording.value || pointerId.value !== event.pointerId) return;
	isCancelling.value = isWithinCancelArea(event.clientX, event.clientY);
};

const onPointerUp = (event) => {
	if (!isRecording.value || pointerId.value !== event.pointerId) {
		cleanupPointerCapture();
		return;
	}
	finishRecording(isCancelling.value);
};

const onPointerCancel = (event) => {
	if (!isRecording.value || pointerId.value !== event.pointerId) return;
	finishRecording(true);
};

onBeforeUnmount(() => {
	if (isRecording.value) finishRecording(true);
	stopMeter();
	releaseMic();
});
</script>

<template>
	<div class="relative w-full">
		<button
			ref="holdButtonRef"
			type="button"
			class="btn touch-none rounded-2xl border-0 font-semibold h-11 transition-all"
			:class="isRecording ? 'bg-primary text-primary-content' : 'bg-base-200 text-base-content active:scale-[0.99]'"
			:style="{ width: isRecording ? `${buttonWidthPx}px` : '100%', minWidth: '100%' }"
			:disabled="isLocked"
			@pointerdown.prevent="onPointerDown"
			@pointermove.prevent="onPointerMove"
			@pointerup.prevent="onPointerUp"
			@pointercancel.prevent="onPointerCancel"
		>
			<LucideIcons name="mic" class="h-4 w-4 shrink-0" />
			<span v-if="isPreparing" class="truncate">
                <span class="loading loading-dots loading-sm"></span>
            </span>

			<span v-else-if="isRecording" class="truncate">
                {{ elapsedSeconds }}s
            </span>

			<span v-else class="truncate">Hold to speak</span>
		</button>

        <!-- Recording UI -->
		<Teleport to="body">
			<Transition name="fade">
				<div v-if="isRecording" class="fixed inset-0 z-50">
					<!-- Dark overlay — behind bubble and cancel -->
					<div class="fixed inset-0 bg-black/40 pointer-events-auto" />

					<!-- Bubble -->
					<div
                        class="fixed z-10 pointer-events-none"
                        :style="{
                            top: `${bubblePosition.top - 160}px`,
                            left: `${bubblePosition.left}px`,
                        }"
                    >
                        <div
                            class="h-15 rounded-full bg-linear-to-r from-primary to-primary/80 flex flex-col items-center justify-center shadow-lg"
                            :style="{
                                width: `${bubbleWidthPx}px`,
                                transition: 'width 1.5s cubic-bezier(0.25, 0.46, 0.45, 0.94), transform 1.5s cubic-bezier(0.25, 0.46, 0.45, 0.94)',
                                transform: `translateX(-${bubbleWidthPx / 2}px)`,
                            }"
                        >
                            <LucideIcons name="mic" class="h-6 w-6 text-white/90 shrink-0" />
                            <span class="text-xs text-white/80 font-bold mt-0.5 shrink-0">{{ elapsedSeconds }}s</span>
                        </div>
                    </div>

					<!-- Cancel button -->
					<div
                        class="fixed z-20 flex items-center justify-center pointer-events-auto"
                        :style="{
                            top: `${bubblePosition.top - 68}px`,
                            left: `${bubblePosition.left}px`,
                            transform: 'translateX(-50%)',
                        }"
                    >
                        <div
                            ref="cancelTargetRef"
                            class="rounded-full border-2 px-6 py-3 text-center text-sm font-semibold transition-all duration-150"
                            :class="isCancelling
                                ? 'border-error bg-error text-error-content scale-110 shadow-lg'
                                : 'border-white/30 bg-white/10 text-white/90 shadow-md'"
                        >
                            <LucideIcons name="x" :size="18" class="inline-block mr-1" />
                            Cancel
                        </div>
                    </div>
				</div>
			</Transition>
		</Teleport>
	</div>
</template>

<style scoped>
.fade-enter-active,
.fade-leave-active {
	transition: opacity 0.2s ease;
}

.fade-enter-from,
.fade-leave-to {
	opacity: 0;
}
</style>
