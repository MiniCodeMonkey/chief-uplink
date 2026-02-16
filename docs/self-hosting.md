# Self-Hosting

While most users should use the hosted version at [chiefloop.com](https://chiefloop.com), you can self-host the Chief web app for full control over your data and infrastructure.

## Requirements

- **PHP** 8.3+
- **Node.js** 20+
- **PostgreSQL** 15+
- **Redis** 7+
- **Composer** 2.0+
- **Nginx** or **Apache** (reverse proxy)

## Installation

### 1. Clone the repository

```bash
git clone https://github.com/minicodemonkey/chief-uplink.git
cd chief-uplink
```

### 2. Install dependencies

```bash
composer install --no-dev
npm install
npm run build
```

### 3. Configure environment

```bash
cp .env.example .env
php artisan key:generate
```

Edit `.env` with your database, Redis, and application settings:

```env
APP_URL=https://your-domain.com
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_DATABASE=chief
DB_USERNAME=chief
DB_PASSWORD=your-secure-password

REDIS_HOST=127.0.0.1

REVERB_HOST=your-domain.com
REVERB_PORT=8080
REVERB_SCHEME=https
```

### 4. Set up the database

```bash
php artisan migrate
```

### 5. Configure GitHub OAuth

Create a GitHub OAuth app at [github.com/settings/developers](https://github.com/settings/developers):

- **Homepage URL:** `https://your-domain.com`
- **Callback URL:** `https://your-domain.com/auth/github/callback`

Add the credentials to `.env`:

```env
GITHUB_CLIENT_ID=your-client-id
GITHUB_CLIENT_SECRET=your-client-secret
```

## Running Services

### Web server

Configure Nginx to proxy to PHP-FPM:

```nginx
server {
    listen 80;
    server_name your-domain.com;
    root /path/to/chief-uplink/public;

    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

### WebSocket server (Reverb)

```bash
php artisan reverb:start --host=0.0.0.0 --port=8080
```

For production, run this as a systemd service:

```ini
[Unit]
Description=Chief Reverb WebSocket Server
After=network.target

[Service]
User=www-data
WorkingDirectory=/path/to/chief-uplink
ExecStart=/usr/bin/php artisan reverb:start --host=0.0.0.0 --port=8080
Restart=always

[Install]
WantedBy=multi-user.target
```

### Queue worker

```bash
php artisan queue:work redis --sleep=3 --tries=3
```

### Scheduler

Add to crontab:

```
* * * * * cd /path/to/chief-uplink && php artisan schedule:run >> /dev/null 2>&1
```

## Nginx WebSocket Proxy

To proxy WebSocket connections through Nginx:

```nginx
location /ws {
    proxy_pass http://127.0.0.1:8080;
    proxy_http_version 1.1;
    proxy_set_header Upgrade $http_upgrade;
    proxy_set_header Connection "upgrade";
    proxy_set_header Host $host;
    proxy_read_timeout 86400;
}
```

## Push Notifications

To enable push notifications, generate VAPID keys:

```bash
php artisan webpush:vapid
```

Add the keys to `.env`:

```env
VAPID_PUBLIC_KEY=your-public-key
VAPID_PRIVATE_KEY=your-private-key
```

## Email Notifications

Configure a transactional email provider (Postmark or Amazon SES):

```env
MAIL_MAILER=postmark
POSTMARK_TOKEN=your-token
MAIL_FROM_ADDRESS=notifications@your-domain.com
MAIL_FROM_NAME="Chief"
```

## Updating

```bash
git pull
composer install --no-dev
npm install
npm run build
php artisan migrate
php artisan queue:restart
```

## Security Considerations

- Always use HTTPS in production
- Keep your `APP_KEY` secret and backed up
- Rotate database passwords regularly
- Enable PostgreSQL SSL connections
- Run `composer audit` and `npm audit` regularly
- Set proper file permissions (storage/ and bootstrap/cache/ writable by web server)
