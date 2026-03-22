# Self-Hosting Guide

This guide covers deploying Chief Uplink on your own infrastructure.

## Requirements

- **Docker** & **Docker Compose** (v2+)
- A **domain name** with DNS pointing to your server
- **SSL/TLS certificate** (Let's Encrypt via Caddy recommended)
- Ports **80** and **443** available (or a reverse proxy in front)

## Deployment

### 1. Clone and Configure

```bash
git clone https://github.com/chief-tools/chief-uplink.git
cd chief-uplink
cp .env.example .env
```

### 2. Environment Variables

Edit `.env` with your production values. Critical variables:

| Variable | Description | Notes |
|----------|-------------|-------|
| `APP_KEY` | Encryption key | **Critical** — run `php artisan key:generate` to create. All encrypted data (tokens, API keys) depends on this. Back it up securely. Losing it means losing access to all encrypted data. |
| `APP_URL` | Your public URL | e.g., `https://uplink.example.com` |
| `APP_ENV` | Environment | Set to `production` |
| `APP_DEBUG` | Debug mode | Set to `false` |
| `DB_PASSWORD` | Database password | Use a strong, unique password |
| `DB_DATABASE` | Database name | Default: `chief_uplink` |
| `DB_USERNAME` | Database user | Default: `chief` |
| `REVERB_APP_KEY` | Reverb WebSocket key | Generate a random string |
| `REVERB_APP_SECRET` | Reverb WebSocket secret | Generate a random string |
| `GITHUB_CLIENT_ID` | GitHub OAuth app ID | Create at GitHub Developer Settings |
| `GITHUB_CLIENT_SECRET` | GitHub OAuth secret | From the same OAuth app |
| `VAPID_PUBLIC_KEY` | Push notification public key | Generate with `npx web-push generate-vapid-keys` |
| `VAPID_PRIVATE_KEY` | Push notification private key | From the same command |

### 3. Start Services

```bash
docker compose -f docker-compose.prod.yml up -d
```

This starts:
- **app** — Laravel on FrankenPHP (port 8000 internally)
- **mariadb** — MariaDB 11 with persistent volume
- **redis** — Redis 7 with persistent volume

### 4. Initialize the Application

```bash
# Generate application key (if not already set)
docker compose -f docker-compose.prod.yml exec app php artisan key:generate

# Run database migrations
docker compose -f docker-compose.prod.yml exec app php artisan migrate --force
```

## Reverse Proxy

A reverse proxy handles SSL termination and routes traffic to the Docker containers. Caddy is recommended for its automatic HTTPS.

### Caddy (Recommended)

```
uplink.example.com {
    # Main application
    reverse_proxy localhost:8000

    # WebSocket upgrade for device connections
    @ws_device path /ws/device
    reverse_proxy @ws_device localhost:8000 {
        header_up Connection {>Connection}
        header_up Upgrade {>Upgrade}
    }

    # Reverb WebSocket
    @reverb path /app/*
    reverse_proxy @reverb localhost:8080 {
        header_up Connection {>Connection}
        header_up Upgrade {>Upgrade}
    }
}
```

### Nginx

```nginx
server {
    listen 443 ssl http2;
    server_name uplink.example.com;

    ssl_certificate /path/to/cert.pem;
    ssl_certificate_key /path/to/key.pem;

    # Main application
    location / {
        proxy_pass http://127.0.0.1:8000;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }

    # Device WebSocket
    location /ws/device {
        proxy_pass http://127.0.0.1:8000;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "upgrade";
        proxy_set_header Host $host;
        proxy_read_timeout 86400;
    }

    # Reverb WebSocket
    location /app/ {
        proxy_pass http://127.0.0.1:8080;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "upgrade";
        proxy_set_header Host $host;
        proxy_read_timeout 86400;
    }
}
```

## Database

MariaDB 11 is included in the Docker Compose stack with a persistent volume (`mariadb-data`).

### Backups

```bash
# Create a backup
docker compose -f docker-compose.prod.yml exec mariadb \
    mariadb-dump -u root -p"$DB_PASSWORD" chief_uplink > backup_$(date +%Y%m%d_%H%M%S).sql

# Restore from backup
docker compose -f docker-compose.prod.yml exec -T mariadb \
    mariadb -u root -p"$DB_PASSWORD" chief_uplink < backup.sql
```

Set up automated daily backups with cron:

```bash
0 2 * * * cd /path/to/chief-uplink && docker compose -f docker-compose.prod.yml exec -T mariadb mariadb-dump -u root -p"$DB_PASSWORD" chief_uplink | gzip > /path/to/backups/chief_uplink_$(date +\%Y\%m\%d).sql.gz
```

## Redis

Redis 7 is used for caching, pub/sub (device command routing), and the WebSocket connection manager. Data is persisted via the `redis-data` volume.

No special configuration is needed — the defaults work for most deployments.

## Updates

```bash
# Pull the latest code
git pull origin main

# Rebuild and restart containers
docker compose -f docker-compose.prod.yml up -d --build

# Run any new migrations
docker compose -f docker-compose.prod.yml exec app php artisan migrate --force

# Clear caches
docker compose -f docker-compose.prod.yml exec app php artisan config:cache
docker compose -f docker-compose.prod.yml exec app php artisan route:cache
docker compose -f docker-compose.prod.yml exec app php artisan view:cache
```

## Troubleshooting

### Application won't start
Check logs: `docker compose -f docker-compose.prod.yml logs app`

### Database connection errors
Ensure MariaDB is healthy: `docker compose -f docker-compose.prod.yml ps`
The app container waits for MariaDB's healthcheck before starting.

### WebSocket connections failing
Verify your reverse proxy passes `Upgrade` and `Connection` headers for `/ws/device` and `/app/*` paths.

### Missing encryption key
If you see decryption errors, your `APP_KEY` may be missing or changed. This key is critical — all encrypted data (device tokens, API keys) depends on it.
