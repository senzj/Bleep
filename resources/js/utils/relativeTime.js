/**
 * Format a date as relative time (e.g., "3hrs ago", "2d ago", "1w ago")
 * Respects user timezone for accurate date calculations
 *
 * @param {string|Date} date - The date to format
 * @param {string} timezone - User's timezone (e.g., 'America/New_York')
 * @returns {string} Relative time string
 */
export function formatRelativeTime(date, timezone = null) {
    if (!date) return '';

    // Parse the date
    const commentDate = new Date(date);
    if (isNaN(commentDate)) return '';

    // Get current time in user's timezone
    const now = new Date();
    const userTimezone = timezone || Intl.DateTimeFormat().resolvedOptions().timeZone || 'UTC';

    // Get time difference in milliseconds
    const diffMs = now - commentDate;
    const diffSecs = Math.floor(diffMs / 1000);

    // Within 60 seconds
    if (diffSecs < 60) {
        return `${diffSecs}s ago`;
    }

    const diffMins = Math.floor(diffSecs / 60);
    // Within 60 minutes
    if (diffMins < 60) {
        return `${diffMins}m ago`;
    }

    const diffHours = Math.floor(diffMins / 60);
    // Within 24 hours
    if (diffHours < 24) {
        return `${diffHours}h ago`;
    }

    const diffDays = Math.floor(diffHours / 24);
    // Within 7 days (1 week)
    if (diffDays < 7) {
        return `${diffDays}d ago`;
    }

    const diffWeeks = Math.floor(diffDays / 7);
    // Within 52 weeks (1 year)
    if (diffWeeks < 52) {
        return `${diffWeeks}w ago`;
    }

    // Beyond 1 year - show full date
    const formatter = new Intl.DateTimeFormat('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        timeZone: userTimezone,
    });

    return formatter.format(commentDate);
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
