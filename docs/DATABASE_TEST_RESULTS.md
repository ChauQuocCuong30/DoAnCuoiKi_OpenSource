# Kết quả kiểm tra database v1.0

- Đích triển khai: MariaDB 10.4.32 (XAMPP của nhóm).
- Engine thực thi kiểm thử: MariaDB 10.11.14 trong môi trường cô lập. Chưa chạy trực tiếp trên MariaDB 10.4.32 của người dùng.
- Import một file: PASS, đủ 18 bảng.
- Import tách migration + seed: PASS, 18 bảng, 5 từ, 22 câu hỏi, 0 tài khoản.
- Các câu lệnh đều dùng tính năng đã có trên MariaDB 10.4.32; vẫn cần kiểm tra import trên máy nhóm trước khi coi là bản phát hành chính thức.

| Kiểm tra | Lỗi mong đợi | Kết quả |
|---|---|---|
| Trùng email | 1062 | PASS |
| HP vượt 3 | 4025 | PASS |
| HP âm | 1264 | PASS |
| Role không hợp lệ | 4025 | PASS |
| FK chủ đề không tồn tại | 1452 | PASS |
| Từ mục tiêu không thuộc bài | 1452 | PASS |
| JSON sai cú pháp | 4025 | PASS |
| JSON sai kiểu object | 4025 | PASS |
| Độ khó ngoài 1–5 | 4025 | PASS |
| Ngưỡng 100 với so sánh > | 4025 | PASS |
| Mode lesson thiếu bài | 4025 | PASS |
| Số đúng vượt tổng | 4025 | PASS |
| Kết thúc trước bắt đầu | 4025 | PASS |
| Nguồn sửa sai khác session | 1452 | PASS |
| Sửa sai thiếu nguồn | 4025 | PASS |
| Trùng vị trí câu | 1062 | PASS |
| Trùng request trả lời | 1062 | PASS |
| Trùng lần thử | 1062 | PASS |
| Trùng trạng thái người–từ–chiều | 1062 | PASS |
| EF dưới 1.3 | 4025 | PASS |
| Lịch ôn trước lúc học | 4025 | PASS |
| Quality ngoài 0–5 | 4025 | PASS |
| Trùng bằng chứng SM-2 | 1062 | PASS |
| XP bằng 0 | 4025 | PASS |
| Trùng thưởng XP | 1062 | PASS |
| Trùng ngày học | 1062 | PASS |
| Trùng nhiệm vụ/ngày | 1062 | PASS |
| Trùng nhắc học | 1062 | PASS |
| Chặn xóa từ đã tham chiếu | 1451 | PASS |
| Import initial lần hai báo trùng bảng, không xóa dữ liệu | 1050 | PASS |

## Giới hạn
- Kiểm thử này xác nhận import và các ràng buộc SQL, không kiểm thử API, UI, thuật toán SM-2, gửi thông báo hoặc xử lý đồng thời của ứng dụng chưa được viết.
- Các quyền sở hữu dữ liệu, trạng thái hoàn thành, chấm điểm và quy tắc thưởng cần PHP transaction như docs/DATABASE.md.
- Tài khoản QA chỉ được tạo trong database kiểm thử, không xuất trong seed hoặc initial.