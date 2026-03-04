<script setup>
import { computed, onBeforeUnmount, ref } from 'vue';
import MediaUploader from './MediaUploader.vue';
import TextAreaInput from './TextAreaInput.vue';
import VoiceRecorder from './VoiceRecorder.vue';
import { useMessageStore } from '../../store/useMessageStore';

const store = useMessageStore();

const text = ref('');
const sending = ref(false);
const pendingMediaFile = ref(null);
const pendingMediaKind = ref('media');
const pendingMediaPreviewUrl = ref('');

const hasPendingMedia = computed(() => Boolean(pendingMediaFile.value));
const pendingMediaIsImage = computed(() => pendingMediaFile.value?.type?.startsWith('image/'));
const pendingMediaIsVideo = computed(() => pendingMediaFile.value?.type?.startsWith('video/'));

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

	sending.value = true;
	try {
		if (hasPendingMedia.value) {
			const uploaded = await store.uploadMedia(pendingMediaFile.value, pendingMediaKind.value);
			await store.sendMessage({
				body: body || null,
				media_path: uploaded.media_path,
				media_url: uploaded.media_url,
				media_type: uploaded.media_type,
				media_kind: uploaded.media_kind,
			});
			clearPendingMedia();
		} else {
			await store.sendMessage({ body });
		}

		text.value = '';
	} catch {
		window.alert('Failed to send message.');
	} finally {
		sending.value = false;
	}
};

const onMediaSelected = async ({ file, mediaKind }) => {
	if (!file || !store.state.activeConversationId) return;

	clearPendingMedia();
	pendingMediaFile.value = file;
	pendingMediaKind.value = mediaKind;
	pendingMediaPreviewUrl.value = URL.createObjectURL(file);
};

const onVoiceRecorded = async (blob) => {
	if (!blob || !store.state.activeConversationId) return;

	sending.value = true;
	try {
		await store.sendVoiceMessage(blob);
	} catch {
		window.alert('Failed to send voice message.');
	} finally {
		sending.value = false;
	}
};

const onTyping = () => {
	store.sendTyping();
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

				<button
					class="btn btn-xs btn-circle btn-error absolute right-1 top-1"
					type="button"
					:disabled="sending"
					@click="clearPendingMedia"
				>
					✕
				</button>
			</div>
		</div>

		<div class="flex items-center gap-2">
			<div class="flex items-center gap-2">
				<MediaUploader :disabled="sending || !store.state.activeConversationId" @file-selected="onMediaSelected" />
				<VoiceRecorder
					:disabled="sending || !store.state.activeConversationId"
					@voice-recorded="onVoiceRecorded"
				/>
			</div>

			<TextAreaInput
				v-model="text"
				:disabled="sending || !store.state.activeConversationId"
				@submit="submitText"
				@typing="onTyping"
			/>

			<button
				class="btn btn-primary min-w-20"
				:disabled="sending || !store.state.activeConversationId"
				@click="submitText"
			>
				Send
			</button>
		</div>
	</div>
</template>
