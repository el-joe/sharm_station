#!/usr/bin/env bash
# Fetch the latest code and rebuild everything. Run after every update:
#   ./deploy/deploy.sh [branch]        (default branch: main)
#
# Flags via env:  SKIP_BACKEND=1  SKIP_FRONTEND=1  SKIP_MIGRATE=1
#
# Backend  (Laravel)  -> composer install, migrate, vite build, cache, API on :8090
# Frontend (Next.js)  -> npm ci, next build, served on :3100
# Both processes are managed by PM2 (started if missing, restarted otherwise).
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
BRANCH="${1:-main}"
API_PORT=8090
WEB_PORT=3100

log() { printf '\n\033[1;36m==> %s\033[0m\n' "$*"; }

need() { command -v "$1" >/dev/null || { echo "Missing required command: $1" >&2; exit 1; }; }
for c in git php composer node npm pm2; do need "$c"; done

cd "$ROOT"

# ---------------------------------------------------------------- git
log "Fetching origin/${BRANCH}"
if [[ -n "$(git status --porcelain --untracked-files=no)" ]]; then
    echo "Working tree has uncommitted changes to tracked files; commit or stash them first." >&2
    exit 1
fi
git fetch --prune origin
git checkout "$BRANCH"
git pull --ff-only origin "$BRANCH"
echo "Now at $(git log -1 --format='%h %s')"

# ---------------------------------------------------------------- backend
if [[ -z "${SKIP_BACKEND:-}" ]]; then
    log "Backend: dependencies"
    cd "$ROOT/backend"
    [[ -f .env ]] || { echo "backend/.env is missing; create it first." >&2; exit 1; }
    composer install --no-interaction --prefer-dist --optimize-autoloader --no-dev
    npm ci --no-audit --no-fund

    log "Backend: assets"
    npm run build

    if [[ -z "${SKIP_MIGRATE:-}" ]]; then
        log "Backend: migrations"
        php artisan migrate --force
    fi

    log "Backend: caches"
    php artisan optimize:clear
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
    php artisan storage:link 2>/dev/null || true
    php artisan queue:restart 2>/dev/null || true

    log "Backend: API process (pm2: sharm-api on :${API_PORT})"
    if pm2 describe sharm-api >/dev/null 2>&1; then
        pm2 restart sharm-api --update-env
    else
        PHP_CLI_SERVER_WORKERS=4 pm2 start php --name sharm-api -- \
            artisan serve --host=127.0.0.1 --port="$API_PORT"
    fi
fi

# ---------------------------------------------------------------- frontend
if [[ -z "${SKIP_FRONTEND:-}" ]]; then
    log "Frontend: dependencies"
    cd "$ROOT/frontend"
    npm ci --no-audit --no-fund

    log "Frontend: build"
    npm run build

    log "Frontend: process (pm2: sharm-web on :${WEB_PORT})"
    # A stray (non-PM2) server on the web port would keep serving the old build
    # from memory and 500 on every chunk that this build just deleted.
    if ! pm2 describe sharm-web >/dev/null 2>&1 && ss -ltn "sport = :${WEB_PORT}" | grep -q LISTEN; then
        echo "Port ${WEB_PORT} is held by a non-PM2 process; stop it first." >&2
        exit 1
    fi
    if pm2 describe sharm-web >/dev/null 2>&1; then
        pm2 restart sharm-web --update-env
    else
        pm2 start npm --name sharm-web -- run start -- -p "$WEB_PORT" -H 127.0.0.1
    fi
fi

pm2 save >/dev/null

# ---------------------------------------------------------------- sanity checks
# nginx and Laravel must both target the PM2 web port, or the site serves a stale build.
for f in /etc/nginx/conf.d/*.conf /etc/nginx/sites-enabled/*; do
    [[ -f "$f" ]] || continue
    if grep -E 'upstream +sharm_next' "$f" | grep -qv ":${WEB_PORT}"; then
        echo "WARNING: nginx upstream sharm_next in $f does not point at :${WEB_PORT}" >&2
    fi
done
if grep -q '^FRONTEND_REVALIDATE_URL=' "$ROOT/backend/.env" \
   && ! grep -q "^FRONTEND_REVALIDATE_URL=http://127.0.0.1:${WEB_PORT}/" "$ROOT/backend/.env"; then
    echo "WARNING: backend/.env FRONTEND_REVALIDATE_URL does not point at :${WEB_PORT}" >&2
fi
log "Done"
pm2 ls
