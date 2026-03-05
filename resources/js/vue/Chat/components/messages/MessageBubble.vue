<script setup>
import { computed, ref } from 'vue';
import MessageMedia from './MessageMedia.vue';

const props = defineProps({
	message: {
		type: Object,
		required: true,
	},
	mine: {
		type: Boolean,
		default: false,
	},
	seenAvatars: {
		type: Array,
		default: () => [],
	},
});

const showSeenPopover = ref(false);

const visibleSeenBy = computed(() => props.seenAvatars.slice(0, 5));
const hiddenSeenBy = computed(() => props.seenAvatars.slice(5));

const messageStatusText = computed(() => {
	if (!props.mine || props.seenAvatars.length > 0) return '';

	switch (props.message.status) {
		case 'sending':
			return 'Sending';
		case 'sent':
			return 'Sent';
		case 'delivered':
			return 'Received';
		case 'seen':
			return 'Seen';
		default:
			return 'Sent';
	}
});
</script>

<template>
	<div class="flex flex-col" :class="mine ? 'items-end' : 'items-start mt-3'">
		<!-- Sender name -->
		<p v-if="!mine" class="mb-1 text-xs font-semibold opacity-70 ml-11">
			{{ message.sender?.dname || message.sender?.username || 'User' }}
		</p>

		<!-- Avatar + Bubble -->
		<div class="flex items-end gap-2" :class="mine ? 'flex-row-reverse' : 'flex-row'">
			<img
				:src="message.sender?.profile_picture_url || '/images/avatar/default.jpg'"
				:alt="`${message.sender?.username || 'user'} avatar`"
				class="h-8 w-8 shrink-0 rounded-full object-cover"
			>

			<div class="max-w-[80%] rounded-lg px-3 py-2" :class="mine ? 'bg-primary text-primary-content' : 'bg-base-100 border-base-300 border'">
				<p v-if="message.body" class="whitespace-pre-wrap text-sm">{{ message.body }}</p>
				<MessageMedia :message="message" />

				<div class="mt-1 text-[11px] opacity-75">
					<span>{{ new Date(message.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) }}</span>
				</div>
			</div>
		</div>

		<!-- Seen-by avatar stack -->
		<div v-if="seenAvatars.length > 0" class="flex items-center mt-1" :class="mine ? 'mr-12' : 'ml-11'">
			<img
				v-for="(person, index) in visibleSeenBy"
				:key="person.id"
				:src="person.profile_picture_url || '/images/avatar/default.jpg'"
				:alt="person.dname || person.username"
				:title="person.dname || person.username"
				:class="index > 0 ? '-ml-1.5' : ''"
				class="h-5 w-5 rounded-full object-cover border-2 border-base-100"
			/>

			<!-- +N more button with speech bubble popover -->
			<div v-if="hiddenSeenBy.length" class="relative -ml-1.5">
				<button
					class="h-5 w-5 rounded-full bg-base-300 text-base-content text-[9px] font-bold flex items-center justify-center border-2 border-base-100 cursor-pointer hover:bg-base-200"
					@click.stop="showSeenPopover = !showSeenPopover"
				>
					+{{ hiddenSeenBy.length }}
				</button>

				<!-- Click-outside backdrop -->
				<Teleport to="body">
					<div v-if="showSeenPopover" class="fixed inset-0 z-40" @click="showSeenPopover = false" />
				</Teleport>

				<!-- Speech bubble popover -->
				<div
					v-if="showSeenPopover"
					class="absolute bottom-full right-0 mb-3 min-w-40 rounded-2xl border border-base-300 bg-base-100 p-2 shadow-xl z-50"
				>
					<!-- Triangle tail (border outline layer) -->
					<div class="absolute -bottom-2 right-2 h-0 w-0 border-l-[7px] border-l-transparent border-r-[7px] border-r-transparent border-t-8 border-t-base-300" />
					<!-- Triangle tail (fill layer) -->
					<div class="absolute -bottom-2 right-2 h-0 w-0 border-l-[6px] border-l-transparent border-r-[6px] border-r-transparent border-t-[7px] border-t-base-100" />

					<p class="mb-1 px-1 text-[10px] font-semibold opacity-50">Seen by</p>
					<div
						v-for="person in hiddenSeenBy"
						:key="person.id"
						class="flex items-center gap-2 rounded-lg px-1 py-1 hover:bg-base-200"
					>
						<img
							:src="person.profile_picture_url || '/images/avatar/default.jpg'"
							class="h-5 w-5 shrink-0 rounded-full object-cover"
						/>
						<span class="max-w-28 truncate text-xs">{{ person.dname || person.username }}</span>
					</div>
				</div>
			</div>
		</div>

		<!-- Status text (Sending / Sent / Received) -->
		<p v-if="messageStatusText" class="mt-1 mr-12 text-[11px] opacity-80 text-right">
			{{ messageStatusText }}
		</p>
	</div>
</template>
