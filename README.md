## ĐỒ ÁN CUỐI KÌ OPEN SOURCE  

## Thành viên:
| **MSSV** | **Họ và tên** |
| :--- | :--- |
| **65133696** | **Võ Đức Song Toàn (Trưởng nhóm)** |
| **65130338** | **Châu Quốc Cường** |
| **65130386** | **Văn Thanh Danh** |
| **65134070** | **Hà Văn Tuấn** |

# Doulingo — bộ khung nhóm 4 người

Hướng triển khai hiện tại: PHP thuần MVC + MySQL, phù hợp XAMPP. Không phải Laravel.
Web và ứng dụng desktop tương lai dùng chung backend/API. Desktop chưa được triển khai.

## Cài lần đầu cho nhóm trưởng
1. Nếu C:\xampp\htdocs\doulingo đã có nội dung, sao lưu trước; không ghi đè bộ khung lên code cũ.
2. Giải nén thư mục doulingo vào C:\xampp\htdocs, tạo đúng đường dẫn C:\xampp\htdocs\doulingo (tránh lồng doulingo\doulingo).
3. Mở terminal trong thư mục đó. Tạo repo GitHub rỗng, không tạo README sẵn.
4. Chạy: git init -b main; git add .; git commit -m "chore: initialize project structure" (từng lệnh riêng).
5. Chạy git remote add origin YOUR_REPOSITORY_URL rồi git push -u origin main.
6. Thay placeholder trong .github/CODEOWNERS bằng username thật và push cập nhật.
7. Làm theo docs/GITHUB_SETUP.md để bật bảo vệ nhánh, sau đó mời thành viên.

## Thành viên clone
Chạy từng lệnh trong PowerShell:

```powershell
cd C:\xampp\htdocs
git clone https://github.com/VoDucSongToan/DoAnCuoiKi_OpenSource doulingo
cd doulingo
git switch -c feature/12-user-profile
Copy-Item .env.example .env
```

Thư mục đích phải chưa tồn tại hoặc rỗng trước khi clone; sao lưu thư mục cũ nếu có.
Không tải ZIP để thay quy trình clone khi làm việc nhóm.
Tên thư mục cha trên máy có thể khác; mọi đường dẫn code phải tương đối.

## Kiểm tra bộ khung trên XAMPP
- Bật Apache. MySQL chưa cần cho trang kiểm tra bộ khung.
- Mở http://localhost/doulingo/public/ để xem trang mẫu.
- Mở http://localhost/doulingo/public/api/v1/health để kiểm tra PHP/API mẫu.
- Endpoint health chỉ kiểm tra tiến trình PHP, không kiểm tra kết nối DB.
- Apache cần mod_rewrite và AllowOverride cho .htaccess. Production nên đặt DocumentRoot vào public/.
- .env.example chỉ là cấu hình mẫu: chưa có bộ nạp .env, kết nối DB, Router tổng quát hoặc nghiệp vụ.
- Chưa cần Composer/Node cho bộ khung. Khi bổ sung, commit composer.json/composer.lock hoặc package.json/package-lock.json đúng vị trí và cập nhật policy cấu trúc qua PR.

## Quy ước nhóm
Đọc CONTRIBUTING.md, docs/STRUCTURE.md, docs/TEAM.md và docs/GITHUB_SETUP.md.
Các thư mục rỗng có .gitkeep để Git giữ lại khi clone. Xóa .gitkeep khi thư mục có file thật nếu muốn.
Không có chức năng đăng nhập, trò chơi, SM-2 hay bộ cài .exe hoàn chỉnh trong gói này.
