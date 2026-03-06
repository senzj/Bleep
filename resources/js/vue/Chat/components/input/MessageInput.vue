<script setup>
import { computed, onBeforeUnmount, ref } from 'vue';
import MediaUploader from './MediaUploader.vue';
import TextAreaInput from './TextAreaInput.vue';
import VoiceRecorder from './VoiceRecorder.vue';
import { useMessageStore } from '../../store/useMessageStore';
import LucideIcons from '../../../LucideIcons.vue';

const store = useMessageStore();

const text = ref('');
const sending = ref(false);
const uploadProgress = ref(null); // null = idle, 0-100 = uploading
const pendingMediaFile = ref(null);
const pendingMediaKind = ref('media');
const pendingMediaPreviewUrl = ref('');
const inputMode = ref('text');

const hasPendingMedia = computed(() => Boolean(pendingMediaFile.value));
const pendingMediaIsImage = computed(() => pendingMediaFile.value?.type?.startsWith('image/'));
const pendingMediaIsVideo = computed(() => pendingMediaFile.value?.type?.startsWith('video/'));
const canTalk = computed(() => !sending.value && Boolean(store.state.activeConversationId));
const canSendText = computed(() => Boolean(store.state.activeConversationId));
const isVoiceMode = computed(() => inputMode.value === 'voice');

const clearPendingMedia = () => {
    if (pendingMediaPreviewUrl.value) {
        URL.revokeObjectURL(pendingMediaPreviewUrl.value);
    }

    pendingMediaFile.value = null;
    pendingMediaKind.value = 'media';
    pendingMediaPreviewUrl.value = '';
};

onBeforeUnmount(() => {
    clearPendingMedia();
});

const submitText = async () => {
	const body = text.value.trim();
	if ((!body && !hasPendingMedia.value) || !store.state.activeConversationId) return;

	if (hasPendingMedia.value) {
		// Media uploads must block so we can show progress
		sending.value = true;
		try {
			uploadProgress.value = 0;
			const uploaded = await store.uploadMedia(
				pendingMediaFile.value,
				pendingMediaKind.value,
				(pct) => { uploadProgress.value = pct; },
			);
			uploadProgress.value = null;
			await store.sendMessage({
				body: body || null,
				media_path: uploaded.media_path,
				media_url: uploaded.media_url,
				media_type: uploaded.media_type,
				media_kind: uploaded.media_kind,
			});
			clearPendingMedia();
			text.value = '';
		} catch {
			window.alert('Failed to send message.');
		} finally {
			sending.value = false;
			uploadProgress.value = null;
		}
	} else {
		// Text-only: optimistic update is instant, fire HTTP in background
		text.value = '';
		store.sendMessage({ body }).catch(() => {
			// Silently ignore — the store already removes the optimistic message on failure
		});
	}
};

const onMediaSelected = async ({ file, mediaKind }) => {
	if (!file || !store.state.activeConversationId) return;

	clearPendingMedia();
	pendingMediaFile.value = file;
	pendingMediaKind.value = mediaKind;
	pendingMediaPreviewUrl.value = URL.createObjectURL(file);
};

const onVoiceRecorded = async (blob, durationSeconds) => {
	if (!blob || !store.state.activeConversationId) return;

	sending.value = true;
	uploadProgress.value = 0;
	try {
		await store.sendVoiceMessage(blob, durationSeconds || 0, (pct) => { uploadProgress.value = pct; });
	} catch {
		window.alert('Failed to send voice message.');
	} finally {
		sending.value = false;
		uploadProgress.value = null;
	}
};

const onTyping = () => {
	store.sendTyping();
};

const toggleInputMode = () => {
	if (hasPendingMedia.value && inputMode.value === 'text') return;
	inputMode.value = inputMode.value === 'text' ? 'voice' : 'text';
};
</script>

<template>
	<div class="border-base-300 bg-base-100 border-t p-3">
		<div v-if="hasPendingMedia" class="mb-2">
			<div class="relative inline-block overflow-hidden rounded-lg border border-base-300 bg-base-200 p-1">
				<img
					v-if="pendingMediaIsImage"
					:src="pendingMediaPreviewUrl"
					alt="Selected media preview"
					class="max-h-40 rounded object-cover"
				>
				<video v-else-if="pendingMediaIsVideo" :src="pendingMediaPreviewUrl" class="max-h-40 rounded" controls />
				<div v-else class="px-3 py-2 text-xs">{{ pendingMediaFile?.name }}</div>

				<!-- Upload progress overlay -->
				<div
					v-if="uploadProgress !== null"
					class="absolute inset-0 flex flex-col items-center justify-center gap-1.5 rounded bg-base-100/80 px-4"
				>
					<progress class="progress progress-primary w-full" :value="uploadProgress" max="100" />
					<span class="text-xs font-medium">{{ uploadProgress }}%</span>
				</div>

				<button
					v-if="uploadProgress === null"
					class="btn btn-xs btn-circle btn-error absolute right-1 top-1"
					type="button"
					:disabled="sending"
					@click="clearPendingMedia"
				>
					<LucideIcons name="x" class="h-3 w-3" />
				</button>
			</div>
		</div>

		<!-- Voice upload progress (no media preview to overlay onto) -->
		<div v-if="uploadProgress !== null && !hasPendingMedia" class="mb-2 flex items-center gap-2">
			<span class="text-base-content/60 shrink-0 text-xs">Sending voice…</span>
			<progress class="progress progress-primary flex-1" :value="uploadProgress" max="100" />
			<span class="text-base-content/70 shrink-0 text-xs font-medium">{{ uploadProgress }}%</span>
		</div>

		<div class="flex items-end gap-2">
			<div class="flex shrink-0 items-center gap-2">
				<MediaUploader
					v-if="!isVoiceMode"
					:disabled="sending || !store.state.activeConversationId"
					@file-selected="onMediaSelected"
				/>

				<button
					type="button"
					class="btn btn-circle"
					:class="isVoiceMode ? 'btn-primary' : 'btn-outline'"
					:disabled="sending || !store.state.activeConversationId"
					:title="isVoiceMode ? 'Switch to keyboard' : 'Switch to voice hold'"
					@click="toggleInputMode"
				>
					<LucideIcons :name="isVoiceMode ? 'keyboard' : 'mic'" class="h-5 w-5" />
				</button>
			</div>

			<div class="min-w-0 flex-1">
				<VoiceRecorder
					v-if="isVoiceMode"
					:disabled="!canTalk"
					@voice-recorded="onVoiceRecorded"
				/>

				<div v-else class="flex items-center gap-2">
					<TextAreaInput
						v-model="text"
						:disabled="!canSendText"
						@submit="submitText"
						@typing="onTyping"
					/>

					<button
						class="btn btn-primary min-w-20"
						:disabled="!canSendText"
						@click="submitText"
					>
						<LucideIcons name="send" class="h-5 w-5" />
						Send
					</button>
				</div>
			</div>
		</div>
	</div>
</template>
