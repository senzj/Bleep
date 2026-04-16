# Bleep

Bleep is a simple Laravel social media platform inspired by Chirper/Twitter.
It focuses on short posts, social interactions, and live chat, with user-driven UI customization powered by DaisyUI themes.

## Features

- Post, edit, and delete short updates ("bleeps")
- Like, repost, comment, and reply
- Share posts with tokenized share links
- User profiles with lazy-loaded content
- Follow system with follow requests
- Blocked users management
- Report flow for content moderation
- Live chat with real-time updates
- Message reactions, edits, deletes, read states, and media upload
- User preference settings, including appearance/theme and layout options
- Admin/moderation routes for management tools

## Tech Stack

- Backend: Laravel 12, PHP 8.2+
- Realtime: Laravel Reverb + Laravel Echo + Pusher JS protocol
- Frontend: Blade + Vite + Tailwind CSS v4 + DaisyUI
- JS ecosystem: Alpine.js, Vue 3 (for selected modules), Chart.js, Lucide icons
- Testing: Pest + Laravel testing tools

## Requirements

- PHP 8.2+
- Composer
- Node.js 18+ and npm
- A SQL database supported by Laravel (SQLite/MySQL/PostgreSQL)

## Quick Start

1. Clone the repository.
2. Install PHP dependencies.
3. Create and configure your environment file.
4. Run migrations.
5. Install frontend dependencies.
6. Start development services.

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
npm install
composer run dev
```

`composer run dev` starts these services concurrently:

- Laravel app server
- Queue worker
- Reverb websocket server
- Vite dev server

## Environment Notes

For live chat and broadcasting to work, ensure your `.env` has a valid Reverb/broadcast configuration. Typical values include:

```env
BROADCAST_CONNECTION=reverb
QUEUE_CONNECTION=database

REVERB_APP_ID=local-app-id
REVERB_APP_KEY=local-app-key
REVERB_APP_SECRET=local-app-secret
REVERB_HOST=127.0.0.1
REVERB_PORT=8080
REVERB_SCHEME=http
```

Use your own values for local/staging/production as needed.

## Theming and UI Customization

Bleep uses DaisyUI presets (`themes: all`) and exposes appearance settings in the user preferences page.

Users can customize:

- Theme preset (DaisyUI theme list)
- Navigation layout (horizontal or vertical)
- Other display/content preferences from settings

This makes it easy to personalize the UI without changing core styles.

## Useful Commands

```bash
# Full local setup (installs deps, prepares env, migrates, builds assets)
composer run setup

# Development mode (server + queue + reverb + vite)
composer run dev

# Build frontend assets
npm run build

# Run tests
composer run test
```

## Project Structure (High Level)

- `app/Http/Controllers` - application controllers (bleeps, chat, settings, social)
- `app/Models` - Eloquent models for posts, users, chat, follows, reports, etc.
- `resources/views` - Blade templates
- `resources/js` - frontend modules (chat, feed interactions, settings UI)
- `resources/css` - Tailwind + DaisyUI styling
- `routes/web.php` - web and API-like app routes

## Current Product Direction

This project is intentionally simple and practical:

- A lightweight social feed experience
- Real-time messaging for direct/group chat
- Strong user control over visual style and preferences

## Contributing

1. Create a feature branch.
2. Keep changes focused and small.
3. Run tests before opening a PR.
4. Open a pull request with a clear summary.

## License

This project is open-sourced under the MIT license.
