<script setup>
import { ref, onMounted, onBeforeUnmount } from 'vue';
import LucideIcon from '../LucideIcons.vue';

const props = defineProps({
    buttonTitle: {
        type: String,
        default: 'More options',
    },
    buttonClass: {
        type: String,
        default: 'btn btn-ghost btn-xs rounded',
    },
    buttonIcon: {
        type: String,
        default: 'ellipsis-vertical',
    },
    buttonIconSize: {
        type: [Number, String],
        default: 16,
    },
    menuClass: {
        type: String,
        default: 'absolute right-0 top-full mt-1 menu bg-base-100 rounded-box z-50 shadow-lg border border-base-300/50 flex flex-col w-max',
    },
});

const root = ref(null);
const isOpen = ref(false);

const close = () => {
    isOpen.value = false;
};

const toggle = () => {
    isOpen.value = !isOpen.value;
};

const handleDocumentClick = (event) => {
    if (root.value && !root.value.contains(event.target)) {
        close();
    }
};

onMounted(() => document.addEventListener('click', handleDocumentClick, { passive: true }));

onBeforeUnmount(() => {
    close();
    document.removeEventListener('click', handleDocumentClick);
});
</script>

<template>
    <div ref="root" class="relative">
        <button
            type="button"
            :title="props.buttonTitle"
            :class="props.buttonClass"
            @click.stop="toggle"
        >
            <LucideIcon :name="props.buttonIcon" :size="props.buttonIconSize" />
        </button>

        <ul v-if="isOpen" :class="props.menuClass">
            <slot :close="close" :toggle="toggle" :is-open="isOpen" />
        </ul>
    </div>
</template>
