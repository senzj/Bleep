<script setup>
import LucideIcons from '../../../LucideIcons.vue';

defineProps({
    bleep: {
        type: Object,
        required: true,
    },
});
</script>

<template>
    <div
        v-if="!bleep.available"
        class="flex items-center gap-2 rounded-xl border border-base-300 bg-base-200/60 px-3 py-2 text-xs text-base-content/60"
    >
        <LucideIcons name="link-2" class="h-4 w-4 shrink-0" />
        <span>This bleep is no longer available</span>
    </div>

    <a
        v-else
        :href="bleep.url"
        target="_blank"
        rel="noopener noreferrer"
        class="flex max-w-sm items-stretch gap-3 overflow-hidden rounded-xl border border-base-300 bg-base-100 text-left transition hover:border-primary hover:bg-primary/5"
    >
        <img
            v-if="bleep.media_url"
            :src="bleep.media_url"
            alt=""
            loading="lazy"
            class="h-20 w-20 shrink-0 object-cover"
        />

        <div class="min-w-0 flex-1 px-3 py-2">
            <div class="flex items-center gap-1 text-[11px] uppercase tracking-wide text-base-content/50">
                <LucideIcons name="at-sign" class="h-3 w-3 shrink-0" />
                <span class="truncate">{{ bleep.author_name }}</span>
            </div>

            <p v-if="bleep.excerpt" class="mt-1 line-clamp-3 text-sm text-base-content">
                {{ bleep.excerpt }}
            </p>
            <p v-else class="mt-1 text-sm italic text-base-content/60">Media bleep</p>

            <div class="mt-1.5 flex items-center gap-1 text-[11px] font-medium text-primary">
                <LucideIcons name="external-link" class="h-3 w-3" />
                <span>Open bleep</span>
                <span v-if="bleep.is_nsfw" class="badge badge-warning badge-xs ml-1">NSFW</span>
            </div>
        </div>
    </a>
</template>
