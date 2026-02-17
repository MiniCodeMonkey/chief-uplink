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
