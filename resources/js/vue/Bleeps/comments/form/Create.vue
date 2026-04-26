<script setup>
import { ref, computed, nextTick } from 'vue';
import LucideIcon from '../../../LucideIcons.vue';

const props = defineProps({
    bleepId: {
        type: [String, Number],
        required: true,
    },
    replyToCommentId: {
        type: [String, Number],
        default: null,
    },
    userAvatar: {
        type: String,
        default: '/images/avatar/default.jpg',
    },
    isAnonymousEnabled: {
        type: [Boolean, String],
        default: false,
    },
    submitLabel: {
        type: String,
        default: 'Send',
    },
    compact: {
        type: Boolean,
        default: false,
    },
});

const isAnonEnabled = computed(() => {
    if (typeof props.isAnonymousEnabled === 'string') {
        return props.isAnonymousEnabled === 'true' || props.isAnonymousEnabled === '1';
    }
    return Boolean(props.isAnonymousEnabled);
});

const userAvatarUrl = computed(() => {
    if (!props.userAvatar) return '/images/avatar/default.jpg';
    if (props.userAvatar.startsWith('http') || props.userAvatar.startsWith('/')) {
        return props.userAvatar;
    }
    return `/storage/${props.userAvatar}`;
});

const emit = defineEmits(['submitted', 'cancelled']);

const message = ref('');
const isAnonymous = ref(false);
const selectedMedia = ref(null);
const isSubmitting = ref(false);
const uploadProgress = ref(0);
const charCount = computed(() => message.value.length);
const maxChars = props.compact ? 255 : 500;
const textareaRef = ref(null);

const mediaPreview = computed(() => {
    if (!selectedMedia.value) return null;
    return {
        name: selectedMedia.value.name,
        type: selectedMedia.value.type,
        url: URL.createObjectURL(selectedMedia.value),
    };
});

const showMediaPreview = computed(() => {
  return mediaPreview.value && mediaPreview.value.url;
});

const isImageMedia = computed(() => {
  return mediaPreview.value?.type.startsWith('image/');
});

const isVideoMedia = computed(() => {
  return mediaPreview.value?.type.startsWith('video/');
});

const isAudioMedia = computed(() => {
  return mediaPreview.value?.type.startsWith('audio/');
});

const canSubmit = computed(() => {
  return (message.value.trim().length > 0 || selectedMedia.value) && !isSubmitting.value;
});

const autoGrow = (el) => {
  if (!el) return;
  const minHeight = parseInt(el.dataset.minHeight ?? 32, 10);
  el.style.height = 'auto';
  el.style.height = `${Math.max(el.scrollHeight, minHeight)}px`;
};

const handleTextareaInput = () => {
  nextTick(() => {
    autoGrow(textareaRef.value);
  });
};

const handleMediaSelect = () => {
  const input = document.createElement('input');
  input.type = 'file';
  input.accept = 'image/*,video/mp4,video/quicktime,video/webm,audio/mpeg,audio/mp3,audio/wav,audio/ogg,audio/x-m4a';
  input.onchange = (e) => {
    const file = e.target.files?.[0];
    if (file) {
      selectedMedia.value = file;
    }
  };
  input.click();
};

const clearMedia = () => {
  if (mediaPreview.value?.url) {
    URL.revokeObjectURL(mediaPreview.value.url);
  }
  selectedMedia.value = null;
};

const handleSubmit = async () => {
  if (!canSubmit.value) return;

  const messageText = message.value.trim();
  if (!messageText && !selectedMedia.value) {
    return;
  }

  isSubmitting.value = true;
  uploadProgress.value = 0;
  try {
    const endpoint = props.replyToCommentId
      ? `/bleeps/comments/${props.replyToCommentId}/replies`
      : `/bleeps/comments/${props.bleepId}/post`;

    const formData = new FormData();
    if (messageText) formData.append('message', messageText);
    formData.append('is_anonymous', isAnonymous.value ? '1' : '0');
    if (selectedMedia.value) formData.append('media', selectedMedia.value);

    const data = await new Promise((resolve, reject) => {
      const xhr = new XMLHttpRequest();
      xhr.open('POST', endpoint);
      xhr.setRequestHeader('X-CSRF-TOKEN', document.querySelector('meta[name="csrf-token"]')?.content || '');
      xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
      xhr.setRequestHeader('Accept', 'application/json');

      xhr.upload.onprogress = (event) => {
        if (event.lengthComputable) {
          uploadProgress.value = Math.round((event.loaded / event.total) * 100);
        }
      };

      xhr.onload = () => {
        if (xhr.status >= 200 && xhr.status < 300) {
          try {
            resolve(JSON.parse(xhr.responseText || '{}'));
          } catch (error) {
            reject(error);
          }
        } else if (xhr.status === 401) {
          window.location.href = '/login';
        } else {
          reject(new Error('Failed to submit comment'));
        }
      };

      xhr.onerror = () => reject(new Error('Failed to submit comment'));
      xhr.send(formData);
    });

    // Clear form
    message.value = '';
    isAnonymous.value = false;
    clearMedia();

    if (textareaRef.value) {
      textareaRef.value.style.height = 'auto';
    }

    // Notify parent - handle both response formats
    // CommentsController returns { success: true, comment: {...} }
    // CommentsRepliesController returns {...} directly
    const comment = data.comment || data;
    window.playSendSound?.();
    emit('submitted', comment);
  } catch (error) {
    console.error('Error submitting comment:', error);
    alert('Failed to submit comment. Please try again.');
  } finally {
    isSubmitting.value = false;
    uploadProgress.value = 0;
  }
};

const handleCancel = () => {
  message.value = '';
  isAnonymous.value = false;
  clearMedia();
  emit('cancelled');
};
</script>

<template>
    <div class="comment-form">
        <!-- Media Preview -->
        <div v-if="showMediaPreview" class="mb-1">
            <div class="inline-flex max-w-44 w-full relative rounded-xl overflow-hidden bg-base-200 shadow">
                <figure v-if="isImageMedia" class="w-full">
                    <img :src="mediaPreview.url" :alt="mediaPreview.name" class="w-full h-45 object-cover" />
                </figure>
              <video v-else-if="isVideoMedia" controls class="w-full h-45">
                    <source :src="mediaPreview.url" />
                </video>
              <audio v-else-if="isAudioMedia" controls class="w-full">
                <source :src="mediaPreview.url" />
              </audio>

                <button
                    type="button"
                    class="absolute top-2 right-2 btn btn-xs btn-circle btn-error text-white"
                    @click="clearMedia"
                >
                    <LucideIcon name="x" size="12" />
                </button>
            </div>
        </div>

        <form @submit.prevent="handleSubmit" class="flex flex-col gap-3">
            <!-- Textarea -->
            <div>
                <textarea
                    ref="textareaRef"
                    v-model="message"
                    :maxlength="maxChars"
                    rows="1"
                    :data-min-height="compact ? 32 : 48"
                    class="textarea textarea-bordered w-full resize-none text-sm leading-snug"
                    :class="{ 'min-h-9': compact, 'min-h-12': !compact }"
                    :placeholder="replyToCommentId ? 'Write a reply...' : 'Write a comment...'"
                    :disabled="isSubmitting"
                    @input="handleTextareaInput"
                ></textarea>

                <div v-if="!compact" class="text-xs text-base-content/50 mt-1 text-right">
                    {{ charCount }}/{{ maxChars }}
                </div>
            </div>

            <!-- Controls Row -->
            <div class="flex items-end gap-2">
                <!-- Media Button -->
                <button
                    v-if="!compact"
                    type="button"
                    class="btn btn-secondary btn-sm"
                    :disabled="isSubmitting"
                    @click="handleMediaSelect"
                >
                    <LucideIcon name="image" size="16" />
                    Media
                </button>

                <!-- NSFW Toggle -->


                <!-- Anonymous Toggle -->
                <div v-if="isAnonymousEnabled" class="flex items-center gap-2 shrink-0">
                    <label class="relative inline-flex cursor-pointer">
                        <input v-model="isAnonymous" type="checkbox" class="peer sr-only" />

                        <div class="w-15 h-9 bg-base-300 peer-checked:bg-base-300 rounded-full peer-focus:ring-2 peer-focus:ring-primary transition-all border border-gray-300"></div>

                        <!-- Anonymous Icon -->
                        <div v-if="isAnonymous" class="absolute top-1 left-7 size-7 rounded-full transition-all duration-500 bg-base-100 flex items-center justify-center">
                            <LucideIcon name="hat-glasses" size="16" class="text-base-content/80" />
                        </div>

                        <!-- User Avatar -->
                        <div v-else class="absolute top-1 left-1 size-7 rounded-full transition-all duration-300 bg-cover bg-center"
                            :style="{ backgroundImage: `url('${userAvatarUrl}')` }"
                        ></div>
                    </label>
                </div>

                <!-- Submit Button -->
                <button
                    type="submit"
                    :disabled="!canSubmit"
                    class="btn btn-primary btn-sm ml-auto"
                >
                    <LucideIcon name="send" size="16" />
                    {{ submitLabel }}
                </button>
            </div>

            <div v-if="isSubmitting" class="flex flex-col gap-1">
                <progress class="progress progress-primary w-full" :value="uploadProgress" max="100"></progress>
                <div class="text-xs text-base-content/60 text-right">Uploading {{ uploadProgress }}%</div>
            </div>

            <!-- Cancel Button (for replies) -->
            <button
                v-if="replyToCommentId"
                type="button"
                class="btn btn-ghost btn-sm"
                :disabled="isSubmitting"
                @click="handleCancel"
            >
                Cancel
            </button>
        </form>
    </div>
</template>

<style scoped>
.comment-form textarea {
  max-height: calc(var(--max-height, 20) * 1.5rem);
}
</style>
