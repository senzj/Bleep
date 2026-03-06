<script setup>
defineProps({
	src: { type: String, required: true },
	alt: { type: String, default: 'Image' },
});
</script>

<template>
	<div data-bleep-media>
		<div
			class="inline-block cursor-pointer overflow-hidden rounded-lg"
			data-media-index="0"
			data-media-type="image"
			:data-media-src="src"
			:data-media-alt="alt"
		>
			<img
				:src="src"
				:alt="alt"
				class="max-h-64 w-auto rounded-lg object-cover hover:opacity-90 transition-opacity select-none"
				loading="lazy"
			/>
		</div>
	</div>
</template>
