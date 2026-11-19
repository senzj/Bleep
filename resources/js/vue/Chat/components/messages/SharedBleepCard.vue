<script setup>
import { computed } from 'vue';
import LucideIcons from '../../../LucideIcons.vue';

const props = defineProps({
    bleep: {
        type: Object,
        required: true,
    },
    mine: {
        type: Boolean,
        default: false,
    },
});

const postedAt = computed(() => {
    if (!props.bleep.created_at) return '';

    const date = new Date(props.bleep.created_at);
    if (Number.isNaN(date.getTime())) return '';

    return date.toLocaleDateString([], { month: 'short', day: 'numeric', year: 'numeric' });
});

const viewCount = computed(() => {
    const views = Number(props.bleep.views || 0);
    if (views >= 1000) return `${(views / 1000).toFixed(views >= 10000 ? 0 : 1)}k`;
    return String(views);
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
        class="block w-full overflow-hidden rounded-xl border bg-base-100 text-left transition hover:border-primary hover:bg-primary/5"
        :class="mine ? 'border-primary/30' : 'border-base-300'"
        :aria-label="`Open bleep by ${bleep.author_name}`"
    >
        <!-- Header: the original poster -->
        <div class="flex items-center gap-2 px-3 pt-2.5">
            <img
                v-if="bleep.author_profile_picture_url"
                :src="bleep.author_profile_picture_url"
                alt=""
                loading="lazy"
                class="h-7 w-7 shrink-0 rounded-full object-cover"
            />
            <span
                v-else
                class="grid h-7 w-7 shrink-0 place-items-center rounded-full bg-base-300 text-base-content/60"
            >
                <LucideIcons name="user" class="h-4 w-4" />
            </span>

            <div class="min-w-0 leading-tight">
                <p class="truncate text-xs font-semibold">{{ bleep.author_name }}</p>
                <p v-if="bleep.author_username" class="truncate text-[11px] text-base-content/50">
                    @{{ bleep.author_username }}
                </p>
            </div>

            <span v-if="bleep.is_nsfw" class="badge badge-warning badge-xs ml-auto shrink-0">NSFW</span>
        </div>

        <!-- Caption -->
        <p v-if="bleep.excerpt" class="mt-1.5 line-clamp-4 px-3 text-sm text-base-content">
            {{ bleep.excerpt }}
        </p>
        <p v-else class="mt-1.5 px-3 text-sm italic text-base-content/60">Media bleep</p>

        <!-- Media -->
        <img
            v-if="bleep.media_url"
            :src="bleep.media_url"
            alt=""
            loading="lazy"
            class="mt-2 max-h-72 w-full object-cover"
        />

        <!-- Footer: date and reach -->
        <div class="mt-2 flex items-center justify-between gap-2 border-t border-base-200 px-3 py-1.5 text-[11px] text-base-content/60">
            <span>{{ postedAt }}</span>
            <span class="flex items-center gap-1">
                <LucideIcons name="eye" class="h-3 w-3" />
                {{ viewCount }}
            </span>
        </div>
    </a>
</template>
