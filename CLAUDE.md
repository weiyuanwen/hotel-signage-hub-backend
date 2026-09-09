Bạn là một Technical Lead và Software Architect cấp cao chuyên về Laravel, WebSockets và kiến trúc Multi-tenant. 

Tôi vừa khởi tạo repository `hotel-signage-hub-backend` và cần bạn lập kế hoạch kỹ thuật chi tiết từ A đến Z để phát triển toàn bộ hệ thống backend này.

### 1. THÔNG TIN DỰ ÁN
- Tên dự án: Hotel Signage Hub (Backend & CMS)
- Bản chất: Hệ thống quản trị tập trung (CMS), RESTful API và Realtime WebSocket Server phục vụ quản lý màn hình chào mừng / Digital Signage đa khách sạn.
- Quy mô dự kiến: ~100 khách sạn, ~20 phòng/khách sạn, ~1-2 màn hình/phòng (~3.000 thiết bị kết nối realtime đồng thời).

### 2. TECH STACK
- Framework: Laravel 13 (PHP 8.4+)
- Database: MySQL (Dữ liệu chính), Redis (Cache, Queues, Pub/Sub cho WebSocket)
- Realtime Engine: Laravel Reverb
- Authentication & Security: Laravel Sanctum (Cấp token cho User CMS & Device TV)
- Authorization: Spatie Laravel-Permission (Phân quyền theo Role/Permission)
- Storage: S3 Compatible / Local Storage cho Media (Ảnh/Video nền, logo)

### 3. CÁC TÍNH NĂNG CỐT LÕI
1. Multi-tenancy Architecture: Phân cấp chặt chẽ: Super Admin -> Khách sạn (Hotel/Tenant) -> Phòng/Khu vực (Room/Area) -> Thiết bị (Device/TV Player).
2. Quản lý nội dung (CMS): Lễ tân/Quản lý cập nhật thông tin khách lưu trú (Tên, thông điệp chào mừng, ngôn ngữ), cấu hình media, template theo từng phòng hoặc hàng loạt.
3. Device Pairing & Telemetry:
   - TV Play Backend ghi nhận trạng thái Online/Offline qua Redis.
4. Real-time Broadcasting (Laravel Reverb): Khi CMS cập nhật thông tin phòng, tự động bắn Event qua Private Channel (`private-room.{hotel_id}.{room_id}` hoặc `private-device.{device_id}`) để TV render lại tức thì.

---

### YÊU CẦU ĐẦU RA (VUI LÒNG TRÌNH BÀY CHI TIẾT THEO CÁC MỤC):

1. **Database Architecture & ERD Schema:**
   - Thiết kế chi tiết các bảng: `users`, `hotels`, `rooms`, `devices`, `device_pairing_codes`, `welcome_contents`, `media_assets`, `roles/permissions` (Spatie), v.v.
   - Định nghĩa khóa chính, khóa ngoại, kiểu dữ liệu và index tối ưu cho 3.000 thiết bị.
   - Chiến lược Multi-tenancy (Single Database vructure):**
   - Bố cục thư mục mã nguồn trong Laravel 13 (Controllers, Services, Events, Listeners, Channels, Policies, DTOs/Requests).

3. **Thiết kế RESTful API Specs:**
   - Danh sách Endpoints nhóm theo: Super Admin, Hotel Staff (CMS), và Device API (Pairing, Screen-data, Heartbeat).
   - Chi tiết Request / Response JSON mẫu cho các luồng quan trọng (Pairing, Lấy dữ liệu hiển thị, Đổi tên khách).

4. **Kiến trúc Realtime & Laravel Reverb:**
   - Thiết kế Channel Authorization (Private Channelsst Events (Payload, BroadcastOn, BroadcastAs).
   - Cơ chế xử lý Heartbeat hiệu năng cao bằng Redis (tránh ghi trực tiếp vào MySQL mỗi 30s).

5. **Kế hoạch Phân quyền & Bảo mật (Sanctum + Spatie):**
   - Danh sách Role (`super-admin`, `hotel-manager`, `receptionist`) và Permissions tương ứng.
   - Middleware kiểm tra Tenant Isolation (`EnsureHotelScope`) để tránh rò ren phân quyền riêng biệt giữa User CMS và Device Token.

6. **Roadmap Triển khai từng Sprint (Từ Sprint 0 đến Production):**
   - Phân chia các giai đoạn (Milestones): Setup & DB -> Auth & CRUD -> Pairing & Telemetry -> Realtime Reverb -> Tối ưu hóa & Scale.
