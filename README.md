# Chief Web App

**Remote-control your Chief instances from anywhere.**

Chief Web App is the companion web interface for [Chief](https://github.com/minicodemonkey/chief) — an AI-powered coding agent. It lets you monitor runs, review progress, manage PRDs, and deploy cloud servers from your browser or phone. The web app performs zero AI work; it acts as a relay, cache, and account system that communicates with Chief servers running on your machines via WebSocket.

> **Most users should use the hosted version at [chiefloop.com](https://chiefloop.com).** Self-hosting is available for teams that need full control over their infrastructure.

<!-- Screenshots / GIF demos will be added here once the UI is built -->

## Features

- **Remote Monitoring** — Check on your runs from your phone. See live Claude output, story progress, and diffs in real-time.
- **PRD Chat** — Create and refine PRDs through a conversational interface with Claude, with a live preview panel.
- **Run Controls** — Start, pause, resume, and stop runs remotely.
- **Diff Viewer** — Review per-story syntax-highlighted diffs with a file tree sidebar.
- **Cloud Deploy** — Spin up a Chief server on Hetzner or DigitalOcean with a guided wizard. No SSH required.
- **Device Management** — Authorize and manage multiple Chief servers via a device OAuth flow.
- **Push & Email Notifications** — Get notified when runs complete, fail, or need attention.
- **Command Palette** — Quick navigation and actions with `Cmd+K` / `Ctrl+K`.
- **Dark & Light Mode** — System-aware theming with manual override.
- **Offline Support** — Dashboard renders from cache when your server is offline.

## Tech Stack

| Layer | Technology |
|---|---|
| Backend | [Laravel 12](https://laravel.com) (PHP 8.2+) |
| Frontend | [Vue 3](https://vuejs.org) + [Inertia.js](https://inertiajs.com) |
| Styling | [Tailwind CSS 4](https://tailwindcss.com) |
| WebSocket | [Laravel Reverb](https://reverb.laravel.com) + [Laravel Echo](https://github.com/laravel/echo) |
| Database | PostgreSQL |
| Cache / Queue / Sessions | Redis |
| Auth | GitHub OAuth via [Laravel Socialite](https://laravel.com/docs/socialite) |
| Testing | [Pest](https://pestphp.com) + [Laravel Dusk](https://laravel.com/docs/dusk) |
| Code Quality | [Laravel Pint](https://laravel.com/docs/pint) (PHP), [ESLint](https://eslint.org) + [Prettier](https://prettier.io) (JS/Vue) |
| Syntax Highlighting | [Shiki](https://shiki.matsu.io) |
| Diff Rendering | [diff2html](https://diff2html.xyz) |
| Markdown | [markdown-it](https://github.com/markdown-it/markdown-it) |

## Prerequisites

- **PHP 8.3+** with extensions: `mbstring`, `pgsql`, `redis`, `xml`, `curl`, `openssl`
- **Node.js 20+** and npm
- **PostgreSQL 15+**
- **Redis 7+**
- **Composer 2+**

## Local Development Setup

```bash
# Clone the repository
git clone https://github.com/minicodemonkey/chief-uplink.git
cd chief-uplink

# Install dependencies and set up the project
composer setup

# Configure your environment
cp .env.example .env    # (done by composer setup if .env doesn't exist)
php artisan key:generate # (done by composer setup)

# Edit .env with your database credentials and GitHub OAuth keys
# See "Environment Variables" below for details

# Run migrations
php artisan migrate

# Seed the database with sample data (optional, for development)
php artisan db:seed

# Start all services (server, queue, Vite, Reverb, log tail)
composer run dev
```

The app will be available at `http://localhost:8000`.

## Environment Variables

| Variable | Description | Default |
|---|---|---|
| `DB_CONNECTION` | Database driver | `pgsql` |
| `DB_HOST` | Database host | `127.0.0.1` |
| `DB_PORT` | Database port | `5432` |
| `DB_DATABASE` | Database name | `chief` |
| `DB_USERNAME` | Database user | `chief` |
| `DB_PASSWORD` | Database password | _(empty)_ |
| `SESSION_DRIVER` | Session storage | `redis` |
| `QUEUE_CONNECTION` | Queue backend | `redis` |
| `CACHE_STORE` | Cache backend | `redis` |
| `REDIS_HOST` | Redis host | `127.0.0.1` |
| `REDIS_PORT` | Redis port | `6379` |
| `GITHUB_CLIENT_ID` | GitHub OAuth app client ID | _(required)_ |
| `GITHUB_CLIENT_SECRET` | GitHub OAuth app client secret | _(required)_ |
| `GITHUB_REDIRECT_URI` | GitHub OAuth callback path | `/auth/github/callback` |
| `REVERB_APP_ID` | Reverb application ID | `chief-local` |
| `REVERB_APP_KEY` | Reverb application key | `chief-local-key` |
| `REVERB_APP_SECRET` | Reverb application secret | `chief-local-secret` |
| `REVERB_HOST` | Reverb WebSocket host | `localhost` |
| `REVERB_PORT` | Reverb WebSocket port | `8080` |
| `REVERB_SCHEME` | Reverb protocol (`http` or `https`) | `http` |
| `REVERB_SERVER_HOST` | Reverb bind address | `0.0.0.0` |
| `REVERB_SERVER_PORT` | Reverb bind port | `8080` |
| `WS_BUFFER_MAX_SIZE` | Max WebSocket buffer per session (bytes) | `5242880` (5 MB) |
| `WS_BUFFER_GRACE_PERIOD` | Buffer retention after disconnect (seconds) | `300` |
| `MAIL_MAILER` | Mail driver (`smtp`, `postmark`, `ses`, `log`) | `log` |
| `MAIL_FROM_ADDRESS` | Sender email address | `noreply@chiefloop.com` |

## Self-Hosting Guide

For teams that want to run their own instance of the Chief Web App.

### Server Requirements

- 1 vCPU, 1 GB RAM minimum (2 vCPU, 2 GB recommended)
- PHP 8.3+ with required extensions
- PostgreSQL 15+
- Redis 7+
- Nginx or Caddy as reverse proxy
- SSL certificate (required for secure WebSocket connections)

### Setup

1. **Clone and install** following the local development steps above.

2. **Configure environment** for production:
   ```bash
   APP_ENV=production
   APP_DEBUG=false
   APP_URL=https://your-domain.com
   ```

3. **Configure Reverb for production:**
   ```bash
   REVERB_HOST=your-domain.com
   REVERB_PORT=443
   REVERB_SCHEME=https
   REVERB_SERVER_HOST=0.0.0.0
   REVERB_SERVER_PORT=8080
   ```
   Your reverse proxy should terminate SSL and forward WebSocket connections on `/app` and `/ws` to the Reverb server port.

4. **Set up GitHub OAuth:**
   - Create a GitHub OAuth App at https://github.com/settings/developers
   - Set the callback URL to `https://your-domain.com/auth/github/callback`
   - Add the client ID and secret to your `.env`

5. **Run queue workers** (required for notifications and background jobs):
   ```bash
   php artisan queue:work redis --tries=3 --timeout=90
   ```
   Use a process manager like Supervisor to keep the queue worker running.

6. **Run the scheduler** (required for buffer cleanup and maintenance):
   ```bash
   # Add to crontab
   * * * * * cd /path/to/chief-uplink && php artisan schedule:run >> /dev/null 2>&1
   ```

7. **Start Reverb** (required for WebSocket connections):
   ```bash
   php artisan reverb:start
   ```
   Use Supervisor to keep Reverb running.

8. **Build frontend assets:**
   ```bash
   npm run build
   ```

9. **Optimize for production:**
   ```bash
   php artisan config:cache
   php artisan route:cache
   php artisan view:cache
   ```

### Reverse Proxy (Nginx)

```nginx
server {
    listen 443 ssl;
    server_name your-domain.com;

    ssl_certificate /path/to/cert.pem;
    ssl_certificate_key /path/to/key.pem;

    root /path/to/chief-uplink/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    # WebSocket proxy for Reverb
    location /app {
        proxy_pass http://127.0.0.1:8080;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "upgrade";
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_read_timeout 60s;
    }

    location /ws {
        proxy_pass http://127.0.0.1:8080;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "upgrade";
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_read_timeout 60s;
    }
}
```

## Contributing

Contributions are welcome! Here's how to get started:

1. Fork the repository
2. Create a feature branch: `git checkout -b my-feature`
3. Make your changes
4. Run the test suite:
   ```bash
   # PHP linting
   ./vendor/bin/pint --test

   # Frontend linting
   npx eslint .
   npx prettier --check resources/

   # Tests
   php artisan test
   ```
5. Commit your changes: `git commit -m 'Add my feature'`
6. Push to your fork: `git push origin my-feature`
7. Open a Pull Request

Please ensure all tests pass and code style checks are clean before submitting.

## License

Chief Web App is open-source software licensed under the [MIT License](LICENSE).
