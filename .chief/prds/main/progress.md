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
- User model uses SoftDeletes — tests checking deletion should use `assertSoftDeleted()` not `fresh()->toBeNull()`
- Models: User, DeviceAuthorization, OauthDeviceCode, CachedProjectState, RunHistory, LogCache, CloudDeployment
- CloudDeployment.provider_api_key uses `encrypted` cast (Laravel's built-in encryption)
- CachedProjectState table name is `cached_project_state` (singular), RunHistory is `run_history`, LogCache is `log_cache`
- Factory states: DeviceAuthorization (online/offline/revoked), CachedProjectState (running/idle/error/paused/noPrd), RunHistory (completed/failed/paused/stopped), CloudDeployment (provisioning/destroyed/hetzner/digitalocean)

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

## 2026-02-15 - US-002
- What was implemented:
  - Comprehensive README.md with all required sections: project name/tagline/description, screenshot placeholder, feature highlights, tech stack table, prerequisites, local dev setup, environment variable reference table, self-hosting guide (server requirements, Reverb config, queue workers, scheduler, Nginx reverse proxy), note about hosted version at chiefloop.com, contributing guidelines, license section
  - MIT LICENSE file created
- Files changed:
  - README.md (created comprehensive documentation)
  - LICENSE (MIT license file)
- **Learnings for future iterations:**
  - ESLint errors in `resources/js/actions/` are from auto-generated gitignored files — not a blocker for commits
  - The `composer setup` script handles `.env` copy, key generation, migration, npm install, and build in one command
  - Pre-existing tests (41) all pass without needing any README-related changes

---

## 2026-02-15 - US-003
- What was implemented:
  - Migration to add GitHub OAuth columns to users table (github_id, github_username, avatar_url, notification_preferences, soft deletes, nullable email/password)
  - Migrations for all new tables: device_authorizations, oauth_device_codes, cached_project_state, run_history, log_cache, cloud_deployments
  - All columns, types, indexes, and foreign keys match the PRD spec
  - cloud_deployments.provider_api_key uses Laravel's `encrypted` cast
  - Eloquent models for all 7 tables with relationships, casts, and fillable attributes
  - Model factories for all models with state methods (online/offline, running/idle/error/paused/noPrd, completed/failed, etc.)
  - DatabaseSeeder creates: 3 users (sarahchen, mwebb-dev, aikot), 2 devices per user (1 online + 1 offline), 4-5 projects per device with varied statuses, run history entries, log cache entries, 1 cloud deployment
  - Updated ProfileUpdateTest to use assertSoftDeleted() for user deletion test
  - All 41 Pest tests passing, Pint clean, ESLint clean
- Files changed:
  - database/migrations/2026_02_15_000001_add_github_oauth_columns_to_users_table.php (new)
  - database/migrations/2026_02_15_000002_create_device_authorizations_table.php (new)
  - database/migrations/2026_02_15_000003_create_oauth_device_codes_table.php (new)
  - database/migrations/2026_02_15_000004_create_cached_project_state_table.php (new)
  - database/migrations/2026_02_15_000005_create_run_history_table.php (new)
  - database/migrations/2026_02_15_000006_create_log_cache_table.php (new)
  - database/migrations/2026_02_15_000007_create_cloud_deployments_table.php (new)
  - app/Models/User.php (updated: added GitHub fields, relationships, SoftDeletes)
  - app/Models/DeviceAuthorization.php (new)
  - app/Models/OauthDeviceCode.php (new)
  - app/Models/CachedProjectState.php (new)
  - app/Models/RunHistory.php (new)
  - app/Models/LogCache.php (new)
  - app/Models/CloudDeployment.php (new)
  - database/factories/UserFactory.php (updated: added GitHub fields, withoutGithub/withoutEmail states)
  - database/factories/DeviceAuthorizationFactory.php (new)
  - database/factories/OauthDeviceCodeFactory.php (new)
  - database/factories/CachedProjectStateFactory.php (new)
  - database/factories/RunHistoryFactory.php (new)
  - database/factories/LogCacheFactory.php (new)
  - database/factories/CloudDeploymentFactory.php (new)
  - database/seeders/DatabaseSeeder.php (rewritten with comprehensive seed data)
  - tests/Feature/Settings/ProfileUpdateTest.php (updated for soft delete)
- **Learnings for future iterations:**
  - Adding SoftDeletes to User breaks the existing "user can delete their account" test — must use `assertSoftDeleted()` instead of `fresh()->toBeNull()`
  - Factory `unique()` with `randomElement()` fails when creating more records than array elements — use non-unique or pass slug explicitly in seeders
  - Laravel 12 handles column changes natively (no doctrine/dbal needed)
  - Table names for some models are non-standard (cached_project_state, run_history, log_cache) — must set `$table` explicitly in the model

---
