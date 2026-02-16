# Cloud Deployment

Chief can be deployed to a cloud VPS directly from the web app, giving you a dedicated server without managing infrastructure manually.

## Supported Providers

- **Hetzner** — European cloud provider with competitive pricing
- **DigitalOcean** — Popular cloud provider with global data centers

## Deploy Wizard

The deploy wizard guides you through setting up a cloud server:

### Step 1: Select Provider

Choose between Hetzner and DigitalOcean. If you haven't added an API key yet, you'll be prompted to do so.

### Step 2: Select Region

Choose a data center region close to you for the best latency. Available regions are fetched from the provider's API.

### Step 3: Select Server Tier

Choose a server size based on your needs. Available tiers show CPU cores, RAM, disk space, and monthly cost.

### Step 4: Confirm & Deploy

Review your selections and click **Deploy**. The server will be provisioned automatically with Chief pre-installed and connected.

## API Key Setup

Before deploying, you need to add your provider's API key:

1. Go to **Settings > Cloud Servers**
2. Click **Add API Key** for your provider
3. Paste your API key — it will be validated automatically
4. Keys are stored encrypted and never exposed after saving

### Getting API Keys

**Hetzner:**
1. Log in to [console.hetzner.cloud](https://console.hetzner.cloud)
2. Select your project
3. Go to **Security > API tokens**
4. Generate a new token with **Read & Write** permissions

**DigitalOcean:**
1. Log in to [cloud.digitalocean.com](https://cloud.digitalocean.com)
2. Go to **API > Tokens**
3. Generate a new personal access token with full access

## Managing Cloud Servers

After deployment, manage your servers from **Settings > Cloud Servers**:

- View server status, IP address, and resource usage
- Copy SSH connection command: `ssh chief@<ip>`
- Restart the Chief process
- Destroy the server (with confirmation)

## How It Works

When you deploy a cloud server:

1. The web app creates a VPS with your provider using their API
2. A cloud-init script installs Chief and its dependencies
3. The server automatically authenticates with chiefloop.com using a one-time setup token
4. Chief starts serving and appears as an online device in your dashboard

No manual SSH or `chief login` is needed for cloud-deployed servers.

## Cost

Cloud servers are billed directly by your provider. Chief does not add any markup. You can destroy servers at any time to stop billing.
