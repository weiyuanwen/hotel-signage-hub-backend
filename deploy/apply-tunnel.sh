#!/usr/bin/env bash
set -euo pipefail
# Cần sudo một lần để cloudflared nhận hostname signagehub.online.
SRC="$(cd "$(dirname "$0")" && pwd)/cloudflared.config.yml"
sudo cp "$SRC" /etc/cloudflared/config.yml
sudo cloudflared tunnel --config /etc/cloudflared/config.yml ingress validate
sudo systemctl restart cloudflared
echo "Đã áp ingress tunnel. Thử: curl -sSI https://signagehub.online"
