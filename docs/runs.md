# Runs

A run is the process of Chief working through a PRD's user stories, implementing each one sequentially.

## Starting a Run

### From the CLI

```bash
chief run
```

Or specify a particular PRD:

```bash
chief run --prd main
```

### From the Web App

1. Navigate to your project
2. Go to the **Run** tab
3. Click **Start Run**
4. If multiple PRDs exist, select which one to run

## Run Lifecycle

A run progresses through the following states:

1. **Starting** — Run is being initialized
2. **Running** — Claude is actively working on a story
3. **Paused** — Run is paused (manually or due to quota)
4. **Completed** — All stories have been implemented
5. **Failed** — A story failed after maximum retries
6. **Stopped** — Run was manually stopped

## Monitoring Progress

### Story List

The Run tab shows all stories with their current status:

- **Checkmark** (green) — Story completed successfully
- **Filled circle** (amber, pulsing) — Story currently in progress
- **Hollow circle** (gray) — Story pending
- **X** (red) — Story failed

### Claude Output

Watch Claude's work in real-time:

- Output streams live as Claude writes code
- Syntax highlighting for code blocks
- Auto-scrolls to follow new content
- Scroll up to review previous output without losing your place

### Progress Bar

A progress bar at the top shows overall completion: stories completed vs. total.

## Run Controls

| Action | Shortcut | Description |
|--------|----------|-------------|
| Start | `Cmd+Enter` | Begin a new run |
| Pause | `Cmd+.` | Pause the current run |
| Resume | `Cmd+Enter` | Resume a paused run |
| Stop | `Escape` | Stop the run (with confirmation) |

## Run History

Past runs are accessible from the Overview tab or the Run tab:

- Each entry shows PRD name, status, story count, duration, and tokens used
- Click to expand and see per-story results
- History is stored in the database and available even when the server is offline

## Save & Run

When creating or refining a PRD, you can use **Save & Run** to immediately start a run with the saved PRD. This is the fastest way to iterate: define requirements, then execute.
