# Chief Web App — chiefloop.com

A web UI that remote-controls running `chief serve` instances. Built with Laravel + Inertia.js + Vue + Tailwind CSS, hosted on Laravel Cloud. The web app does zero AI work — it is a relay, cache, and account system that talks to chief servers running on users' machines or VPSes.

-----

## Architecture Overview

```
Browser (Vue + Inertia.js on chiefloop.com)
    ↕ WebSocket (Laravel Reverb) + HTTP (Inertia)
Laravel App (chiefloop.com on Laravel Cloud)
    ↕ Redis (session buffering, cache)
    ↕ Database (accounts, device authorizations, cached state)
    ↕ WebSocket relay
chief serve (user's machine or VPS)
    ↕ spawns
Claude Code CLI (user's own Max plan)
    ↕ operates on
~/projects/
```

The chief server initiates all connections outbound to chiefloop.com. No port forwarding, no firewall configuration required on the user's end.

**There is no local web UI.** The web app runs exclusively on chiefloop.com via Laravel Cloud. `chief serve` is a headless daemon that connects outbound — it does not serve any HTTP interface. The web app exists for remote control: monitoring runs from your phone, managing a VPS you don't want to SSH into, kicking off work while away from your desk.

-----

## Accounts & Authentication

### GitHub OAuth

- **Single auth provider**: GitHub OAuth via Laravel Socialite. One click, no forms.
- **No email/password**: Developers already have GitHub accounts.
- **Email capture**: GitHub OAuth provides the user's email (if public). If the user's GitHub email is private, prompt for an email address during onboarding (required for email notifications). Stored in the users table.

### Data Model

```sql
users
    id
    github_id
    github_username
    email              -- from GitHub or manually provided
    avatar_url
    notification_preferences  -- JSON: { email: bool, push: bool }
    created_at
    updated_at

device_authorizations
    id
    user_id
    device_name        -- auto-detected hostname or user-provided ("hetzner-vps")
    refresh_token_hash -- bcrypt hash of the refresh token
    last_connected_at
    last_connected_ip
    chief_version      -- last reported binary version
    os                 -- linux, darwin, windows
    arch               -- amd64, arm64
    created_at
    revoked_at         -- null if active

oauth_device_codes
    id
    user_id            -- null until user approves; set during approval
    device_code        -- random, used by chief to poll
    user_code          -- short human-readable code (e.g., ABCD-1234)
    device_name        -- from the requesting chief instance
    expires_at         -- 15 minutes from creation
    approved_at        -- null until user approves
    created_at

cached_project_state
    id
    device_id          -- which device/server this project belongs to
    project_name
    project_path
    git_branch
    last_commit_hash
    last_commit_message
    has_chief          -- bool: .chief/ directory exists
    prd_summaries      -- JSON: [{ id, name, story_count, status }]
    active_run         -- JSON: { prd_id, progress, stories_completed, total_stories, started_at } or null
    active_sessions    -- JSON: [{ session_id, prd_id, started_at, last_activity_at }]
    settings           -- JSON: project settings
    last_synced_at
    created_at
    updated_at

run_history
    id
    device_id
    project_name
    prd_id
    prd_name
    status             -- completed, failed, paused, stopped
    stories_completed
    stories_total
    stories_failed
    duration_seconds
    tokens_used
    started_at
    finished_at
    error_message      -- null on success

log_cache
    id
    device_id
    project_name
    prd_id
    story_id
    log_lines          -- TEXT: last N lines of Claude output
    updated_at

cloud_deployments
    id
    user_id
    provider           -- hetzner, digitalocean
    region
    server_ip
    provider_server_id
    status             -- provisioning, active, suspended, destroyed
    device_id          -- linked device authorization (set after chief login on the VPS)
    monthly_cost_cents
    created_at
    updated_at
```

### What Gets Cached vs. What's Live

| Data | Stored in DB | Fetched live from chief |
|---|---|---|
| Project list + status | Yes (cached_project_state) | Yes, replaces cache when online |
| PRD names + story counts | Yes (cached_project_state) | Yes |
| Run progress (active) | Yes (updated in real-time) | Yes |
| Run history (completed) | Yes (run_history) | No — pushed on completion |
| PRD markdown content | No | Yes — fetched on demand |
| Git diffs | No | Yes — fetched on demand |
| Claude streaming output | No (Redis buffer only) | Yes |
| Full log output | Partial (log_cache, last N lines) | Yes — fetched on demand |
| Project settings | Yes (cached_project_state) | Yes |

When a chief server is **online**, the web app uses live data and updates the cache in the background. When a server is **offline**, the web app shows cached data with a clear "Offline — last synced 2h ago" indicator. Data that isn't cached (PRD content, diffs, full logs) is unavailable when offline and the UI shows "Server offline — connect to view" in place of that content.

-----

## Server Connection

### Device OAuth Authentication

Chief servers authenticate via OAuth 2.0 device authorization flow. No tokens to copy-paste, no secrets in shell history or config files.

**OAuth endpoints (Laravel):**

| Endpoint | Method | Purpose |
|---|---|---|
| `/oauth/device/code` | POST | Chief requests a device code + user code |
| `/oauth/device` | GET | Browser page where user enters the user code |
| `/oauth/device/token` | POST | Chief polls this until the user approves |
| `/oauth/token` | POST | Token refresh (grant_type=refresh_token) |
| `/oauth/revoke` | POST | Revoke a refresh token (logout / deauthorize) |

**Device authorization flow:**

1. User runs `chief login` on their machine or VPS.
2. Chief calls `POST /oauth/device/code` with `{ device_name: "hostname" }`.
3. Server generates a `device_code` (long, random, used for polling) and a `user_code` (short, human-readable, e.g., "ABCD-1234"). Stores both in `oauth_device_codes` with a 15-minute expiry.
4. Chief displays: "Visit chiefloop.com/device and enter code: ABCD-1234"
5. Chief polls `POST /oauth/device/token` every 5 seconds with the `device_code`.
6. User visits `/oauth/device` in their browser (where they're already logged in via GitHub), enters the user code, clicks "Authorize."
7. Server marks the device code as approved, creates a `device_authorizations` record.
8. Next time chief polls, it receives an access token (1-hour expiry) and a refresh token (90-day expiry).
9. Chief stores credentials in `~/.chief/credentials.json`.

**Token lifecycle:**

- **Access tokens**: Short-lived (1 hour). Sent as a query parameter on the WebSocket connection URL. Chief refreshes automatically when within 5 minutes of expiry.
- **Refresh tokens**: Long-lived (90 days). Stored hashed in `device_authorizations`. Used to obtain new access tokens without re-authentication.
- **Refresh token rotation**: Each time a refresh token is used, a new one is issued and the old one is invalidated. This limits the damage if a refresh token is compromised.

**Device management (Settings → Devices):**

- List all authorized devices: name, OS, arch, Chief version, last connected time, last connected IP, online/offline status.
- Deauthorize a device: revokes the refresh token and immediately closes the active WebSocket connection. Chief receives an auth error and exits with a message to re-run `chief login`.
- No manual token generation or copy-paste. Every device authenticates through the OAuth flow.

### WebSocket Relay

Laravel Reverb handles WebSocket connections from both browsers and chief servers.

**Chief server connections:**
- Chief connects to `wss://chiefloop.com/ws/server?token=<access_token>`
- Reverb validates the access token, resolves it to a user and device, rejects if invalid or expired
- On successful auth, the connection is registered and the device is marked online
- If the access token expires during an active connection, the server sends an `auth_refresh_required` message; chief refreshes and reconnects (transparent to the user)
- All subsequent messages are JSON (see protocol spec in the CLI changes document)

**Browser connections:**
- Standard Laravel Echo + Reverb setup
- Browsers subscribe to channels scoped to their user ID
- The relay routes messages between the browser channel and the chief server's WebSocket

**Message routing:**
- Browser → Web App (Inertia/HTTP) → Chief: For actions like start_run, new_prd, clone_repo
- Chief → Web App → Browser (Reverb channel): For streaming output, progress updates, state changes
- The web app acts as a router, not a transformer — messages pass through with minimal processing, but the web app updates its cache as messages flow through

### Redis Session Buffering

All WebSocket messages for active connections are buffered in Redis:

- **Key pattern**: `ws:session:{device_id}:{message_type}:{session_id}` or `ws:session:{device_id}:messages`
- **Purpose**: If the browser disconnects and reconnects (tab closed, network blip, phone locked/unlocked), the web app replays buffered messages so the user doesn't miss Claude output or progress updates.
- **Scope**: Buffer is per-device-connection. When the chief server's WebSocket disconnects, the buffer is kept for a grace period (5 minutes) then flushed.
- **Size management**: Cap per-session buffer at a reasonable size (e.g., 1MB). Oldest messages are evicted when the cap is hit. For Claude streaming output specifically, buffer the complete output of the current story/session — not the entire run history.
- **What gets buffered**: `claude_output`, `run_progress`, `run_complete`, `run_paused`, `clone_progress`, `session_timeout_warning`, `error`, `quota_exhausted`. State snapshots and project lists are not buffered (they're re-requested on reconnect).

### Connection Status States

The web app tracks and displays server connection status:

| State | Condition | UI Treatment |
|---|---|---|
| Online | Active WebSocket connection | Green dot, live data |
| Reconnecting | WebSocket dropped < 60 seconds ago | Yellow dot, "Reconnecting...", show cached data |
| Offline | WebSocket dropped > 60 seconds ago | Gray dot, "Offline — last synced [time]", show cached data |
| Never Connected | Device authorized but has never connected via `chief serve` | Hollow dot, show "Run `chief serve` to connect" |

Transitions are smooth — "Reconnecting" doesn't flash if the connection drops for 2 seconds. The UI uses cached data seamlessly during reconnection, then swaps to live data once restored (no full page reload).

-----

## Notifications

### Browser Push Notifications

Web Push API via service worker. Opt-in during onboarding or from Settings → Preferences.

**Triggers:**
- Ralph loop completed (success)
- Ralph loop failed (story exceeded max iterations)
- Ralph loop paused (quota exhausted)
- Server went offline unexpectedly (was online, now disconnected for > 2 minutes)

**Behavior:**
- Each notification deep-links to the relevant view (e.g., tap "Run completed" → project run tab)
- Notifications are sent from the Laravel backend when the relevant WebSocket event arrives
- Respect user preferences — both push and email can be independently toggled

### Email Notifications

Sent from the Laravel app. Conservative — never spammy.

**Triggers (same as push, but batched):**
- Ralph loop completed or failed
- Server went offline unexpectedly
- Quota exhausted (all runs paused)

**Batching:** If multiple events occur within 5 minutes, combine into a single email. E.g., "3 runs completed on hetzner-vps" rather than 3 separate emails.

**Email content:** Brief, actionable, links back to chiefloop.com. Plain text + simple HTML. No marketing, no fluff.

**Provider:** Laravel's built-in mail with a transactional provider (Postmark, SES, or similar via Laravel Cloud).

-----

## Core Features

### Project Dashboard

The main view after login. Shows all projects across the selected server.

**Data source:** Cached project state from the database, overlaid with live WebSocket data when the server is online.

**Project cards show:**
- Project name
- Status indicator (Running / Idle / Error / No PRD / Paused)
- Current/last PRD name and story progress (if applicable)
- Last activity timestamp
- Git branch name

**Actions:**
- Click a project card → navigate to project detail
- "+ New" dropdown → "Clone Repository" or "New Project"
- Server selector in the top bar for users with multiple servers

**Empty state (no projects):** "No projects in workspace. Clone a repository or create a new project to get started."

**Offline state:** Dashboard renders from cached state with an "Offline" banner. Project cards show last-known status. Actions that require a live connection (clone, new project, start run) are disabled with a tooltip: "Server offline."

### Project Detail

Tabbed view within a project.

**Tabs:**
- **Overview**: At-a-glance status. Current/last run summary, quick actions (Start Run, New PRD), recent activity feed, git info, active Claude sessions count.
- **Run**: Ralph loop monitoring. Story list with progress, live Claude output stream, run controls (start/pause/resume/stop), progress bar, duration and token stats.
- **Diffs**: Per-story git diffs. Collapsible accordion by story. Syntax-highlighted unified diff view. File tree.
- **PRDs**: List of all PRDs. Card per PRD with name, story count, status. "+ New" to start PRD creation chat.
- **Settings**: Project configuration (see Chief CLI spec for editable settings). Simple form, saves via WebSocket command.

### PRD Creation / Refinement Chat

Dedicated full-screen page. The primary interaction for creating and refining PRDs.

**How it works:**

1. User clicks "New PRD" → web app generates a `session_id` and sends `new_prd` to chief.
2. Chief spawns an interactive Claude process and streams output back.
3. The browser renders Claude's streaming response in a chat interface.
4. User types a response → web app sends `prd_message` → chief pipes it to Claude's stdin.
5. As Claude generates PRD content, the preview panel (desktop: side-by-side, mobile: toggle) shows the PRD forming in real-time.
6. User clicks "Save" → web app sends `close_prd_session` with `save: true` → chief saves the PRD to disk and kills the Claude process.

**Session management:**
- The session has a **30-minute inactivity timeout** managed by the chief server.
- The web app shows a countdown timer when the session approaches timeout (warnings at 10min, 5min, 1min remaining).
- If the session expires, the UI shows "Session expired. Your progress has been saved." with a "Resume" button that starts a new Claude session with the saved PRD as context.
- Multiple PRD sessions can be active simultaneously (across different projects). The dashboard shows an "Active sessions" indicator.

**Reconnection:**
- If the browser disconnects mid-conversation, Redis-buffered messages are replayed on reconnect.
- The Claude process on the chief server stays alive (it doesn't know the browser disconnected).
- On reconnect, the user sees the full conversation including any Claude output they missed.

**Refinement:**
- Same chat interface, but pre-loaded with the existing PRD as context.
- Route: `/projects/{slug}/prd/{id}/refine`
- Claude sees the full PRD and conversation history. User can make targeted requests ("split US-003 into two stories", "add error handling criteria to US-005").

### Run Monitoring

The core "check on my phone" experience.

**Live view (server online):**
- Story list with real-time status updates (complete/in-progress/pending/failed)
- Current story shows iteration count and attempt number
- Claude output streams below the story list (expandable/collapsible)
- Progress bar with stats: stories completed / total, elapsed time, tokens used
- Run controls: Start, Pause, Resume, Stop

**Cached view (server offline):**
- Last-known progress from `cached_project_state`
- "Server offline — showing last known state from [time]" banner
- Run controls disabled
- If the run completed while the server was connected, `run_history` has the full summary

**Run history:**
- Accessible from the Overview tab or a sub-view of the Run tab
- Shows past runs with: PRD name, status, stories completed/total, duration, tokens, timestamp
- Stored in `run_history` table — available even when server is offline

### Diff Viewer

Per-story diffs fetched on demand from the chief server.

- Only available when the server is online (diffs are not cached)
- Story accordion: click a story to expand its file list, click a file to see the diff
- Syntax-highlighted unified diff using a library like diff2html
- "Server offline" placeholder when the connection is down

### Logging & Debugging

For diagnosing stuck runs or unexpected Claude behavior.

**Live logs:** Claude output streams in real-time on the Run tab during active runs.

**Historical logs:** The web app caches the last N lines (configurable, default 500) of Claude output per story in the `log_cache` table. This is pushed by chief as `log_lines` messages during and after each story.

**On-demand full logs:** A "View full log" link per story sends `get_logs` to the chief server and displays the complete log. Only available when online.

**Purpose:** The cached log lines let users quickly see what went wrong ("test failed on line 47") without needing a live connection. Full logs require the server to be online.

### Clone Repository

Modal (desktop) or full-screen page (mobile).

**Fields:**
- Repository URL (HTTPS or SSH)
- Directory name (auto-filled from URL, editable)

**Info:** "Private repos require SSH access configured on the server. The server uses its existing SSH keys — no credentials are stored in the web app."

**Progress:** Streams in real-time via `clone_progress` messages. On completion, navigates to the new project's overview.

**Errors:** If the clone fails (auth failure, network error, invalid URL), show the git error message inline with suggestions: "Authentication failed — ensure the server's SSH key has access to this repository."

### Create Project

Simpler than clone. Fields: project name (validated: filesystem-safe, no duplicates in workspace), toggle for git init (default on), toggle for "Start PRD creation" (default on → navigates to PRD chat on success).

-----

## One-Click Cloud Deploy

For users who want a persistent chief server without managing their own VPS.

### Deploy Flow

1. User clicks "Deploy Server" from the dashboard or Settings → Cloud Servers
2. Selects provider (Hetzner, DigitalOcean) and region
3. Web app calls the provider API to create a VPS with cloud-init (see CLI spec for the cloud-init script)
4. Dashboard shows "Provisioning..." with status updates
5. VPS boots (~60 seconds). Web app shows "Server deployed. Authorize it to connect."
6. User SSHes into the VPS (copyable `ssh chief@<ip>` command) and runs `chief login` + authenticates Claude Code CLI
7. User starts `chief serve`, device appears as online in the dashboard
8. This is a one-time setup. After initial auth, the user never needs to SSH in again — updates and management happen through the web UI.

### VPS Management

From Settings → Cloud Servers:
- Server status (provisioning, active, suspended, destroyed)
- IP address (copyable)
- Resource usage if available from provider API (CPU, memory, disk)
- Restart chief process (sends signal via provider API or triggers `chief serve` restart)
- Destroy server (with confirmation dialog)
- Monthly cost estimate

-----

## Technical Stack

### Backend

- **Framework**: Laravel (latest)
- **Authentication**: Laravel Socialite (GitHub OAuth)
- **WebSocket**: Laravel Reverb
- **Real-time**: Laravel Echo (browser-side)
- **Database**: MySQL or PostgreSQL (Laravel Cloud default)
- **Cache/Buffer**: Redis (WebSocket message buffering, general caching)
- **Email**: Laravel Mail with Postmark or SES
- **Push Notifications**: Web Push API via Laravel
- **Hosting**: Laravel Cloud

### Frontend

- **Framework**: Vue 3 via Inertia.js
- **Styling**: Tailwind CSS
- **Real-time**: Laravel Echo + Reverb (WebSocket subscriptions)
- **Markdown rendering**: markdown-it or similar (for PRD preview)
- **Diff rendering**: diff2html
- **Syntax highlighting**: Shiki or Prism

### Data Flow

```
Page load:       Laravel Controller → Inertia → Vue (server-rendered props, includes cached state)
Live updates:    Reverb → Laravel Echo → Vue component (WebSocket subscription)
User actions:    Vue → Inertia form/visit → Laravel Controller → relayed to chief via WebSocket
Streaming:       Chief → Reverb → Laravel Echo → Vue (real-time Claude output, progress)
```

Inertia handles navigation and initial data. Vue components subscribe to Reverb channels for live updates. Actions go through Inertia (which means through Laravel controllers), where the web app relays them to the appropriate chief server's WebSocket connection.

-----

## UI Specification

### Design Direction

Design reference: Laravel Cloud, Vercel. Clean, minimal chrome, generous whitespace, sharp typography, monospace accents for technical data. Dark mode first with light mode support. Premium developer tool aesthetic.

**Mobile first.** The most common mobile use case is checking on a run. Monitoring, reviewing progress, and managing runs should feel native on a phone. Creation flows (PRD chat) work on mobile but shine on larger screens.

### Typography

- **Headings/body**: Geometric sans-serif — Geist, Satoshi, or General Sans.
- **Monospace**: Geist Mono or JetBrains Mono for code, diffs, technical data.
- **Base size**: 16px body. Terminal and secondary text scale down. Touch targets minimum 44px.

### Color Palette (Dark Mode Primary)

- **Background**: `#0a0a0b` — near-black
- **Surface**: `#141416`, `#1a1a1e` — cards and elevated surfaces. No shadows, use border or background shift.
- **Border**: `#27272a` — very subtle
- **Text primary**: `#fafafa`
- **Text secondary**: `#a1a1aa`
- **Accent**: Single strong color used sparingly for actions and active states. Warm amber, sharp green, or muted teal — not default blue.
- **Semantic**: Desaturated success (green), error (red), warning (amber), info (blue) colors.

Light mode: inverted with careful contrast ratios.

### Spacing & Motion

- **Spacing scale**: 4px base — 8, 12, 16, 24, 32, 48.
- **Cards**: Subtle border, no shadow, 8px radius. Full-width mobile, max-width ~1200px desktop.
- **Touch targets**: 44×44px minimum, non-negotiable.
- **Page transitions**: Inertia.js morphing. Mobile: directional slides (forward = left, back = right).
- **Streaming text**: Smooth character-by-character appearance.
- **Progress bars**: Smooth fills, gentle pulsing for active status dots.
- **No gratuitous animation.** Every motion serves a purpose.

### Responsive Breakpoints

| Breakpoint | Layout |
|---|---|
| `<640px` | Single column. Bottom tab bar in project detail. Full-screen modals. Chat/preview as toggle. |
| `640–1023px` | Wider cards. Side-by-side where useful. Modals instead of full-screen. |
| `≥1024px` | Full side-by-side layouts. Top tab bar. Command palette. Keyboard shortcuts. |

-----

## Page Layouts

### Global Layout

**Top bar (all breakpoints):**

Hierarchical breadcrumb: `Chief logo` → `Server name ▾` → `Project name ▾`

- Each segment is a dropdown selector.
- Mobile: server selector only in top bar. Project selection via dashboard cards. Inside a project, project name with back arrow.
- Tablet+: both server and project dropdowns.
- Server dropdown shows connection status dot (green/yellow/gray).
- Project dropdown shows run status (running/idle/error).
- Right side: user avatar with dropdown (desktop adds keyboard shortcut help `?` and settings gear).

### Pages

#### 1. Login (`/login`)

Centered card. Chief logo, tagline, "Sign in with GitHub" button. If email is needed post-OAuth (GitHub email private), a follow-up step asks for email with explanation ("We need your email for notifications").

#### 2. Onboarding (`/` — empty state)

After first login with no devices connected:

- Welcome message
- Copyable `chief login` command (no token needed — the OAuth flow handles it)
- Brief explanation: "Run this on your machine or VPS. Chief will show a code to enter here."
- "Or deploy a cloud server" button
- Live listener — when a device authorizes and connects, transitions to dashboard with animation

#### 3. Dashboard (`/`)

**Project cards (primary content):**
- Full-width stacked on mobile, grid on desktop
- Card shows: project name, status dot + label, current PRD + progress (if running), last activity
- Running projects show progress bar
- Entire card is tappable → navigates to project detail
- Mobile: long-press for quick actions (pause, stop)
- Desktop: hover reveals action buttons

**Top actions:** "+ New" dropdown → "Clone Repository" / "New Project"

**Server offline state:** "Server offline — showing last known state from [time]" banner. Cards render from cache. Action buttons disabled with tooltip.

**Active sessions indicator:** If Claude PRD sessions are active, show count in a subtle badge: "2 active sessions"

#### 4. Clone Repository (`/clone` or modal)

- URL field, directory name field (auto-filled)
- SSH info note with link to docs
- Stream clone progress inline
- On success: navigate to new project

#### 5. Create Project (`/new` or modal)

- Name field (validated)
- Git init toggle (default on)
- Start PRD creation toggle (default on)
- On success: navigate to PRD chat or project overview

#### 6. Project Detail (`/projects/{slug}`)

**Mobile: bottom tab bar** — Overview, Run, Diffs, PRDs. Settings in overflow menu. Back arrow to dashboard.

**Desktop: top tab bar** — Overview, Run, Diffs, PRDs, Settings. Below breadcrumb.

##### Overview Tab (default)

At-a-glance project status. Stacked cards on mobile.

- Status card: current PRD name, run status + progress bar, story count
- Quick actions: "Start Run", "New PRD" — full-width stacked on mobile
- Active Claude sessions: count + timeout info if applicable
- Recent activity feed: last 5–10 events (tappable)
- Git info: branch, last commit
- Stats: total stories, completed, remaining, token usage

##### Run Tab

**Mobile (single column):**
- Run controls pinned at top (Start/Pause/Resume/Stop)
- Story list: status icons (✓ complete, ● in progress, ○ pending, ✕ failed), story name, iteration info
- Progress bar + stats (stories, time, tokens)
- Collapsible "Claude Output" section below: scrolling stream of Claude's current output
- Tap completed story → jump to diff
- Tap current story → expand iteration detail

**Desktop (two columns):**
- Story list left, Claude output right, resizable divider
- Run controls and progress bar span full width at top

**Offline:** Shows last-known progress from cache. "Offline" badge. Controls disabled.

##### Diffs Tab

- Stories as collapsible accordion
- Each story shows file count and line changes (+/-)
- Expand → file list → tap file → syntax-highlighted unified diff
- Only available when server is online. Offline: "Connect server to view diffs."

##### PRDs Tab

- Card per PRD: name, story count, status (Active/Done/Draft)
- Actions per card: Run, Refine (opens chat)
- "+ New" button → PRD chat

##### Settings Tab

Form for editable project settings (max_iterations, auto_commit, commit_prefix, claude_model, test_command). Saves via WebSocket. Disabled when offline.

#### 7. PRD Chat (`/projects/{slug}/prd/new` or `/{id}/refine`)

Separate full-screen page. Full focus on conversation.

**Mobile (single column):**
- Chat messages (user + Claude, streaming)
- Input pinned to bottom
- "Preview" button toggles to full-screen PRD preview (toggle, not split)
- "Save" dropdown: "Save & Close", "Save & Run"
- Session timeout countdown visible when < 10 minutes remain
- Back arrow to project (confirm if unsaved)

**Desktop (split view):**
- Chat left, live PRD preview right
- Resizable divider
- Preview updates in real-time as Claude generates
- Session timeout countdown in top-right

#### 8. Settings (`/settings`)

**Sections:**
- **Account**: GitHub info, email (editable for notifications), sign out
- **Devices**: Authorized device list with status, OS, Chief version, last connected time/IP. Deauthorize button per device.
- **Cloud Servers**: Managed VPS list, deploy, restart, destroy, SSH info
- **Preferences**: Notification toggles (push + email), theme (dark/light/system)

#### 9. Docs (`/docs`)

Existing Chief documentation hosted at chiefloop.com/docs. Static site (VitePress, similar to current GitHub Pages docs). Integrated into the same domain but can be a separate build deployed alongside the Laravel app.

-----

## Status Indicators

| State | Icon | Color | Meaning |
|---|---|---|---|
| Running | ● (pulsing) | Green | Claude is actively working |
| Idle | ○ | Gray | Connected, nothing happening |
| Error | ● | Red/orange | Last run failed |
| Paused | ⏸ | Amber | Run paused (quota or user) |
| No PRD | ◌ (hollow) | Gray | Project has no PRD |
| Offline | ● (dim) | Gray | Server disconnected |
| Reconnecting | ● (pulsing) | Amber | Server connection dropped, attempting reconnect |

-----

## Keyboard Shortcuts (Desktop)

| Shortcut | Action |
|---|---|
| `Cmd+K` | Command palette (search projects, quick actions, switch servers) |
| `Cmd+Enter` | Start/resume run |
| `Cmd+.` | Pause run |
| `Escape` | Stop run / close modal |

-----

## Information Architecture

```
/
├── /login
├── /                          (dashboard — project list for selected server)
├── /clone                     (full-screen mobile, modal desktop)
├── /new                       (full-screen mobile, modal desktop)
├── /projects/{slug}
│   ├── /overview              (default tab)
│   ├── /run
│   ├── /diffs
│   ├── /prds
│   │   ├── /new               (PRD chat — full-screen)
│   │   └── /{id}/refine       (PRD refinement chat — full-screen)
│   └── /settings
├── /settings
│   ├── /account
│   ├── /devices
│   ├── /cloud
│   └── /preferences
├── /oauth
│   └── /device                (device code entry page)
└── /docs                      (static documentation site)
```

Most common mobile flow: open app → tap running project card → see run status. Two taps. Push notifications deep-link directly to the run view — zero taps.

-----

## What's Not in Scope (V1)

- **Team/org accounts**: Single-user only. One account, one or more servers.
- **Billing/payments**: The web app is free. Users pay for their own VPS + Claude subscription.
- **Web-based terminal**: Cut from V1. Users SSH into VPS directly when needed.
- **Self-hosted web app**: V1 is chiefloop.com only.
- **CLAUDE.md editing**: Too free-form for a settings UI. Edit directly on the server.
- **Device permission scoping**: All authorized devices have full access. Read-only device permissions are a future consideration.
- **Full git UI**: The web app surfaces clone, status, and diffs. Branch management, merging, rebasing — use SSH or local terminal.
- **PRD/diff caching**: PRD markdown and diffs are not stored in the web app. They're fetched live from the chief server.
- **Mobile app / PWA**: The web app is responsive from day one, but no native app or PWA-specific features.

-----

## Open Questions (Reduced)

1. **Reverb scaling for streaming**: Multiple concurrent users streaming Claude output and run progress through Reverb. Likely fine for early scale (hundreds of users), but monitor bandwidth. If it becomes a bottleneck, consider a dedicated streaming endpoint outside Reverb for high-throughput data.

2. **Multiple browser tabs**: Both tabs receive live updates (standard Reverb broadcasting). Commands are idempotent on the chief server — duplicate "start run" commands return "already running." No client-side mutex needed. PRD chat sessions: if two tabs open the same project's chat, they share the same Claude session (same session_id) — both see the output, but only one should send messages at a time (last-write-wins, with a gentle "editing in another tab" indicator if detected).

