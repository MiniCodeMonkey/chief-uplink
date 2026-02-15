## Codebase Patterns
- Laravel 12 + Vue 3 starter kit uses Inertia.js for SPA-like navigation
- Tests use SQLite in-memory (`:memory:`) — configured in phpunit.xml
- Frontend build (`npm run build`) is required before Pest tests that render Inertia pages (Vite manifest)
- ESLint auto-fix with `npx eslint . --fix`, Pint with `./vendor/bin/pint`
- Composer dev script (`composer run dev`) runs: server, queue, logs, vite, reverb concurrently
- `.env.example` has PostgreSQL defaults; `.env` locally uses SQLite for simplicity
- Starter kit includes Fortify for authentication (email/password + 2FA)
- UI components are in `resources/js/components/ui/` (shadcn/vue style with reka-ui)
- Generated action/route types are gitignored (`resources/js/actions`, `resources/js/routes`, `resources/js/wayfinder`)

---

## 2026-02-15 - US-001
- What was implemented:
  - Fresh Laravel 12 project with Inertia.js + Vue 3 + Tailwind CSS 4 starter kit
  - PostgreSQL configured as default database (config/database.php default changed to pgsql)
  - Redis configured for cache, sessions, and queue in .env.example
  - Laravel Reverb installed and configured (config/reverb.php, broadcasting.php)
  - Laravel Echo + Pusher JS installed and configured (resources/js/echo.ts)
  - Laravel Socialite installed with GitHub provider (config/services.php)
  - Laravel Dusk installed for browser testing
  - diff2html, markdown-it, and Shiki installed as npm dependencies
  - ESLint + Prettier already configured by starter kit (eslint.config.js, .prettierrc)
  - Laravel Pint already configured by starter kit (pint.json)
  - .env.example updated with all required variables (PostgreSQL, Redis, Reverb, GitHub OAuth, WS buffer)
  - All 41 Pest tests passing, ESLint clean, Prettier clean, Pint clean
- Files changed:
  - All files (initial scaffolding from scratch)
  - Key modifications to starter kit: config/database.php, config/services.php, .env.example, composer.json (dev script), resources/js/app.ts, resources/js/echo.ts (new), .gitignore
- **Learnings for future iterations:**
  - The Vue starter kit comes with Fortify auth (email/password + 2FA) — GitHub OAuth (Socialite) will need to replace/augment this
  - `npm run build` must be run before tests or they fail with ViteManifestNotFoundException
  - The starter kit auto-generates route/action types that are gitignored — run `npm run build` to regenerate
  - php8.4-zip has dependency issues on this system (libzip4 vs libzip5) — use `--ignore-platform-req=ext-zip` for composer packages needing it
  - ESLint import/order rule catches generated file issues — run `--fix` after code generation

---
