# Chief Uplink

Remote control panel for [Chief](https://github.com/chief-tools/chief) — manage your AI coding agent from a web dashboard.

Chief Uplink is the web companion to the Chief CLI. It provides a real-time dashboard for monitoring and controlling Chief sessions, managing PRDs, viewing run history, and receiving push notifications — all from your browser.

## Features

- **Real-time device connection** — WebSocket link between the CLI and dashboard with live status updates
- **Run management** — Start, monitor, and review AI coding runs with story-level progress tracking
- **PRD workspace** — Create and manage Product Requirement Documents that drive Chief's planning
- **Team collaboration** — Invite team members, manage roles, and share device access
- **Push notifications** — Browser push notifications for run completions and device events
- **GitHub OAuth** — Sign in with GitHub, link deploy keys for repository access
- **Cloud provider integration** — Connect Anthropic and other AI providers via the dashboard
- **Self-hostable** — Run the entire stack on your own infrastructure with Docker

## Tech Stack

| Layer | Technology |
|-------|-----------|
| Backend | PHP 8.5, Laravel 13, Octane (FrankenPHP) |
| Frontend | Vue 3, Inertia.js v2, Tailwind CSS 4 |
| Real-time | Laravel Reverb (WebSockets), Laravel Echo |
| Database | MariaDB 11 |
| Cache/Pub-Sub | Redis 7 |
| Auth | Laravel Sanctum, Laravel Socialite (GitHub OAuth) |
| Testing | Pest 4, Vitest, Playwright |
| Containerization | Docker, FrankenPHP |

## Quick Start (Hosted)

Sign up at [chiefuplink.com](https://chiefuplink.com), connect your Chief CLI, and you're ready to go.

## Development Setup

### Prerequisites

- Docker & Docker Compose
- Git

### Getting Started

```bash
# Clone the repository
git clone https://github.com/chief-tools/chief-uplink.git
cd chief-uplink

# Copy environment file
cp .env.example .env

# Start all services (app, MariaDB, Redis, Mailpit)
docker compose up -d

# Run setup (install deps, generate key, run migrations, build assets)
docker compose exec app composer setup

# The app is now running at http://localhost:8000
```

### Development Server

For active development with hot-reload:

```bash
# Inside the container
docker compose exec app composer run dev
```

This starts concurrently:
- **Laravel server** on port 8000
- **Queue worker** for background jobs
- **Pail** for log tailing
- **Vite** dev server on port 5173

### Environment

Mailpit is included for local email testing — view captured emails at `http://localhost:8025`.

Configure GitHub OAuth by setting `GITHUB_CLIENT_ID` and `GITHUB_CLIENT_SECRET` in `.env`.

For push notifications, generate VAPID keys and set `VAPID_PUBLIC_KEY` and `VAPID_PRIVATE_KEY`.

## Testing

```bash
# PHP tests (Pest)
docker compose exec app php artisan test --compact

# Run a specific test
docker compose exec app php artisan test --compact --filter=testName

# JavaScript tests (Vitest)
docker compose exec app npm run test

# Browser tests (Playwright)
docker compose exec app npm run test:browser

# Code formatting (Pint)
docker compose exec app vendor/bin/pint --dirty
```

## Project Structure

```
app/
├── Contracts/          # Interfaces (WebSocketConnection, CloudProviderInterface)
├── Enums/              # Backed string enums (TitleCase keys)
├── Events/             # Broadcast events
├── Http/Controllers/   # Route controllers
├── Models/             # Eloquent models
└── Services/           # Business logic (CloudProvider, WebSocket)
resources/
├── css/                # Tailwind theme & design tokens
└── js/
    ├── Components/     # Shared Vue components
    ├── Layouts/        # App layouts
    ├── Pages/          # Inertia page components
    └── composables/    # Vue composables (Echo, utilities)
tests/
├── Feature/            # Feature tests (Pest)
├── Unit/               # Unit tests (Pest)
└── Browser/            # Playwright browser tests
```

## Self-Hosting

See [docs/self-hosting.md](docs/self-hosting.md) for deployment instructions, reverse proxy configuration, database backups, and update procedures.

## License

MIT
