# Bắt buộc nhóm trưởng duyệt trước merge

Gói này KHÔNG tự thay đổi cài đặt repo GitHub. Cần thực hiện sau khi push.

## CODEOWNERS
Thay dòng trong .github/CODEOWNERS bằng:

```text
* @TEN_GITHUB_THAT_CUA_BAN
```

Bạn phải có quyền Write trở lên. File phải tồn tại trên nhánh đích main.
Mẫu yêu cầu nhóm trưởng sở hữu tất cả file, kể cả chính CODEOWNERS và workflow.
Chưa biết username nên placeholder chưa có hiệu lực.

## Bảo vệ main
Settings → Branches → Add branch protection rule (hoặc Rulesets tương đương), chọn main.
- Require a pull request before merging.
- Require approvals: 1.
- Require review from Code Owners: BẮT BUỘC, vì 1 approval bất kỳ chưa đảm bảo là bạn.
- Dismiss stale pull request approvals when new commits are pushed.
- Require conversation resolution before merging.
- Require status checks to pass: structure và php-lint (chờ workflow chạy lần đầu để chọn đúng tên).
- Không cho force push, không cho xóa nhánh.
- Không cho bypass quy tắc, kể cả admin nếu muốn áp dụng nghiêm.
- Nếu có tùy chọn hạn chế dismiss review, chỉ cấp nhóm trưởng.
- Thành viên chỉ có quyền Write, không cấp Admin/Maintain hay quyền đổi rules.

CODEOWNERS và review không ngăn người dùng tự tạo file ở máy; chúng kiểm soát thay đổi được đưa vào main.
CI kiểm tra vị trí chỉ thực sự là điều kiện merge khi đã cấu hình required checks.
Nếu thành viên sửa cả CI/policy để né kiểm tra, nhóm trưởng phải từ chối PR.

## PR của chính nhóm trưởng
GitHub không cho tác giả tự approve PR. Nếu bạn là Code Owner duy nhất và chặn bypass thì PR của bạn có thể bị chặn.
Cách vận hành: giao phần thay đổi cho người khác thực sự chịu trách nhiệm PR để bạn review, hoặc chỉ định reviewer dự phòng và chấp nhận họ cũng có quyền duyệt theo policy mới.
Không dùng tài khoản phụ để giả lập review. Thống nhất ngoại lệ minh bạch trước khi thay đổi quy tắc.

## Điều kiện gói GitHub
Protected branches/CODEOWNERS có trên repo public với GitHub Free; repo private cần gói hỗ trợ tương ứng.
Nếu repo private không có tính năng này: chỉ cấp nhóm trưởng quyền ghi upstream, thành viên làm trên fork và gửi PR; bảo vệ máy móc bằng required review/CI vẫn cần gói hỗ trợ. Không công khai mã chỉ để lấy tính năng khi chưa thống nhất.

Nguồn:
- https://docs.github.com/en/repositories/managing-your-repositorys-settings-and-features/customizing-your-repository/about-code-owners
- https://docs.github.com/en/repositories/configuring-branches-and-merges-in-your-repository/managing-protected-branches/about-protected-branches
