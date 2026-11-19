<script setup>
defineProps({
	src: { type: String, required: true },
	alt: { type: String, default: 'Image' },
});
</script>

<template>
	<div data-bleep-media class="w-full">
		<div
			class="w-full cursor-pointer overflow-hidden rounded-xl"
			data-media-index="0"
			data-media-type="image"
			:data-media-src="src"
			:data-media-alt="alt"
		>
			<img
				:src="src"
				:alt="alt"
				class="max-h-[70vh] w-full rounded-xl object-cover hover:opacity-90 transition-opacity select-none"
				loading="lazy"
			/>
		</div>
	</div>
</template>
