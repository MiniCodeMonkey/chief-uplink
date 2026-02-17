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
