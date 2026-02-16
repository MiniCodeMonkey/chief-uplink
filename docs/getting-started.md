# Getting Started

Chief is an AI-powered development tool that automates software engineering tasks using Claude. This guide will help you get up and running quickly.

## Prerequisites

Before installing Chief, ensure you have the following:

- **Node.js** 20+ or **Bun** 1.0+
- **Git** 2.30+
- An **Anthropic API key** (get one at [console.anthropic.com](https://console.anthropic.com))

## Installation

Install Chief globally using npm:

```bash
npm install -g @anthropic/chief
```

Or using Bun:

```bash
bun install -g @anthropic/chief
```

Verify the installation:

```bash
chief --version
```

## Quick Start

### 1. Set up your API key

```bash
export ANTHROPIC_API_KEY=sk-ant-...
```

Or add it to your shell profile for persistence.

### 2. Initialize a project

Navigate to your project directory and create a PRD (Product Requirements Document):

```bash
cd your-project
chief init
```

This will start an interactive session with Claude to define what you want to build.

### 3. Start a run

Once your PRD is ready, start an automated run:

```bash
chief run
```

Chief will work through each user story in your PRD, implementing the code, running tests, and committing changes.

### 4. Connect to the web app

To monitor your runs remotely from [chiefloop.com](https://chiefloop.com):

```bash
chief login
```

This will display a device code. Enter it at chiefloop.com to authorize your machine.

## Next Steps

- Learn about [PRDs](/docs/prds) and how to write effective requirements
- Set up [remote monitoring](/docs/remote-monitoring) via the web app
- Explore [configuration options](/docs/configuration) to customize Chief's behavior
- Read about [self-hosting](/docs/self-hosting) the web app
