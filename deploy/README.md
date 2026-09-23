# Deploy (VPS + Cloudflare Tunnel)

Toàn bộ Signage Hub chạy trên Dell Ubuntu 24 qua tunnel **`homelab`**. Không dùng Vercel. Không chạy thêm `cloudflared`.

| Public | Nội bộ VPS |
|---|---|
| `https://signagehub.online` | CMS Next.js `127.0.0.1:9082` |
| `https://www.signagehub.online` | CMS `127.0.0.1:9082` |
| `https://app.signagehub.online` | TV player `127.0.0.1:9083` |
| `https://api.signagehub.online` | Laravel `127.0.0.1:9080` |
| `wss://ws.signagehub.online` | Reverb `127.0.0.1:9081` |

`api.onthilaixe.online` / `ws.onthilaixe.online` vẫn trỏ cùng cổng 9080/9081. Cổng 80 / 3000 / 8000 / 8080 giữ cho Asiagiving.

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

Cập nhật sau này: **push `main` là đủ**. GitHub Actions runner trên VPS (`self-hosted`) chạy `deploy/pull-and-up.sh`. Cron mỗi phút (`deploy/watch-repos.sh`) kéo backend + CMS + player nếu runner tắt.

```bash
cd ~/hotel-signage-hub-backend
git pull
cd deploy
docker compose up -d --build
```

## Tunnel `homelab`

`signagehub.online` phải nằm **cùng tài khoản Cloudflare** với tunnel `homelab` (cùng account với `asiagiving.org` / `onthilaixe.online`). CNAME ngoài account đó không đi vào tunnel.

DNS trong zone `signagehub.online` (proxied, CNAME flattening cho apex):

| Name | Type | Target |
|---|---|---|
| `@` | CNAME | `cfa2cf84-48fd-4eee-88b0-d62f468cd69f.cfargotunnel.com` |
| `www` | CNAME | `cfa2cf84-48fd-4eee-88b0-d62f468cd69f.cfargotunnel.com` |
| `app` | CNAME | `cfa2cf84-48fd-4eee-88b0-d62f468cd69f.cfargotunnel.com` |
| `api` | CNAME | `cfa2cf84-48fd-4eee-88b0-d62f468cd69f.cfargotunnel.com` |
| `ws` | CNAME | `cfa2cf84-48fd-4eee-88b0-d62f468cd69f.cfargotunnel.com` |

Xóa A `@` → `76.76.21.21` (Vercel). Không A về IP WAN nhà.

Ingress (cần sudo trên VPS):

```bash
~/hotel-signage-hub-backend/deploy/apply-tunnel.sh
```

Không sửa hostname Asiagiving.

## CMS và player trên cùng VPS

```bash
# CMS
cd ~/hotel-signage-hub-cms/deploy
docker compose up -d --build

# Player — VITE_REVERB_APP_KEY lấy từ backend deploy/.env
cd ~/hotel-signage-hub-player/deploy
docker compose up -d --build
```

Production: CMS gọi `/cms` cùng origin (Next rewrite → `:9080`). Player gọi `/api` cùng origin (nginx proxy → `:9080`). Reverb bake lúc build: `ws.signagehub.online:443`.

## Kiểm tra

```bash
curl -sS http://127.0.0.1:9080/up
curl -sS http://127.0.0.1:9082/ | head
curl -sS http://127.0.0.1:9083/ | head
curl -sSI https://signagehub.online
curl -sSI https://api.signagehub.online/up
```

Seed demo: `docker compose exec app php artisan db:seed --force`

## Xem database (Adminer)

Chỉ mở qua Tailscale, không public:

`http://100.125.150.56:9084`

- System: `MySQL`
- Server: `mysql`
- Username / password / database: `DB_USERNAME`, `DB_PASSWORD`, `DB_DATABASE` trong `~/hotel-signage-hub-backend/deploy/.env`

Bảng thanh toán: `billing_orders`, `billing_transactions`. Hạn gói: `hotels.plan`, `hotels.device_limit`, `hotels.subscription_expires_at`.

## Cloudflare R2 + Redis

Logo, nền khách sạn / phòng / TV, và ảnh mẫu chào upload qua API rồi ghi **Cloudflare R2** (`MEDIA_DISK=r2`). Điền token R2 vào `deploy/.env`:

| Biến | Ý nghĩa |
|---|---|
| `R2_ACCESS_KEY_ID` / `R2_SECRET_ACCESS_KEY` | API token Object Read & Write |
| `R2_BUCKET` | Tên bucket |
| `R2_ENDPOINT` | `https://<ACCOUNT_ID>.r2.cloudflarestorage.com` |
| `R2_URL` | URL public, ví dụ `https://media.signagehub.online` hoặc `https://pub-….r2.dev` |

Bật public access (custom domain hoặc r2.dev) và CORS GET/HEAD cho `signagehub.online`, `app.signagehub.online`. Không set ACL `public-read` — R2 không dùng ACL như S3.

Redis (container `redis`) giữ:

- session CMS, queue, rate limit
- heartbeat TV (`device:{id}:hb`)
- payload màn hình theo revision (`screen:…`)
- nhiệt độ Open-Meteo 15 phút (`weather:now:{region}`)
- trạng thái ghép PIN (`pairing:{code}`)

Webhook Stripe (tuỳ chọn, poll session vẫn xác nhận thanh toán):

`https://api.signagehub.online/api/cms/billing/stripe/webhook`

Điền `STRIPE_WEBHOOK_SECRET` vào `deploy/.env` rồi `docker compose up -d app queue scheduler`.

## Ghi chú

- `deploy/.env` không commit. `./setup.sh` tự tạo mật khẩu MySQL / key Reverb / `APP_KEY`.
- Không mở 9080/9081 ra LAN. Chỉ Tunnel và Tailscale SSH.
- Mail mặc định `log`. Gửi mail thật thì sửa `MAIL_*` sau.
