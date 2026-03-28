# Infrastructure

This project runs entirely in Docker using a multi-stage `Dockerfile` and two Compose files. The same `Dockerfile` produces both development and production images, with no external tooling required on the host beyond Docker itself.

---

## Architecture overview

```
Browser
  └── nginx (port 8080 in dev / 80 in prod)
        └── PHP-FPM (port 9000, internal only)
              └── PostgreSQL (port 5432, internal; exposed on host in dev)
```

The web server (nginx) and the application runtime (PHP-FPM) are separate containers that communicate over the internal Docker network. Nginx handles static file serving directly and proxies only `.php` requests to PHP-FPM via FastCGI on port 9000.

---

## File structure

```
book-tools/
├── Dockerfile                  # Multi-stage build (PHP + Nginx)
├── compose.yaml                # Base services — production targets
├── compose.override.yaml       # Dev overrides — auto-merged locally
└── docker/
    ├── nginx/
    │   └── default.conf        # Nginx site configuration
    └── php/
        ├── php.ini             # Shared PHP runtime settings
        ├── opcache.ini         # OPcache settings (tuned for prod, applied in both)
        ├── xdebug.ini          # Xdebug settings (dev only)
        └── entrypoint.sh       # Dev entrypoint: runs composer install on startup
```

---

## Dockerfile stages

The `Dockerfile` defines five named stages that build on each other:

```
php-base
├── php-dev     (adds Xdebug + dev entrypoint)
└── php-prod    (copies source, installs deps, compiles assets, warms cache)

nginx-base
├── nginx-dev   (no files — expects a volume mount at runtime)
└── nginx-prod  (copies public/ from php-prod)
```

### `php-base`

- Based on `php:8.3-fpm-alpine` for a small image footprint.
- Installs PHP extensions: `intl`, `opcache`, `pdo_pgsql`, `zip`.
- Build dependencies (`$PHPIZE_DEPS`, icu-dev, libpq-dev, etc.) are installed, used, then removed in a single `RUN` layer to avoid bloating the image.
- Composer 2 binary is copied directly from the official `composer:2` image.
- Applies `php.ini` and `opcache.ini`.

### `php-dev`

- Sets `APP_ENV=dev` and `APP_DEBUG=1`.
- Installs Xdebug via PECL and applies `xdebug.ini`.
- Uses a custom **entrypoint** (`docker/php/entrypoint.sh`) that runs `composer install` automatically every time the container starts. This ensures dependencies are always up to date when you add a new package and restart the container — even though the source code is mounted as a volume and `vendor/` is not committed.

### `php-prod`

- Sets `APP_ENV=prod` and `APP_DEBUG=0`.
- A dummy `DATABASE_URL` is set during build so Symfony console commands succeed without a live database. The real value is injected at runtime via environment variable.
- Copies the full source code into the image (owned by `www-data`).
- Runs the full production asset pipeline:
  1. `composer install --no-dev --optimize-autoloader` — installs only production dependencies with an optimized class map.
  2. `importmap:install` — downloads JavaScript packages declared in `importmap.php`.
  3. `assets:install public` — installs bundle assets into `public/`.
  4. `asset-map:compile` — compiles and versions all assets for production (AssetMapper).
  5. `cache:warmup` — pre-builds the Symfony container cache so the first request is fast.
- Runs as `www-data` (non-root).

### `nginx-base`

- Based on `nginx:1.27-alpine`.
- Copies `docker/nginx/default.conf` as the only site config.

### `nginx-dev`

- Identical to `nginx-base`. Static files are served from a bind-mounted `./public` directory injected at runtime by `compose.override.yaml`.

### `nginx-prod`

- Copies `public/` from the `php-prod` stage into the image. Both the PHP container and the Nginx container are fully self-contained with no host dependencies.

---

## Compose files

Docker Compose automatically merges `compose.yaml` and `compose.override.yaml` when both are present. This gives us:

| File | Purpose |
|---|---|
| `compose.yaml` | Defines all services with production build targets and settings |
| `compose.override.yaml` | Overrides targets, ports, and volumes for local development |

To use **only** `compose.yaml` (e.g. in CI or on a server), pass the file explicitly:

```bash
docker compose -f compose.yaml up -d
```

### Environment variables

All tuneable values are driven by environment variables with sensible defaults. Create a `.env.local` file (not committed) to override them locally:

```dotenv
# .env.local
DATABASE_PASS=mysecretpassword
APP_PORT=8080
MAILER_PORT=8025
DATABASE_PORT=5432
```

| Variable | Default | Description |
|---|---|---|
| `APP_PORT` | `8080` (dev) / `80` (prod) | Host port for the nginx container |
| `DATABASE_VERSION` | `16` | PostgreSQL major version |
| `DATABASE_NAME` | `app` | Database name |
| `DATABASE_USER` | `app` | Database user |
| `DATABASE_PASS` | `!ChangeMe!` | Database password — **change in production** |
| `DATABASE_PORT` | `5432` | Host port for PostgreSQL (dev only) |
| `MAILER_PORT` | `8025` | Host port for the Mailpit UI (dev only) |
| `XDEBUG_MODE` | `off` | Xdebug mode — set to `debug` to enable step debugging |

---

## Development workflow

### First start

```bash
docker compose up -d --build
```

This builds the `php-dev` and `nginx-dev` images and starts all services. On first run the `php` container entrypoint will run `composer install`, which takes a minute.

- **App**: http://localhost:8080
- **Mailpit** (catch-all SMTP UI): http://localhost:8025

### Day-to-day commands

```bash
# Start all services
docker compose up -d

# Stop all services
docker compose down

# Rebuild after changing the Dockerfile or docker/ configs
docker compose up -d --build php nginx

# Tail logs
docker compose logs -f php
docker compose logs -f nginx

# Run a Symfony console command
docker compose exec php bin/console <command>

# Run database migrations
docker compose exec php bin/console doctrine:migrations:migrate

# Open a shell in the PHP container
docker compose exec php bash

# Run Composer
docker compose exec php composer require <package>
```

### Adding a Composer package

Because the source is mounted as a volume, you can run Composer either inside the container or on the host (if you have PHP installed locally). The `vendor/` directory lives in your project folder and is shared with the container:

```bash
# Recommended: run inside the container to use the same PHP version
docker compose exec php composer require symfony/uid
```

### Xdebug

Xdebug is installed in the `php-dev` image but **disabled by default** (`XDEBUG_MODE=off`) to avoid the performance overhead during normal development.

Enable it for a session by setting the environment variable before starting the container:

```bash
XDEBUG_MODE=debug docker compose up -d php
```

Or add it permanently to your `.env.local`:

```dotenv
XDEBUG_MODE=debug
```

The debugger listens on port `9003` and connects back to `host.docker.internal` (your machine). Configure your IDE to listen on port 9003 and set the path mapping from `/var/www/html` to your local project root.

### Accessing the database directly

The PostgreSQL port is exposed to the host in dev (default `5432`). Connect with any client:

```
Host:     localhost
Port:     5432
Database: app
User:     app
Password: !ChangeMe!
```

---

## Production deployment

### Building production images

```bash
docker compose -f compose.yaml build
```

This builds `php-prod` and `nginx-prod`. The resulting images are fully self-contained — no volumes, no host dependencies.

### Providing secrets

Never put real credentials in committed files. Pass them as environment variables to the containers at deploy time:

```bash
# Example: docker run
docker run \
  -e APP_SECRET=<your-secret> \
  -e DATABASE_URL=postgresql://user:pass@db:5432/app?serverVersion=16&charset=utf8 \
  -e MAILER_DSN=smtp://user:pass@smtp.example.com:587 \
  your-registry/book-tools-php:latest
```

Or via a `.env` file that is **not committed to git** on the server, then:

```bash
docker compose -f compose.yaml --env-file .env.production up -d
```

### Database migrations

Run migrations as a one-off command after deploying new images:

```bash
docker compose -f compose.yaml exec php bin/console doctrine:migrations:migrate --no-interaction
```

Or add it as a deploy step / init container in your orchestration setup.

### Checklist before going live

- [ ] Change `DATABASE_PASS` from `!ChangeMe!` to a strong password.
- [ ] Set a strong random `APP_SECRET` (generate with `openssl rand -hex 32`).
- [ ] Set a real `MAILER_DSN` pointing to your mail provider.
- [ ] Put nginx behind a TLS terminator (reverse proxy, load balancer, or Caddy).
- [ ] Verify `APP_ENV=prod` and `APP_DEBUG=0` are set in the container environment.
- [ ] Run `doctrine:migrations:migrate` after each deployment.
