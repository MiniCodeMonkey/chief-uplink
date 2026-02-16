# Viewing Diffs

Chief provides per-story git diffs so you can review exactly what Claude changed for each user story.

## Accessing Diffs

Navigate to your project and click the **Diffs** tab. You'll see an accordion view with one section per completed story.

## Story Diffs

Each story's diff is scoped — it shows only the changes made for that specific story, not the entire branch diff. This makes it easy to review changes in context.

### Story Header

Each story section shows:

- **Story name** and ID
- **File count** — number of files changed
- **Line changes** — additions (+N) and deletions (-N)

### File View

Click a story to expand its diff. You'll see:

- A **file tree** sidebar (desktop) or file list (mobile) showing all changed files grouped by directory
- **Syntax-highlighted diffs** with line numbers, additions (green), and deletions (red)
- A **copy button** to copy the raw diff for any file

## Requirements

Diffs are fetched on demand from the Chief server, so your server must be **online** to view them. When the server is offline, you'll see a "Connect server to view diffs" message.

## Tips

- Diffs use syntax highlighting appropriate to each file type
- Large diffs are virtualized for performance
- The diff color scheme adapts to your dark/light mode preference
- Click on completed stories in the Run tab to jump directly to their diff
