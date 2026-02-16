# Configuration

Chief can be configured at the project level to customize its behavior for different codebases and workflows.

## Project Settings

Project settings are stored in `.chief/config.yaml` and can be edited directly or via the web app's project settings tab.

### Available Settings

| Setting | Type | Default | Description |
|---------|------|---------|-------------|
| `max_iterations` | number | `3` | Maximum retries per story before marking as failed |
| `auto_commit` | boolean | `true` | Automatically commit after each successful story |
| `commit_prefix` | string | `"feat"` | Prefix for auto-generated commit messages |
| `claude_model` | string | `"claude-sonnet-4-5-20250929"` | Claude model to use |
| `test_command` | string | `""` | Command to run tests (e.g., `npm test`, `pytest`) |

### Example Configuration

```yaml
max_iterations: 5
auto_commit: true
commit_prefix: "feat"
claude_model: "claude-sonnet-4-5-20250929"
test_command: "npm run test && npm run lint"
```

## Environment Variables

Chief uses the following environment variables:

| Variable | Required | Description |
|----------|----------|-------------|
| `ANTHROPIC_API_KEY` | Yes | Your Anthropic API key |
| `CHIEF_WORKSPACE` | No | Custom workspace directory (default: current directory) |
| `CHIEF_LOG_LEVEL` | No | Logging verbosity: `debug`, `info`, `warn`, `error` |

## Credentials

After running `chief login`, credentials are stored in `~/.chief/credentials.yaml`:

```yaml
access_token: "eyJ..."
refresh_token: "eyJ..."
server_url: "https://chiefloop.com"
```

These tokens are automatically refreshed. Never share or commit this file.

## Web App Settings

When connected to the web app, you can manage project settings remotely:

1. Navigate to your project
2. Click the **Settings** tab
3. Modify settings and click **Save**

Changes are sent to the Chief server via WebSocket and applied immediately.
