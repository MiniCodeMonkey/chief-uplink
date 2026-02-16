# Remote Monitoring

The Chief web app at [chiefloop.com](https://chiefloop.com) lets you monitor and control your Chief servers from anywhere — perfect for checking progress on your phone.

## Connecting Your Server

### Step 1: Start the Chief server

On your development machine or VPS:

```bash
chief serve
```

This starts a persistent server that accepts WebSocket connections from the web app.

### Step 2: Authorize the device

```bash
chief login
```

This will display a device code like `ABCD-1234`. Enter this code at [chiefloop.com/oauth/device](https://chiefloop.com/oauth/device) to authorize the connection.

### Step 3: Verify connection

Once authorized, your server will appear in the web app's server dropdown with a green status dot indicating it's online.

## Dashboard

The dashboard shows all projects across your connected servers:

- **Project cards** display name, status, current PRD, and story progress
- **Running projects** show an animated progress bar
- **Quick actions** let you pause or stop runs directly from the dashboard
- Click any card to view project details

## Real-Time Updates

The web app receives real-time updates via WebSocket:

- **Claude's output** streams live as it works through stories
- **Story progress** updates as stories complete or fail
- **Run status** changes (started, paused, completed, failed) are reflected immediately

## Offline Mode

When your server goes offline:

- The dashboard shows last-known project states from cache
- An "Offline" banner indicates you're viewing cached data
- Action buttons that require a live connection are disabled
- When the server reconnects, data refreshes automatically

## Connection States

Your server's connection is shown with a colored status dot:

| State | Indicator | Description |
|-------|-----------|-------------|
| Online | Green dot | Server connected and responsive |
| Reconnecting | Yellow pulsing dot | Connection dropped, attempting to reconnect |
| Offline | Gray dot | Disconnected for more than 60 seconds |
| Never Connected | Hollow dot | Device authorized but never connected |

## Managing Devices

View and manage your authorized devices from **Settings > Devices**:

- See all authorized devices with connection status
- View device details (OS, architecture, Chief version, last connected)
- Deauthorize devices to revoke access
