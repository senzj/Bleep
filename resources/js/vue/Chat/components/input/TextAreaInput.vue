<script setup>
import { computed, nextTick, ref } from 'vue';

const props = defineProps({
	modelValue: {
		type: String,
		default: '',
	},
	disabled: {
		type: Boolean,
		default: false,
	},
	mentions: {
		type: Array,
		default: () => [],
	},
});

const emit = defineEmits(['update:modelValue', 'submit', 'typing']);

const inputRef = ref(null);
const mentionQuery = ref(null);
const highlighted = ref(0);

const suggestions = computed(() => {
	if (mentionQuery.value === null) return [];

	const term = mentionQuery.value.toLowerCase();

	return props.mentions
		.filter((user) => user.username)
		.filter((user) => {
			if (!term) return true;
			return user.username.toLowerCase().includes(term)
				|| (user.dname || '').toLowerCase().includes(term);
		})
		.slice(0, 6);
});

const isOpen = computed(() => suggestions.value.length > 0 && mentionQuery.value !== null);

const detectMention = (event) => {
	const value = event.target.value;
	const caret = event.target.selectionStart ?? value.length;
	const beforeCaret = value.slice(0, caret);
	const match = beforeCaret.match(/(^|\s)@([a-z0-9_.-]*)$/i);

	mentionQuery.value = match ? match[2] : null;
	highlighted.value = 0;
};

const onInput = (event) => {
	emit('update:modelValue', event.target.value);
	emit('typing');
	detectMention(event);
};

const choose = (user) => {
	const input = inputRef.value;
	if (!input || !user) return;

	const value = props.modelValue || '';
	const caret = input.selectionStart ?? value.length;
	const beforeCaret = value.slice(0, caret);
	const match = beforeCaret.match(/@([a-z0-9_.-]*)$/i);

	const start = match ? caret - match[1].length - 1 : caret;
	const nextValue = `${value.slice(0, start)}@${user.username} ${value.slice(caret)}`;

	emit('update:modelValue', nextValue);
	mentionQuery.value = null;

	nextTick(() => {
		const position = start + user.username.length + 2;
		input.focus();
		input.setSelectionRange(position, position);
	});
};

const onKeydown = (event) => {
	if (isOpen.value) {
		if (event.key === 'ArrowDown') {
			event.preventDefault();
			highlighted.value = (highlighted.value + 1) % suggestions.value.length;
			return;
		}

		if (event.key === 'ArrowUp') {
			event.preventDefault();
			highlighted.value = (highlighted.value - 1 + suggestions.value.length) % suggestions.value.length;
			return;
		}

		if (event.key === 'Enter' || event.key === 'Tab') {
			event.preventDefault();
			choose(suggestions.value[highlighted.value]);
			return;
		}

		if (event.key === 'Escape') {
			event.preventDefault();
			mentionQuery.value = null;
			return;
		}
	}

	if (event.key === 'Enter' && !event.shiftKey) {
		event.preventDefault();
		emit('submit');
	}
};

const onBlur = () => {
	// Let a click on a suggestion land before the list disappears.
	window.setTimeout(() => {
		mentionQuery.value = null;
	}, 120);
};
</script>

<template>
	<div class="relative flex-1">
		<input
			ref="inputRef"
			:value="modelValue"
			:disabled="disabled"
			type="text"
			class="input input-bordered h-10 w-full"
			placeholder="Type a message..."
			@input="onInput"
			@keydown="onKeydown"
			@blur="onBlur"
		/>

		<ul
			v-if="isOpen"
			class="absolute bottom-full left-0 z-30 mb-1 w-56 overflow-hidden rounded-lg border border-base-300 bg-base-100 py-1 shadow-lg"
			role="listbox"
			aria-label="Mention someone"
		>
			<li v-for="(user, index) in suggestions" :key="user.id">
				<button
					type="button"
					class="flex w-full items-center gap-2 px-3 py-1.5 text-left text-sm"
					:class="index === highlighted ? 'bg-primary/10 text-primary' : 'hover:bg-base-200'"
					role="option"
					:aria-selected="index === highlighted"
					@mousedown.prevent="choose(user)"
				>
					<img
						:src="user.profile_picture_url || '/images/avatar/default.jpg'"
						alt=""
						class="h-6 w-6 rounded-full object-cover"
					/>
					<span class="min-w-0 flex-1 truncate">{{ user.dname || user.username }}</span>
					<span class="text-xs text-base-content/50">@{{ user.username }}</span>
				</button>
			</li>
		</ul>
	</div>
</template>
