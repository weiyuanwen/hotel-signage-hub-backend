# Hotel Signage Hub - Backend & CMS

Hệ thống quản trị tập trung (CMS), Backend API và Realtime WebSocket Server phục vụ quản lý màn hình chào mừng / bảng hiệu kỹ thuật số (Digital Signage) đa khách sạn (Multi-tenant).

## Tính năng chính
- **Multi-tenancy Architecture:** Quản lý phân cấp Super Admin -> Khách sạn -> Phòng/Khu vực -> Màn hình TV.
- **Quản lý nội dung (CMS):** Cập nhật thông tin khách lưu trú, thông điệp chào mừng, playlist hình ảnh/video theo từng phòng hoặc toàn hệ thống.
- **Real-time Broadcasting:** Đồng bộ và đẩy nội dung cập nhật tức thì tới hàng nghìn thiết bị TV thông qua WebSocket (Laravel Reverb).
- **Device Pairing & Telemetry:** Cơ chế ghép nối thiết bị qua mã PIN ngắn hạn và theo dõi trạng thái online/offline (Heartbeat).

## Tech Stack
- **Framework:** Laravel 13 (PHP 8.4)
- **Database:** MySQL, Redis
- **Realtime:** Laravel Reverb
- **Auth & Permissions:** Laravel Sanctum & Spatie Permission
