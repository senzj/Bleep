export const NOTE_GAP = 14;
export const BOARD_PADDING = 20;
export const MAX_NOTE_WIDTH = 2;
export const MAX_NOTE_HEIGHT = 4;
export const DESKTOP_MAX_ROWS = 4;
export const MIN_NOTE_CELL = 150;

export const REACTIONS = [
    { key: 'heart', emoji: '❤️', label: 'Love' },
    { key: 'thumbs_up', emoji: '👍', label: 'Like' },
    { key: 'laugh', emoji: '😂', label: 'Funny' },
    { key: 'wow', emoji: '😮', label: 'Surprised' },
    { key: 'fire', emoji: '🔥', label: 'Fire' },
    { key: 'idea', emoji: '💡', label: 'Idea' },
];

export const REPORT_REASONS = [
    { key: 'spam', label: 'Spam or advertising' },
    { key: 'harassment', label: 'Harassment or hate' },
    { key: 'unsafe', label: 'Unsafe link or image' },
    { key: 'other', label: 'Something else' },
];

export const PAPER_COLORS = [
    { value: '#fef08a', label: 'Butter' },
    { value: '#fbcfe8', label: 'Blossom' },
    { value: '#bbf7d0', label: 'Mint' },
    { value: '#bfdbfe', label: 'Sky' },
    { value: '#ddd6fe', label: 'Lilac' },
    { value: '#fed7aa', label: 'Apricot' },
    { value: '#fecaca', label: 'Rose' },
    { value: '#e5e7eb', label: 'Pebble' },
];

export const PAPER_TEXTURES = [
    { value: 'plain', label: 'Plain' },
    { value: 'torn', label: 'Torn' },
    { value: 'folded', label: 'Folded' },
    { value: 'crumpled', label: 'Crumpled' },
    { value: 'scratched', label: 'Scratched' },
    { value: 'perforated', label: 'Perforated' },
    { value: 'scraps', label: 'Scraps' },
    { value: 'notebook', label: 'Notebook' },
    { value: 'torn-edges', label: 'Torn edges' },
    { value: 'zigzag', label: 'Zigzag' },
];

const NOTE_STYLES = PAPER_TEXTURES.map((texture) => texture.value);

/**
 * Some designs have several hand-drawn variants. Picking one from the note hash
 * keeps a note stable across visits while letting notes with the same texture
 * look different from each other.
 */
const PATTERN_COUNTS = {
    crumpled: 4,
    scratched: 4,
    scraps: 4,
};

/**
 * Random wear and tear: a torn edge or a folded corner. "none" keeps some
 * notes clean so the board does not look uniformly damaged.
 */
const NOTE_WEARS = [
    'none',
    'none',
    'tear-top',
    'tear-right',
    'tear-bottom',
    'tear-left',
    'fold-tl',
    'fold-tr',
    'fold-br',
    'fold-bl',
];

const dateFormatter = new Intl.DateTimeFormat('en-US', {
    month: 'short',
    day: '2-digit',
    year: 'numeric',
});

const timeFormatter = new Intl.DateTimeFormat('en-US', {
    hour: 'numeric',
    minute: '2-digit',
    hour12: true,
});

const isHexColor = (value) => /^#[0-9a-f]{6}$/i.test(value ?? '');

/**
 * Pick a readable ink colour for any paper colour the visitor chooses.
 */
export function inkForColor(value) {
    if (!isHexColor(value)) {
        return '#2b2412';
    }

    const hex = value.slice(1);
    const channels = [0, 2, 4].map((offset) => {
        const channel = parseInt(hex.slice(offset, offset + 2), 16) / 255;

        return channel <= 0.03928
            ? channel / 12.92
            : Math.pow((channel + 0.055) / 1.055, 2.4);
    });

    const luminance = 0.2126 * channels[0] + 0.7152 * channels[1] + 0.0722 * channels[2];

    return luminance > 0.42 ? '#2b2412' : '#fffdf5';
}

/**
 * Pick a cell size and column count that fills the board width without a
 * leftover strip. On desktop the row count is fixed first, so the cell is also
 * capped by the available height and notes shrink on shorter screens instead of
 * disappearing off the bottom.
 *
 * @returns {{cell: number, columns: number, padding: number}}
 */
export function boardMetrics(viewportWidth, availableHeight = null, rowCount = DESKTOP_MAX_ROWS) {
    const padding = viewportWidth < 640 ? 12 : BOARD_PADDING;
    const target = viewportWidth < 520 ? 168 : viewportWidth < 768 ? 178 : 205;

    let columns = Math.max(
        2,
        Math.floor((viewportWidth - padding * 2 + NOTE_GAP) / (target + NOTE_GAP))
    );

    let cell = Math.max(
        140,
        Math.min(
            238,
            Math.floor((viewportWidth - padding * 2 - (columns - 1) * NOTE_GAP) / columns)
        )
    );

    if (availableHeight) {
        const heightCell = Math.floor(
            (availableHeight - (rowCount - 1) * NOTE_GAP) / Math.max(1, rowCount)
        );

        if (heightCell < cell) {
            cell = Math.max(132, heightCell);
            columns = Math.max(
                2,
                Math.floor((viewportWidth - padding * 2 + NOTE_GAP) / (cell + NOTE_GAP))
            );
        }
    }

    return { cell, columns, padding };
}

/**
 * Keep note copy readable on phones without letting it explode on wide desks.
 */
export function noteFontSize(cell) {
    return Math.max(14, Math.min(18, Math.round(cell * 0.075)));
}

/**
 * Shelf-pack notes into a grid of fixed rows that grows to the right.
 *
 * Every note is placed at the first free cell scanning column by column, which
 * keeps the newest notes near the start of the board while mixed note sizes
 * still break up the grid. `reserved` lets the page keep a hole for the
 * composer so nothing ever sits underneath it.
 *
 * @returns {{ placed: Array<{note: object, x: number, y: number, w: number, h: number}>, columns: number }}
 */
export function packNotes(notes, { rows, reserved = [] }) {
    const rowCount = Math.max(1, rows);
    const occupied = new Set();

    const mark = (x, y, w, h) => {
        for (let dx = 0; dx < w; dx += 1) {
            for (let dy = 0; dy < h; dy += 1) {
                occupied.add(`${x + dx}:${y + dy}`);
            }
        }
    };

    const isFree = (x, y, w, h) => {
        if (y + h > rowCount) {
            return false;
        }

        for (let dx = 0; dx < w; dx += 1) {
            for (let dy = 0; dy < h; dy += 1) {
                if (occupied.has(`${x + dx}:${y + dy}`)) {
                    return false;
                }
            }
        }

        return true;
    };

    reserved.forEach((rect) => mark(rect.x, rect.y, rect.w, rect.h));

    const placed = [];
    let maxColumn = 0;

    for (const note of notes) {
        const w = Math.min(MAX_NOTE_WIDTH, Math.max(1, Number(note.width) || 1));
        const h = Math.min(rowCount, MAX_NOTE_HEIGHT, Math.max(1, Number(note.height) || 1));

        let position = null;

        for (let x = 0; x < 500 && !position; x += 1) {
            for (let y = 0; y + h <= rowCount; y += 1) {
                if (isFree(x, y, w, h)) {
                    position = { x, y };
                    break;
                }
            }
        }

        if (!position) {
            position = { x: maxColumn, y: 0 };
        }

        mark(position.x, position.y, w, h);
        maxColumn = Math.max(maxColumn, position.x + w);
        placed.push({ note, x: position.x, y: position.y, w, h });
    }

    return { placed, columns: maxColumn };
}

/**
 * Keep the top-left cell(s) free for the composer.
 */
export function composerRect(rows, width = 1, height = 2) {
    return {
        x: 0,
        y: 0,
        w: width,
        h: Math.min(height, Math.max(1, rows)),
    };
}

/**
 * Stable per-note look. The same note keeps the same paper design, pin and tilt
 * on every visit because the values come from the public ULID, not the layout.
 *
 * A note is pinned somewhere along its top edge. Because the paper hangs from
 * that pin, the tilt follows the pin: a pin left of centre makes the right side
 * dip (clockwise, positive CSS rotation) and a pin right of centre raises it.
 */
export function noteVariant(id = '', paperColor = null, paperTexture = null) {
    let hash = 2166136261;

    for (let index = 0; index < id.length; index += 1) {
        hash ^= id.charCodeAt(index);
        hash = Math.imul(hash, 16777619);
    }

    const unsigned = hash >>> 0;
    const fallback = PAPER_COLORS[unsigned % PAPER_COLORS.length].value;
    const color = isHexColor(paperColor) ? paperColor.toLowerCase() : fallback;
    const pinX = 14 + ((unsigned >>> 16) % 72);
    const lean = Math.max(-2.2, Math.min(2.2, (50 - pinX) * 0.055));
    const style = NOTE_STYLES.includes(paperTexture)
        ? paperTexture
        : NOTE_STYLES[(unsigned >>> 4) % NOTE_STYLES.length];
    const patternCount = PATTERN_COUNTS[style] ?? 0;
    const patternSeed = (unsigned ^ 0x9e3779b9) >>> 0;

    // Folded paper loses one corner. The corner and size follow the note hash,
    // so folded notes do not all point the same way.
    const fold = style === 'folded'
        ? {
              corner: ['tr', 'tl', 'br', 'bl'][(unsigned >>> 27) % 4],
              size: 14 + ((unsigned >>> 29) % 9),
          }
        : null;

    // Horizontal pull varies the gap and can close it completely, so a few notes
    // tuck slightly under their neighbour. Vertical pull stays short of the gap
    // so signatures and dates are never covered from above.
    const pullSeed = (unsigned >>> 13) % 100;
    const pullX = pullSeed < 35 ? 8 + ((unsigned >>> 21) % 9) : (unsigned >>> 21) % 10;

    return {
        color,
        ink: inkForColor(color),
        style,
        pattern: patternCount ? (patternSeed % patternCount) + 1 : null,
        fold,
        wear: NOTE_WEARS[((unsigned >>> 9) ^ (unsigned >>> 25)) % NOTE_WEARS.length],
        rotation: lean,
        pinX,
        // Small deterministic insets keep notes from looking machine-aligned.
        inset: 2 + ((unsigned >>> 20) % 4),
        nudgeX: ((unsigned >>> 24) % 5) - 2,
        nudgeY: ((unsigned >>> 12) % 5) - 2,
        pullX,
        pullY: (unsigned >>> 19) % 7,
    };
}

export function formatNoteTimestamp(value) {
    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return '';
    }

    return `${dateFormatter.format(date)} | ${timeFormatter.format(date).toLowerCase()}`;
}

/**
 * Edits show the time only when they happened on the same calendar day as the
 * original note; otherwise the full date makes the difference obvious.
 */
export function formatEditedTimestamp(createdValue, editedValue) {
    if (!editedValue) {
        return '';
    }

    const edited = new Date(editedValue);

    if (Number.isNaN(edited.getTime())) {
        return '';
    }

    const created = new Date(createdValue);
    const time = timeFormatter.format(edited).toLowerCase();
    const sameDay =
        !Number.isNaN(created.getTime()) &&
        dateFormatter.format(created) === dateFormatter.format(edited);

    return sameDay ? `edited ${time}` : `edited ${dateFormatter.format(edited)} | ${time}`;
}
