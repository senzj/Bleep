<script setup>
import { computed, ref } from 'vue';
import {
    Flame,
    Heart,
    Image as ImageIcon,
    Laugh,
    Lightbulb,
    Maximize2,
    MoreVertical,
    Music,
    Pencil,
    Smile,
    SmilePlus,
    ThumbsUp,
    Trash2,
    Video,
} from 'lucide-vue-next';
import { REACTIONS, formatEditedTimestamp, formatNoteTimestamp, noteVariant } from './boardLayout';

const props = defineProps({
    note: { type: Object, required: true },
    placement: { type: Object, required: true },
    metrics: { type: Object, required: true },
    isNew: { type: Boolean, default: false },
    delay: { type: Number, default: 0 },
    pendingReaction: { type: String, default: null },
    openMenu: { type: Object, default: null },
});

const emit = defineEmits(['react', 'open', 'report', 'edit', 'delete', 'menu-toggle']);

const isMenuOpen = (menu) =>
    props.openMenu?.noteId === props.note.id && props.openMenu?.menu === menu;

const toggleMenu = (menu) => {
    emit('menu-toggle', {
        noteId: props.note.id,
        menu,
        open: !isMenuOpen(menu),
    });
};

const mediaFailed = ref(false);

const variant = computed(() =>
    noteVariant(props.note.id, props.note.color, props.note.texture)
);

const paperClass = computed(() => {
    const { style, pattern, fold } = variant.value;

    if (fold) {
        return `bulletin-paper--folded bulletin-paper--folded-${fold.corner}`;
    }

    return pattern ? `bulletin-paper--${style}-${pattern}` : `bulletin-paper--${style}`;
});

const timestamp = computed(() => formatNoteTimestamp(props.note.created_at));

const editedTimestamp = computed(() =>
    formatEditedTimestamp(props.note.created_at, props.note.edited_at)
);

const counts = computed(() => props.note.reactions?.counts ?? {});

const mine = computed(() => props.note.reactions?.mine ?? null);

const mineReaction = computed(() => REACTIONS.find((reaction) => reaction.key === mine.value) ?? null);

const REACTION_ICONS = {
    heart: Heart,
    thumbs_up: ThumbsUp,
    laugh: Laugh,
    wow: Smile,
    fire: Flame,
    idea: Lightbulb,
};

const reactionIcon = computed(() => REACTION_ICONS[mine.value] ?? SmilePlus);

const totalReactions = computed(() =>
    REACTIONS.reduce((total, reaction) => total + reactionCount(reaction.key), 0)
);

const reactionCount = (key) => Number(counts.value[key] ?? 0);

const pick = (key) => {
    emit('menu-toggle', { noteId: props.note.id, menu: 'reaction', open: false });
    emit('react', key);
};

const reportNote = () => {
    emit('menu-toggle', { noteId: props.note.id, menu: 'more', open: false });
    emit('report');
};

const editNote = () => {
    emit('menu-toggle', { noteId: props.note.id, menu: 'more', open: false });
    emit('edit');
};

const deleteNote = () => {
    emit('menu-toggle', { noteId: props.note.id, menu: 'more', open: false });
    emit('delete');
};

const media = computed(() => {
    if (props.note.media_url) {
        return {
            url: props.note.media_url,
            kind: props.note.media_kind ?? 'image',
        };
    }

    if (props.note.image_url) {
        return { url: props.note.image_url, kind: 'image' };
    }

    return null;
});

const showMedia = computed(() => Boolean(media.value) && !mediaFailed.value);

const showInlineMedia = computed(() => showMedia.value && props.placement.h >= 2);

const hiddenMedia = computed(() => showMedia.value && !showInlineMedia.value);

const hiddenMediaIcon = computed(() => {
    if (media.value?.kind === 'video') {
        return Video;
    }

    if (media.value?.kind === 'audio') {
        return Music;
    }

    return ImageIcon;
});

const style = computed(() => {
    const { cell, gap, offsetX, offsetY, fontSize } = props.metrics;
    const { x, y, w, h } = props.placement;
    const pitch = cell + gap;
    const inset = variant.value.inset;
    const width = w * cell + (w - 1) * gap - inset * 2;
    const height = h * cell + (h - 1) * gap - inset * 2;

    const padding = Math.max(10, Math.min(16, cell * 0.055));
    const footer = Math.max(38, fontSize * 2.35);
    const signature = Math.max(32, fontSize * 2.5);
    let lines = Math.floor((height - padding * 2 - footer - signature) / (fontSize * 1.42));

    if (showInlineMedia.value) {
        lines -= 4;
    }

    if (props.note.link_url) {
        lines -= 1;
    }

    if (w > 1) {
        lines += 1;
    }

    return {
        left: `${offsetX + x * pitch + inset + variant.value.nudgeX - variant.value.pullX}px`,
        top: `${offsetY + y * pitch + inset + variant.value.nudgeY - variant.value.pullY}px`,
        width: `${width}px`,
        height: `${height}px`,
        '--paper': variant.value.color,
        '--paper-ink': variant.value.ink,
        '--rot': `${variant.value.rotation}deg`,
        '--pin-x': `${variant.value.pinX}%`,
        '--delay': `${props.delay}ms`,
        '--note-fs': `${fontSize}px`,
        '--line-clamp': String(Math.max(1, Math.min(lines, 20))),
        ...(variant.value.fold ? { '--fold-size': `${variant.value.fold.size}px` } : {}),
    };
});
</script>

<template>
    <article
        class="bulletin-note"
        :class="[
            `bulletin-note--${placement.w}x${placement.h}`,
            paperClass,
            {
                'bulletin-note--new': isNew,
                'bulletin-note--menu-open': isMenuOpen('reaction') || isMenuOpen('more'),
            },
        ]"
        :style="style"
        :data-note-id="note.id"
        :aria-label="`Note from ${note.anonymous_name}`"
    >
        <span class="bulletin-pin" aria-hidden="true"></span>
        <span
            v-if="variant.wear !== 'none'"
            class="bulletin-note__wear"
            :class="`bulletin-wear--${variant.wear}`"
            aria-hidden="true"
        ></span>

        <div class="bulletin-note__content">
            <p class="bulletin-note__text" @click="$emit('open')">
                {{ note.content }}
            </p>

            <div
                v-if="showInlineMedia"
                class="bulletin-note__media"
                role="button"
                tabindex="0"
                :aria-label="`Open the attachment from ${note.anonymous_name}`"
                @click="$emit('open')"
                @keydown.enter.prevent="$emit('open')"
                @keydown.space.prevent="$emit('open')"
            >
                <img
                    v-if="media.kind === 'image'"
                    :src="media.url"
                    :alt="`Image attached to ${note.anonymous_name}'s note`"
                    class="bulletin-note__image"
                    loading="lazy"
                    decoding="async"
                    referrerpolicy="no-referrer"
                    @error="mediaFailed = true"
                >
                <template v-else-if="media.kind === 'video'">
                    <video
                        :src="media.url"
                        class="bulletin-note__image"
                        muted
                        loop
                        playsinline
                        preload="metadata"
                        @error="mediaFailed = true"
                    ></video>
                    <span class="bulletin-note__media-badge" aria-hidden="true">▶</span>
                </template>
                <audio
                    v-else
                    :src="media.url"
                    class="bulletin-note__audio"
                    controls
                    preload="metadata"
                    @error="mediaFailed = true"
                ></audio>
            </div>

            <a
                v-if="note.link_url"
                :href="note.link_url"
                class="bulletin-note__link"
                target="_blank"
                rel="nofollow noopener noreferrer"
            >
                Open link ↗
            </a>

            <div class="bulletin-note__signature">
                <span class="bulletin-note__name">{{ note.anonymous_name }}</span>
                <time :datetime="note.created_at">
                    {{ timestamp }}<template v-if="editedTimestamp"> · {{ editedTimestamp }}</template>
                </time>
            </div>
        </div>

        <footer class="bulletin-note__footer">
            <details
                class="dropdown dropdown-top dropdown-start bulletin-note__menu"
                :open="isMenuOpen('reaction')"
            >
                <summary
                    class="btn btn-ghost btn-xs px-2"
                    :aria-label="
                        mineReaction
                            ? `Your reaction: ${mineReaction.label}. Change reaction`
                            : 'React to this note'
                    "
                    @click.prevent="toggleMenu('reaction')"
                >
                    <component
                        :is="reactionIcon"
                        class="bulletin-note__action-icon"
                        :size="18"
                        :stroke-width="2.1"
                        aria-hidden="true"
                    />
                    <span v-if="totalReactions" class="text-[11px] font-bold tabular-nums">
                        {{ totalReactions }}
                    </span>
                </summary>

                <ul
                    class="dropdown-content menu z-30 w-48 rounded-box bg-base-100 p-1 text-base-content shadow-xl"
                >
                    <li v-for="reaction in REACTIONS" :key="reaction.key">
                        <button
                            type="button"
                            class="text-sm"
                            :class="{ active: mine === reaction.key }"
                            :aria-pressed="mine === reaction.key"
                            :disabled="Boolean(pendingReaction)"
                            @click="pick(reaction.key)"
                        >
                            <span aria-hidden="true">{{ reaction.emoji }}</span>
                            <span>{{ reaction.label }}</span>
                            <span class="badge badge-ghost badge-sm ml-auto tabular-nums">
                                {{
                                    pendingReaction === reaction.key
                                        ? '…'
                                        : reactionCount(reaction.key)
                                }}
                            </span>
                        </button>
                    </li>
                </ul>
            </details>

            <button
                type="button"
                class="btn btn-ghost btn-xs px-2"
                :aria-label="
                    hiddenMedia
                        ? `Open the attachment and full note from ${note.anonymous_name}`
                        : `Read the full note from ${note.anonymous_name}`
                "
                @click.stop="$emit('open')"
            >
                <component
                    :is="hiddenMedia ? hiddenMediaIcon : Maximize2"
                    class="bulletin-note__action-icon"
                    :size="18"
                    :stroke-width="2.1"
                    aria-hidden="true"
                />
            </button>

            <details
                class="dropdown dropdown-top dropdown-end bulletin-note__menu"
                :open="isMenuOpen('more')"
            >
                <summary
                    class="btn btn-ghost btn-xs px-2"
                    :aria-label="`More actions for ${note.anonymous_name}'s note`"
                    @click.prevent="toggleMenu('more')"
                >
                    <MoreVertical
                        class="bulletin-note__action-icon"
                        :size="18"
                        :stroke-width="2.1"
                        aria-hidden="true"
                    />
                </summary>

                <ul
                    class="dropdown-content menu z-30 w-40 rounded-box bg-base-100 p-1 text-base-content shadow-xl"
                >
                    <li v-if="note.can_edit">
                        <button type="button" class="text-sm" @click="editNote">
                            <Pencil :size="15" :stroke-width="2.1" aria-hidden="true" />
                            <span>Edit note</span>
                        </button>
                    </li>
                    <li v-if="note.can_delete">
                        <button type="button" class="text-sm text-error" @click="deleteNote">
                            <Trash2 :size="15" :stroke-width="2.1" aria-hidden="true" />
                            <span>Delete note</span>
                        </button>
                    </li>
                    <li>
                        <button type="button" class="text-sm" @click="reportNote">
                            Report note
                        </button>
                    </li>
                </ul>
            </details>
        </footer>
    </article>
</template>
