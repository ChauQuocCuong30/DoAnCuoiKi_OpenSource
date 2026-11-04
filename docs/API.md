# Hợp đồng API dự kiến

Prefix /api/v1, JSON UTF-8. Thành công: {"data": ...}; lỗi: {"error": {"code": "...", "message": "..."}}.
Các API nghiệp vụ chưa triển khai. Chỉ có GET /api/v1/health, response database=not_configured.
Trước khi code mỗi endpoint cần ghi: method, path, quyền, request, response, mã lỗi và chống gửi trùng nếu cần.
Web/desktop gọi cùng API; không kết nối MySQL trực tiếp từ desktop.
Không đưa mật khẩu/token vào log. Backend kiểm tra quyền trên từng tài nguyên.

## Tài khoản và hồ sơ (Cuong)

Xác thực bằng phiên PHP: đăng nhập thành công backend gửi cookie phiên (HttpOnly, SameSite=Lax) và đổi mã phiên. Mật khẩu lưu bằng `password_hash`. Quyền: `public` (không cần đăng nhập), `user` (đã đăng nhập), `admin`. Backend kiểm tra `status` của tài khoản ở mỗi request. Desktop dùng chung cookie phiên; nếu cần token sẽ bổ sung hợp đồng riêng.

Đối tượng `user` trả về (không bao giờ có `password_hash`):

```json
{"id": 1, "name": "An", "email": "an@example.com", "role": "user", "status": "active", "current_hp": 3, "daily_goal_xp": 20, "streak_goal_days": 7, "timezone": "Asia/Ho_Chi_Minh"}
```

### POST /api/v1/auth/register
- Quyền: public. Không tự đăng nhập sau khi đăng ký; `role` luôn là `user`.
- Request: `{"name": "An", "email": "an@example.com", "password": "matkhau123"}`
- Ràng buộc: `name` 1-100 ký tự; `email` hợp lệ, tối đa 191 ký tự, không phân biệt hoa thường; `password` 8-72 ký tự.
- Thành công 201: `{"data": {user}}`
- Lỗi: 422 `VALIDATION_FAILED` (có `details` theo từng trường), 409 `EMAIL_ALREADY_EXISTS`.
- Chống gửi trùng: email là duy nhất; gửi lại cùng email trả 409, không tạo bản ghi mới.

### POST /api/v1/auth/login
- Quyền: public.
- Request: `{"email": "an@example.com", "password": "matkhau123"}`
- Thành công 200: `{"data": {user}}` kèm cookie phiên.
- Lỗi: 422 `VALIDATION_FAILED`; 401 `INVALID_CREDENTIALS` (cùng một thông báo cho sai email và sai mật khẩu); 403 `ACCOUNT_LOCKED`; 403 `ACCOUNT_DEACTIVATED`.

### POST /api/v1/auth/logout
- Quyền: user.
- Request: không có nội dung.
- Thành công 200: `{"data": {"logged_out": true}}`; phiên bị hủy.
- Lỗi: 401 `UNAUTHENTICATED`.

### GET /api/v1/users/me
- Quyền: user.
- Thành công 200: `{"data": {user}}`
- Lỗi: 401 `UNAUTHENTICATED`.

### PATCH /api/v1/users/me
- Quyền: user. Chỉ gửi các trường cần sửa; gửi lại cùng giá trị cho kết quả như nhau.
- Request: `{"name": "An", "daily_goal_xp": 30, "streak_goal_days": 10, "timezone": "Asia/Ho_Chi_Minh"}`
- Ràng buộc: `name` 1-100 ký tự; `daily_goal_xp` số nguyên từ 1 đến 1000; `streak_goal_days` số nguyên từ 1 đến 365; `timezone` là múi giờ hợp lệ.
- Không được gửi `email`, `role`, `status`, `password`, `current_hp`; gửi các trường này trả 422.
- Thành công 200: `{"data": {user}}`
- Lỗi: 401 `UNAUTHENTICATED`, 422 `VALIDATION_FAILED`.

### PATCH /api/v1/users/{id}/status
- Quyền: admin. Khóa hoặc mở khóa tài khoản.
- Request: `{"status": "locked"}` (giá trị `locked` hoặc `active`).
- Thành công 200: `{"data": {user}}`. Tài khoản bị khóa mất phiên hiện tại ở request kế tiếp.
- Lỗi: 401 `UNAUTHENTICATED`; 403 `FORBIDDEN` (không phải admin); 404 `USER_NOT_FOUND`; 422 `VALIDATION_FAILED` (gồm trường hợp admin tự khóa chính mình).

### Mã lỗi dùng chung
| HTTP | code | Ý nghĩa |
|---|---|---|
| 401 | `UNAUTHENTICATED` | Chưa đăng nhập |
| 401 | `INVALID_CREDENTIALS` | Sai email hoặc mật khẩu |
| 403 | `FORBIDDEN` | Không đủ quyền |
| 403 | `ACCOUNT_LOCKED` | Tài khoản bị khóa |
| 403 | `ACCOUNT_DEACTIVATED` | Tài khoản đã ngưng hoạt động |
| 404 | `NOT_FOUND` | Không có đường dẫn này |
| 404 | `USER_NOT_FOUND` | Không có người dùng này |
| 409 | `EMAIL_ALREADY_EXISTS` | Email đã được đăng ký |
| 422 | `VALIDATION_FAILED` | Dữ liệu gửi lên không hợp lệ |
| 500 | `INTERNAL_ERROR` | Lỗi máy chủ (không lộ chi tiết) |