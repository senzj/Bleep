<script setup>
import { computed, toRef } from 'vue';
import { useAvatarCache } from '../../composables/useAvatarCache';

const props = defineProps({
	src: { type: String, default: '' },
	alt: { type: String, default: '' },
});

const urlRef = computed(() => props.src || '/images/avatar/default.jpg');
const cachedSrc = useAvatarCache(urlRef);
</script>

<template>
	<img :src="cachedSrc" :alt="alt" />
</template>
