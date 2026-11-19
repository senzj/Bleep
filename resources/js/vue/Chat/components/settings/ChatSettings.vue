<script setup>
import { computed, ref, watch } from 'vue';
import LucideIcons from '../../../LucideIcons.vue';
import { useMessageStore } from '../../store/useMessageStore';
import { useChatPreferences, BUBBLE_STYLES, CHAT_BACKGROUNDS } from '../../composables/useChatPreferences';

const props = defineProps({
    conversation: {
        type: Object,
        default: null,
    },
});

const emit = defineEmits(['close']);

const store = useMessageStore();
const { preferences, setNickname } = useChatPreferences();

const tabs = [
    { key: 'appearance', label: 'Appearance', icon: 'palette' },
    { key: 'media', label: 'Media', icon: 'image' },
    { key: 'files', label: 'Files', icon: 'file' },
    { key: 'links', label: 'Links', icon: 'link-2' },
    { key: 'search', label: 'Search', icon: 'search' },
    { key: 'nicknames', label: 'Nicknames', icon: 'at-sign' },
];

const groupTab = { key: 'group', label: 'Group', icon: 'users-round' };

const activeTab = ref('appearance');
const gallery = ref({ media: [], files: [], links: [] });
const galleryLoading = ref(false);
const searchTerm = ref('');
const searchResults = ref([]);
const searchLoading = ref(false);
const renameValue = ref('');
const memberQuery = ref('');
const busy = ref('');
const notice = ref('');
const errorText = ref('');
const copied = ref(false);

const conversationId = computed(() => Number(props.conversation?.id || 0));
const isGroup = computed(() => Boolean(props.conversation?.is_group));
const isAdmin = computed(() => Boolean(props.conversation?.is_admin));
const hasLeft = computed(() => Boolean(props.conversation?.has_left));

const visibleTabs = computed(() => (isGroup.value ? [...tabs, groupTab] : tabs));

const participants = computed(() => props.conversation?.participants || []);
const pendingInvites = computed(() => props.conversation?.pending_invites || []);

const memberableFriends = computed(() => {
    const inGroup = new Set(participants.value.map((p) => Number(p.id)));
    const term = memberQuery.value.trim().toLowerCase();

    return (store.state.userDirectory || [])
        .filter((user) => !inGroup.has(Number(user.id)))
        .filter((user) => {
            if (!term) return true;
            return (user.dname || '').toLowerCase().includes(term)
                || (user.username || '').toLowerCase().includes(term);
        })
        .slice(0, 8);
});

const setBusy = (key, value) => {
    busy.value = value ? key : '';
};

const flash = (message) => {
    notice.value = message;
    window.setTimeout(() => {
        if (notice.value === message) notice.value = '';
    }, 2600);
};

const fail = (err, fallback) => {
    errorText.value = err?.response?.data?.message || fallback;
    window.setTimeout(() => { errorText.value = ''; }, 3200);
};

const loadGallery = async (type) => {
    if (!conversationId.value) return;

    const key = type === 'files' ? 'files' : type === 'links' ? 'links' : 'media';
    if (gallery.value[key].length) return;

    galleryLoading.value = true;
    try {
        gallery.value[key] = await store.fetchSharedContent(conversationId.value, key);
    } catch (err) {
        fail(err, 'Could not load shared content.');
    } finally {
        galleryLoading.value = false;
    }
};

watch(activeTab, (tab) => {
    if (['media', 'files', 'links'].includes(tab)) {
        loadGallery(tab);
    }

    if (tab === 'group' && !(store.state.userDirectory || []).length) {
        store.fetchUserDirectory().catch(() => {});
    }
});

watch(
    () => conversationId.value,
    () => {
        gallery.value = { media: [], files: [], links: [] };
        searchTerm.value = '';
        searchResults.value = [];
        renameValue.value = props.conversation?.name || '';
        activeTab.value = 'appearance';
    },
    { immediate: true }
);

const runSearch = async () => {
    const term = searchTerm.value.trim();
    if (!term || !conversationId.value) {
        searchResults.value = [];
        return;
    }

    searchLoading.value = true;
    try {
        searchResults.value = await store.searchConversation(conversationId.value, term);
    } catch (err) {
        fail(err, 'Search failed.');
    } finally {
        searchLoading.value = false;
    }
};

const renameGroup = async () => {
    const name = renameValue.value.trim();
    if (!name || name === props.conversation?.name) return;

    setBusy('rename', true);
    try {
        await store.updateGroupConversation(conversationId.value, { name });
        flash('Group renamed.');
    } catch (err) {
        fail(err, 'Could not rename the group.');
    } finally {
        setBusy('rename', false);
    }
};

const toggleInviteOnly = async (event) => {
    const value = event.target.checked;
    setBusy('inviteOnly', true);
    try {
        await store.updateGroupConversation(conversationId.value, { invite_only: value });
        flash(value ? 'New members now need approval.' : 'Anyone with the link can join.');
    } catch (err) {
        event.target.checked = !value;
        fail(err, 'Could not update the invite setting.');
    } finally {
        setBusy('inviteOnly', false);
    }
};

const createInviteLink = async () => {
    setBusy('inviteLink', true);
    try {
        await store.refreshGroupInviteLink(conversationId.value);
        flash('New invite link created.');
    } catch (err) {
        fail(err, 'Could not create an invite link.');
    } finally {
        setBusy('inviteLink', false);
    }
};

const copyInviteLink = async () => {
    const url = props.conversation?.invite_url;
    if (!url) return;

    try {
        await navigator.clipboard.writeText(url);
        copied.value = true;
        window.setTimeout(() => { copied.value = false; }, 1800);
    } catch {
        fail(null, 'Copy failed. Select the link and copy it manually.');
    }
};

const addMember = async (user) => {
    setBusy(`add-${user.id}`, true);
    try {
        const result = await store.addGroupMember(conversationId.value, user.id);
        flash(result?.message || 'Member added.');
        memberQuery.value = '';
    } catch (err) {
        fail(err, 'Could not add that person.');
    } finally {
        setBusy('', false);
    }
};

const kickMember = async (participant) => {
    if (!window.confirm(`Remove ${participant.dname || participant.username} from this group?`)) return;

    setBusy(`kick-${participant.id}`, true);
    try {
        await store.removeGroupMember(conversationId.value, participant.id);
        flash('Member removed.');
    } catch (err) {
        fail(err, 'Could not remove that member.');
    } finally {
        setBusy('', false);
    }
};

const approveInvite = async (invite) => {
    setBusy(`invite-${invite.id}`, true);
    try {
        await store.approveGroupInvite(conversationId.value, invite.id);
        flash('Member approved.');
    } catch (err) {
        fail(err, 'Could not approve that request.');
    } finally {
        setBusy('', false);
    }
};

const declineInvite = async (invite) => {
    setBusy(`invite-${invite.id}`, true);
    try {
        await store.declineGroupInvite(conversationId.value, invite.id);
        flash('Request declined.');
    } catch (err) {
        fail(err, 'Could not decline that request.');
    } finally {
        setBusy('', false);
    }
};

const profileUrl = (username) => (username ? `/bleeper/${username}` : '#');

const formatTime = (isoString) => {
    if (!isoString) return '';

    const date = new Date(isoString);
    if (Number.isNaN(date.getTime())) return '';

    return date.toLocaleDateString([], { month: 'short', day: 'numeric' });
};

const mediaItems = (message) => message?.media_items || [];

const isImageItem = (item) => (item.media_type || '').startsWith('image/');
const isVideoItem = (item) => (item.media_type || '').startsWith('video/');

const galleryThumb = (message) => mediaItems(message).find((item) => isImageItem(item) || isVideoItem(item));

const fileItems = (message) => mediaItems(message).filter((item) => {
    const type = item.media_type || '';
    return !type.startsWith('image/') && !type.startsWith('video/') && !type.startsWith('audio/');
});

const messageExcerpt = (message) => (message?.body || '').slice(0, 120);
</script>

<template>
    <aside class="flex h-full min-h-0 w-full flex-col border-l border-base-300 bg-base-100">
        <header class="flex items-center justify-between gap-2 border-b border-base-300 px-4 py-3">
            <div class="min-w-0">
                <h2 class="truncate text-sm font-semibold">Chat settings</h2>
                <p class="truncate text-xs text-base-content/60">{{ conversation?.title || 'Conversation' }}</p>
            </div>

            <button type="button" class="btn btn-ghost btn-sm btn-circle" aria-label="Close settings" @click="emit('close')">
                <LucideIcons name="x" class="h-4 w-4" />
            </button>
        </header>

        <nav class="flex gap-1 overflow-x-auto border-b border-base-300 px-2 py-1.5" aria-label="Settings sections">
            <button
                v-for="tab in visibleTabs"
                :key="tab.key"
                type="button"
                class="flex shrink-0 items-center gap-1.5 rounded-lg px-2.5 py-1.5 text-xs font-medium transition"
                :class="activeTab === tab.key ? 'bg-primary/10 text-primary' : 'text-base-content/70 hover:bg-base-200'"
                @click="activeTab = tab.key"
            >
                <LucideIcons :name="tab.icon" class="h-3.5 w-3.5" />
                {{ tab.label }}
            </button>
        </nav>

        <div class="min-h-0 flex-1 overflow-y-auto p-4">
            <p v-if="notice" class="mb-3 rounded-lg bg-success/15 px-3 py-2 text-xs text-success-content">{{ notice }}</p>
            <p v-if="errorText" class="mb-3 rounded-lg bg-error/15 px-3 py-2 text-xs text-error">{{ errorText }}</p>

            <!-- Appearance -->
            <section v-if="activeTab === 'appearance'" class="space-y-5">
                <div>
                    <h3 class="mb-2 text-xs font-semibold uppercase tracking-wide text-base-content/60">Chat background</h3>
                    <div class="grid grid-cols-2 gap-2">
                        <button
                            v-for="bg in CHAT_BACKGROUNDS"
                            :key="bg.value"
                            type="button"
                            class="flex items-center gap-2 rounded-lg border px-3 py-2 text-left text-sm transition"
                            :class="preferences.background === bg.value ? 'border-primary bg-primary/10' : 'border-base-300 hover:bg-base-200'"
                            @click="preferences.background = bg.value"
                        >
                            <span class="chat-surface h-6 w-6 rounded-md border border-base-300" :data-chat-bg="bg.value"></span>
                            {{ bg.label }}
                        </button>
                    </div>
                </div>

                <div>
                    <h3 class="mb-2 text-xs font-semibold uppercase tracking-wide text-base-content/60">Bubble colour</h3>
                    <div class="space-y-2">
                        <button
                            v-for="style in BUBBLE_STYLES"
                            :key="style.value"
                            type="button"
                            class="flex w-full items-center justify-between gap-3 rounded-lg border px-3 py-2 text-sm transition"
                            :class="preferences.bubbleStyle === style.value ? 'border-primary bg-primary/10' : 'border-base-300 hover:bg-base-200'"
                            @click="preferences.bubbleStyle = style.value"
                        >
                            <span>{{ style.label }}</span>
                            <span class="flex items-center gap-1">
                                <span class="h-4 w-6 rounded-full" :class="style.mine"></span>
                                <span class="h-4 w-6 rounded-full" :class="style.theirs"></span>
                            </span>
                        </button>
                    </div>
                </div>

                <label class="flex cursor-pointer items-center justify-between gap-3 rounded-lg border border-base-300 px-3 py-2">
                    <span class="text-sm">Show seen indicators</span>
                    <input
                        type="checkbox"
                        class="toggle toggle-primary toggle-sm"
                        :checked="preferences.showSeen"
                        @change="preferences.showSeen = $event.target.checked"
                    />
                </label>
            </section>

            <!-- Media -->
            <section v-else-if="activeTab === 'media'" class="space-y-3">
                <p v-if="galleryLoading" class="text-xs text-base-content/60">Loading media…</p>
                <p v-else-if="!gallery.media.length" class="text-xs text-base-content/60">No photos or videos shared yet.</p>
                <div v-else class="grid grid-cols-3 gap-1.5">
                    <template v-for="message in gallery.media" :key="message.id">
                        <a
                            v-for="item in mediaItems(message).filter((entry) => isImageItem(entry) || isVideoItem(entry))"
                            :key="`${message.id}-${item.id}`"
                            :href="item.media_url || `/storage/${item.media_path}`"
                            target="_blank"
                            rel="noopener"
                            class="relative aspect-square overflow-hidden rounded-lg bg-base-200"
                        >
                            <img
                                v-if="isImageItem(item)"
                                :src="item.media_url || `/storage/${item.media_path}`"
                                alt=""
                                loading="lazy"
                                class="h-full w-full object-cover"
                            />
                            <span v-else class="grid h-full w-full place-items-center text-base-content/60">
                                <LucideIcons name="play" class="h-5 w-5" />
                            </span>
                        </a>
                    </template>
                </div>
            </section>

            <!-- Files -->
            <section v-else-if="activeTab === 'files'" class="space-y-2">
                <p v-if="galleryLoading" class="text-xs text-base-content/60">Loading files…</p>
                <p v-else-if="!gallery.files.length" class="text-xs text-base-content/60">No files shared yet.</p>
                <template v-else>
                    <template v-for="message in gallery.files" :key="message.id">
                        <a
                            v-for="item in fileItems(message)"
                            :key="`${message.id}-${item.id}`"
                            :href="item.media_url || `/storage/${item.media_path}`"
                            target="_blank"
                            rel="noopener"
                            class="flex items-center gap-2 rounded-lg border border-base-300 px-3 py-2 text-sm hover:bg-base-200"
                        >
                            <LucideIcons name="file" class="h-4 w-4 shrink-0 text-base-content/60" />
                            <span class="min-w-0 flex-1 truncate">{{ item.media_path?.split('/').pop() || 'Attachment' }}</span>
                            <span class="text-xs text-base-content/50">{{ formatTime(message.created_at) }}</span>
                        </a>
                    </template>
                </template>
            </section>

            <!-- Links -->
            <section v-else-if="activeTab === 'links'" class="space-y-2">
                <p v-if="galleryLoading" class="text-xs text-base-content/60">Loading links…</p>
                <p v-else-if="!gallery.links.length" class="text-xs text-base-content/60">No links shared yet.</p>
                <template v-else>
                    <a
                        v-for="message in gallery.links"
                        :key="message.id"
                        :href="message.shared_bleep?.url || '#'"
                        class="block rounded-lg border border-base-300 px-3 py-2 text-sm hover:bg-base-200"
                        :target="message.shared_bleep ? '_blank' : undefined"
                        rel="noopener"
                    >
                        <span class="block truncate font-medium">
                            {{ message.shared_bleep ? `Bleep by ${message.shared_bleep.author_name}` : messageExcerpt(message) }}
                        </span>
                        <span class="text-xs text-base-content/50">
                            {{ message.sender?.dname || message.sender?.username }} · {{ formatTime(message.created_at) }}
                        </span>
                    </a>
                </template>
            </section>

            <!-- Search -->
            <section v-else-if="activeTab === 'search'" class="space-y-3">
                <form class="flex gap-2" @submit.prevent="runSearch">
                    <input
                        v-model="searchTerm"
                        type="search"
                        class="input input-bordered input-sm flex-1"
                        placeholder="Search this conversation"
                        aria-label="Search messages"
                    />
                    <button type="submit" class="btn btn-primary btn-sm" :disabled="searchLoading">
                        <span v-if="searchLoading" class="loading loading-spinner loading-xs"></span>
                        <LucideIcons v-else name="search" class="h-4 w-4" />
                    </button>
                </form>

                <p v-if="!searchResults.length && searchTerm && !searchLoading" class="text-xs text-base-content/60">
                    No messages matched.
                </p>

                <button
                    v-for="message in searchResults"
                    :key="message.id"
                    type="button"
                    class="block w-full rounded-lg border border-base-300 px-3 py-2 text-left text-sm hover:bg-base-200"
                    @click="emit('close')"
                >
                    <span class="block truncate">{{ message.body || 'Attachment' }}</span>
                    <span class="text-xs text-base-content/50">
                        {{ message.sender?.dname || message.sender?.username }} · {{ formatTime(message.created_at) }}
                    </span>
                </button>
            </section>

            <!-- Nicknames -->
            <section v-else-if="activeTab === 'nicknames'" class="space-y-2">
                <p class="text-xs text-base-content/60">Nicknames are private to you and only change what you see.</p>
                <label
                    v-for="participant in participants"
                    :key="participant.id"
                    class="flex items-center gap-2 rounded-lg border border-base-300 px-2 py-2"
                >
                    <img
                        :src="participant.profile_picture_url || '/images/avatar/default.jpg'"
                        alt=""
                        class="h-8 w-8 rounded-full object-cover"
                    />
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-xs font-semibold">{{ participant.dname || participant.username }}</p>
                        <input
                            :value="preferences.nicknames[participant.id] || ''"
                            type="text"
                            maxlength="40"
                            class="input input-ghost input-xs h-6 w-full px-0"
                            :placeholder="participant.dname || participant.username"
                            @change="setNickname(participant.id, $event.target.value)"
                        />
                    </div>
                </label>
            </section>

            <!-- Group management -->
            <section v-if="isGroup && activeTab === 'group'" class="space-y-5">
                <h3 class="text-xs font-semibold uppercase tracking-wide text-base-content/60">Group</h3>

                <div v-if="!isAdmin" class="rounded-lg bg-base-200 px-3 py-2 text-xs text-base-content/70">
                    Only group admins can rename, invite or remove members.
                </div>

                <template v-else-if="!hasLeft">
                    <label class="form-control">
                        <span class="mb-1 text-sm">Group name</span>
                        <div class="flex gap-2">
                            <input v-model="renameValue" type="text" maxlength="100" class="input input-bordered input-sm flex-1" />
                            <button type="button" class="btn btn-primary btn-sm" :disabled="busy === 'rename'" @click="renameGroup">
                                <span v-if="busy === 'rename'" class="loading loading-spinner loading-xs"></span>
                                Save
                            </button>
                        </div>
                    </label>

                    <label class="flex cursor-pointer items-center justify-between gap-3 rounded-lg border border-base-300 px-3 py-2">
                        <span class="text-sm">
                            Invite only
                            <span class="block text-xs text-base-content/60">New joiners stay pending until an admin approves.</span>
                        </span>
                        <input
                            type="checkbox"
                            class="toggle toggle-primary toggle-sm"
                            :checked="Boolean(conversation?.invite_only)"
                            :disabled="busy === 'inviteOnly'"
                            @change="toggleInviteOnly"
                        />
                    </label>

                    <div>
                        <h4 class="mb-2 text-sm font-medium">Invite link</h4>
                        <div class="flex gap-2">
                            <input
                                :value="conversation?.invite_url || ''"
                                type="text"
                                readonly
                                class="input input-bordered input-sm flex-1 text-xs"
                                placeholder="Create a link to share"
                            />
                            <button type="button" class="btn btn-ghost btn-sm" :disabled="!conversation?.invite_url" @click="copyInviteLink">
                                <LucideIcons :name="copied ? 'check' : 'copy'" class="h-4 w-4" />
                            </button>
                            <button type="button" class="btn btn-ghost btn-sm" :disabled="busy === 'inviteLink'" @click="createInviteLink">
                                <LucideIcons name="refresh-cw" class="h-4 w-4" />
                            </button>
                        </div>
                    </div>

                    <div v-if="pendingInvites.length">
                        <h4 class="mb-2 text-sm font-medium">Pending requests</h4>
                        <div class="space-y-2">
                            <div
                                v-for="invite in pendingInvites"
                                :key="invite.id"
                                class="flex items-center gap-2 rounded-lg border border-base-300 px-2 py-2"
                            >
                                <img
                                    :src="invite.profile_picture_url || '/images/avatar/default.jpg'"
                                    alt=""
                                    class="h-8 w-8 rounded-full object-cover"
                                />
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm">{{ invite.dname || invite.username }}</p>
                                    <p class="text-xs text-base-content/50">{{ formatTime(invite.requested_at) }}</p>
                                </div>
                                <button type="button" class="btn btn-success btn-xs" :disabled="busy === `invite-${invite.id}`" @click="approveInvite(invite)">Approve</button>
                                <button type="button" class="btn btn-ghost btn-xs" :disabled="busy === `invite-${invite.id}`" @click="declineInvite(invite)">Decline</button>
                            </div>
                        </div>
                    </div>

                    <div>
                        <h4 class="mb-2 text-sm font-medium">Members</h4>
                        <div class="space-y-1.5">
                            <div
                                v-for="participant in participants"
                                :key="participant.id"
                                class="flex items-center gap-2 rounded-lg px-1 py-1.5"
                            >
                                <img
                                    :src="participant.profile_picture_url || '/images/avatar/default.jpg'"
                                    alt=""
                                    class="h-8 w-8 rounded-full object-cover"
                                />
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm">
                                        {{ participant.dname || participant.username }}
                                        <span v-if="participant.role === 'admin'" class="badge badge-primary badge-xs ml-1">Admin</span>
                                    </p>
                                    <a
                                        v-if="participant.username"
                                        :href="profileUrl(participant.username)"
                                        class="text-xs text-primary hover:underline"
                                    >
                                        View bleep profile
                                    </a>
                                </div>
                                <button
                                    v-if="Number(participant.id) !== Number(store.state.currentUserId)"
                                    type="button"
                                    class="btn btn-ghost btn-xs text-error"
                                    :disabled="busy === `kick-${participant.id}`"
                                    @click="kickMember(participant)"
                                >
                                    Remove
                                </button>
                            </div>
                        </div>
                    </div>

                    <div>
                        <h4 class="mb-2 text-sm font-medium">Add people</h4>
                        <input
                            v-model="memberQuery"
                            type="search"
                            class="input input-bordered input-sm mb-2 w-full"
                            placeholder="Search your friends"
                        />
                        <div class="space-y-1">
                            <div
                                v-for="friend in memberableFriends"
                                :key="friend.id"
                                class="flex items-center gap-2 rounded-lg px-1 py-1.5"
                            >
                                <img
                                    :src="friend.profile_picture_url || '/images/avatar/default.jpg'"
                                    alt=""
                                    class="h-7 w-7 rounded-full object-cover"
                                />
                                <span class="min-w-0 flex-1 truncate text-sm">{{ friend.dname || friend.username }}</span>
                                <button
                                    type="button"
                                    class="btn btn-primary btn-xs"
                                    :disabled="busy === `add-${friend.id}`"
                                    @click="addMember(friend)"
                                >
                                    {{ conversation?.invite_only ? 'Invite' : 'Add' }}
                                </button>
                            </div>
                            <p v-if="!memberableFriends.length" class="text-xs text-base-content/50">No friends left to add.</p>
                        </div>
                    </div>
                </template>
            </section>
        </div>
    </aside>
</template>
