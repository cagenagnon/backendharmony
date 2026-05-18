# Harmony Backend — Deployment Guide

This document is the single source of truth for deploying Harmony Backend.
A developer following this guide alone should be able to go from a fresh machine to a running production deployment.

---

## Table of contents

1. [Prerequisites](#1-prerequisites)
2. [Environment variables](#2-environment-variables)
3. [Local development with Docker](#3-local-development-with-docker)
4. [First deployment to Railway](#4-first-deployment-to-railway)
5. [Subsequent deployments](#5-subsequent-deployments)
6. [Database migrations](#6-database-migrations)
7. [Rollback](#7-rollback)
8. [Monitoring and logs](#8-monitoring-and-logs)
9. [Troubleshooting](#9-troubleshooting)

---

## 1. Prerequisites

### Local machine

| Tool | Version | Install |
|---|---|---|
| Docker Desktop (or Docker Engine) | >= 25 | https://docs.docker.com/get-docker/ |
| Docker Compose | >= 2.24 (included with Docker Desktop) | — |
| Git | any | https://git-scm.com |
| Node.js | >= 18 (for Railway CLI only) | https://nodejs.org |
| Railway CLI | 3.11.x | `npm install -g @railway/cli@3.11.5` |

### Accounts

| Service | Purpose | URL |
|---|---|---|
| Railway | Hosting platform | https://railway.app |
| GitHub | Source code + container registry | https://github.com |
| FedaPay | Payment gateway | https://fedapay.com |

---

## 2. Environment variables

Every variable the application reads is listed in `.env.example`.
Copy it and fill in all values marked **REQUIRED** before running anything.

```bash
cp .env.example .env
```

### Required variables (app will not start without these)

| Variable | Description | How to get the value |
|---|---|---|
| `APP_KEY` | Laravel encryption key | `php artisan key:generate --show` |
| `JWT_SECRET` | JWT signing secret | `php artisan jwt:secret --show` |
| `DB_HOST` | Database hostname | Railway dashboard > MySQL > Connect |
| `DB_PORT` | Database port | Railway dashboard > MySQL > Connect |
| `DB_DATABASE` | Database name | Railway dashboard > MySQL > Connect |
| `DB_USERNAME` | Database user | Railway dashboard > MySQL > Connect |
| `DB_PASSWORD` | Database password | Railway dashboard > MySQL > Connect |
| `FEDAPAY_SECRET_KEY` | FedaPay API key | FedaPay dashboard > Settings > API keys |
| `APP_URL` | Public URL of the app | Railway dashboard > Service > Settings > Domain |

### Recommended production values

```dotenv
APP_ENV=production
APP_DEBUG=false
DB_CONNECTION=mysql
LOG_CHANNEL=stderr
QUEUE_CONNECTION=database
CACHE_STORE=database
FEDAPAY_MODE=live
FRONTEND_URL=https://harmonybymdn.netlify.app
CORS_ALLOWED_ORIGINS=https://harmonybymdn.netlify.app
```

If you deploy a preview or custom Netlify domain, add it to `CORS_ALLOWED_ORIGINS` as a comma-separated list and redeploy so Laravel rebuilds its config cache.

---

## 3. Local development with Docker

### Start the full stack

```bash
# 1. Clone the repository
git clone https://github.com/YOUR_ORG/harmonybackend.git
cd harmonybackend

# 2. Create .env from the example and fill in secrets
cp .env.example .env
# Edit .env: set APP_KEY, JWT_SECRET, FEDAPAY_SECRET_KEY at minimum.
# DB_HOST, DB_PORT, etc. are pre-filled for the Docker Compose MySQL container.

# 3. Start (production-equivalent compose)
docker compose up -d

# 4. Follow logs
docker compose logs -f app
```

The app will be reachable at http://localhost:8080.
The health endpoint is http://localhost:8080/up.

### Start with development overrides (hot-reload)

```bash
docker compose -f docker-compose.yml -f docker-compose.dev.yml up
```

Differences from production:
- Source code is bind-mounted — edits are instantly reflected inside the container.
- `APP_DEBUG=true` — detailed error pages.
- Mailpit runs on http://localhost:8025 — capture all outgoing email.

### Useful container commands

```bash
# Run an Artisan command
docker compose exec app php artisan <command>

# Open a shell inside the app container
docker compose exec app bash

# Tail logs only
docker compose logs -f app

# Stop and remove containers (keeps volumes)
docker compose down

# Full reset (removes volumes — destroys database data)
docker compose down -v
```

---

## 4. First deployment to Railway

### 4.1 Create a Railway project

1. Go to https://railway.app and sign in.
2. Click **New Project** > **Empty project**.
3. Name it `harmonybackend`.

### 4.2 Provision a MySQL database

1. Inside the project, click **New Service** > **Database** > **MySQL**.
2. Wait for provisioning (~30 seconds).
3. Click the MySQL service, then **Variables**. Note the values for:
   - `MYSQL_HOST`, `MYSQL_PORT`, `MYSQL_DATABASE`, `MYSQL_USER`, `MYSQL_PASSWORD`.

### 4.3 Create the application service

1. Click **New Service** > **GitHub Repo** and connect your repository.
2. Railway will detect the `Dockerfile` automatically (or use `railway.toml` which sets `builder = "DOCKERFILE"`).
3. **If the repo is a monorepo** (Dockerfile is not at the repo root): In the service, go to **Settings** > **Source** and set **Root Directory** to the directory that contains both `Dockerfile` and the `docker/` folder (e.g. `harmonybackend`). Otherwise the Docker build can fail with `docker/entrypoint.sh: not found`.

### 4.4 Set environment variables

In the application service, go to **Variables** and add all **REQUIRED** variables.

Use Railway variable references to wire the database:
```
DB_HOST        = ${{MySQL.MYSQL_HOST}}
DB_PORT        = ${{MySQL.MYSQL_PORT}}
DB_DATABASE    = ${{MySQL.MYSQL_DATABASE}}
DB_USERNAME    = ${{MySQL.MYSQL_USER}}
DB_PASSWORD    = ${{MySQL.MYSQL_PASSWORD}}
DB_CONNECTION  = mysql
```

Generate and add the remaining secrets:
```bash
# Run once locally (does not require a running app):
docker run --rm php:8.4-fpm-alpine php -r "echo 'base64:' . base64_encode(random_bytes(32)) . PHP_EOL;"
# Paste the output into APP_KEY in Railway.

# For JWT_SECRET, generate a random 64-character string:
openssl rand -hex 32
# Paste into JWT_SECRET in Railway.
```

### 4.5 Link the CLI and get the service ID

```bash
railway login
railway link   # select your project and environment
```

Copy the service ID from the Railway dashboard URL:
`https://railway.app/project/<project_id>/service/<service_id>`

Store it as `RAILWAY_SERVICE_ID` in your GitHub repository secrets.

### 4.6 Set GitHub repository secrets

Go to your GitHub repository > **Settings** > **Secrets and variables** > **Actions** and add:

| Secret | Value |
|---|---|
| `RAILWAY_TOKEN` | Railway API token (Account > Tokens > New token) |
| `RAILWAY_SERVICE_ID` | Railway service ID from step 4.5 |

Go to **Variables** (same page) and add:

| Variable | Value |
|---|---|
| `APP_URL` | Your Railway public URL, e.g. `https://harmonybackend.up.railway.app` |

### 4.7 Trigger the first deployment

```bash
git push origin main
```

GitHub Actions will:
1. Run PHP tests against a MySQL container.
2. Build the Docker image and push it to `ghcr.io`.
3. Deploy to Railway via `railway up`.
4. Poll `/up` until the deployment is healthy.

Watch the deployment live at https://railway.app in your project dashboard.

### 4.8 Assign a custom domain (optional)

1. In Railway, Service > Settings > Domains > **Generate Domain** (for the free *.railway.app domain).
2. Or add a custom domain and follow Railway's DNS instructions.
3. Update `APP_URL` in Railway variables to match.

---

## 5. Subsequent deployments

Every push to the `main` branch triggers the full CI/CD pipeline automatically:

```
push to main
  → GitHub Actions: run tests
  → Build Docker image → push to GHCR
  → railway up (deploy new image)
  → Health check (/up) — fails pipeline if app does not respond
```

### Manual deployment (bypass CI)

```bash
# From repo root, with Railway CLI authenticated
railway up --service <RAILWAY_SERVICE_ID>
```

### Deploy a specific image tag

```bash
# Push the tag to GHCR first, then:
railway variables set RAILWAY_DEPLOYMENT_IMAGE=ghcr.io/YOUR_ORG/harmonybackend:sha-abc1234
railway up --service <RAILWAY_SERVICE_ID>
```

---

## 6. Database migrations

### Automatic (default)

Migrations run automatically every time the container starts, via `entrypoint.sh`:
```bash
php artisan migrate --force --no-interaction
```

This is safe because `migrate` is idempotent — it skips already-applied migrations.

### Manual (from CI or local)

```bash
# Using Railway CLI
railway run --service <RAILWAY_SERVICE_ID> -- php artisan migrate --force

# Or exec into a running container
railway exec --service <RAILWAY_SERVICE_ID> -- bash
php artisan migrate --force
```

### Skip migrations (worker containers)

Set `SKIP_MIGRATIONS=true` in the service variables to prevent a container from running migrations on start. Useful when scaling out worker-only replicas.

### Create a new migration

```bash
php artisan make:migration <migration_name>
```

Add it to `database/migrations/`, commit and push — the pipeline will deploy it and the container entrypoint will apply it automatically.

---

## 7. Rollback

### Option A — Railway dashboard (fastest, one click)

1. Go to Railway > Project > Service > **Deployments**.
2. Find the last known-good deployment.
3. Click the three-dot menu > **Rollback**.

Railway swaps traffic to the selected deployment instantly.

### Option B — Railway CLI

```bash
railway service rollback --service <RAILWAY_SERVICE_ID>
# This rolls back to the immediately previous deployment.
```

### Option C — Git revert (for code rollback with CI verification)

```bash
git revert HEAD --no-edit
git push origin main
# Triggers a full CI/CD cycle with the reverted code.
```

### Database rollback

If a migration must be reversed:
```bash
railway run --service <RAILWAY_SERVICE_ID> -- php artisan migrate:rollback --step=1
```

Warning: `migrate:rollback` is irreversible for destructive migrations (DROP COLUMN, etc.).
Always back up the database before running destructive migrations in production.

---

## 8. Monitoring and logs

### Logs

- **Railway dashboard**: Service > **Logs** tab — live streaming.
- **Railway CLI**: `railway logs -t --service <RAILWAY_SERVICE_ID>`
- Inside container: all processes (nginx, php-fpm, queue worker, scheduler) write to stdout/stderr, which Railway captures.

### Health check

- Endpoint: `GET /up` — returns HTTP 200 when the app is running.
- Railway monitors this endpoint and restarts the container if it fails.
- Docker: `docker compose ps` shows the health status column.

### Application errors

- Laravel logs at level `warning` and above go to `stderr` (captured by Railway).
- Set `LOG_LEVEL=debug` temporarily to increase verbosity.
- For structured error tracking, integrate Sentry:
  ```bash
  composer require sentry/sentry-laravel
  # Set SENTRY_LARAVEL_DSN=https://... in Railway variables.
  ```

---

## 9. Troubleshooting

### Build fails: "docker/entrypoint.sh" or "docker/* not found"

**Symptom**: Docker build fails with `"/docker/entrypoint.sh": not found` (or similar for `docker/php.ini`, `docker/nginx.conf`, `docker/supervisord.conf`).

**Cause**: The build context sent to Docker does not include the `docker/` directory. This usually happens when deploying from a **monorepo**: the repository root is a parent folder, and the Dockerfile lives in a subfolder (e.g. `harmonybackend/`), so the default context is the repo root and there is no `docker/` at the root.

**Fix**:
1. In Railway, open the application service → **Settings** → **Source**.
2. Set **Root Directory** to the directory that contains both the `Dockerfile` and the `docker/` folder (e.g. `harmonybackend`).
3. Save and redeploy. The build context will then be that directory, so the builder stage gets `docker/` and the image builds successfully.

Also ensure the `docker/` directory and its files are committed and pushed (they must not be in `.gitignore` or `.dockerignore`).

---

### Build fails: "composer install" error

**Symptom**: Docker build exits at the `RUN composer install` step.

**Cause**: A PHP extension required by a Composer package is not installed in the build stage.

**Fix**: Identify the missing extension from the error message and add it to the `RUN docker-php-ext-install` line in the `builder` stage of `Dockerfile`.

---

### Container crashes immediately after start

**Symptom**: Railway shows "Crashed" or the Docker container exits with code 1 right after starting.

**Cause A**: A required environment variable is missing.
- Check Railway logs for `<VARIABLE> is required` (from `entrypoint.sh`).
- Add the missing variable in Railway > Service > Variables.

**Cause B**: The database is unreachable.
- Verify `DB_HOST`, `DB_PORT`, `DB_USERNAME`, `DB_PASSWORD` are correct.
- Check that the MySQL Railway service is running.

---

### "APP_KEY" or "No application encryption key" error

**Symptom**: 500 error with message about missing key.

**Fix**:
```bash
# Generate locally:
docker run --rm php:8.4-fpm-alpine php -r "echo 'base64:' . base64_encode(random_bytes(32)) . PHP_EOL;"
# Set the output as APP_KEY in Railway variables, then redeploy.
```

---

### Database connection refused

**Symptom**: `Connection refused` or `SQLSTATE[HY000] [2002]` in logs.

**Checks**:
1. `DB_HOST` is set to the Railway MySQL service hostname (not `localhost`).
2. The MySQL service is running (Railway dashboard).
3. The port matches (`DB_PORT=3306` for MySQL).
4. In Docker Compose local: use `DB_HOST=db` (the service name), not `127.0.0.1`.

---

### Migrations fail: "Access denied for user"

**Cause**: The DB user does not have `ALTER TABLE` / `CREATE TABLE` privileges.

**Fix**: In Railway MySQL, the auto-provisioned user has full access to its own database. Double-check that `DB_USERNAME` matches `MYSQL_USER` from the MySQL service variables and NOT `root`.

---

### "JWT Secret not set" error

**Symptom**: 500 on any `auth:api` protected route.

**Fix**: Set `JWT_SECRET` in Railway variables:
```bash
openssl rand -hex 32
```
After setting the variable, Railway will redeploy automatically.

---

### Queue jobs not processing

**Symptom**: Jobs are created (visible in the `jobs` table) but never executed.

**Checks**:
1. `QUEUE_CONNECTION=database` is set.
2. `ENABLE_QUEUE_WORKER=true` (should be the default).
3. Check supervisord logs: `railway logs -t | grep queue-worker`.
4. Manually run a worker: `railway run -- php artisan queue:work --once`.

---

### Port conflict on local Docker Compose

**Symptom**: `Error: address already in use` on port 8080.

**Fix**: Change `APP_PORT` in `.env` to an unused port, e.g. `APP_PORT=8090`, then `docker compose up -d`.

---

### "No space left on device" during Docker build

**Fix**: Prune unused Docker resources:
```bash
docker system prune -af --volumes
```
