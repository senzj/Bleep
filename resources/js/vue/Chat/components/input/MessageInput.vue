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
const pendingMediaItems = ref([]);
const inputMode = ref('text');

const hasPendingMedia = computed(() => pendingMediaItems.value.length > 0);
const canTalk = computed(() => !sending.value && Boolean(store.state.activeConversationId));
const canSendText = computed(() => Boolean(store.state.activeConversationId));
const isVoiceMode = computed(() => inputMode.value === 'voice');

const clearPendingMedia = () => {
	pendingMediaItems.value.forEach((item) => {
		if (item.previewUrl) {
			URL.revokeObjectURL(item.previewUrl);
		}
	});

	pendingMediaItems.value = [];
};

onBeforeUnmount(() => {
    clearPendingMedia();
});

const submitText = async () => {
	const body = text.value.trim();
	if ((!body && !hasPendingMedia.value) || !store.state.activeConversationId) return;

	if (hasPendingMedia.value) {
		// Media uploads must block so we can show progress and send one message payload.
		sending.value = true;
		try {
			const uploadedItems = [];
			const total = pendingMediaItems.value.length;

			for (let i = 0; i < total; i++) {
				const item = pendingMediaItems.value[i];
				const uploaded = await store.uploadMedia(
					item.file,
					item.mediaKind,
					(pct) => {
						const overall = Math.round(((i + pct / 100) / total) * 100);
						uploadProgress.value = overall;
					},
				);

				uploadedItems.push(uploaded);
			}

			uploadProgress.value = null;
			const firstMedia = uploadedItems[0] || null;
			await store.sendMessage({
				body: body || null,
				media_path: firstMedia?.media_path || null,
				media_url: firstMedia?.media_url || null,
				media_type: firstMedia?.media_type || null,
				media_kind: firstMedia?.media_kind || 'media',
				media_items: uploadedItems,
				reply_to_id: store.state.replyToMessage?.id || null,
			});
			clearPendingMedia();
			text.value = '';
			store.clearReplyTo();
		} catch {
			window.alert('Failed to send message.');
		} finally {
			sending.value = false;
			uploadProgress.value = null;
		}
	} else {
		// Text-only: optimistic update is instant, fire HTTP in background
		const replyId = store.state.replyToMessage?.id || null;
		text.value = '';
		store.clearReplyTo();
		store.sendMessage({ body, reply_to_id: replyId }).catch(() => {
			// Silently ignore — the store already removes the optimistic message on failure
		});
	}
};

const removePendingMediaAt = (index) => {
	const item = pendingMediaItems.value[index];
	if (!item) return;

	if (item.previewUrl) {
		URL.revokeObjectURL(item.previewUrl);
	}

	pendingMediaItems.value = pendingMediaItems.value.filter((_, i) => i !== index);
};

const onMediaSelected = async (filesPayload) => {
	if (!Array.isArray(filesPayload) || !filesPayload.length || !store.state.activeConversationId) return;

	const MAX_IMAGES = 10;
	const MAX_VIDEOS = 5;

	const nextItems = filesPayload.map(({ file, mediaKind }) => ({
		file,
		mediaKind,
		previewUrl: URL.createObjectURL(file),
	}));

	const combined = [...pendingMediaItems.value, ...nextItems];

	// Count existing images and videos
	const currentImages = combined.filter((item) => item.file?.type?.startsWith('image/')).length;
	const currentVideos = combined.filter((item) => item.file?.type?.startsWith('video/')).length;

	// Check limits
	if (currentImages > MAX_IMAGES) {
		window.alert(`Maximum ${MAX_IMAGES} images per message allowed. Excess images removed.`);
		combined = combined.filter((item) => !item.file?.type?.startsWith('image/')).concat(
			combined.filter((item) => item.file?.type?.startsWith('image/')).slice(0, MAX_IMAGES)
		);
	}

	if (currentVideos > MAX_VIDEOS) {
		window.alert(`Maximum ${MAX_VIDEOS} videos per message allowed. Excess videos removed.`);
		combined = combined.filter((item) => !item.file?.type?.startsWith('video/')).concat(
			combined.filter((item) => item.file?.type?.startsWith('video/')).slice(0, MAX_VIDEOS)
		);
	}

	// Clean up dropped items
	const kept = combined;
	const dropped = filesPayload.filter((payload) => !kept.find((k) => k.file?.name === payload.file?.name));
	dropped.forEach((item) => {
		if (item.previewUrl) {
			URL.revokeObjectURL(item.previewUrl);
		}
	});

	pendingMediaItems.value = kept;
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
		<!-- Reply preview bar -->
		<div v-if="store.state.replyToMessage" class="mb-2 flex items-center gap-2 rounded-lg border-l-3 border-primary bg-base-200 px-3 py-2">
			<div class="min-w-0 flex-1">
				<p class="text-xs font-semibold text-primary">
					Replying to {{ store.state.replyToMessage.sender?.dname || store.state.replyToMessage.sender?.username || 'User' }}
				</p>
				<p class="text-xs opacity-60 truncate">
					{{ store.state.replyToMessage.body || '(media)' }}
				</p>
			</div>
			<button type="button" class="btn btn-ghost btn-xs btn-circle shrink-0" @click="store.clearReplyTo()">
				<LucideIcons name="x" class="h-3.5 w-3.5" />
			</button>
		</div>

		<div v-if="hasPendingMedia" class="mb-2">
			<div class="grid grid-cols-2 gap-2 md:grid-cols-3">
				<div
					v-for="(item, index) in pendingMediaItems"
					:key="`${item.file.name}-${index}`"
					class="relative overflow-hidden rounded-lg border border-base-300 bg-base-200 p-1"
				>
					<img
						v-if="item.file?.type?.startsWith('image/')"
						:src="item.previewUrl"
						alt="Selected media preview"
						class="h-24 w-full rounded object-cover"
					>
					<video
						v-else-if="item.file?.type?.startsWith('video/')"
						:src="item.previewUrl"
						class="h-24 w-full rounded object-cover"
						controls
					/>
					<div v-else class="line-clamp-2 px-2 py-2 text-xs">{{ item.file?.name }}</div>

					<button
						v-if="uploadProgress === null"
						class="btn btn-xs btn-circle btn-error absolute right-1 top-1"
						type="button"
						:disabled="sending"
						@click="removePendingMediaAt(index)"
					>
						<LucideIcons name="x" class="h-3 w-3" />
					</button>
				</div>

				<!-- Upload progress overlay -->
				<div
					v-if="uploadProgress !== null"
					class="col-span-full flex flex-col items-center justify-center gap-1.5 rounded bg-base-100/80 px-4 py-2"
				>
					<progress class="progress progress-primary w-full" :value="uploadProgress" max="100" />
					<span class="text-xs font-medium">{{ uploadProgress }}%</span>
				</div>
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
					@files-selected="onMediaSelected"
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
