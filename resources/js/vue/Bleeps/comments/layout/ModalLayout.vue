<script setup>
import { ref, computed, onMounted, nextTick } from 'vue';
import Card from '../component/Card.vue';
import Create from '../form/Create.vue';
import LucideIcon from '../../../LucideIcons.vue';

const props = defineProps({
    bleepId: {
        type: [String, Number],
        required: true,
    },
    bleep: {
        type: Object,
        required: true,
        validator: (obj) => {
            return obj && typeof obj === 'object' && !Array.isArray(obj);
        }
    },
    mode: {
        type: String,
        enum: ['post', 'modal'],
        default: 'post',
    },
    authUser: {
        type: Object,
        default: null,
    },
    userAvatar: {
        type: String,
        default: '/images/avatar/default.jpg',
    },
    isAuthenticated: {
        type: String,
        default: "false",
    },
    isAnonymousEnabled: {
        type: [Boolean, String],
        default: false,
    },

});

console.log('ModalLayout props:', props);

const emit = defineEmits(['close']);

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

const comments = ref([]);
const isLoading = ref(false);
const currentPage = ref(1);
const hasMorePages = ref(false);
const commentGroups = ref([]);
const editingComment = ref(null);
const scrollContainer = ref(null);
const hasLoadedOnce = ref(false);

const authenticatedUserId = computed(() => {
    return props.authUser?.id || null;
});

onMounted(async () => {
    isLoading.value = true;
    hasLoadedOnce.value = false;

    if (props.bleepId) {
        await loadComments(true);
        setupScrollListener();
    }
});

const loadComments = async (force = false) => {
    if (isLoading.value && !force) return;

    isLoading.value = true;
    try {
        const response = await fetch(`/bleeps/comments/${props.bleepId}/comments?page=${currentPage.value}`, {
            headers: { 'Accept': 'application/json' },
        });

        if (!response.ok) throw new Error('Failed to load comments');

        const data = await response.json();
        comments.value = [...comments.value, ...data.comments];
        currentPage.value = data.current_page + 1;
        hasMorePages.value = data.has_more;

        groupCommentsByDate();
        await nextTick();
    } catch (error) {
        console.error('Error loading comments:', error);
    } finally {
        isLoading.value = false;
        hasLoadedOnce.value = true;
    }
};

const setupScrollListener = () => {
    if (!scrollContainer.value) return;

    scrollContainer.value.addEventListener('scroll', () => {
        const { scrollTop, scrollHeight, clientHeight } = scrollContainer.value;
        if (scrollTop + clientHeight >= scrollHeight - 200 && hasMorePages.value && !isLoading.value) {
        loadComments();
        }
    });
};

const handleCommentCreated = async (newComment) => {
    comments.value.unshift(newComment);
    groupCommentsByDate();
    await nextTick();
};

const handleCommentDeleted = (commentId) => {
    comments.value = comments.value.filter((c) => c.id !== commentId);
    groupCommentsByDate();
};

const handleViewEdit = (comment) => {
    editingComment.value = comment;
};

const closeModal = () => {
    window.dispatchEvent(new CustomEvent('close-comments'));
    emit('close');
};

const groupCommentsByDate = () => {
    // Get user's timezone for grouping purposes
    const userTimezone = Intl.DateTimeFormat().resolvedOptions().timeZone || 'UTC';
    const yearMap = new Map();

    comments.value.forEach((comment) => {
        // Use current time as fallback if created_at is missing or invalid
        let date = comment.created_at ? new Date(comment.created_at) : new Date();

        // If date is invalid, use current time
        if (isNaN(date.getTime())) {
            date = new Date();
            // Update comment with current timestamp for display
            comment.created_at = date.toISOString();
        }

        // Format date parts according to user's timezone
        const formatter = new Intl.DateTimeFormat('en-US', {
            year: 'numeric',
            month: 'long',
            day: 'numeric',
            timeZone: userTimezone
        });

        const parts = formatter.formatToParts(date);
        const year = parseInt(parts.find(p => p.type === 'year').value);
        const monthName = parts.find(p => p.type === 'month').value;
        const day = parseInt(parts.find(p => p.type === 'day').value);
        const dateDisplay = `${monthName} ${day}, ${year}`;

        // Build nested structure: Year -> Month -> Day
        if (!yearMap.has(year)) {
            yearMap.set(year, new Map());
        }

        const monthMap = yearMap.get(year);
        if (!monthMap.has(monthName)) {
            monthMap.set(monthName, new Map());
        }

        const dayMap = monthMap.get(monthName);
        if (!dayMap.has(day)) {
            dayMap.set(day, { display: dateDisplay, comments: [] });
        }

        dayMap.get(day).comments.push(comment);
    });

    // Convert to array structure, sorted by year (descending)
    const monthOrder = ['January', 'February', 'March', 'April', 'May', 'June',
                       'July', 'August', 'September', 'October', 'November', 'December'];

    commentGroups.value = Array.from(yearMap.entries())
        .sort((a, b) => b[0] - a[0])
        .map(([year, monthMap]) => ({
            year,
            months: Array.from(monthMap.entries())
                .sort((a, b) => monthOrder.indexOf(b[0]) - monthOrder.indexOf(a[0]))
                .map(([monthName, dayMap]) => ({
                    name: monthName,
                    days: Array.from(dayMap.values())
                        .sort((a, b) => b.display.localeCompare(a.display))
                }))
        }));
};
</script>

<template>
    <div class="modal-layout flex flex-col h-full overflow-hidden">

        <!-- Header -->
        <div class="shrink-0 flex items-center justify-between px-4 py-3 border-b border-base-200 bg-base-100/95 backdrop-blur-sm">
            <h2 class="text-lg font-semibold flex items-center gap-2">
                <LucideIcon name="message-circle-more" size="20" />
                {{ bleep.user?.username || 'User' }}'s Bleep Comments
            </h2>
            <button class="btn btn-ghost btn-sm btn-circle" @click="closeModal">
                <LucideIcon name="x" size="16" />
            </button>
        </div>

        <!-- Scroll Area Container -->
        <div ref="scrollContainer" class="flex-1 min-h-0 overflow-y-auto px-4 py-3 bg-base-300/80">

            <!-- Wrapper -->
             <div class="flex flex-col min-h-full">
                <!-- Content -->
                <template v-if="commentGroups.length > 0">
                    <div v-for="yearGroup in commentGroups" :key="yearGroup.year" class="space-y-3">
                        <div v-for="monthGroup in yearGroup.months" :key="monthGroup.name" class="space-y-3">
                            <div v-for="dayGroup in monthGroup.days" :key="dayGroup.display" class="space-y-3">
                                <!-- Date Header -->
                                <div class="text-xs text-base-content/60 font-semibold tracking-wide px-2">
                                    {{ dayGroup.display }}
                                </div>

                                <!-- Comments -->
                                <Card
                                    v-for="comment in dayGroup.comments"
                                    :key="comment.id"
                                    :comment="comment"
                                    :bleep="bleep"
                                    :depth="0"
                                    :user-avatar="userAvatarUrl"
                                    :isAnonymousEnabled="isAnonEnabled"
                                    :authenticatedUserId="authenticatedUserId"
                                    @edit="handleViewEdit"
                                    @delete="handleCommentDeleted"
                                />
                            </div>
                        </div>
                    </div>
                </template>

                <!-- Empty/Loading States - Fill remaining space -->
                <div v-if="!commentGroups.length" class="flex-1 flex items-center justify-center">
                    <div v-if="isLoading || !hasLoadedOnce" class="flex flex-col items-center justify-center text-base-content/60">
                        <span class="loading loading-spinner loading-md mb-2"></span>
                        <p class="text-sm">Loading comments...</p>
                    </div>
                    <div v-else class="flex flex-col items-center justify-center text-base-content/60">
                        <LucideIcon name="message-circle-off" size="32" class="mb-3" />
                        <p class="text-sm font-semibold">No comments yet</p>
                    </div>
                </div>

                <!-- Loading more indicator (when scrolling) -->
                <div v-if="isLoading && commentGroups.length > 0" class="flex justify-center py-4">
                    <span class="loading loading-spinner loading-sm"></span>
                </div>
             </div>

        </div>

        <!-- Sticky Input Footer -->
        <div v-if="isAuthenticated" class="shrink-0 bg-base-100 p-4 border-t border-base-200">
            <Create
                :bleep-id="bleepId"
                :user-avatar="userAvatarUrl"
                :is-anonymous-enabled="isAnonEnabled"
                :compact="false"
                submit-label="Send"
                @submitted="handleCommentCreated"
            />
        </div>

        <div v-else class="shrink-0 border-t border-base-200 bg-base-100/95 backdrop-blur-sm p-4 text-center text-sm text-base-content/60 mt-auto">
            <a href="/login" class="link link-primary">Login</a> to comment
        </div>
    </div>
</template>

<!-- Edit Modal -->
<Edit
    v-if="editingComment"
    :comment="editingComment"
    @updated="handleCommentUpdated"
    @closed="editingComment = null"
/>

<style scoped>
.modal-layout {
  animation: slideUp 0.3s ease-out;
}

@keyframes slideUp {
  from {
    opacity: 0;
    transform: translateY(20px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}
</style>
