# Hotel Signage Hub - Backend & CMS API

Hệ thống quản trị tập trung (REST API) và Realtime WebSocket Server phục vụ Digital Signage đa khách sạn.

## Tính năng

- Multi-tenancy: Super Admin → Hotel → Room/Area → Device
- CMS API: check-in / checkout / đổi tên khách, branding phòng trống
- Pairing PIN (TV hiện mã, lễ tân claim trên CMS)
- Heartbeat qua cache (không ghi MySQL mỗi nhịp)
- Reverb: `private-room.{hotel_id}.{room_id}` và `private-device.{device_id}`

## Tech stack

Laravel 13 (PHP 8.4+), MySQL, Redis, Reverb, Sanctum, Spatie Permission

## Setup

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan db:seed
php artisan test
```

Spec: `docs/superpowers/specs/2026-09-09-hotel-signage-hub-backend-design.md`
