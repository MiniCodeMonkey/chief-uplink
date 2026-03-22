#!/usr/bin/env bash
set -e

cd "$(dirname "$0")/../.."

# Create a .env.testing file for browser tests (Laravel auto-loads this when APP_ENV=testing)
cp .env.playwright .env.testing

# Generate key if missing
if ! grep -q "^APP_KEY=base64:" .env.testing; then
    php artisan key:generate --env=testing --no-interaction
fi

# Create SQLite database and run migrations
touch database/playwright.sqlite
APP_ENV=testing php artisan migrate:fresh --force --no-interaction

# Clean up .env.testing on exit
trap 'rm -f .env.testing database/playwright.sqlite' EXIT INT TERM

# Start the server with testing environment
APP_ENV=testing php artisan serve --port=8000
