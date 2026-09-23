#!/usr/bin/env bash
set -euo pipefail

# Cron on the VPS: pull each SignageHub repo when origin/main moves.
# * * * * * /home/edward/hotel-signage-hub-backend/deploy/watch-repos.sh >> /home/edward/logs/signagehub-deploy.log 2>&1

HOME_DIR="${HOME}"
LOCK="${SIGNAGEHUB_DEPLOY_LOCK:-/tmp/signagehub-deploy.lock}"
OWNER="weiyuanwen"
umask 022

exec 9>"$LOCK"
flock 9

clone_or_update() {
    local name="$1"
    local dir="$HOME_DIR/$name"
    local url="https://github.com/${OWNER}/${name}.git"

    if [ ! -d "$dir/.git" ]; then
        echo "$(date -Is) clone $name"
        git clone --branch main "$url" "$dir"
        return 0
    fi

    git -C "$dir" fetch origin main
    local local_sha remote_sha
    local_sha="$(git -C "$dir" rev-parse HEAD)"
    remote_sha="$(git -C "$dir" rev-parse origin/main)"
    if [ "$local_sha" = "$remote_sha" ]; then
        return 1
    fi

    echo "$(date -Is) $name $local_sha -> $remote_sha"
    git -C "$dir" checkout -q main
    git -C "$dir" reset --hard origin/main
    chmod -R a+rX "$dir"
    return 0
}

ensure_player_env() {
    local env_file="$HOME_DIR/hotel-signage-hub-player/deploy/.env"
    local backend_env="$HOME_DIR/hotel-signage-hub-backend/deploy/.env"
    if [ -f "$env_file" ]; then
        return
    fi
    local key=""
    if [ -f "$backend_env" ]; then
        key="$(grep -E '^REVERB_APP_KEY=' "$backend_env" | head -1 | cut -d= -f2- || true)"
    fi
    cat > "$env_file" <<EOF
VITE_REVERB_APP_KEY=${key}
VITE_REVERB_HOST=ws.signagehub.online
VITE_REVERB_PORT=443
VITE_REVERB_SCHEME=https
EOF
}

changed=0
if clone_or_update hotel-signage-hub-backend; then
    (cd "$HOME_DIR/hotel-signage-hub-backend/deploy" && docker compose up -d --build)
    changed=1
fi
if clone_or_update hotel-signage-hub-cms; then
    (cd "$HOME_DIR/hotel-signage-hub-cms/deploy" && docker compose up -d --build)
    changed=1
fi
if clone_or_update hotel-signage-hub-player; then
    ensure_player_env
    (cd "$HOME_DIR/hotel-signage-hub-player/deploy" && docker compose up -d --build)
    changed=1
fi

if [ "$changed" -eq 0 ]; then
    exit 0
fi
echo "$(date -Is) deploy done"
