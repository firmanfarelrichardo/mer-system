# SI Project TIK - Production Deployment

Panduan untuk deploy SI Project TIK ke environment production menggunakan Docker.

## Prerequisites

- Docker Engine 20.10+
- Docker Compose 2.0+
- PostgreSQL database (external atau dalam Docker)
- Domain dengan SSL certificate (recommended)

## Struktur File

```
deployment/production/
├── docker-compose.yml    # Konfigurasi Docker services
├── Dockerfile            # Multi-stage build (optimized)
├── docker-entrypoint.sh  # Script inisialisasi container
├── nginx.conf            # Konfigurasi Nginx
├── supervisord.conf      # Konfigurasi Supervisor
├── php.ini               # Konfigurasi PHP production
├── .env.example          # Template environment variables
└── README.md             # Dokumentasi ini
```

## Quick Start

### 1. Clone Repository

```bash
git clone https://github.com/your-org/si-project-tik.git
cd si-project-tik
```

### 2. Setup Environment

```bash
cd deployment/production

# Copy dan edit environment file
cp .env.example .env
nano .env
```

**PENTING:** Pastikan mengisi:
- `APP_KEY` - Generate dengan: `php artisan key:generate --show`
- `APP_URL` - URL production
- `DB_*` - Credentials database
- `MAIL_*` - Konfigurasi email (jika diperlukan)

### 3. Build & Deploy

```bash
# Build image
docker-compose build

# Start container
docker-compose up -d

# Cek logs
docker-compose logs -f
```

### 4. Verifikasi

```bash
# Cek status
docker-compose ps

# Test endpoint
curl http://localhost/health
```

## Environment Variables

### Required

| Variable | Keterangan |
|----------|------------|
| `APP_KEY` | Encryption key (generate dengan artisan) |
| `APP_URL` | URL aplikasi production |
| `DB_HOST` | Host database PostgreSQL |
| `DB_DATABASE` | Nama database |
| `DB_USERNAME` | Username database |
| `DB_PASSWORD` | Password database |

### Optional

| Variable | Default | Keterangan |
|----------|---------|------------|
| `APP_PORT` | 80 | Port expose |
| `DB_PORT` | 5432 | Port PostgreSQL |
| `CACHE_STORE` | database | Driver cache |
| `SESSION_DRIVER` | database | Driver session |
| `QUEUE_CONNECTION` | database | Driver queue |

## Deployment Commands

### Basic Operations

```bash
# Start
docker-compose up -d

# Stop
docker-compose down

# Restart
docker-compose restart

# Rebuild & restart
docker-compose up -d --build

# View logs
docker-compose logs -f app
```

### Maintenance

```bash
# Run migrations
docker-compose exec app php artisan migrate --force

# Clear cache
docker-compose exec app php artisan optimize:clear

# Re-cache config
docker-compose exec app php artisan config:cache
docker-compose exec app php artisan route:cache
docker-compose exec app php artisan view:cache

# Access shell
docker-compose exec app sh
```

## Production Optimizations

### Image Features

- **Multi-stage build** - Image size ~150MB (vs ~500MB single stage)
- **OPcache enabled** - PHP bytecode caching
- **Gzip compression** - Nginx response compression
- **Static file caching** - 1 year cache untuk assets
- **Security headers** - X-Frame-Options, X-Content-Type-Options, etc.

### PHP Configuration

| Setting | Value | Keterangan |
|---------|-------|------------|
| `memory_limit` | 256M | Memory per request |
| `max_execution_time` | 60s | Max execution time |
| `upload_max_filesize` | 64M | Max upload size |
| `opcache.enable` | 1 | OPcache enabled |
| `display_errors` | Off | No error display |

## SSL/HTTPS Setup

### Option 1: Reverse Proxy (Recommended)

Gunakan reverse proxy seperti Nginx, Traefik, atau Caddy di depan container:

```nginx
# /etc/nginx/sites-available/si-project-tik
server {
    listen 443 ssl http2;
    server_name si-project-tik.example.com;

    ssl_certificate /path/to/cert.pem;
    ssl_certificate_key /path/to/key.pem;

    location / {
        proxy_pass http://127.0.0.1:8080;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }
}
```

### Option 2: Traefik (Docker)

```yaml
# docker-compose.yml
services:
  app:
    labels:
      - "traefik.enable=true"
      - "traefik.http.routers.app.rule=Host(`si-project-tik.example.com`)"
      - "traefik.http.routers.app.tls.certresolver=letsencrypt"
```

## Health Check

Container memiliki built-in health check:

```bash
# Manual check
curl http://localhost/health

# Docker health status
docker inspect --format='{{.State.Health.Status}}' si-project-tik-app
```

## Scaling (Optional)

Untuk load yang tinggi, pertimbangkan:

1. **Horizontal scaling** dengan Docker Swarm atau Kubernetes
2. **Redis** untuk cache & session (uncomment di docker-compose.yml)
3. **Queue workers** untuk background jobs (uncomment di supervisord.conf)
4. **CDN** untuk static assets

## Backup

### Database

```bash
# Backup
docker-compose exec db pg_dump -U postgres si_project_tik > backup.sql

# Restore
cat backup.sql | docker-compose exec -T db psql -U postgres si_project_tik
```

### Storage

```bash
# Backup storage volume
docker run --rm -v si-project-tik_app-storage:/data -v $(pwd):/backup alpine \
    tar cvf /backup/storage-backup.tar /data

# Restore
docker run --rm -v si-project-tik_app-storage:/data -v $(pwd):/backup alpine \
    tar xvf /backup/storage-backup.tar -C /
```

## Troubleshooting

### Container tidak start

```bash
# Cek logs
docker-compose logs app

# Cek health
docker inspect si-project-tik-app | grep -A 10 Health
```

### Database connection failed

1. Pastikan database accessible dari container
2. Cek credentials di `.env`
3. Jika database di host, gunakan `host.docker.internal` (Docker Desktop) atau IP host

### 502 Bad Gateway

```bash
# Restart PHP-FPM
docker-compose exec app supervisorctl restart php-fpm
```

### Permission denied

```bash
docker-compose exec app chown -R www-data:www-data /var/www/html/storage
docker-compose exec app chmod -R 775 /var/www/html/storage
```

## CI/CD Integration

### GitHub Actions Example

```yaml
# .github/workflows/deploy.yml
name: Deploy

on:
  push:
    branches: [main]

jobs:
  deploy:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4

      - name: Build & Push Image
        run: |
          docker build -f deployment/production/Dockerfile -t your-registry/si-project-tik:${{ github.sha }} .
          docker push your-registry/si-project-tik:${{ github.sha }}

      - name: Deploy to Server
        run: |
          ssh user@server "cd /app && docker-compose pull && docker-compose up -d"
```

## Architecture

```
                         ┌─────────────────┐
                         │   Load Balancer │
                         │   (Optional)    │
                         └────────┬────────┘
                                  │
                                  ▼
┌─────────────────────────────────────────────────────────┐
│                    Docker Container                      │
│                  si-project-tik-app                      │
│  ┌─────────────────────────────────────────────────┐    │
│  │                  Supervisor                      │    │
│  │  ┌──────────────┐      ┌──────────────┐        │    │
│  │  │    Nginx     │      │   PHP-FPM    │        │    │
│  │  │   Port 80    │─────▶│   Port 9000  │        │    │
│  │  └──────────────┘      └──────────────┘        │    │
│  └─────────────────────────────────────────────────┘    │
│                           │                              │
└───────────────────────────┼──────────────────────────────┘
                            │
                            ▼
              ┌─────────────────────────────┐
              │         PostgreSQL          │
              │     (External/Docker)       │
              └─────────────────────────────┘
```
