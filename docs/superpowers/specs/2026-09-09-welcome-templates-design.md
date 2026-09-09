# Welcome templates — Design Spec

Ngày: 2026-09-09  
Phạm vi: `hotel-signage-hub-backend`, `hotel-signage-hub-cms`, `hotel-signage-hub-player`  
Spec backend gốc (`2026-09-09-hotel-signage-hub-backend-design.md`) để “playlist/template version” ngoài MVP. Spec này **mở template** (không mở playlist).

## Mục tiêu

TV phòng có khách chiếu một trong **năm mẫu chào** (layout + màu + chuyển động cố định trong player). Lễ tân chọn mẫu lúc Nhận phòng (và đổi được sau đó). Quản lý khách sạn bật/tắt, đặt mặc định, đổi tên gọi trên CMS. Phòng trống không dùng template.

Thành công: nhận phòng thêm đúng một thao tác (bấm giữ hoặc đổi thumbnail); TV đổi mẫu mượt, chữ đọc được từ ~3 m; không editor màu/layout.

## Quyết định đã chốt

- Năm mẫu preset. Không tạo mẫu mới, không chỉnh màu/layout theo khách sạn.
- Quản lý template: `hotel-manager` và `super-admin`. Lễ tân chỉ chọn lúc nhận phòng / đổi tên.
- Sau nhận phòng, lễ tân đổi template trong dialog Đổi tên; TV cập nhật realtime.
- Phòng trống: branding khách sạn như hiện tại (`guest: null`, `template: null`).
- Cài đặt (bật/tắt, mặc định, tên gọi) **theo khách sạn**. Năm layout trên TV dùng chung.
- Dialog nhận phòng/đổi tên **chọn sẵn** mẫu mặc định của khách sạn (hoặc mẫu đang chạy khi đổi tên).
- Kiến trúc: **key trên stay + component trong player**. Backend không lưu CSS.

## Ngoài scope

Playlist, versioning template, kéo-thả layout, upload ảnh vào mẫu đang có khách, template mặc định theo phòng, preview CMS bắn ra TV thật, gói npm dùng chung CMS/player.

## Catalog (nguồn sự thật)

Năm key bất biến, thứ tự cố định. Tên built-in tiếng Việt; khách sạn có thể override `display_name` (CMS only — player không hiện tên mẫu).

| `sort_order` | `template_key` | Tên built-in | Mặc định KS mới |
|---:|---|---|---|
| 1 | `dusk` | Đêm vàng | Có |
| 2 | `linen` | Sáng nhẹ | |
| 3 | `harbor` | Cảng đêm | |
| 4 | `garden` | Vườn trà | |
| 5 | `stone` | Đá ấm | |

Catalog sống trong code backend (`WelcomeTemplateKey` enum + `builtInLabel()`). Không bảng `welcome_templates` toàn cục.

## Năm mẫu trên TV

Đọc từ cuối giường, 16:9. Font Geist (đã dùng). Không icon trang trí, không vòng lặp animation, không video. Tên khách `clamp(2.75rem, 8vw, 6.5rem)`, `leading ~1.05`, `tracking -0.03em`. Thông điệp tối đa ~2 dòng cảm giác (`max-w-[36ch]`, `text-xl`–`2xl`). Mã phòng chữ nhỏ, tracking rộng.

**Màn có khách bỏ `media.background_url`.** Năm look phải ổn định; ảnh KS chỉ dùng màn trống.

**Chuyển động chung:** vào cảnh 420–600ms, `opacity` + translate ngắn (12–20px), `cubic-bezier(0.22, 1, 0.36, 1)`. Đổi `template_key`: crossfade 280ms. Đổi tên cùng mẫu: opacity 200ms (như player hiện tại). `prefers-reduced-motion: reduce` → không translate, opacity 150ms.

Banner “Đã ghép với phòng …” nằm ngoài mẫu, không đổi.

### 1. `dusk` — Đêm vàng

Cảnh đêm boutique, gần DESIGN.md player hiện tại. Nền gần đen chroma 0; tên khách olive-gold; bề mặt yên.

```css
--bg: oklch(0.10 0 0);
--ink: oklch(0.94 0.012 110);
--muted: oklch(0.68 0.02 110);
--name: oklch(0.78 0.11 110);
--accent: oklch(0.62 0.07 230);
```

Bố cục editorial trái: logo + tên KS trên cùng trái; tên khách + thông điệp khối giữa trái (`max-w-[18ch]` tên); mã phòng dưới cùng trái, tracking `0.18em`, `--accent`. Motion: tên trượt lên 12px + fade.

### 2. `linen` — Sáng nhẹ

Cảnh sáng / resort. Giấy ấm, chữ mực, điểm nhấn terracotta nhẹ — không trắng phòng mổ.

```css
--bg: oklch(0.97 0.012 85);
--ink: oklch(0.28 0.035 55);
--muted: oklch(0.48 0.02 55);
--name: oklch(0.38 0.08 45);
--rule: oklch(0.82 0.03 75);
```

Bố cục giữa, nghi lễ: logo nhỏ trên; kẻ ngang 48px; tên khách căn giữa; thông điệp dưới; mã phòng đáy, chữ thường, `--muted`. Motion: chỉ fade 400ms (không trượt — buổi sáng yên).

### 3. `harbor` — Cảng đêm

Khách sạn phố, spa-calm. Xanh đá đậm, chữ kem, nhấn aqua thấp chroma.

```css
--bg: oklch(0.16 0.028 230);
--panel: oklch(0.12 0.032 230);
--ink: oklch(0.93 0.015 95);
--muted: oklch(0.72 0.03 220);
--name: oklch(0.88 0.04 95);
--accent: oklch(0.70 0.06 200);
```

Split: cột trái ~42% `--panel` chứa tên (stack); phải thông điệp + mã phòng `--accent`. Motion: panel từ trái 16px, tên fade.

### 4. `garden` — Vườn trà

Boutique vườn. Nền trà sữa-xanh, tên rêu đậm, thông điệp nâu ấm.

```css
--bg: oklch(0.93 0.022 140);
--ink: oklch(0.32 0.04 55);
--muted: oklch(0.45 0.03 145);
--name: oklch(0.34 0.07 145);
```

Nền gradient dọc rất nhẹ (`--bg` → `oklch(0.90 0.028 150)`). Tên lớn trên-trái; thông điệp dưới-phải; mã phòng trên-phải. Nhiều khoảng trống. Motion: tên fade, thông điệp delay 120ms.

### 5. `stone` — Đá ấm

Luxury tối giản. Đen ấm (hue 55), tên ngà — không gold la liệt.

```css
--bg: oklch(0.11 0.012 55);
--band: oklch(0.15 0.016 55);
--ink: oklch(0.93 0.02 80);
--muted: oklch(0.66 0.02 55);
--name: oklch(0.91 0.025 85);
--accent: oklch(0.72 0.08 75);
```

Hạ-tam: ~32% chiều cao dưới là `--band`; tên + thông điệp trong band; logo nhỏ trên-trái trên vùng trống; mã phòng `--accent` trong band, phải. Motion: band trượt lên 20px, 600ms.

## Kiến trúc dữ liệu

### `welcome_contents.template_key`

`string(32)`, NOT NULL sau backfill. Stay đang mở và lịch sử đều có key. Check-in mới: body `template_key` hoặc fallback `hotels.default_welcome_template_key`.

### `hotels.default_welcome_template_key`

`string(32)`, NOT NULL, default `'dusk'`. Phải là key trong catalog và **đang bật** tại khách sạn đó.

### `hotel_welcome_templates`

| Cột | Quy tắc |
|---|---|
| `hotel_id` | FK |
| `template_key` | một trong 5 key |
| `is_enabled` | bool, default true |
| `display_name` | nullable, max 40; null → tên built-in |
| `sort_order` | 1–5, copy catalog |
| unique | `(hotel_id, template_key)` |

Không FK tới bảng catalog (catalog là enum).

### Đồng bộ catalog

`WelcomeTemplateCatalog::syncHotel(Hotel $hotel)`: với mỗi key thiếu, insert `is_enabled=true`, `display_name=null`, `sort_order` catalog. Không xóa row thừa (không có key thừa ở v1). Không tự bật lại mẫu quản lý đã tắt.

Gọi khi: tạo khách sạn, GET/PATCH templates, check-in (phòng hờ hotel factory/test thiếu row). Migration backfill mọi hotel hiện có + `default_welcome_template_key = dusk`.

Hotel factory: `afterCreating` gọi `syncHotel` để test không phụ thuộc thứ tự.

## Phân quyền

Permission mới: `templates.manage`. Gán `super-admin`, `hotel-manager`. Không gán `receptionist`. Cập nhật `RoleSeeder` (và mọi test dựa trên seeder). Không migration permission riêng — Spatie seed như các permission hiện có.

| Hành động | Permission |
|---|---|
| GET danh sách mẫu (để nhận phòng) | `rooms.view` |
| PATCH bật/tắt, tên, mặc định | `templates.manage` |
| Check-in / PATCH welcome có `template_key` | `stays.manage` (như hiện tại) |

GET: lễ tân chỉ nhận mẫu `is_enabled=true` + `default_key`. Manager/super-admin nhận đủ 5 (kể cả tắt) + `default_key`.

## API

Mọi route dưới `/api/cms/hotels/{hotel}`, middleware `hotel.scope` như cũ. Khai báo `PATCH .../welcome-templates/default` **trước** `PATCH .../welcome-templates/{template}` để `default` không bị nuốt thành key.

### `GET /welcome-templates`

```json
{
  "data": {
    "default_key": "dusk",
    "templates": [
      {
        "key": "dusk",
        "built_in_name": "Đêm vàng",
        "display_name": null,
        "label": "Đêm vàng",
        "is_enabled": true,
        "sort_order": 1
      }
    ]
  }
}
```

`label` = `display_name` nếu có, không thì `built_in_name`. Receptionist: mảng chỉ enabled.

### `PATCH /welcome-templates/{template}`

`{template}` là key. Body (các field optional): `{ "is_enabled": false, "display_name": "Tối boutique" }`. `display_name` chuỗi rỗng → `null`. 403 nếu không `templates.manage`.

### `PATCH /welcome-templates/default`

Body: `{ "key": "linen" }`. Key phải enabled. 422 nếu tắt hoặc không thuộc catalog.

Không có POST/DELETE mẫu.

### Check-in / cập nhật stay

`POST .../rooms/{room}/check-in` thêm `template_key` optional, `in:dusk,linen,harbor,garden,stone`.

- Thiếu → default KS (sau `syncHotel`).
- Có nhưng disabled → 422 `template_key`.
- Response stay gồm `template_key`.

`PATCH .../rooms/{room}/welcome` thêm `template_key` optional, cùng rule. Đổi tên không gửi key → giữ key cũ.

Ngoại lệ Đổi tên: nếu mẫu đang chạy vừa bị tắt, PATCH **được giữ nguyên key đó**; chỉ 422 khi gửi key **khác** mà đang tắt. CMS union danh sách enabled với `current_welcome.template_key` (đã có trên GET rooms) để ô đang chạy không biến mất. Token/thumbnail của đủ 5 key nằm sẵn trong CMS; API lễ tân không cần trả mẫu tắt.

### Screen (device)

`ScreenDataBuilder` thêm:

```json
"template": { "key": "dusk" }
```

hoặc `"template": null` khi `guest` null.

Checkout / phòng trống: `guest` null và `template` null. Broadcast `RoomContentUpdated` payload = screen data (đã có); chỉ cần builder trả thêm field.

Player key lạ hoặc thiếu khi đang có guest → render `dusk` (không màn trắng).

## Luồng

1. Lễ tân mở Nhận phòng → CMS GET templates → chọn sẵn `default_key` → lưu POST check-in kèm `template_key`.
2. StayService ghi key, bump revision, broadcast.
3. TV `GET /device/screen` hoặc event kênh phòng → `WelcomeScreen` nếu `guest` thì `switch (template.key)`.
4. Đổi mẫu: PATCH welcome `{ template_key }` → cùng broadcast.
5. Trả phòng: current stay đóng → TV vacant, không template.
6. Manager `/templates`: GET đủ 5, PATCH từng dòng / default. Không ảnh hưởng stay đang mở, trừ khi lễ tân đổi sau.

**Tắt mẫu đang chiếu:** TV giữ key đến khi đổi hoặc trả phòng. Check-in mới không chọn được mẫu đó.

**Tắt mẫu đang là default:** 422. CMS không cho tắt default — đổi default sang mẫu khác đang bật trước. Không cho tắt mẫu **cuối cùng** còn bật.

## CMS

### Điều hướng

AppShell thêm “Mẫu chào” (`/templates`), icon Phosphor `FrameCorners` weight regular. Chỉ hiện nếu user có `templates.manage` (cùng kiểu Nhân viên: ẩn với lễ tân, không 403 sau khi vào).

### Trang `/templates`

Một cột, đúng desk: không card lồng card.

- Tiêu đề “Mẫu chào”, một câu: “Chọn mẫu mặc định và mẫu lễ tân được dùng lúc nhận phòng.”
- Năm hàng, thứ tự `sort_order`. Mỗi hàng: thumbnail 16:9 (~240px rộng), `label`, input tên gọi, toggle bật, nút/radio “Mặc định”.
- Thumbnail: khung CSS cùng token với player, tên giả “Nguyễn Văn A”, thông điệp “Chào mừng quý khách”, mã “101”. Không iframe player, không screenshot PNG.
- Mặc định: đúng một mẫu. Nút “Mặc định” trên hàng đang tắt thì disabled — bật mẫu trước. API 422 nếu `default_key` trỏ mẫu tắt.
- Lưu tên: blur hoặc “Lưu” theo từng hàng (PATCH). Không form 12 field.
- `rooms.kind` `guest` hay `public`: cùng rule (template chỉ khi đang có stay).

### Dialog Nhận phòng / Đổi tên

Giữ tên + thông điệp. Thêm hàng thumbnail (wrap, ~5 ô). Ô đang chọn: viền `--primary` 2px. Đổi tên: preselect `current_welcome.template_key`. Nhận phòng: preselect `default_key`. Bắt buộc có một ô được chọn (đã preselect nên luôn có).

Submit gửi `template_key`. Không thêm locale UI (vẫn `vi` như hiện tại).

### Danh sách phòng

Dưới tên khách, một dòng muted: `label` của template (map từ GET templates hoặc đính `template_key` + `label` trên `current_welcome`). Phòng trống: không dòng này.

`GET /rooms` → `current_welcome` thêm `template_key`. Backend không trả label. CMS: map built-in 5 key; overlay `display_name` từ GET templates khi có. Mẫu tắt (lễ tân không thấy trong GET templates) vẫn hiện tên built-in trên hàng phòng.

## Player

- `VacantWelcome`: logic màn trống hiện tại (logo, tên KS, “Chào mừng quý khách”, optional `background_url`).
- `OccupiedWelcome`: switch 5 component `DuskWelcome` … `StoneWelcome` (cùng props: `hotel`, `room`, `guest`).
- `WelcomeScreen`: `guest ? Occupied : Vacant`; banner ghép TV giữ nguyên.
- Token CSS trên wrapper từng mẫu (`data-template="dusk"`). Không thêm Framer Motion; CSS transition + `@starting-style` hoặc class `is-in` sau rAF.
- Đổi key: unmount mẫu cũ / mount mẫu mới, crossfade wrapper.

## Lỗi

| Tình huống | Hành vi |
|---|---|
| `template_key` không thuộc catalog | 422 |
| Check-in key đang tắt | 422 |
| PATCH welcome sang key tắt (khác key hiện tại) | 422 |
| Tắt mẫu cuối | 422 |
| Tắt mẫu đang default | 422 |
| Default = key tắt / lạ | 422 |
| Receptionist PATCH templates | 403 |
| Hotel chưa sync | `syncHotel` rồi xử lý |
| TV key lạ | `dusk` |
| Mất mạng CMS lúc đổi mẫu | toast/error hiện có; TV giữ revision cũ |

Copy CMS ngắn, tiếng Việt, không jargon validation Laravel.

## Kiểm thử

PHPUnit (bắt buộc):

- Tạo hotel → 5 row templates, default `dusk`.
- Check-in không gửi key → `dusk` (hoặc default đã đổi).
- Check-in gửi `linen` → lưu `linen`; screen JSON `template.key=linen`; event payload cùng key.
- Check-in key tắt → 422; không tạo stay.
- PATCH welcome đổi key → revision tăng, broadcast key mới.
- Checkout → screen `template` null, `guest` null.
- Receptionist GET: không thấy mẫu tắt; PATCH templates 403.
- Manager tắt `garden` (không phải default, không phải cuối) → 200; check-in `garden` 422.
- Stay đang `garden` khi tắt `garden` → screen vẫn `garden` đến checkout.
- Không tắt default; không tắt mẫu cuối.
- Không nhận phòng hotel khác (giữ test tenant).

CMS/player: không có test runner sẵn. Verify tay:

- Nhận phòng 101: thumbnail default, lưu, TV `dusk`.
- Đổi sang `linen` trong Đổi tên; TV crossfade.
- Trả phòng: branding, có `background_url` nếu KS có media.
- Lễ tân không thấy nav Mẫu chào.
- Manager đổi tên gọi, tắt `stone`, đặt default `harbor`; nhận phòng mới preselect Cảng đêm.
- `prefers-reduced-motion` trên player: không trượt.

## Ranh giới module

- **Backend Content:** catalog, `StayService` ghi key, `HotelWelcomeTemplate` model, sync, endpoints templates.
- **Backend Device:** `ScreenDataBuilder` field `template`.
- **CMS:** `/templates`, picker trong `DeskDialog`, types `template_key`.
- **Player:** 5 cảnh + vacant tách khỏi occupied.

Mỗi mẫu player: một file, props rõ, không đọc `background_url`. Catalog key chỉ xuất hiện ở enum backend, union type CMS/player, và bảng này — không string rải trong SQL seed ngoài 5 giá trị trên.
