# PRDs (Product Requirements Documents)

PRDs are the core input for Chief. They define what you want to build as a structured set of user stories that Chief works through sequentially.

## What is a PRD?

A PRD in Chief is a JSON document containing:

- **Project metadata** — name, description, tech stack
- **User stories** — ordered list of features to implement
- **Acceptance criteria** — specific requirements for each story

## Creating a PRD

### Interactive creation

The easiest way to create a PRD is through conversation with Claude:

```bash
chief init
```

Describe what you want to build, and Claude will help you structure it into well-defined user stories with clear acceptance criteria.

### Via the web app

If you're connected to [chiefloop.com](https://chiefloop.com):

1. Navigate to your project
2. Click the **PRDs** tab
3. Click **+ New PRD**
4. Chat with Claude to define your requirements

The web app provides a live preview panel showing the PRD as it's being created.

### Manual creation

You can also write PRDs manually. Create a `.chief/prds/main/prd.json` file:

```json
{
  "project": "My Project",
  "description": "A brief description of the project",
  "userStories": [
    {
      "id": "US-001",
      "title": "Feature Name",
      "description": "As a user, I want...",
      "acceptanceCriteria": [
        "Criterion 1",
        "Criterion 2"
      ],
      "priority": 1,
      "passes": false
    }
  ]
}
```

## Writing Effective PRDs

### Be specific

Good acceptance criteria are testable and unambiguous:

- **Good:** "Login form validates email format and shows inline error below the field"
- **Bad:** "Login should work properly"

### Order matters

Stories are executed in priority order. Earlier stories should establish foundations that later stories build upon:

1. Project scaffolding and tooling
2. Data models and database schema
3. Core business logic
4. UI components and pages
5. Integration and polish

### Keep stories focused

Each story should be a single, coherent unit of work. If a story has more than 8-10 acceptance criteria, consider splitting it.

## Refining PRDs

You can refine existing PRDs through conversation:

```bash
chief refine
```

Or via the web app:

1. Navigate to the PRD
2. Click **Refine**
3. Ask Claude to make specific changes (e.g., "split US-003 into two stories")

## PRD Sessions

When creating or refining PRDs through conversation, Chief manages a session with Claude:

- Sessions have a **30-minute inactivity timeout**
- Progress is saved automatically
- You can resume where you left off if a session expires
- Multiple PRD sessions can be active across different projects
