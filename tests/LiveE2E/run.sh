#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
PROJECT_DIR="$(cd "$SCRIPT_DIR/../.." && pwd)"
E2E_WORKSPACE=$(mktemp -d -t chief-e2e-XXXXXX)
REVERB_PORT=8085
APP_PORT=8001
PIDS=()
EXIT_CODE=1

cleanup() {
    echo ""
    echo "=== Cleaning up ==="
    for pid in "${PIDS[@]+"${PIDS[@]}"}"; do
        kill "$pid" 2>/dev/null && wait "$pid" 2>/dev/null || true
    done
    # Don't stop Sail containers (they're shared infra)
    rm -rf "$E2E_WORKSPACE"
    # Restore original .env if we backed it up
    if [[ -f "$PROJECT_DIR/.env.bak.e2e" ]]; then
        mv "$PROJECT_DIR/.env.bak.e2e" "$PROJECT_DIR/.env"
    fi
    rm -f "$PROJECT_DIR/.env.dusk.testing"
    echo "Cleanup complete. Exit code: $EXIT_CODE"
}
trap cleanup EXIT

cd "$PROJECT_DIR"

# --- Prerequisites ---
echo "=== Checking prerequisites ==="
command -v chief >/dev/null 2>&1 || { echo "ERROR: 'chief' CLI not in PATH"; exit 1; }
command -v php >/dev/null 2>&1   || { echo "ERROR: 'php' not in PATH"; exit 1; }
command -v node >/dev/null 2>&1  || { echo "ERROR: 'node' not in PATH"; exit 1; }
command -v docker >/dev/null 2>&1 || { echo "ERROR: 'docker' not in PATH"; exit 1; }

# --- Ensure Sail containers are running ---
echo "=== Starting Sail (PostgreSQL + Redis) ==="
./vendor/bin/sail up -d pgsql redis
# Wait for PostgreSQL to be ready
echo "Waiting for PostgreSQL..."
for i in $(seq 1 30); do
    if ./vendor/bin/sail exec -T pgsql pg_isready -U sail 2>/dev/null; then
        echo "PostgreSQL ready after ${i}s"
        break
    fi
    if [[ $i -eq 30 ]]; then
        echo "ERROR: PostgreSQL not ready after 30s"
        exit 1
    fi
    sleep 1
done

# --- Set up environment ---
echo "=== Setting up E2E environment ==="
if [[ -f "$PROJECT_DIR/.env" ]]; then
    cp "$PROJECT_DIR/.env" "$PROJECT_DIR/.env.bak.e2e"
fi
cp "$PROJECT_DIR/.env.dusk.e2e" "$PROJECT_DIR/.env"
# Dusk loads .env.dusk.{APP_ENV} instead of .env — ensure it uses the e2e config
cp "$PROJECT_DIR/.env.dusk.e2e" "$PROJECT_DIR/.env.dusk.testing"

# Create test database
./vendor/bin/sail exec -T pgsql psql -U sail -d postgres -c "DROP DATABASE IF EXISTS chief_e2e_test;" 2>/dev/null || true
./vendor/bin/sail exec -T pgsql psql -U sail -d postgres -c "CREATE DATABASE chief_e2e_test;" 2>/dev/null

# Run migrations
php artisan migrate --force --no-interaction
php artisan config:clear

# --- Seed test data ---
echo "=== Seeding test data ==="
SETUP_JSON=$(php artisan e2e:setup --workspace="$E2E_WORKSPACE" 2>&1 | tail -1)
echo "Setup: $SETUP_JSON"
DEVICE_ID=$(echo "$SETUP_JSON" | php -r 'echo json_decode(file_get_contents("php://stdin"))->device_id;')
echo "Device ID: $DEVICE_ID"

# --- Kill stale processes on our ports ---
lsof -ti :$APP_PORT | xargs kill 2>/dev/null || true
lsof -ti :$REVERB_PORT | xargs kill 2>/dev/null || true

# --- Build frontend ---
echo "=== Building frontend assets ==="
npm run build

# --- Start Reverb ---
echo "=== Starting Reverb on port $REVERB_PORT ==="
php artisan reverb:start --port=$REVERB_PORT &
PIDS+=($!)
sleep 2

# --- Start Laravel app server ---
echo "=== Starting Laravel on port $APP_PORT ==="
php artisan serve --port=$APP_PORT --no-reload &
PIDS+=($!)
sleep 2

# --- Create mock claude binary for E2E testing ---
# The real claude CLI can't run in the test sandbox (no auth tokens), so we use a
# mock that simulates PRD output. This tests the full integration pipeline:
# web app → CLI → subprocess → output streaming → browser.
MOCK_CLAUDE="$E2E_WORKSPACE/bin/mock-claude"
mkdir -p "$E2E_WORKSPACE/bin"
cat > "$MOCK_CLAUDE" << 'MOCK_SCRIPT'
#!/bin/sh
# Mock claude CLI for E2E testing — simulates `claude -p --dangerously-skip-permissions`
# Outputs PRD-like markdown content, then exits.
# Uses /bin/sh (not bash) for portability. No stdin reading to avoid pipe issues.
sleep 1
echo "# Todo List Application PRD"
echo ""
echo "## Overview"
echo "A simple todo list application with add and delete functionality."
echo ""
echo "## User Stories"
echo ""
echo "### US-001: Add Todo Item"
echo "**As a** user"
echo "**I want to** add new todo items to my list"
echo "**So that** I can track tasks I need to complete"
echo ""
echo "#### Acceptance Criteria"
echo "- User can enter text for a new todo item"
echo "- Todo item appears in the list after submission"
echo "- Input field clears after adding"
echo ""
echo "### US-002: Delete Todo Item"
echo "**As a** user"
echo "**I want to** delete todo items from my list"
echo "**So that** I can remove completed or unwanted tasks"
echo ""
echo "#### Acceptance Criteria"
echo "- Each todo item has a delete button"
echo "- Clicking delete removes the item from the list"
echo "- Deletion is immediate without confirmation"
echo ""
echo "### US-003: View Todo List"
echo "**As a** user"
echo "**I want to** see all my todo items in a list"
echo "**So that** I can review what needs to be done"
echo ""
echo "#### Acceptance Criteria"
echo "- All todo items are displayed in a vertical list"
echo "- Items are shown in the order they were added"
echo "- Empty state message when no items exist"
exit 0
MOCK_SCRIPT
chmod +x "$MOCK_CLAUDE"

# --- Start chief serve ---
echo "=== Starting chief serve ==="
HOME="$E2E_WORKSPACE/.chief-home" \
    CHIEF_CLAUDE_BINARY="$MOCK_CLAUDE" \
    chief serve \
    --workspace="$E2E_WORKSPACE/projects" \
    --server-url="http://127.0.0.1:$APP_PORT" \
    --log-file="$E2E_WORKSPACE/chief-serve.log" &
PIDS+=($!)

# --- Wait for chief to connect ---
echo "=== Waiting for chief CLI to connect ==="
for i in $(seq 1 30); do
    IS_ONLINE=$(php artisan tinker --execute="echo \App\Models\DeviceAuthorization::find($DEVICE_ID)?->is_online ? '1' : '0';" 2>/dev/null | tr -d '[:space:]')
    if [[ "$IS_ONLINE" == "1" ]]; then
        echo "Chief connected after ${i}s"
        break
    fi
    if [[ $i -eq 30 ]]; then
        echo "ERROR: Chief did not connect after 30s"
        echo "Chief log:"
        cat "$E2E_WORKSPACE/chief-serve.log" 2>/dev/null || echo "(no log)"
        exit 1
    fi
    sleep 1
done

# --- Run Dusk E2E tests ---
echo "=== Running Dusk E2E tests ==="
php artisan dusk tests/LiveE2E/LiveEndToEndTest.php
EXIT_CODE=$?

if [[ $EXIT_CODE -eq 0 ]]; then
    echo ""
    echo "=== All E2E tests passed! ==="
else
    echo ""
    echo "=== E2E tests FAILED (exit code: $EXIT_CODE) ==="
    echo "Chief serve log:"
    cat "$E2E_WORKSPACE/chief-serve.log" 2>/dev/null || echo "(no log)"
fi

exit $EXIT_CODE
