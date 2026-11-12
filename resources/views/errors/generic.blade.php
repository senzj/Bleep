<x-error-page
    :status="$status ?? 500"
    :title="$title ?? 'Something went wrong'"
    :message="$message ?? 'An unexpected error occurred. Please try again in a moment.'"
/>
