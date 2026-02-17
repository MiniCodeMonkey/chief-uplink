## Codebase Patterns
- CLI code lives in `/Users/codemonkey/projects/chief/` (separate repo from chief-uplink)
- Message types defined in `internal/ws/messages.go` with constants (TypeXxx) and structs (XxxMessage)
- Batcher tier routing in `internal/uplink/batcher.go` `tierFor()` — completion signals go to immediate tier
- Session manager (`internal/cmd/session.go`) manages PRD Claude sessions — all sessions are PRD sessions
- `streamOutput()` sends output line-by-line via scanner; process exit handler sends completion signal
- Tests use `captureSender` mock to capture sent messages and verify types/fields
- Laravel side already has `prd_output` and `prd_response_complete` registered in `MessageIngestionController` and `WebSocketMessageBuffer`
- PrdChat.vue expects `prd_output` with `text` field and `session_id`, and `prd_response_complete` with `session_id`
- Contract fixtures live in `contract/fixtures/cli-to-server/` and `contract/fixtures/server-to-cli/`
- Contract fixtures source of truth is chief-uplink repo; chief CLI syncs via `make sync-fixtures`
- `.chief/` dir is gitignored in chief-uplink but prd.json was force-added (tracked)
- Browser sends `message` field for both `new_prd` and `prd_message` commands (PrdChat.vue)
- Session tests in `session_test.go` use `map[string]string` raw JSON for message construction
- `embed.GetEditPrompt(prdDir)` returns the edit prompt template with `{{PRD_DIR}}` replaced — used for refine_prd sessions
- `refinePRD()` differs from `newPRD()`: uses edit prompt, targets specific PRD dir, sends user message via stdin after spawn
- PrdChat.vue sends `refine_prd` with `prd_id` field; `refine_prd` is already in `useCommandRelay.ts` CommandType union
- `sendCommand()` in `useCommandRelay.ts` returns `CommandResponse | null` — null = failure (toast already shown by composable)
- `.chief/` dir is gitignored in chief-uplink — use `git add -f` for tracked files like prd.json
- Sail installer modifies `phpunit.xml` — always revert after `sail:install` to keep existing unit test DB config
- E2E infra: `docker-compose.yml` has pgsql + redis only (no laravel.test container); app runs on host
- Run full test suite with `php -d memory_limit=512M ./vendor/bin/pest --no-coverage` to avoid OOM
- Live E2E tests use `tests/LiveE2E/` directory; Pest.php must include `'LiveE2E'` in the DuskTestCase `->in()` call
- Live E2E tests don't create mock data — they rely on data seeded by `e2e:setup` and real CLI state via WebSocket
- Dashboard project name comes from CLI state_snapshot; use `waitForText('test-project', 30)` to wait for async WebSocket data
- Desktop tab bar nav: `nav[aria-label="Project tabs"]` — use `within()` + `clickLink()` to navigate tabs; `waitForLocation()` for Inertia SPA navigation

## 2026-02-17 - US-001
- Implemented prd_output and prd_response_complete message types in the CLI
- Files changed (in chief repo):
  - `internal/ws/messages.go` — Added TypePRDOutput, TypePRDResponseComplete constants and PRDOutputMessage, PRDResponseCompleteMessage structs
  - `internal/ws/messages_test.go` — Added round-trip tests for both new message types
  - `internal/cmd/session.go` — Changed streamOutput to send prd_output (with text field) instead of claude_output; changed exit handler to send prd_response_complete instead of claude_output with Done:true
  - `internal/cmd/session_test.go` — Updated all tests to expect prd_output/prd_response_complete instead of claude_output
  - `internal/uplink/batcher.go` — Added prd_response_complete to immediate tier routing
  - `internal/uplink/batcher_test.go` — Added prd_response_complete to tier routing test
- **Learnings for future iterations:**
  - The batcher already had `prd_output` in standard tier before this change — it was anticipated
  - `claude_output` with `data` field is still used for non-PRD run sessions (unchanged)
  - PRD sessions use `text` field (matching what PrdChat.vue expects) vs run sessions using `data` field
  - Completion signals should always be in the immediate batcher tier for responsiveness
---

## 2026-02-17 - US-002
- Fixed CLI field name mismatches for `new_prd` and `prd_message` message types
- Files changed (in chief repo):
  - `internal/ws/messages.go` — Changed `NewPRDMessage.InitialMessage` JSON tag from `"initial_message"` to `"message"` (renamed field to `Message`); Changed `PRDMessageMessage.Content` JSON tag from `"content"` to `"message"` (renamed field to `Message`); Added `Project` field to `PRDMessageMessage`
  - `internal/ws/messages_test.go` — Updated round-trip tests for both message types to use new field names
  - `internal/cmd/session.go` — Updated `handleNewPRD` to use `req.Message` instead of `req.InitialMessage`; Updated `handlePRDMessage` to use `req.Message` instead of `req.Content`
  - `internal/cmd/session_test.go` — Updated all test fixtures to use `"message"` key instead of `"initial_message"` / `"content"`
  - `internal/contract/contract_test.go` — Added `TestCommandNewPRD_PayloadWrapper` and `TestCommandPRDMessage_PayloadWrapper` contract tests
  - `Makefile` — Updated sync-fixtures list to include all current fixtures (was missing several)
- Files changed (in chief-uplink repo):
  - `contract/fixtures/server-to-cli/command_new_prd.json` — New fixture with `message` field
  - `contract/fixtures/server-to-cli/command_prd_message.json` — New fixture with `message` field
- **Learnings for future iterations:**
  - Always check PrdChat.vue for the exact field names the browser sends — it uses `sendCommand()` from `useCommandRelay`
  - Both `new_prd` and `prd_message` use the same field name `"message"` for the user's text
  - `PRDMessageMessage` was also missing the `project` field that the browser sends
  - Contract fixtures in chief repo are gitignored — source of truth is chief-uplink, synced via `make sync-fixtures`
  - The Makefile sync list must be manually updated when new fixtures are added
---

## 2026-02-17 - US-003
- Implemented `refine_prd` handler in CLI so users can edit existing PRDs from the web UI
- Files changed (in chief repo):
  - `internal/ws/messages.go` — Added `TypeRefinePRD = "refine_prd"` constant and `RefinePRDMessage` struct with Project, SessionID, PRDID, and Message fields
  - `internal/ws/messages_test.go` — Added `TestRefinePRDRoundTrip` round-trip test
  - `internal/cmd/session.go` — Added `refinePRD()` method to sessionManager (uses `embed.GetEditPrompt`, verifies PRD dir exists, spawns Claude, sends user message via stdin, streams prd_output, sends prd_response_complete on exit, auto-converts); Added `handleRefinePRD()` handler function
  - `internal/cmd/serve.go` — Added `case ws.TypeRefinePRD:` in handleMessage switch, wired to handleRefinePRD
  - `internal/cmd/session_test.go` — Added 4 tests: `TestSessionManager_RefinePRD` (basic), `_ProjectNotFound`, `_PRDNotFound`, `_WithMockClaude_RefinePRD` (full lifecycle with mock Claude)
  - `internal/contract/contract_test.go` — Added `TestCommandRefinePRD_PayloadWrapper` contract test
  - `Makefile` — Added `server-to-cli/command_refine_prd.json` to sync-fixtures list
- Files changed (in chief-uplink repo):
  - `contract/fixtures/server-to-cli/command_refine_prd.json` — New fixture with project, session_id, prd_id, message fields
- **Learnings for future iterations:**
  - `refinePRD()` is very similar to `newPRD()` but: uses edit prompt (not init prompt), targets specific PRD directory, sends user's message as first stdin input (with small delay to let Claude process the prompt)
  - The PRD directory must exist before refine — the handler returns CLAUDE_ERROR if the PRD dir doesn't exist (not PRD_NOT_FOUND, since it gets through to sessions.refinePRD first)
  - Contract fixtures in the chief repo are gitignored — don't try `git add` them, only add in chief-uplink
  - The mock Claude script test confirms full lifecycle: spawn → prd_output streaming → prd_message follow-up → close → prd_response_complete
---

## 2026-02-18 - US-004
- Fixed PrdChat.vue error handling and stuck "thinking" states
- Files changed (in chief-uplink repo):
  - `resources/js/pages/projects/PrdChat.vue` — Added error handling after `sendCommand` returns null in `handleSend()` (both first-message and subsequent-message branches); Added 3-minute response timeout safety net that clears `isClaudeResponding` and shows error toast; Refactored error handler to use shared `resetRespondingState()` helper; Clear response timeout on `prd_response_complete`, `error`, and `session_expired` events; Clean up response timeout timer on component unmount; Also added response timeout to `handleResume()` flow
- **Learnings for future iterations:**
  - `sendCommand()` returns `CommandResponse | null` — null means failure (the composable already shows a toast for the specific error)
  - The `on('error', ...)` handler was already correctly resetting `isClaudeResponding` and cleaning up the placeholder — just needed `clearResponseTimeout()` added
  - When the first sendCommand fails, must also reset `hasActiveSession` and `sessionId` since they were optimistically set
  - Prettier reformats the entire file when run — many formatting-only changes in the diff are from Prettier, not logic changes
  - `.chief/` dir is gitignored — must use `git add -f` to stage changes to tracked files like prd.json
  - Pre-existing test failures in `CommandPalette.test.ts` (localStorage.clear issue) — not related to this change
---

## 2026-02-18 - US-005
- Installed and configured Laravel Sail for PostgreSQL + Redis
- Files changed (in chief-uplink repo):
  - `docker-compose.yml` — New file: Sail-generated docker-compose with only pgsql and redis services (laravel.test app container intentionally removed); PostgreSQL 17 on port 5432, Redis Alpine on port 6379; includes comment explaining why app container was removed
- Reverted sail:install side effects:
  - `phpunit.xml` — Sail installer changed DB config from SQLite in-memory to `testing` database; reverted to preserve existing unit test configuration
  - `compose.yaml` — Sail generated this file (Docker Compose V2 naming); renamed to `docker-compose.yml` for consistency with PRD spec
- Verified:
  - PostgreSQL accessible via `pg_isready -U sail` — returns "accepting connections"
  - Redis accessible via `redis-cli ping` — returns PONG
  - SailServiceProvider auto-discovered via package discovery (no manual registration needed in AppServiceProvider)
  - 743 existing tests still pass (93 pre-existing Redis-dependent test failures unrelated to this change)
- **Learnings for future iterations:**
  - Sail installer in Laravel 12 generates `compose.yaml` (Docker Compose V2 naming), not `docker-compose.yml`
  - Sail installer modifies `phpunit.xml` to change DB_CONNECTION from sqlite to pgsql and DB_DATABASE to `testing` — always revert this if you want to keep existing unit test config
  - SailServiceProvider is auto-registered via package discovery (bootstrap/cache/packages.php) — no need to manually add to AppServiceProvider
  - Sail is a `require-dev` dependency, so it's only available in dev/testing environments (sufficient gating for production safety)
  - Pre-existing test failures: 93 tests require Redis running locally (RedisException) — these are not caused by any changes
  - Use `php -d memory_limit=512M` when running the full test suite to avoid OOM
---

## 2026-02-18 - US-006
- Created `.env.dusk.e2e` environment configuration for E2E tests
- Files changed (in chief-uplink repo):
  - `.env.dusk.e2e` — New file: E2E environment config with PostgreSQL on Sail (localhost:5432, user=sail, db=chief_e2e_test), Redis on localhost:6379, Reverb on port 8085, queue=sync, session=file, BCRYPT_ROUNDS=4
- Key configuration choices:
  - APP_URL=http://127.0.0.1:8001 (matches the `php artisan serve --port=8001` used in run.sh)
  - REDIS_CLIENT=predis (not phpredis, matching .env.dusk.local pattern — predis is a pure-PHP client that doesn't need the phpredis extension)
  - Reverb on port 8085 (different from default 8080 to avoid conflicts with local dev)
  - Test Reverb credentials: e2e-test / e2e-test-key / e2e-test-secret (no real secrets)
  - APP_KEY uses a test-only base64 key (not a real secret)
- **Learnings for future iterations:**
  - `.env.dusk.*` files are NOT gitignored by default (only `.env`, `.env.backup`, `.env.production` are)
  - The existing `.env.dusk.local` uses SQLite + broadcast=log — E2E env uses pgsql + broadcast=reverb for real end-to-end testing
  - REVERB_SERVER_HOST and REVERB_SERVER_PORT are needed alongside REVERB_HOST/REVERB_PORT — SERVER_* is what Reverb binds to, regular is what clients connect to
---

## 2026-02-18 - US-007
- Created `e2e:setup` artisan command that seeds all test data for E2E tests
- Files changed (in chief-uplink repo):
  - `app/Console/Commands/E2ESetupCommand.php` — New file: Artisan command with signature `e2e:setup {--workspace=}` that creates test user (e2e-test@example.com), device authorization (e2e-test-device), generates HMAC access token (replicating DeviceOAuthController::generateAccessToken), creates test workspace with git repo + .chief/config.yaml + .chief/prds/feature-auth/{prd.md, prd.json}, writes CLI credentials.yaml
- Key implementation details:
  - Token generation replicates exact logic from `DeviceOAuthController::generateAccessToken()` (lines 290-304): base64url-encode JSON payload, HMAC-SHA256 sign with app key
  - Credentials YAML format matches `internal/auth/auth.go` Credentials struct: access_token, refresh_token, expires_at, device_name, user, ws_url
  - Config YAML format matches `internal/config/config.go` Config struct: maxIterations, autoCommit, commitPrefix, claudeModel, testCommand
  - Command is idempotent: finds existing user/device by email/name before creating, updates refresh token on re-run
  - Outputs JSON to stdout with all generated IDs, paths, and tokens (consumed by run.sh)
  - ws_url built using same logic as DeviceOAuthController::buildWsUrl()
- **Learnings for future iterations:**
  - Artisan commands follow the pattern: `protected $signature`, `protected $description`, `public function handle(): int`
  - Existing commands in `app/Console/Commands/` use service injection in handle() — E2ESetupCommand doesn't need any services
  - `User::factory()->create()` requires the UserFactory — it uses `github_id` and `github_username` fields which are fake-generated
  - credentials.yaml requires quoted strings for tokens and URLs (YAML string quoting)
  - The `runGit()` helper uses `exec()` with escaped paths — safe for E2E workspace temp dirs
  - `chmod($credPath, 0600)` matches the CLI's `SaveCredentials` which uses 0600 permissions
  - Pre-existing 93 Redis test failures still present (not related to this change)
---

## 2026-02-18 - US-008
- Fixed DuskTestCase Chrome binary path to work on both macOS and Linux
- Files changed (in chief-uplink repo):
  - `tests/DuskTestCase.php` — Made Chromium binary path conditional: only sets `setExperimentalOption('binary', '/usr/bin/chromium')` if the file exists (Linux CI); on macOS, ChromeDriver auto-detects Chrome at its default location
- **Learnings for future iterations:**
  - DuskTestCase.php is the base class for all Dusk browser tests — changes here affect all Dusk tests
  - ChromeDriver auto-detects Chrome on macOS without needing an explicit binary path
  - The `/usr/bin/chromium` path is specific to Linux CI environments (e.g., GitHub Actions with chromium package)
  - `file_exists()` is the simplest conditional — no need to check `PHP_OS` or other platform detection
---

## 2026-02-18 - US-009
- Created `tests/LiveE2E/run.sh` master orchestration script for E2E test lifecycle
- Files changed (in chief-uplink repo):
  - `tests/LiveE2E/run.sh` — New file: Full lifecycle script that checks prerequisites (chief, php, node, docker), starts Sail containers, waits for PostgreSQL, backs up .env, copies .env.dusk.e2e, creates test DB, runs migrations, runs e2e:setup, builds frontend (npm run build), starts Reverb on port 8085, Laravel on port 8001, chief serve pointing at localhost, waits for CLI to connect (polls is_online), runs Dusk tests, cleanup trap restores .env and kills processes
- Key implementation details:
  - Script closely follows E2E_TESTING_SPEC.md plan
  - Uses `set -euo pipefail` for strict error handling
  - Cleanup function in EXIT trap: kills all PIDs, removes temp workspace, restores .env backup
  - PostgreSQL readiness poll: up to 30 iterations with 1s sleep
  - Chief CLI connection poll: uses `php artisan tinker --execute` to check `is_online` on DeviceAuthorization
  - SETUP_JSON captured from e2e:setup stdout; DEVICE_ID extracted via inline PHP
  - EXIT_CODE initially 1 (failure), overwritten by Dusk exit code on success
- **Learnings for future iterations:**
  - The run.sh script does NOT stop Sail containers on cleanup (they're shared infra that may be used by other tests)
  - `php artisan tinker --execute` is useful for quick DB queries in shell scripts
  - The script uses `HOME="$E2E_WORKSPACE/.chief-home"` to isolate chief CLI credentials
  - `--no-reload` flag on `php artisan serve` prevents the server from watching for file changes
---

## 2026-02-18 - US-010
- Created the first live E2E Dusk test: "Project Appears on Dashboard"
- Files changed (in chief-uplink repo):
  - `tests/LiveE2E/LiveEndToEndTest.php` — New file: Live E2E test that logs in as the seeded e2e-test@example.com user, visits /dashboard, and waits for "test-project" to appear (proving CLI→Reverb→Laravel→Browser state_snapshot pipeline works)
  - `tests/Pest.php` — Added `'LiveE2E'` to the DuskTestCase `->in()` call so Pest recognizes LiveE2E directory as Dusk tests with DatabaseTruncation
- Key implementation details:
  - Uses `User::where('email', 'e2e-test@example.com')->firstOrFail()` — no factory creation; relies on e2e:setup seeded data
  - Uses `waitForText('test-project', 30)` with 30s timeout to account for CLI connecting and sending state_snapshot via WebSocket
  - Test proves the full pipeline: CLI sends state_snapshot → Reverb delivers → Laravel processes into CachedProjectState → Dashboard renders via Inertia
  - Follows Pest describe/test syntax matching existing Browser tests
- **Learnings for future iterations:**
  - LiveE2E tests are different from Browser tests: no factory data, rely on real CLI state via WebSocket
  - The `tests/Pest.php` `->in()` call must include each directory with Dusk tests — `LiveE2E` was missing initially
  - The project slug `test-project` comes from the directory name in the workspace created by `e2e:setup`
  - Dashboard shows `project_name` from CachedProjectState which falls back to the slug if CLI doesn't send a display name
  - Use generous timeouts (30s) for `waitForText` since the CLI needs to connect, authenticate, and send state_snapshot
---

## 2026-02-18 - US-011
- Added Dusk test verifying settings page loads config values from CLI
- Files changed (in chief-uplink repo):
  - `tests/LiveE2E/LiveEndToEndTest.php` — Added `describe('Settings', ...)` block with test that visits `/projects/test-project/settings`, waits for skeleton loaders to disappear, asserts max iterations input value is `5`, and asserts auto commit toggle is visible with `aria-checked="true"`
- Key implementation details:
  - Uses `waitUntilMissing('[data-slot="skeleton"]', 30)` to wait for settings_response — skeleton elements have `data-slot="skeleton"` attribute with `animate-pulse` class
  - Settings form fields use ID-based selectors: `#max-iterations`, `#auto-commit`, `#commit-prefix`, `#claude-model`, `#test-command`
  - Auto commit toggle is a custom `<Toggle>` component that uses `role="switch"` and `aria-checked` attribute for state
  - Seeded config.yaml has `maxIterations: 5` and `autoCommit: true` — these are the expected values
  - `assertInputValue` checks the value attribute/property of input fields
  - `assertAttribute` checks DOM attributes — used for `aria-checked` on the toggle button
- **Learnings for future iterations:**
  - Settings page uses `[data-slot="skeleton"]` for loading skeletons — use `waitUntilMissing` with this selector to wait for settings to load
  - Toggle component uses `aria-checked="true"/"false"` — not a regular checkbox, so use `assertAttribute` instead of `assertChecked`
  - Settings are loaded via WebSocket roundtrip: page sends `get_settings` command → CLI responds with `settings_response` → Vue applies values and hides skeleton
  - All settings form fields have stable `#id` selectors — prefer these over CSS class selectors
---

## 2026-02-18 - US-012
- Added Dusk test verifying settings update roundtrip persists changes to CLI config
- Files changed (in chief-uplink repo):
  - `tests/LiveE2E/LiveEndToEndTest.php` — Added test "settings update roundtrip persists changes to CLI config" in the Settings describe block: visits settings page, waits for load, confirms baseline value of 5, changes max iterations to 10 via `type()`, presses "Save Settings", waits for "Settings saved" toast, asserts field shows 10, reloads page and asserts 10 persists
- Key implementation details:
  - Uses Dusk `type('#max-iterations', '10')` which calls `clear()` then `sendKeys()` — Vue's `v-model.number` directive responds to the `input` event from `sendKeys()`
  - `press('Save Settings')` finds the submit button by its visible text content
  - `waitForText('Settings saved', 15)` waits for the success toast (from `success('Settings saved')` in Settings.vue on `settings_updated` WebSocket event)
  - Page reload triggers fresh `get_settings` WebSocket roundtrip to verify CLI persisted the value to `config.yaml`
  - The `isDirty` computed property in Settings.vue enables the Save button when input value differs from server value
- **Learnings for future iterations:**
  - Dusk's `type()` with a CSS selector works well for number inputs — it clears first then sends keys, triggering proper Vue reactivity
  - `press('Save Settings')` matches button by visible text — works even when button has icon children (Dusk uses text content matching)
  - Toast auto-dismisses after 5000ms (success variant) — assert within that window or use `waitForText` which catches it in time
  - Settings save flow: click Save → `update_settings` command sent via WebSocket → CLI writes config.yaml → CLI sends `settings_updated` response → Vue shows toast + resets dirty state
  - Test order matters: this test changes max iterations from 5 to 10, so it should run after the "loads config values" test that asserts 5
---

## 2026-02-18 - US-013
- Added Dusk test verifying PRD listing page shows PRDs from CLI workspace
- Files changed (in chief-uplink repo):
  - `tests/LiveE2E/LiveEndToEndTest.php` — Added `describe('PRDs', ...)` block with test that visits `/projects/test-project/prds`, waits for skeleton loaders to disappear, asserts "Feature Auth" PRD name appears, and asserts "3 stories" story count is displayed
- Key implementation details:
  - Uses `waitUntilMissing('[data-slot="skeleton"]', 30)` to wait for `prds_response` from CLI — same pattern as settings tests
  - PRD name "Feature Auth" comes from the `name` field in the seeded `prd.json` (created by `e2e:setup`)
  - Story count "3 stories" comes from `story_count` field in the `prds_response` payload
  - Prds.vue displays `{count} stories` or `{count} story` (singular/plural handling)
  - The PRD listing loads via: page sends `get_prds` command → CLI reads `.chief/prds/` dirs → sends `prds_response` → Vue hides skeleton and renders PRD cards
- **Learnings for future iterations:**
  - PRD listing page uses same skeleton loading pattern as settings (`[data-slot="skeleton"]`)
  - PRD card shows: name (from prd.json `name` field), status badge (active/done/draft), story count, Run/Refine buttons
  - The `prds_response` has a 15-second timeout in the Vue component — if CLI doesn't respond, error state shown
  - PRD items have fields: `id`, `name`, `story_count`, `status` — these come from CLI parsing the prd.json files
---

## 2026-02-18 - US-014
- Added Dusk test for creating a new PRD via Claude chat interface
- Files changed (in chief-uplink repo):
  - `tests/LiveE2E/LiveEndToEndTest.php` — Added `describe('PRD Chat', ...)` block with test that visits `/projects/test-project/prd/new`, types a project description into the chat textarea, clicks Send, waits up to 120s for Claude's streamed response to appear (`.prose-chat`), asserts thinking indicator is gone and response content is visible
- Key implementation details:
  - Uses `textarea[aria-label="Chat message input"]` selector for the input (stable ARIA label)
  - Uses `press('Send')` to click the desktop send button by visible text
  - `waitFor('.prose-chat', 120)` waits for Claude's streamed text — `.prose-chat` only appears when `msg.content` is non-empty (v-html rendered markdown)
  - `.animate-pulse.rounded-full.bg-muted-foreground` targets the thinking indicator dots (3 animated circles)
  - Test has `->timeout(180)` (3 minutes) overall to account for real Claude API latency
  - This test depends on US-001 (prd_output message type), US-002 (field name fixes), US-004 (error handling)
- **Learnings for future iterations:**
  - PrdChat.vue uses `aria-label="Chat message input"` on the textarea — stable selector for Dusk tests
  - Desktop send button renders with text "Send" — `press('Send')` works with Dusk's text content matching
  - `.prose-chat` class is the key selector for Claude's rendered response — it only appears when streaming content is non-empty
  - The thinking indicator (3 dots) uses `.animate-pulse.rounded-full.bg-muted-foreground` — all standard Tailwind classes, safe as CSS selectors
  - Real Claude API calls need generous timeouts: 120s for `waitFor`, 180s for test overall
  - PrdChat.vue handles both create (`new_prd`) and refine (`refine_prd`) modes via props — test uses create mode
---

## 2026-02-18 - US-015
- Added Dusk test verifying tab navigation across all project tabs
- Files changed (in chief-uplink repo):
  - `tests/LiveE2E/LiveEndToEndTest.php` — Added `describe('Tab Navigation', ...)` block with test that visits project overview, then navigates through all 5 tabs (Overview, Run, Diffs, PRDs, Settings) via the desktop tab bar, asserting correct URL paths and expected content on each tab
- Key implementation details:
  - Uses `nav[aria-label="Project tabs"]` selector to scope tab clicks to the desktop nav bar
  - Uses `$browser->within()` + `clickLink()` to click tab links by their visible text
  - Uses `waitForLocation()` to wait for Inertia navigation to complete before asserting URL
  - Overview page asserts "Status" and "Recent Activity" card headings (project has seeded PRD, so not in `no_prd` state)
  - Run page asserts "Start Run" button text (project is idle, no active run)
  - Diffs page asserts "Diffs" heading text
  - PRDs and Settings pages assert correct URL only (detailed content already tested by other test cases)
  - Test is a single browser session navigating through all tabs sequentially — proves Inertia SPA navigation works
- **Learnings for future iterations:**
  - `ProjectTabBar.vue` renders two navs: desktop (`hidden lg:block`) and mobile (bottom fixed bar) — both have `aria-label="Project tabs"` but `within()` finds the first match which is the desktop one
  - `clickLink('Run')` in Dusk finds `<a>` tags by their visible text content — works with Inertia `<Link>` components since they render as `<a>` tags
  - `waitForLocation()` is the right way to wait for Inertia client-side navigations (not full page loads)
  - Overview page content depends on project status: `no_prd` shows "Get started by creating a PRD", otherwise shows dashboard cards (Status, Recent Activity, Git Info, Stats, Recent Runs)
  - Run page always shows "Start Run" button when project is idle (no active run)
  - Diffs page has an `<h2>` with "Diffs" that's always present regardless of diff data
---
