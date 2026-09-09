# Hotel Signage Hub Backend — Design Spec

Ngày: 2026-09-09  
Repo: `hotel-signage-hub-backend` (API-only, Laravel 13)

## Mục tiêu

CMS frontend (repo riêng) và TV player gọi REST. Backend cô lập tenant, quản lý stay/welcome, pairing PIN, heartbeat Redis/Cache, broadcast Reverb. Toàn bộ invariant được khóa bằng PHPUnit.

## Quyết định đã chốt

- API-only; không Filament/Inertia trong repo này.
- Welcome nhập tay; schema có `source` + `external_ref` (PMS sau).
- Lễ tân 1 hotel; manager nhiều hotel (`hotel_user`); super-admin không thuộc hotel.
- Stay history: một stay / lần check-in; `rooms.current_welcome_id` (không FK vòng); unique `(room_id, is_current)` với `is_current` nullable.
- Phòng trống: branding hotel, `guest: null`.
- Pairing: TV hiện PIN 6 ký tự alphanumeric (không `0O1I`), TTL 90s; CMS claim + chọn phòng.
- Reverb: `private-room.{hotel_id}.{room_id}` nội dung; `private-device.{device_id}` điều khiển.
- Modular monolith, single MySQL, `hotel_id` trên bảng tenant.
- Hardening: không hard-delete stay; sửa tên update tại chỗ; nhiều TV / phòng.
- `rooms.kind` = `guest|public`; `rooms.content_revision` + `ETag` / `If-None-Match`.
- Một field `guest_display_name`.

## Domain

`Identity`, `Hotel`, `Content`, `Device`, `Realtime`. Controller mỏng, service giữ transaction.

## Auth

Sanctum token. User: abilities `cms`. Device: abilities `device`. Middleware `auth.cms` / `auth.device` từ chối nhầm loại. `EnsureHotelScope` trên `/api/cms/hotels/{hotel}/*`.

Roles (Spatie, guard `web`): `super-admin`, `hotel-manager`, `receptionist`.

## API chính

- `POST /api/cms/login`
- `GET /api/cms/hotels` — theo membership
- `POST /api/cms/hotels` — super-admin
- `GET|POST /api/cms/hotels/{hotel}/rooms`
- `POST .../rooms/{room}/check-in|checkout`
- `PATCH .../rooms/{room}/welcome`
- `POST .../pairing-codes/claim`
- `POST /api/device/pairing-codes` + `GET .../{code}`
- `GET /api/device/screen`
- `POST /api/device/heartbeat` — chỉ Cache TTL 90s, không ghi `last_seen_at`

## Heartbeat

Key `device:{id}:hb`. Job flush MySQL (sau). Dashboard đọc Cache `online`.

## Ngoài MVP

Activity log, PMS connector, pre-provision serial, playlist/template version.
