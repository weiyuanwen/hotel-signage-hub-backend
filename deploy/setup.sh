#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "$0")" && pwd)"
cd "$ROOT"

if ! command -v docker >/dev/null; then
    echo "Cần Docker. Cài: https://docs.docker.com/engine/install/ubuntu/" >&2
    exit 1
fi

if ! docker compose version >/dev/null 2>&1; then
    echo "Cần Docker Compose plugin (docker compose)." >&2
    exit 1
fi

if [ ! -f .env ]; then
    cp .env.example .env
    echo "Đã tạo deploy/.env từ .env.example"
fi

rand() {
    openssl rand -hex 16
}

if grep -q '^DB_PASSWORD=change-me$' .env; then
    sed -i.bak "s/^DB_PASSWORD=change-me$/DB_PASSWORD=$(rand)/" .env
fi
if grep -q '^MYSQL_ROOT_PASSWORD=change-me-root$' .env; then
    sed -i.bak "s/^MYSQL_ROOT_PASSWORD=change-me-root$/MYSQL_ROOT_PASSWORD=$(rand)/" .env
fi
if grep -q '^REVERB_APP_KEY=change-me-key$' .env; then
    sed -i.bak "s/^REVERB_APP_KEY=change-me-key$/REVERB_APP_KEY=$(rand)/" .env
fi
if grep -q '^REVERB_APP_SECRET=change-me-secret$' .env; then
    sed -i.bak "s/^REVERB_APP_SECRET=change-me-secret$/REVERB_APP_SECRET=$(rand)/" .env
fi
rm -f .env.bak

if grep -q '^APP_KEY=$' .env; then
    echo "Tạo APP_KEY..."
    KEY="base64:$(openssl rand -base64 32 | tr -d '\n')"
    sed -i.bak "s|^APP_KEY=$|APP_KEY=${KEY}|" .env
    rm -f .env.bak
fi

echo "Build và chạy stack (API :9080, Reverb :9081, chỉ 127.0.0.1)..."
docker compose up -d --build

echo
echo "API nội bộ:    http://127.0.0.1:9080/up"
echo "Reverb nội bộ: http://127.0.0.1:9081"
echo
echo "Tiếp theo:"
echo "  1. Cloudflare Zero Trust → tunnel homelab → thêm hostname (xem cloudflared.ingress.yml)"
echo "     hoặc sửa /etc/cloudflared/config.yml rồi: sudo systemctl restart cloudflared"
echo "  2. Vercel CMS:  NEXT_PUBLIC_API_URL=https://api.onthilaixe.online/api"
echo "  3. Vercel TV:   VITE_API_URL=https://api.onthilaixe.online/api"
echo "                 VITE_REVERB_HOST=ws.onthilaixe.online"
echo "                 VITE_REVERB_PORT=443"
echo "                 VITE_REVERB_SCHEME=https"
echo "                 VITE_REVERB_APP_KEY=<REVERB_APP_KEY trong deploy/.env>"
echo
echo "Seed demo (tuỳ chọn): docker compose exec app php artisan db:seed --force"
