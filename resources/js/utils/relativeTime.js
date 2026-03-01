/**
 * Format a date as relative time (e.g., "3hrs ago", "2d ago", "1w ago")
 * Respects user timezone for accurate date calculations
 *
 * @param {string|Date} date - The date to format
 * @param {string} timezone - User's timezone (e.g., 'America/New_York')
 * @returns {string} Relative time string
 */
export function formatRelativeTime(date) {
    if (!date) return '';

    const commentDate =
        typeof date === 'string' && !date.endsWith('Z')
            ? new Date(date + 'Z')
            : new Date(date);

    if (isNaN(commentDate)) return '';

    const diffMs = Date.now() - commentDate.getTime();
    const diffSecs = Math.floor(diffMs / 1000);

    if (diffSecs < 60) return `${diffSecs}s ago`;

    const diffMins = Math.floor(diffSecs / 60);
    if (diffMins < 60) return `${diffMins}m ago`;

    const diffHours = Math.floor(diffMins / 60);
    if (diffHours < 24) return `${diffHours}h ago`;

    const diffDays = Math.floor(diffHours / 24);
    if (diffDays < 7) return `${diffDays}d ago`;

    const diffWeeks = Math.floor(diffDays / 7);
    if (diffWeeks < 52) return `${diffWeeks}w ago`;

    return new Intl.DateTimeFormat('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    }).format(commentDate);
}

/**
 * Format a date with full details in specified timezone
 *
 * @param {string|Date} date - The date to format
 * @param {string} timezone - User's timezone
 * @returns {string} Formatted date string
 */
export function formatFullDate(date, timezone = null) {
    if (!date) return '';

    const commentDate = new Date(date);
    if (isNaN(commentDate)) return '';

    const userTimezone = timezone || Intl.DateTimeFormat().resolvedOptions().timeZone || 'UTC';

    const formatter = new Intl.DateTimeFormat('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
        timeZone: userTimezone,
    });

    return formatter.format(commentDate);
}
