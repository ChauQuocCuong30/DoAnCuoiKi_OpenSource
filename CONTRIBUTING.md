# Quy trình đóng góp

1. Nhận Issue có yêu cầu và tiêu chí nghiệm thu.
2. git switch main rồi git pull --ff-only origin main.
3. git switch -c feature/ISSUE-mo-ta (hoặc fix/ISSUE-mo-ta).
4. Chỉ đặt file đúng mục trong docs/STRUCTURE.md; không có thư mục ten-thanh-vien, code-moi, final2.
5. Đặt file tiếng Anh không dấu, không chứa khoảng trắng. Class PHP PascalCase, hàm/biến camelCase; SQL snake_case.
6. PHP namespace App\ tương ứng app/. Không viết SQL trong view/controller; nghiệp vụ ở Service, truy vấn ở Repository.
7. Thêm file trong module đúng lớp; thay cấu trúc, thêm module, thêm thư viện phải giải thích trong PR và được nhóm trưởng duyệt.
8. git add theo file hoặc git add . sau khi kiểm tra git status; tự kiểm tra, commit rồi push nhánh.
9. Mở Pull Request vào main; điền checklist; yêu cầu nhóm trưởng review. Không tự merge.
10. Sửa góp ý trên cùng nhánh; commit mới phải được review lại. Sau merge cập nhật main.

Không commit .env thật, database dump chứa dữ liệu cá nhân, file upload, log, node_modules, vendor hay bộ cài.
Không copy file thủ công giữa máy hoặc gửi ZIP để ghép code. Không dùng chung DB production để thử nghiệm.
Không sửa migration đã áp dụng chung; thêm migration mới. SQL mẫu phải chạy theo thứ tự được ghi rõ.
Nếu có conflict: hiểu cả hai thay đổi, trao đổi chủ module và kiểm tra lại; không chọn toàn bộ ours/theirs cho nhanh.

## Nghiệm thu
- Đúng Issue, cấu trúc, hợp đồng API và không có bí mật.
- Kiểm tra trường hợp thành công/thất bại và chức năng cũ bị ảnh hưởng.
- CI structure và php-lint đạt; đây chưa phải kiểm thử nghiệp vụ.
- Nhóm trưởng duyệt trước merge. PR do nhóm trưởng tạo phải có thành viên được ủy quyền review theo quy trình riêng (không tự approve được).
