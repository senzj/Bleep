<script setup>
defineProps({
	modelValue: {
		type: String,
		default: '',
	},
	disabled: {
		type: Boolean,
		default: false,
	},
});

const emit = defineEmits(['update:modelValue', 'submit', 'typing']);

const onKeydown = (event) => {
	emit('typing');

	if (event.key === 'Enter' && !event.shiftKey) {
		event.preventDefault();
		emit('submit');
	}
};
</script>

<template>
	<input
		:value="modelValue"
		:disabled="disabled"
		type="text"
		class="input input-bordered h-10 flex-1"
		placeholder="Type a message..."
		@input="emit('update:modelValue', $event.target.value)"
		@keydown="onKeydown"
	/>
</template>
