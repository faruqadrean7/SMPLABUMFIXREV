# SISTLABUM — Production Operations & Deployment Checklist (Final)

> **Application:** `apps/api` (Laravel 13.35.0 / PHP 8.5.11 / PostgreSQL 17)  
> **Target Release:** v1.0.0-RC1  
> **Audience:** DevOps, System Administrators & Release Managers  
> **Status:** PRODUCTION READY (Operational Guidelines Established)

---

## 1. Environment & Secrets Management (`.env`)

Before initiating live traffic, ensure the production `.env` is configured with:

```ini
APP_NAME=SISTLABUM
APP_ENV=production
APP_KEY=base64:YOUR_GENERATED_APP_KEY_HERE
APP_DEBUG=false
APP_URL=https://api.sistlabum.sch.id

# Allowed Frontend Domains
FRONTEND_URL=https://portal.sistlabum.sch.id
ADMIN_URL=https://admin.sistlabum.sch.id
CORS_ALLOWED_ORIGINS=https://portal.sistlabum.sch.id,https://admin.sistlabum.sch.id
SANCTUM_STATEFUL_DOMAINS=portal.sistlabum.sch.id,admin.sistlabum.sch.id

# Session Security Attributes
SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_ENCRYPT=true
SESSION_SECURE_COOKIE=true
SESSION_SAME_SITE=lax

# Database (PostgreSQL Cluster)
DB_CONNECTION=pgsql
DB_HOST=postgres-cluster.internal
DB_PORT=5432
DB_DATABASE=sistlabum_production
DB_USERNAME=sistlabum_app
DB_PASSWORD=YOUR_STRONG_DB_PASSWORD
DB_SSLMODE=prefer

# Redis (Cache & Queue)
QUEUE_CONNECTION=redis
CACHE_STORE=redis
REDIS_CLIENT=phpredis
REDIS_HOST=redis-cluster.internal
REDIS_PORT=6379
REDIS_PASSWORD=YOUR_STRONG_REDIS_PASSWORD

# Object Storage S3 / MinIO
FILESYSTEM_DISK=s3
AWS_ACCESS_KEY_ID=YOUR_AWS_ACCESS_KEY
AWS_SECRET_ACCESS_KEY=YOUR_AWS_SECRET_KEY
AWS_DEFAULT_REGION=ap-southeast-1
AWS_BUCKET=sistlabum-prod-storage
AWS_USE_PATH_STYLE_ENDPOINT=false

# Notification Gateways
WA_GATEWAY_URL=https://api.wagw.school.id/v1/send
WA_API_KEY=YOUR_WA_GATEWAY_KEY
```

---

## 2. Pre-Flight Deployment Sequence

Execute these commands in sequence during CI/CD release deployment:

```bash
# 1. Enter application directory
cd /var/www/sistlabum/apps/api

# 2. Install production dependencies without dev packages
composer install --no-dev --optimize-autoloader --no-interaction

# 3. Execute database migrations
php artisan migrate --force

# 4. Cache configurations, routes, and views for high performance
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

# 5. Restart background queue workers
php artisan queue:restart
```

---

## 3. Background Services & Daemons

### 3.1 Supervisor Queue Worker Configuration
Place in `/etc/supervisor/conf.d/sistlabum-worker.conf`:

```ini
[program:sistlabum-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/sistlabum/apps/api/artisan queue:work redis --sleep=3 --tries=3 --max-time=3600 --timeout=90
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=4
redirect_stderr=true
stdout_logfile=/var/log/supervisor/sistlabum-worker.log
```

### 3.2 Scheduled Tasks (Cron)
Add to the web server's crontab (`crontab -e -u www-data`):

```cron
* * * * * cd /var/www/sistlabum/apps/api && php artisan schedule:run >> /dev/null 2>&1
```

---

## 4. Health Monitoring & Alerting

- **Liveness & Readiness Probe:** `GET https://api.sistlabum.sch.id/api/v1/health`
- **Expected Status:** `HTTP 200 OK`
- **Response Format:**
  ```json
  {
    "status": "healthy",
    "timestamp": "2026-10-12T02:00:00+00:00",
    "services": {
      "database": "ok",
      "redis": "ok"
    },
    "version": "1.1.0"
  }
  ```
- **Alert Conditions:** Trigger PagerDuty/Slack notification if `services.database !== "ok"` for 3 consecutive polls.

---

## 5. Backup & Disaster Recovery Strategy

1. **Daily Automated PostgreSQL Dumps:**
   - Schedule snapshot using `pg_dump` at 02:00 WIB daily.
   - Encrypt backup dumps using GPG/AES-256 before uploading to an isolated cold-storage S3 bucket.
2. **Backup Retention:** Keep daily backups for 30 days, monthly backups for 12 months in accordance with school data archiving standards.
3. **Recovery Drill:** Perform quarterly restore verification into a staging database.
