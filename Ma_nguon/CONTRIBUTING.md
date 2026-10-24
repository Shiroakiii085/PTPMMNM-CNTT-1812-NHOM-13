# Hướng dẫn đóng góp mã nguồn (Contributing Guidelines)

Chào mừng bạn tham gia đóng góp cho dự án **AccessTester** (Học phần Phát triển phần mềm mã nguồn mở - Đề tài 4).

## 1. Quy trình làm việc (Git Workflow)

1. **Fork hoặc tạo nhánh riêng từ `main`**:
   ```bash
   git checkout -b feature/ten-tinh-nang
   ```
2. **Tuân thủ quy ước mã nguồn**:
   - Khai báo `declare(strict_types=1);` ở đầu mọi tệp PHP.
   - Luôn sử dụng prepared statements qua `queryParams()` khi thao tác CSDL.
   - Áp dụng `htmlspecialchars` (qua hàm `e()`) khi hiển thị dữ liệu người dùng ra HTML để ngăn chặn XSS.
   - Mọi dữ liệu nhạy cảm (token, mật khẩu) phải đi qua `Redactor::redactText()`.
3. **Kiểm tra tự động trước khi gửi**:
   ```bash
   bash lab.sh check
   bash lab.sh test
   ```
   Toàn bộ bộ kiểm thử (Unit, Integration, E2E) phải đạt 100% PASS trước khi tạo Pull Request.

## 2. Tiêu chí đánh giá Code Review

Mỗi Pull Request sẽ được kiểm duyệt dựa trên:
- **Tính an toàn**: Không để lộ token, mật khẩu trong log, URL hoặc báo cáo.
- **Tuân thủ phân quyền**: Quyền chưa được khai báo phải bị từ chối (`DENY`).
- **Phân định rõ ràng**: Lỗi mạng / timeout phải phân loại là `ERROR`, không được coi là từ chối đúng (`DENY`).
- **Tài liệu**: Cập nhật `CHANGELOG.md` và các tệp hướng dẫn tương ứng.

## 3. Tạo Issue & Pull Request

- Sử dụng các mẫu có sẵn tại `.github/ISSUE_TEMPLATE/` khi báo lỗi hoặc đề xuất tính năng mới.
- Điền đầy đủ thông tin vào mẫu tại `.github/pull_request_template.md` khi mở Pull Request.
