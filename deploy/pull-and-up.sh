#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
LOCK="${SIGNAGEHUB_DEPLOY_LOCK:-/tmp/signagehub-deploy.lock}"
umask 022

exec 9>"$LOCK"
flock 9

cd "$ROOT"
git fetch origin main
git checkout -q main
git reset --hard origin/main
chmod -R a+rX .
cd deploy
docker compose up -d --build
echo "backend $(git -C "$ROOT" rev-parse --short HEAD) up"
