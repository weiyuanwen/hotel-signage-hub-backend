# Deploy backend (VPS + Cloudflare Tunnel)

CMS và player ở Vercel. Folder này chỉ chạy **Laravel + MySQL + Redis + Reverb** trên Dell Ubuntu 24. Tunnel **`homelab`** đã có trên máy — không chạy thêm `cloudflared`.

| Public | Nội bộ VPS |
|---|---|
| `https://api.onthilaixe.online` | `127.0.0.1:9080` |
| `wss://ws.onthilaixe.online` | `127.0.0.1:9081` |

Cổng 80 / 3000 / 8000 / 8080 giữ cho Asiagiving.

## Một lần trên VPS

```bash
sudo apt-get update
# Docker đã có trên edward-server thì bỏ bước cài.

cd ~
git clone https://github.com/weiyuanwen/hotel-signage-hub-backend.git
cd hotel-signage-hub-backend/deploy
chmod +x setup.sh
./setup.sh
```

Cập nhật sau này:

```bash
cd ~/hotel-signage-hub-backend
git pull
cd deploy
docker compose up -d --build
```

## Tunnel `homelab`

Cách A — dashboard Cloudflare Zero Trust → Networks → Tunnels → **homelab** → Public hostname:

- `api.onthilaixe.online` → `http://localhost:9080`
- `ws.onthilaixe.online` → `http://localhost:9081`

Cách B — sửa file (cần sudo). Chèn nội dung `cloudflared.ingress.yml` **trước** `http_status:404` trong `/etc/cloudflared/config.yml`, rồi:

```bash
sudo cloudflared tunnel ingress validate
sudo systemctl restart cloudflared
```

Không sửa hostname Asiagiving.

## Vercel

**CMS** (`hotel-signage-hub-cms`):

```
NEXT_PUBLIC_API_URL=https://api.onthilaixe.online/api
```

**Player** (`hotel-signage-hub-player`):

```
VITE_API_URL=https://api.onthilaixe.online/api
VITE_REVERB_HOST=ws.onthilaixe.online
VITE_REVERB_PORT=443
VITE_REVERB_SCHEME=https
VITE_REVERB_APP_KEY=<cùng REVERB_APP_KEY trong deploy/.env>
```

Deploy lại player sau khi set env (Vite bake lúc build).

## Kiểm tra

```bash
curl -sS http://127.0.0.1:9080/up
curl -sSI https://api.onthilaixe.online/up
```

Seed demo: `docker compose exec app php artisan db:seed --force`

## Ghi chú

- `deploy/.env` không commit. `./setup.sh` tự tạo mật khẩu MySQL / key Reverb / `APP_KEY`.
- Không mở 9080/9081 ra LAN. Chỉ Tunnel và Tailscale SSH.
- Mail mặc định `log`. Gửi mail thật thì sửa `MAIL_*` sau.
