<script setup>
import LucideIcons from '../../../LucideIcons.vue';

defineProps({
	disabled: {
		type: Boolean,
		default: false,
	},
});

const emit = defineEmits(['files-selected']);

const pickFile = () => {
	const input = document.createElement('input');
	input.type = 'file';
	input.accept = 'image/*,video/*,audio/*,.pdf';
	input.multiple = true;

	input.onchange = () => {
		const files = Array.from(input.files || []);
		if (!files.length) return;

		const payload = files.map((file) => ({
			file,
			mediaKind: file.type.startsWith('audio/') ? 'audio' : 'media',
		}));

		emit('files-selected', payload);
	};

	input.click();
};
</script>

<template>
	<button class="btn btn-circle btn-outline" type="button" :disabled="disabled" @click="pickFile">
		<LucideIcons name="paperclip" class="h-5 w-5" />
	</button>
</template>
