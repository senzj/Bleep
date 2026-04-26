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

const authenticatedUserId = computed(() => {
    return props.authUser?.id || null;
});

const comments = ref([]);
const isLoading = ref(false);
const currentPage = ref(1);
const hasMorePages = ref(false);
const commentGroups = ref([]);
const editingComment = ref(null);
const replyingToCommentId = ref(null);

onMounted(async () => {
    await loadComments();
});

const loadComments = async () => {
    if (isLoading.value) return;

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
    }
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

const handleCommentCreated = async (newComment) => {
    comments.value.unshift(newComment);
    groupCommentsByDate();
    replyingToCommentId.value = null;
    await nextTick();
};

const handleCommentDeleted = (commentId) => {
    comments.value = comments.value.filter((c) => c.id !== commentId);
    groupCommentsByDate();
};

const handleViewEdit = (comment) => {
    editingComment.value = comment;
};

const handleReply = (payload) => {
    replyingToCommentId.value = payload.commentId;
};

const handleLoadMore = () => {
    loadComments();
};

</script>

<template>
    <div class="comments-layout space-y-4 border border-base-200 rounded-lg p-4 bg-base-200 shadow-lg">

        <!-- Comment Form (Top) -->
        <div v-if="isAuthenticated" class="bg-base-100 rounded-lg p-4 border border-base-200 shadow">
            <Create
                :bleep-id="bleepId"
                :user-avatar="userAvatarUrl"
                :isAnonymousEnabled="isAnonEnabled"
                submit-label="Post"
                @submitted="handleCommentCreated"
            />
        </div>

        <!-- Login Prompt -->
        <div v-else class="text-center text-sm text-gray-500 rounded-lg p-4 shadow-md bg-base-100">
            <LucideIcon name="message-circle-more" size="16" class="inline-block mr-1" />
            <a href="/login" class="link link-primary">Log in</a> to post a comment.
        </div>

        <!-- Comments List -->
        <div class="comments-container space-y-6">
            <template v-if="commentGroups.length > 0">
                <div v-for="yearGroup in commentGroups" :key="yearGroup.year" class="space-y-6">
                    <div v-for="monthGroup in yearGroup.months" :key="monthGroup.name" class="space-y-4">

                        <!-- Days in Month -->
                        <div class="space-y-4">
                            <div v-for="dayGroup in monthGroup.days" :key="dayGroup.display" class="space-y-2">
                                <!-- Date Header -->
                                <div class="text-sm text-base-content/60 font-semibold tracking-wide pt-2">
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
                                    @reply="handleReply"
                                />
                            </div>
                        </div>
                    </div>
                </div>
            </template>

            <!-- Empty State -->
            <div v-else-if="!isLoading" class="flex flex-col items-center justify-center py-10 text-base-content/60">
                <LucideIcon name="message-circle-off" size="32" class="mb-3" />
                <p class="text-sm font-semibold">No comments yet</p>
                <p class="text-xs">Be the first to share your thoughts.</p>
            </div>

            <!-- Loading State -->
            <div v-if="isLoading" class="flex justify-center py-8">
                <span class="loading loading-spinner loading-md"></span>
            </div>

            <!-- Load More Button -->
            <div v-if="hasMorePages && !isLoading" class="text-center">
                <button
                    class="btn btn-outline btn-sm"
                    @click="handleLoadMore"
                >
                    Load More Comments
                </button>
            </div>
        </div>
    </div>
</template>

<style scoped>
.comments-layout {
    animation: fadeIn 0.3s ease-out;
}

@keyframes fadeIn {
    from {
        opacity: 0;
    }
    to {
        opacity: 1;
    }
}
</style>
