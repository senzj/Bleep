# Bleep

Bleep started as a fork of the Laravel Chirper project and has grown into a small social network with short posts, profiles, follows, moderation tools, and real-time messaging. It is also a long-term learning project for Laravel, Vue, database design, and application security.

The application is a Blade-first Laravel monolith. Vue 3 is used for selected interactive areas (the chat interface and the comments modal) rather than for the whole frontend. There is no Inertia layer and no separate API SPA.

## Features

### Social

- Create, edit, and delete short posts ("bleeps"), with optional image, video, or audio attachments
- Anonymous posts and NSFW posts with per-user content and blur preferences
- For You, Following, and Friends feed tabs with pagination and lazy loading
- Likes on bleeps, comments, and replies
- Comments, nested replies, comment editing/deleting, and comment media
- Reposts and tokenized share links
- Post view counts and soft-deleted posts
- User profiles with separate bleeps and reposts tabs
- Follow/unfollow, follower and following lists, and follow requests for private profiles
- People discovery and user search
- Blocking and blocked-user management
- Reporting of bleeps and comments

### Messaging

- Direct messages and group conversations
- Real-time message delivery, conversation updates, and online presence
- Unread counts, read receipts, and delivery states
- Replies, reactions, editing, and soft deletion
- Image, video, audio, and recorded voice messages
- Typing indicators
- Conversation search and user search when starting a chat

### Accounts and privacy

- Custom session-based registration, login, and logout
- Username/email availability checks and a random username generator
- Profile editing with profile-picture upload and cropping
- Password changes for signed-in users
- Per-user preferences: theme, navigation layout, feed defaults, content visibility, notification sounds, and privacy options
- Remembered-device login, session/device revocation, and activity logs
- Progressive login/registration lockouts after repeated failures
- Bans and moderation strikes

### Administration

- Admin dashboard with chart data
- User management, roles, bans, and strikes
- Report review, dismissal, content removal, and reporter bans
- Session and remembered-device management
- Application logs and visit analytics

## Tech Stack

| Area | Technology |
| --- | --- |
| Backend | Laravel 12 (currently 12.70.1), PHP 8.2+ required, PHP 8.4 used in deployment |
| Frontend | Blade templates with Vue 3 islands, Alpine.js |
| Styling | Tailwind CSS v4 (CSS-first configuration) with DaisyUI themes |
| Build | Vite 7 with the Laravel Vite plugin |
| Realtime | Laravel Reverb, Laravel Echo, Pusher JS protocol |
| Database | MySQL (default application connection); in-memory SQLite for tests |
| Charts/icons | Chart.js, Lucide |
| Testing | Pest 3, PHPUnit 11 |
| Code style | Laravel Pint |
| Deployment | GitHub Actions building assets and uploading over FTP |

## Architecture

- `routes/web.php` contains the application's web routes and small internal JSON endpoints. There is no versioned public API.
- Controllers are grouped by domain: `Auth`, `Bleep`, `Chat`, `Admin`, plus social/settings controllers.
- `app/Services` holds reusable logic: `FeedService` (feed scopes and ordering), `MediaUploadService` (uploads and deletions), and `Chat\ChatMessageFormatter` (message payloads).
- Eloquent models cover posts, comments, follows, blocks, reports, preferences, conversations, messages, deliveries, reactions, and related records.
- Authorization uses `BleepPolicy`, `CommentsPolicy`, and the `is_admin` gate. Admin routes are protected with `can:is_admin`.
- `bootstrap/app.php` registers the global web middleware: timezone updates, ban checks, remembered-device checks, and progressive lockouts.
- Chat broadcasting is authorized in `routes/channels.php`; only conversation participants can join a conversation channel.
- Blade views live in `resources/views`. Reusable UI is in `resources/views/components`.
- Vue components live in `resources/js/vue`; `resources/js/app.js` mounts them only when the matching root element exists.
- Tailwind and DaisyUI are configured in `resources/css/app.css` using Tailwind v4's `@theme` and `@plugin` directives.

Request flow:

```text
Browser
  -> Blade page + mounted Vue island
  -> routes/web.php
  -> controller -> service/model -> MySQL
  -> Blade response, JSON response, or broadcast event -> Reverb -> Echo
```

## Requirements

- PHP 8.2 or newer with the usual Laravel extensions
- Composer 2
- MySQL (the repository defaults to MySQL; tests use SQLite)
- Node.js 18+ and npm (CI uses Node 22)
- Optional: a Reverb-compatible websocket setup for real-time chat

## Local Development

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan storage:link
npm install
npm run build
composer run dev
```

On Windows PowerShell, use `copy .env.example .env` instead of `cp`.

`composer run dev` starts the Laravel server, queue worker, Reverb, and Vite concurrently. The application is then typically available at `http://localhost:8000`.

`composer run setup` is a shortcut that installs dependencies, creates `.env`, generates a key, migrates, installs npm packages, and builds assets. It does not create the public storage link, so run `php artisan storage:link` once after setup.

Configure the database in `.env` before migrating:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=bleep
DB_USERNAME=root
DB_PASSWORD=
```

Optional demo data can be loaded with `php artisan db:seed`. Do not run the demo seeder in production; it creates development accounts with known credentials.

### Realtime chat

`.env.example` defaults to `BROADCAST_CONNECTION=log`, which does not deliver live messages. For local real-time chat, configure Reverb and run it (already included in `composer run dev`):

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

## Useful Commands

| Command | Purpose |
| --- | --- |
| `composer run dev` | Run server, queue worker, Reverb, and Vite |
| `composer run prodlocal` | Run a production-like local server without Vite |
| `npm run build` | Build frontend assets |
| `composer run test` | Clear config and run Pest |
| `php artisan test` | Run the test suite directly |
| `vendor/bin/pint` | Format PHP code |
| `npx skills list` | List installed project agent skills |
| `opencode mcp list` | Check configured MCP server connections |

## Testing

Pest is configured with `phpunit.xml` using an in-memory SQLite database. `RefreshDatabase` is enabled for feature tests, Vite is stubbed during tests, and the broadcasting connection is set to Reverb with test-only credentials so channel authorization can be exercised.

The current suite covers the public feed, bleep creation and deletion authorization, admin dashboard access, report moderation, and private conversation channel authorization. Run it with `php artisan test` before opening a pull request.

## Project Structure

```text
app/Http/Controllers/   Web and internal JSON controllers
app/Http/Middleware/    Ban, device, timezone, and lockout middleware
app/Models/             Eloquent models
app/Policies/           Post and comment authorization
app/Services/           Feed, media, and chat formatting services
app/Events/Chat/        Broadcast events
database/migrations/    Schema, including a combined chat migration
resources/views/        Blade pages and components
resources/js/           Alpine, Vue islands, chat store, and page scripts
resources/css/          Tailwind v4 and DaisyUI configuration
routes/web.php          Web and internal JSON routes
routes/channels.php     Broadcast channel authorization
```

## Agent Development Tooling

This repository is set up for OpenCode. Behavioral guidelines for Laravel work are in `AGENTS.md`, MCP servers are declared in `opencode.json`, and project-level agent skills are installed under `.agents/skills` with their sources recorded in `skills-lock.json`.

These are development aids only. None of them are runtime dependencies of Bleep, and they are not loaded by the deployed application.

### Agent skills

64 project skills are installed from 8 sources:

| Source | Skills | Examples |
| --- | --- | --- |
| `mattpocock/skills` | 38 | `improve-codebase-architecture`, `code-review`, `grill-me`, `handoff`, `tdd`, planning and writing skills |
| `obra/superpowers` | 15 | `brainstorming`, `systematic-debugging`, `test-driven-development`, `using-git-worktrees`, `verification-before-completion` |
| `DietrichGebert/ponytail` | 6 | `ponytail`, `ponytail-review`, `ponytail-audit` |
| `anthropics/skills` | 1 | `frontend-design` |
| `vercel-labs/skills` | 1 | `find-skills` |
| `designed-by-ai/skills` | 1 | `design-mobile-apps` (optional; needs `SLEEK_API_KEY`) |
| `firecrawl/cli` | 1 | `firecrawl` (optional; live web extraction needs the Firecrawl CLI/API) |
| `Egonex-AI/Understand-Anything` | 1 | `understand` (the full dashboard workflow also needs the Understand Anything plugin/core) |

List the current set with:

```bash
npx skills list
```

### MCP servers

| Server | Type | Purpose |
| --- | --- | --- |
| `laravel-boost` | Local, `php artisan boost:mcp` | Laravel docs, database schema, logs, Tinker, Artisan commands |
| `context7` | Remote, `https://mcp.context7.com/mcp` | Current library documentation for Laravel, Vue, Tailwind, Vite, and other dependencies |

Verify both with `opencode mcp list`. Context7 works without an API key; an optional key can be added later if higher limits are needed.

### OpenCode plugins

No OpenCode plugins are declared by this repository. The local OpenCode configuration also loads the user-level `oh-my-openagent` plugin; that is a global user setting and is not part of Bleep or its deployment.

Ponytail's OpenCode plugin and always-on ruleset are intentionally not enabled. Only its project skills are installed.

### Standalone tools

- **Caveman CLI** (`@caveman-ai/cli` 4.0.1) is installed globally. Its native OpenCode integration was intentionally left unapplied because `caveman setup --yes` would modify the global OpenCode configuration, download binaries, and start a local proxy.
- **Composer, Node.js/npm, and the OpenCode CLI** are required for development as documented above.

## Deployment Notes

The GitHub Actions workflow in `.github/workflows/deployment.yml` runs the Pest suite first and only deploys if it passes. The deploy job then builds frontend assets and uploads the repository over FTP when `main` is updated. It excludes `.env` files, Git metadata, tests, and some storage paths, but it does not run migrations.

Before releasing schema changes, run migrations against the production database. Ensure the host supports the storage link, writable storage directories, queue processing, and a long-running Reverb process if real-time chat is required on the free hosting plan. If the host cannot keep Reverb running, chat needs an external websocket service or a fallback strategy.

The `.agents` directory and `skills-lock.json` are development files. Decide whether to commit them; if they are committed, consider excluding `.agents/**` from the FTP deployment to avoid uploading tooling to production.

## Contributing

1. Create a focused feature branch.
2. Follow the existing Blade, Vue, and Laravel conventions.
3. Add or update Pest tests where appropriate.
4. Run `vendor/bin/pint --dirty` and the relevant tests before opening a pull request.

## License

Bleep is open-sourced under the MIT license.
