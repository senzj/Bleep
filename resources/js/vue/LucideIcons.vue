<script setup lang="ts">
    import { computed } from 'vue';
    import { vueIcons } from '../icons/vue/index.js';

    const props = defineProps<{
        name: VueIconName;
        size?: number | string;
        stroke?: number | string;
    }>();

    // Convert kebab-case to camelCase (e.g., 'ellipsis-vertical' -> 'EllipsisVertical')
    const iconName = computed(() => {
        const parts = props.name.split('-');
        return parts
            .map((part, idx) => part.charAt(0).toUpperCase() + part.slice(1))
            .join('');
    });

    const icon = computed(() => vueIcons[iconName.value]);
</script>

<template>
    <component
        :is="icon"
        :size="size ?? 20"
        :stroke-width="stroke ?? 1.75"
        class="lucide-icon"
    />
</template>

