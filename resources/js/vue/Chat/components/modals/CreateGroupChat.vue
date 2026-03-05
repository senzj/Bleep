<script setup>
import { computed, nextTick, onMounted, ref } from 'vue';
import { useMessageStore } from '../../store/useMessageStore';

const emit = defineEmits(['close', 'created']);

const store = useMessageStore();

const groupName = ref('');
const selectedIds = ref([]);
const submitting = ref(false);
const error = ref('');
const search = ref('');

const filteredFriends = computed(() => {
	const keyword = search.value.trim().toLowerCase();
	return (store.state.userDirectory || []).filter((user) => {
		if (!keyword) return true;
		return (user.dname || '').toLowerCase().includes(keyword)
			|| user.username.toLowerCase().includes(keyword);
	});
});

const isSelected = (id) => selectedIds.value.includes(id);

const toggle = (id) => {
	const idx = selectedIds.value.indexOf(id);
	if (idx >= 0) {
		selectedIds.value.splice(idx, 1);
	} else {
		selectedIds.value.push(id);
	}
};

const submit = async () => {
	error.value = '';
	if (!groupName.value.trim()) {
		error.value = 'Please enter a group name.';
		return;
	}
	if (selectedIds.value.length < 2) {
		error.value = 'Select at least 2 people to create a group.';
		return;
	}
	submitting.value = true;
	try {
		await store.createGroupConversation({
			name: groupName.value.trim(),
			userIds: [...selectedIds.value],
		});
		emit('created');
	} catch (e) {
		error.value = e?.response?.data?.message || 'Could not create the group chat.';
	} finally {
		submitting.value = false;
	}
};

onMounted(async () => {
	await nextTick();
	if (window.createLucideIcons) {
		window.createLucideIcons();
	}
	if (!store.state.userDirectory.length) {
		store.fetchUserDirectory();
	}
});
</script>

<template>
	<div class="modal modal-open z-50" role="dialog" aria-modal="true">
		<div class="modal-box w-full max-w-md">
			<div class="mb-4 flex items-center justify-between">
				<h3 class="text-lg font-bold">Create Group Chat</h3>
				<button class="btn btn-ghost btn-sm btn-circle" type="button" @click="emit('close')">
					<i data-lucide="x" class="lucide lucide-sm"></i>
				</button>
			</div>

			<div class="space-y-4">
				<!-- Group Name -->
				<div>
					<label class="label mb-1 text-sm font-medium">Group Name</label>
					<input
						v-model="groupName"
						type="text"
						class="input input-bordered w-full"
						placeholder="e.g. The Squad"
						maxlength="100"
					/>
				</div>

				<!-- People Selector -->
				<div>
					<label class="label mb-1 text-sm font-medium">Add People</label>
					<input
						v-model="search"
						type="text"
						class="input input-bordered input-sm mb-2 w-full"
						placeholder="Search friends..."
					/>

					<div class="border-base-300 max-h-56 space-y-1 overflow-y-auto rounded-lg border p-2">
						<p v-if="store.state.loadingUsers" class="text-base-content/60 py-4 text-center text-sm">
							Loading friends...
						</p>

						<p v-else-if="!filteredFriends.length" class="text-base-content/60 py-4 text-center text-sm">
							No friends found.
						</p>

						<button
							v-for="user in filteredFriends"
							:key="user.id"
							type="button"
							class="flex w-full items-center gap-3 rounded-lg px-3 py-2 text-left transition-colors"
							:class="isSelected(user.id) ? 'bg-primary/15 ring-1 ring-primary/30' : 'hover:bg-base-200'"
							@click="toggle(user.id)"
						>
							<img
								:src="user.profile_picture_url || '/images/avatar/default.jpg'"
								:alt="`${user.username} avatar`"
								class="h-9 w-9 shrink-0 rounded-full object-cover"
							/>

							<div class="min-w-0 flex-1">
								<p class="truncate text-sm font-medium">{{ user.dname || user.username }}</p>
								<p class="text-base-content/40 truncate text-xs">@{{ user.username }}</p>
							</div>

							<!-- Check indicator -->
							<span v-if="isSelected(user.id)" class="text-primary shrink-0">
								<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
									<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
									<polyline points="22 4 12 14.01 9 11.01"/>
								</svg>
							</span>
						</button>
					</div>

				<p v-if="selectedIds.length > 0" class="text-base-content/60 mt-1 text-xs">
					{{ selectedIds.length }} person{{ selectedIds.length !== 1 ? 's' : '' }} selected
					</p>
				</div>

				<!-- Error -->
				<p v-if="error" class="text-error text-sm">{{ error }}</p>
			</div>

			<div class="modal-action mt-6">
				<button class="btn" type="button" :disabled="submitting" @click="emit('close')">
					Cancel
				</button>
				<button
					class="btn btn-primary"
					type="button"
					:disabled="submitting || selectedIds.length < 2 || !groupName.trim()"
					@click="submit"
				>
					<span v-if="submitting" class="loading loading-spinner loading-sm"></span>
					Create Group
				</button>
			</div>
		</div>
		<div class="modal-backdrop bg-black/40" @click="emit('close')" />
	</div>
</template>
