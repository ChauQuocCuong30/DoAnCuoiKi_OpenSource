# Hợp đồng API dự kiến

Prefix /api/v1, JSON UTF-8. Thành công: {"data": ...}; lỗi: {"error": {"code": "...", "message": "..."}}.
Các API nghiệp vụ chưa triển khai. Chỉ có GET /api/v1/health, response database=not_configured.
Trước khi code mỗi endpoint cần ghi: method, path, quyền, request, response, mã lỗi và chống gửi trùng nếu cần.
Web/desktop gọi cùng API; không kết nối MySQL trực tiếp từ desktop.
Không đưa mật khẩu/token vào log. Backend kiểm tra quyền trên từng tài nguyên.
