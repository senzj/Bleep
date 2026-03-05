<script setup>
defineProps({
	modelValue: {
		type: String,
		default: '',
	},
});

const emit = defineEmits(['update:modelValue']);
</script>

<template>
	<label class="input input-bordered mb-3 flex w-full items-center gap-2 rounded-md">
		<input
			:value="modelValue"
			type="text"
			class="grow"
			placeholder="Search chats"
			@input="emit('update:modelValue', $event.target.value)"
		>
	</label>
</template>
