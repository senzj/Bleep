<script setup>
import { ref, computed, nextTick } from 'vue';
import LucideIcon from '../../../LucideIcons.vue';
import CommentMedia from './Media.vue';
import { formatRelativeTime } from '../../../../utils/relativeTime.js';

const props = defineProps({
    comment: {
        type: Object,
        required: true,
    },
    bleep: {
        type: Object,
        required: true,
    },
    depth: {
        type: Number,
        default: 0,
    },
    userAvatar: {
        type: String,
        default: '/images/avatar/default.jpg',
    },
    isAnonymousEnabled: {
        type: [Boolean, String],
        default: false,
    },
    authenticatedUserId: {
        type: [String, Number],
        default: null,
    },
    viewMoreReplies: {
        type: Boolean,
        default: false,
    },
});

const depthMax = 5;

const emit = defineEmits(['reply', 'edit', 'delete', 'like']);

// ── Anonymous helpers
const isAnonEnabled = computed(() => {
    if (typeof props.isAnonymousEnabled === 'string') {
        return props.isAnonymousEnabled === 'true' || props.isAnonymousEnabled === '1';
    }
    return Boolean(props.isAnonymousEnabled);
});

const isCommentAnonymous = computed(() => {
    return props.comment.is_anonymous === true
        || props.comment.is_anonymous === 1
        || props.comment.is_anonymous === '1';
});

// Whether the bleep itself was posted anonymously
const isBleepAnonymous = computed(() => {
    return props.bleep.is_anonymous === true
        || props.bleep.is_anonymous === 1
        || props.bleep.is_anonymous === '1';
});

// ── Ownership and permissions
const isOwner = computed(() => Boolean(props.comment.canEdit));
const canDelete = computed(() => Boolean(props.comment.canDelete));

// ── OP detection
const isOP = computed(() => {
    return props.comment.isOP === true;
});

// ── Avatar — null signals the template to show the hat-glasses icon
const userAvatarUrl = computed(() => {
    if (isCommentAnonymous.value) return null;
    const picture = props.comment.user?.profile_picture;
    if (!picture) return '/images/avatar/default.jpg';
    if (picture.startsWith('http') || picture.startsWith('/')) return picture;
    return `/storage/${picture}`;
});

const userAvatarForReply = computed(() => {
    if (!props.userAvatar) return '/images/avatar/default.jpg';
    if (props.userAvatar.startsWith('http') || props.userAvatar.startsWith('/')) return props.userAvatar;
    return `/storage/${props.userAvatar}`;
});

// ── Display info
const displayName = computed(() => props.comment.display_name || 'Anonymous');

const username = computed(() => {
    if (isCommentAnonymous.value) return 'anonymous';
    return props.comment.user?.username || 'anonymous';
});

const userProfileLink = computed(() => {
    if (isCommentAnonymous.value) return '#';
    return props.comment.user?.username ? `/bleeper/${props.comment.user.username}` : '#';
});

const formattedDate = computed(() => {
    if (!props.comment.created_at) return '';
    const userTimezone = props.comment.user?.timezone
        || Intl.DateTimeFormat().resolvedOptions().timeZone
        || 'UTC';

    console.log('Comment created_at:', props.comment.created_at);

    return formatRelativeTime(props.comment.created_at, userTimezone);
});

// Only show role badge for elevated roles — 'user' is the default and should
// never display a badge.
const userRole = computed(() => {
    if (isCommentAnonymous.value) return null;
    const role = props.comment.user?.role;
    return (role === 'admin' || role === 'moderator') ? role : null;
});

const isVerified = computed(() => {
    if (isCommentAnonymous.value) return false;
    return props.comment.user?.is_verified || false;
});

// ── Dynamic sizing
const isReply = computed(() => props.depth > 0);
const avatarSize = computed(() => isReply.value ? 'w-8 h-8' : 'w-10 h-10');
const textSize = computed(() => isReply.value ? 'text-xs' : 'text-sm');
const iconSize = computed(() => isReply.value ? 12 : 14);
const componentClass = computed(() => isReply.value ? 'bg-base-100' : 'bg-base-200');
const borderClass = computed(() => isReply.value ? 'border-base-300/50' : 'border-base-300');

// ── Likes
const isLiked = computed(() => props.comment.liked === true);
const likesCount = computed(() => props.comment.likes_count || 0);

// ── Replies
const showReplies = ref(false);
const isLoadingReplies = ref(false);
const replies = ref([]);
const hasMoreReplies = ref(false);
const nextReplyPage = ref(1);

const toggleReplies = async () => {
    showReplies.value = !showReplies.value;
    if (showReplies.value && replies.value.length === 0 && depthMax) {
        await loadReplies();
    }
};

const loadReplies = async () => {
    if (isLoadingReplies.value || !showReplies.value) return;
    isLoadingReplies.value = true;
    try {
        const response = await fetch(
            `/bleeps/comments/${props.comment.id}/replies?page=${nextReplyPage.value}&depth=${props.depth + 1}`,
            { headers: { 'Accept': 'application/json' } }
        );
        if (!response.ok) throw new Error('Failed to load replies');
        const data = await response.json();
        replies.value = [...replies.value, ...data.replies];
        hasMoreReplies.value = data.has_more;
        nextReplyPage.value = data.next_page || nextReplyPage.value + 1;
    } catch (error) {
        console.error('Error loading replies:', error);
    } finally {
        isLoadingReplies.value = false;
    }
};

// ── Reply form
const showReplyForm = ref(false);
const replyMessage = ref('');
const replyMedia = ref(null);
const replyIsAnonymous = ref(false);
const isSubmittingReply = ref(false);
const replyUploadProgress = ref(0);

const handleReply = () => {
    showReplyForm.value = !showReplyForm.value;
    if (!showReplyForm.value) {
        replyMessage.value = '';
        replyMedia.value = null;
        replyIsAnonymous.value = false;
    }
};

const replyMediaPreview = computed(() => {
    if (!replyMedia.value) return null;
    return {
        name: replyMedia.value.name,
        type: replyMedia.value.type,
        url: URL.createObjectURL(replyMedia.value),
    };
});

const clearReplyMedia = () => {
    if (replyMediaPreview.value?.url) URL.revokeObjectURL(replyMediaPreview.value.url);
    replyMedia.value = null;
};

const handleMediaSelect = () => {
    const input = document.createElement('input');
    input.type = 'file';
    input.accept = 'image/*,video/mp4,video/quicktime,video/webm,audio/mpeg,audio/mp3,audio/wav,audio/ogg,audio/x-m4a';
    input.onchange = (e) => {
        const file = e.target.files?.[0];
        if (file) replyMedia.value = file;
    };
    input.click();
};

const submitReply = async () => {
    const messageText = replyMessage.value.trim();
    if (!messageText && !replyMedia.value) return;

    isSubmittingReply.value = true;
    replyUploadProgress.value = 0;
    try {
        const formData = new FormData();
        if (messageText) formData.append('message', messageText);
        formData.append('is_anonymous', replyIsAnonymous.value ? '1' : '0');
        if (replyMedia.value) formData.append('media', replyMedia.value);

        const data = await new Promise((resolve, reject) => {
            const xhr = new XMLHttpRequest();
            xhr.open('POST', `/bleeps/comments/${props.comment.id}/replies`);
            xhr.setRequestHeader('X-CSRF-TOKEN', document.querySelector('meta[name="csrf-token"]')?.content || '');
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
            xhr.setRequestHeader('Accept', 'application/json');
            xhr.upload.onprogress = (event) => {
                if (event.lengthComputable) replyUploadProgress.value = Math.round((event.loaded / event.total) * 100);
            };
            xhr.onload = () => {
                if (xhr.status >= 200 && xhr.status < 300) {
                    try { resolve(JSON.parse(xhr.responseText || '{}')); }
                    catch (e) { reject(e); }
                } else if (xhr.status === 401) {
                    window.location.href = '/login';
                } else {
                    reject(new Error('Failed to post reply'));
                }
            };
            xhr.onerror = () => reject(new Error('Failed to post reply'));
            xhr.send(formData);
        });

        const reply = data.comment || data;
        if (!showReplies.value) showReplies.value = true;
        replies.value.unshift(reply);

        window.playSendSound?.();

        if (props.comment.replies_count !== undefined) props.comment.replies_count += 1;

        replyMessage.value = '';
        replyIsAnonymous.value = false;
        clearReplyMedia();
        showReplyForm.value = false;

        await nextTick();
        if (window.lucide) window.lucide.createIcons();
    } catch (error) {
        console.error('Error posting reply:', error);
        alert('Failed to post reply. Please try again.');
    } finally {
        isSubmittingReply.value = false;
        replyUploadProgress.value = 0;
    }
};

// ── Edit
const isEditing = ref(false);
const editMessage = ref('');
const editIsAnonymous = ref(false);
const editSelectedMedia = ref(null);
const editCurrentMediaPath = ref(null);
const editRemoveCurrentMedia = ref(false);
const editIsSubmitting = ref(false);
const editUploadProgress = ref(0);

const editMediaPreview = computed(() => {
    if (!editSelectedMedia.value) return null;
    return {
        name: editSelectedMedia.value.name,
        type: editSelectedMedia.value.type,
        url: URL.createObjectURL(editSelectedMedia.value),
    };
});

const hasEditCurrentMedia = computed(() => Boolean(editCurrentMediaPath.value) && !editRemoveCurrentMedia.value);
const editCurrentIsImage = computed(() => /\.(jpg|jpeg|png|gif|webp)$/i.test(editCurrentMediaPath.value || ''));
const editCurrentIsVideo = computed(() => /\.(mp4|mov|webm)$/i.test(editCurrentMediaPath.value || ''));

// Guard: use canEdit flag (works for both anon and non-anon owned comments)
const handleEdit = () => {
    if (!isOwner.value) return;
    isEditing.value = true;
    editMessage.value = props.comment.message || '';
    editIsAnonymous.value = Boolean(props.comment.is_anonymous);
    editCurrentMediaPath.value = props.comment.media || null;
    editRemoveCurrentMedia.value = false;
    editSelectedMedia.value = null;
    editUploadProgress.value = 0;
};

const handleEditMediaSelect = () => {
    const input = document.createElement('input');
    input.type = 'file';
    input.accept = 'image/*,video/mp4,video/quicktime,video/webm,audio/mpeg,audio/mp3,audio/wav,audio/ogg,audio/x-m4a';
    input.onchange = (e) => {
        const file = e.target.files?.[0];
        if (file) editSelectedMedia.value = file;
    };
    input.click();
};

const clearEditMedia = () => {
    if (editMediaPreview.value?.url) URL.revokeObjectURL(editMediaPreview.value.url);
    editSelectedMedia.value = null;
};

const removeEditCurrentMedia = () => {
    editRemoveCurrentMedia.value = true;
    editCurrentMediaPath.value = null;
};

const cancelEdit = () => {
    isEditing.value = false;
    editMessage.value = '';
    editIsAnonymous.value = false;
    editCurrentMediaPath.value = null;
    editRemoveCurrentMedia.value = false;
    clearEditMedia();
    editUploadProgress.value = 0;
};

const submitEdit = async () => {
    const messageText = editMessage.value.trim();
    if (!messageText && !editSelectedMedia.value && !editCurrentMediaPath.value) return;

    editIsSubmitting.value = true;
    editUploadProgress.value = 0;
    try {
        const formData = new FormData();
        formData.append('message', messageText);
        formData.append('is_anonymous', editIsAnonymous.value ? '1' : '0');
        if (editSelectedMedia.value) formData.append('media', editSelectedMedia.value);
        if (editRemoveCurrentMedia.value) formData.append('remove_media', '1');

        const data = await new Promise((resolve, reject) => {
            const xhr = new XMLHttpRequest();
            xhr.open('POST', `/bleeps/comments/${props.comment.id}/update`);
            xhr.setRequestHeader('X-CSRF-TOKEN', document.querySelector('meta[name="csrf-token"]')?.content || '');
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
            xhr.setRequestHeader('Accept', 'application/json');
            xhr.upload.onprogress = (event) => {
                if (event.lengthComputable) editUploadProgress.value = Math.round((event.loaded / event.total) * 100);
            };
            xhr.onload = () => {
                if (xhr.status >= 200 && xhr.status < 300) {
                    try { resolve(JSON.parse(xhr.responseText || '{}')); }
                    catch (e) { reject(e); }
                } else if (xhr.status === 401) {
                    window.location.href = '/login';
                } else {
                    reject(new Error('Failed to update comment'));
                }
            };
            xhr.onerror = () => reject(new Error('Failed to update comment'));
            xhr.send(formData);
        });

        props.comment.message = messageText;
        props.comment.is_anonymous = editIsAnonymous.value ? 1 : 0;

        window.playSendSound?.();

        if (typeof data.display_name === 'string') props.comment.display_name = data.display_name;

        if (Object.prototype.hasOwnProperty.call(data, 'media_path')) {
            props.comment.media = data.media_path;
        } else if (editRemoveCurrentMedia.value) {
            props.comment.media = null;
        }

        cancelEdit();
        await nextTick();
        if (window.lucide) window.lucide.createIcons();
    } catch (error) {
        console.error('Error updating comment:', error);
        alert('Failed to update comment. Please try again.');
    } finally {
        editIsSubmitting.value = false;
        editUploadProgress.value = 0;
    }
};

// ── Delete
// Guard: use canDelete flag (works for both anon and non-anon owned comments)
const handleDelete = async () => {
    if (!canDelete.value) return;
    if (!confirm('Delete this comment? This action cannot be undone.')) return;

    try {
        const response = await fetch(`/bleeps/comments/${props.comment.id}/delete`, {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
                'X-Requested-With': 'XMLHttpRequest',
            },
        });
        if (!response.ok) throw new Error('Failed to delete comment');
        emit('delete', props.comment.id);
    } catch (error) {
        console.error('Error deleting comment:', error);
    }
};

// ── Like
const handleLike = async () => {
    try {
        const method = isLiked.value ? 'DELETE' : 'POST';
        const response = await fetch(`/bleeps/comments/${props.comment.id}/likes`, {
            method,
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
                'X-Requested-With': 'XMLHttpRequest',
            },
        });
        if (!response.ok) throw new Error('Failed to like comment');
        const data = await response.json();
        props.comment.liked = data.liked;
        props.comment.likes_count = data.likes_count;
        emit('like', { commentId: props.comment.id, liked: data.liked });
    } catch (error) {
        console.error('Error liking comment:', error);
    }
};

// ── Reply deleted
const handleReplyDeleted = (replyId) => {
    replies.value = replies.value.filter(r => r.id !== replyId);
    if (props.comment.replies_count !== undefined && props.comment.replies_count > 0) {
        props.comment.replies_count -= 1;
    }
    emit('delete', replyId);
};

const handleLoadMoreReplies = () => loadReplies();
</script>

<template>
    <div class="comment-card rounded-lg shadow-sm transition-all"
        :class="[
            showReplyForm ? 'border-2 border-primary' : `border ${borderClass}`,
            componentClass,
            isReply ? 'p-3' : 'p-2'
        ]"
        :data-comment-id="comment.id"
        :data-comment-depth="props.depth"
    >
        <div>
            <!-- Header Row -->
            <div class="flex items-start gap-3">

                <!-- Avatar -->
                <a :href="userProfileLink" class="group shrink-0" title="View profile">
                    <div :class="avatarSize" class="rounded-full overflow-hidden bg-base-300 flex items-center justify-center">
                        <img v-if="userAvatarUrl" :src="userAvatarUrl" :alt="displayName" class="w-full h-full object-cover" />
                        <LucideIcon v-else name="hat-glasses" :size="20" class="text-base-content/50" />
                    </div>
                </a>

                <!-- User Info -->
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2 flex-wrap">
                        <a :href="userProfileLink" :class="textSize" class="font-semibold truncate hover:underline comment-display-name">
                            {{ displayName }}
                        </a>

                        <!-- Role Badge (hidden for anon) -->
                        <div v-if="userRole" class="flex items-center">
                            <LucideIcon
                                name="sparkles"
                                :size="iconSize"
                                :class="userRole === 'admin' ? 'text-teal-500' : 'text-amber-500'"
                                :title="userRole.charAt(0).toUpperCase() + userRole.slice(1)"
                            />
                        </div>

                        <!-- OP badge — non-anon only; anon comments never show OP -->
                        <span
                            v-if="isOP && !isCommentAnonymous && !isBleepAnonymous"
                            :class="isReply ? 'px-1 py-0 text-[7px]' : 'px-1.5 py-0.5 text-[8px]'"
                            class="font-extrabold rounded bg-green-500/20 text-green-500 border border-green-600/20"
                        >OP</span>

                        <span
                            v-if="isOP && isBleepAnonymous && isOwner"
                            :class="isReply ? 'px-1 py-0 text-[7px]' : 'px-1.5 py-0.5 text-[8px]'"
                            class="font-extrabold rounded bg-base-content/10 text-base-content/40 border border-base-content/20"
                            title="You posted this bleep"
                        >OP</span>

                        <!-- "You" badge — always shown if you own the comment (anon or not).
                             When anonymous this is the ONLY badge shown. -->
                        <span
                            v-if="isOwner"
                            :class="isReply ? 'px-1 py-0 text-[7px]' : 'px-1.5 py-0.5 text-[8px]'"
                            class="font-extrabold rounded bg-green-500/20 text-green-500 border border-blue-600/20"
                        >You</span>

                        <!-- Verified badge (hidden for anon) -->
                        <div v-if="isVerified" class="flex items-center">
                            <LucideIcon name="badge-check" :size="iconSize" class="text-blue-500" title="Verified user" />
                        </div>
                    </div>

                    <div class="text-xs text-base-content/50 comment-username">
                        @{{ username }}
                    </div>
                </div>

                <!-- Date + Actions -->
                <div class="flex items-center justify-end gap-1">
                    <div class="text-xs text-base-content/50 comment-date">
                        {{ formattedDate }}
                    </div>

                    <div class="dropdown dropdown-end">
                        <button type="button" class="btn btn-ghost btn-xs rounded" title="More options">
                            <LucideIcon name="ellipsis-vertical" :size="isReply ? 12 : 16" />
                        </button>

                        <ul :class="[isReply ? 'w-40 p-1 text-xs' : 'w-52 p-2', 'flex flex-row gap-2']"
                            class="dropdown-content menu bg-base-100/80 rounded-box z-1 shadow-lg border border-gray-300/50">
                            <!-- Edit — shown when canEdit, works for anon and non-anon -->
                            <li v-if="isOwner">
                                <button @click="handleEdit" class="flex flex-col items-center">
                                    <LucideIcon name="pencil" :size="iconSize" />
                                    <span>Edit</span>
                                </button>
                            </li>
                            <!-- Delete — shown when canDelete, works for anon and non-anon -->
                            <li v-if="canDelete">
                                <button @click="handleDelete" class="text-error flex flex-col items-center">
                                    <LucideIcon name="trash-2" :size="iconSize" />
                                    <span>Delete</span>
                                </button>
                            </li>
                            <!-- Report — shown to non-owners only -->
                            <li v-if="!isOwner">
                                <a class="text-warning flex flex-col items-center">
                                    <LucideIcon name="flag" :size="iconSize" />
                                    <span>Report</span>
                                </a>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Body -->
            <div class="ml-5">
                <!-- Edit Form -->
                <div v-if="isEditing" class="space-y-2">
                    <textarea
                        v-model="editMessage"
                        :class="textSize"
                        class="textarea textarea-bordered w-full resize-none"
                        maxlength="500"
                        rows="3"
                        placeholder="Edit your comment..."
                        :disabled="editIsSubmitting"
                    ></textarea>

                    <!-- Current media -->
                    <div v-if="hasEditCurrentMedia" class="space-y-2">
                        <div class="text-xs font-medium">Current Media</div>
                        <div class="relative inline-block max-w-xs">
                            <div class="rounded-lg overflow-hidden bg-base-200">
                                <img v-if="editCurrentIsImage" :src="`/storage/${editCurrentMediaPath}`" :alt="displayName" class="max-w-full h-45" />
                                <video v-else-if="editCurrentIsVideo" controls class="max-w-full h-45"><source :src="`/storage/${editCurrentMediaPath}`" /></video>
                                <audio v-else controls class="w-full"><source :src="`/storage/${editCurrentMediaPath}`" /></audio>
                            </div>
                            <button type="button" class="absolute top-2 right-2 btn btn-error btn-xs btn-circle" @click="removeEditCurrentMedia" title="Remove media">
                                <LucideIcon name="trash-2" :size="iconSize" />
                            </button>
                        </div>
                    </div>

                    <!-- New media preview -->
                    <div v-if="editMediaPreview?.url" class="space-y-2">
                        <div class="text-xs font-medium">New Media</div>
                        <div class="relative inline-block max-w-xs">
                            <div class="rounded-lg overflow-hidden bg-base-200">
                                <img v-if="editMediaPreview.type.startsWith('image/')" :src="editMediaPreview.url" :alt="editMediaPreview.name" class="max-w-full h-45" />
                                <video v-else-if="editMediaPreview.type.startsWith('video/')" controls class="max-w-full h-45"><source :src="editMediaPreview.url" /></video>
                                <audio v-else controls class="w-full"><source :src="editMediaPreview.url" /></audio>
                            </div>
                            <button type="button" class="absolute top-2 right-2 btn btn-error btn-xs btn-circle" @click="clearEditMedia">
                                <LucideIcon name="x" :size="iconSize" />
                            </button>
                        </div>
                    </div>

                    <!-- Edit actions -->
                    <div class="flex items-center gap-2 flex-wrap">
                        <!-- Anonymous toggle — only show if anon is enabled AND the comment was originally anonymous -->
                        <div v-if="isAnonEnabled" class="flex items-center">
                            <label class="relative inline-flex cursor-pointer">
                                <input v-model="editIsAnonymous" type="checkbox" class="peer sr-only" :disabled="editIsSubmitting" />
                                <div :class="isReply ? 'w-11 h-6' : 'w-12 h-7'" class="bg-base-300 peer-checked:bg-base-300 rounded-full peer-focus:ring-2 peer-focus:ring-primary transition-all border border-gray-300"></div>
                                <div
                                    class="absolute top-0.5 left-0.5 rounded-full transition-all duration-300 bg-cover bg-center"
                                    :class="isReply ? 'size-5' : 'size-6'"
                                    :style="{ backgroundImage: `url('${editIsAnonymous ? '/images/avatar/anonymous.jpg' : userAvatarForReply}')` }"
                                ></div>
                            </label>
                        </div>

                        <button type="button" :class="isReply ? 'btn-xs' : 'btn-sm'" class="btn btn-secondary" :disabled="editIsSubmitting" @click="handleEditMediaSelect">
                            <LucideIcon name="image" :size="iconSize" />
                            <span v-if="!isReply">Media</span>
                        </button>

                        <button type="button" :class="isReply ? 'btn-xs' : 'btn-sm'" class="btn btn-ghost" :disabled="editIsSubmitting" @click="cancelEdit">
                            Cancel
                        </button>

                        <button type="button" :class="isReply ? 'btn-xs' : 'btn-sm'" class="btn btn-primary ml-auto" :disabled="!editMessage.trim() || editIsSubmitting" @click="submitEdit">
                            <LucideIcon v-if="!editIsSubmitting" name="check" :size="iconSize" />
                            <span v-if="editIsSubmitting" :class="isReply ? 'loading-xs' : 'loading-sm'" class="loading loading-spinner"></span>
                            <span v-if="!isReply">Update</span>
                        </button>
                    </div>

                    <div v-if="editIsSubmitting" class="flex flex-col gap-1">
                        <progress class="progress progress-primary w-full" :value="editUploadProgress" max="100"></progress>
                        <div class="text-xs text-base-content/60 text-right">Uploading {{ editUploadProgress }}%</div>
                    </div>
                </div>

                <!-- Display mode -->
                <template v-else>
                    <p v-if="comment.message" :class="textSize" class="comment-message text-base-content whitespace-pre-wrap break-words mb-2">
                        {{ comment.message }}
                    </p>
                    <CommentMedia
                        v-if="comment.media"
                        :path="comment.media"
                        :alt="displayName"
                        :is-reply="isReply"
                        :comment-id="comment.id"
                    />
                </template>
            </div>

            <!-- Footer Actions -->
            <div class="grid grid-cols-3 items-center gap-1 mt-1.5 -ml-2 text-sm">
                <!-- Like -->
                <button
                    class="comment-like-btn cursor-pointer flex items-center justify-center gap-1 px-2 py-1 text-xs rounded-md text-base-content/50 hover:text-error hover:bg-error/15 transition-colors"
                    :class="{ 'text-error': isLiked }"
                    :data-comment-id="comment.id"
                    :data-liked="isLiked ? '1' : '0'"
                    @click="handleLike"
                >
                    <LucideIcon name="heart" :size="iconSize" :class="{ 'fill-error stroke-error': isLiked }" class="mb-[1.5px]" />
                    <span class="comment-like-count">{{ likesCount }}</span>
                </button>

                <!-- Reply -->
                <button
                    v-if="props.depth < depthMax"
                    class="comment-reply-btn cursor-pointer flex items-center justify-center gap-1 px-2 py-1 text-xs rounded-md text-base-content/50 hover:text-primary hover:bg-primary/15 transition-colors"
                    @click="handleReply"
                >
                    <LucideIcon name="reply" :size="iconSize" class="mb-[1.5px]" />
                    <span>Reply</span>
                </button>
                <div v-else />

                <!-- Toggle Replies -->
                <button
                    v-if="(comment.replies_count > 0 && props.depth < depthMax) || viewMoreReplies"
                    class="comment-toggle-replies cursor-pointer flex items-center justify-center gap-1 px-2 py-1 text-xs rounded-md text-primary hover:bg-primary/15 transition-colors"
                    @click="toggleReplies"
                >
                    <LucideIcon name="chevron-down" :size="iconSize" :class="{ 'rotate-180': showReplies }" class="transition-transform" />
                    <span>{{ showReplies ? 'Hide' : `${comment.replies_count} ${comment.replies_count === 1 ? 'reply' : 'replies'}` }}</span>
                </button>
                <div v-else />
            </div>

            <!-- Replies -->
            <div v-if="comment.replies_count > 0 && props.depth < depthMax && showReplies" class="mt-2 border-l-2 border-gray-300 space-y-3">
                <div v-if="isLoadingReplies" class="flex justify-center py-3">
                    <span class="loading loading-spinner loading-sm"></span>
                </div>

                <Card
                    v-for="reply in replies"
                    :key="reply.id"
                    :comment="reply"
                    :bleep="bleep"
                    :depth="props.depth + 1"
                    :user-avatar="userAvatarForReply"
                    :isAnonymousEnabled="isAnonEnabled"
                    :authenticatedUserId="authenticatedUserId"
                    @reply="emit('reply', $event)"
                    @edit="emit('edit', $event)"
                    @delete="handleReplyDeleted"
                    @like="emit('like', $event)"
                />

                <button
                    v-if="hasMoreReplies && !isLoadingReplies"
                    class="cursor-pointer text-xs text-primary hover:underline w-full text-center py-2"
                    @click="handleLoadMoreReplies"
                >
                    Load more replies
                </button>
            </div>
        </div>

        <!-- Inline Reply Form -->
        <div v-if="showReplyForm && props.depth < depthMax" :class="isReply ? 'mt-3 pt-3 space-y-2 border-t border-base-300/50' : 'mt-4 pt-4 space-y-3 border-t border-base-300'">
            <!-- Reply media preview -->
            <div v-if="replyMediaPreview?.url" class="relative inline-flex max-w-xs rounded-lg overflow-hidden bg-base-300 shadow">
                <img v-if="replyMediaPreview.type.startsWith('image/')" :src="replyMediaPreview.url" :alt="replyMediaPreview.name" :class="isReply ? 'max-h-32' : 'max-h-45'" class="max-w-full object-contain" />
                <video v-else-if="replyMediaPreview.type.startsWith('video/')" controls :class="isReply ? 'max-h-26' : 'max-h-45'" class="max-w-full"><source :src="replyMediaPreview.url" /></video>
                <audio v-else controls class="w-full"><source :src="replyMediaPreview.url" /></audio>
                <button type="button" class="absolute top-2 right-2 btn btn-xs btn-circle btn-error text-white" @click="clearReplyMedia">
                    <LucideIcon name="x" :size="iconSize" />
                </button>
            </div>

            <textarea
                v-model="replyMessage"
                maxlength="255"
                rows="2"
                :class="textSize"
                class="textarea textarea-bordered w-full resize-none"
                placeholder="Write a reply..."
                :disabled="isSubmittingReply"
            ></textarea>

            <div :class="isReply ? 'gap-1 flex-wrap' : 'gap-2'" class="flex items-center">
                <!-- Media -->
                <button type="button" :class="isReply ? 'btn-xs' : 'btn-sm'" class="btn btn-secondary" :disabled="isSubmittingReply" @click="handleMediaSelect" aria-label="Attach media">
                    <LucideIcon name="image" :size="iconSize" />
                    <span v-if="!isReply">Media</span>
                </button>

                <!-- Anonymous toggle -->
                <div v-if="isAnonEnabled" class="flex items-center">
                    <label class="relative inline-flex cursor-pointer">
                        <input v-model="replyIsAnonymous" type="checkbox" class="peer sr-only" :disabled="isSubmittingReply" />
                        <div :class="isReply ? 'w-11 h-6' : 'w-12 h-7'" class="bg-base-300 rounded-full peer-focus:ring-2 peer-focus:ring-primary transition-all border border-gray-300"></div>
                        <div v-if="replyIsAnonymous" :class="isReply ? 'top-0.5 left-4 size-5' : 'top-0.5 left-5 size-6'" class="absolute rounded-full transition-all duration-300 bg-base-100 flex items-center justify-center">
                            <LucideIcon name="hat-glasses" :size="iconSize" class="text-base-content/80" />
                        </div>
                        <div v-else :class="isReply ? 'top-0.5 left-0.5 size-5' : 'top-0.5 left-0.5 size-6'" class="absolute rounded-full transition-all duration-300 bg-cover bg-center" :style="{ backgroundImage: `url('${userAvatarForReply}')` }"></div>
                    </label>
                </div>

                <button type="button" :class="isReply ? 'btn-xs' : 'btn-sm'" class="btn btn-ghost" :disabled="isSubmittingReply" @click="handleReply">Cancel</button>

                <button type="button" :class="isReply ? 'btn-xs' : 'btn-sm'" class="btn btn-primary ml-auto" :disabled="(!replyMessage.trim() && !replyMedia) || isSubmittingReply" @click="submitReply">
                    <LucideIcon v-if="!isSubmittingReply" name="send" :size="iconSize" />
                    <span v-if="isSubmittingReply" :class="isReply ? 'loading-xs' : 'loading-sm'" class="loading loading-spinner"></span>
                    <span v-if="!isReply">{{ isSubmittingReply ? 'Sending...' : 'Send' }}</span>
                </button>
            </div>

            <div v-if="isSubmittingReply" class="flex flex-col gap-1">
                <progress class="progress progress-primary w-full" :value="replyUploadProgress" max="100"></progress>
                <div class="text-xs text-base-content/60 text-right">Uploading {{ replyUploadProgress }}%</div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.comment-card {
    animation: slideIn 0.3s ease-out;
}

@keyframes slideIn {
    from { opacity: 0; transform: translateY(-10px); }
    to   { opacity: 1; transform: translateY(0); }
}
</style>
