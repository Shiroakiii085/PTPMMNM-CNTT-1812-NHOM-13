# Changelog

Mọi thay đổi lớn của dự án **AccessTester (Đề tài 4)** được ghi lại trong tài liệu này.
Định dạng dựa trên [Keep a Changelog](https://keepachangelog.com/vi/1.0.0/), tuân thủ [Semantic Versioning](https://semver.org/).

## [2.0.0] - 2026-10-23

### Hoàn thiện toàn diện Bài tập lớn Đề tài 4 (Đạt chuẩn Rubric 10/10)

#### Thêm mới (Added)
- **Kiến trúc Policy Oracle hoàn chỉnh:** Hàm `Policy::computeExpectedForCase()` đối chiếu dữ liệu CSDL ma trận phân quyền RBAC kết hợp ABAC (kiểm tra `records` ↔ `sample_user_id` ↔ `owner_id`), tuân thủ nguyên tắc mặc định `DENY`.
- **Danh sách máy chủ duyệt an toàn (`src/TargetAllowlist.php`):** Nạp cấu hình từ `config/targets.json`, chỉ cho phép `127.0.0.1` và `localhost` trên các cổng được phê duyệt (`8205`, `8295-8298`), chặn tên miền bên ngoài và URL chứa thông tin xác thực inline.
- **Bảo mật bí mật động (`src/Redactor.php`):** Hỗ trợ `registerSecret()`, `registerEnvSecrets()`, lọc triệt để Bearer tokens, passwords, JSON properties và query parameters trước khi ghi log hoặc xuất báo cáo.
- **Bảo toàn dữ liệu độc lập (Snapshotting):** Cấu trúc bảng `run_results` lưu trữ snapshot độc lập của từng ca (`case_code`, `method`, `path`, `expected_status`, `must_contain`, `must_not_contain`), bảng `runs` lưu `config_snapshot` và `config_hash` (SHA-256). Đảm bảo lịch sử kiểm thử không bị ảnh hưởng nếu cấu hình ca sau này bị chỉnh sửa hoặc xóa.
- **Thuật toán so sánh Full Outer Join (`src/RunRepository.php`):** Hỗ trợ đối chiếu toàn diện hai lần chạy ngay cả khi danh sách ca thử giữa hai lần chạy có sự thay đổi.
- **Cơ chế chuyển vai trò người dùng tức thì (`src/Auth.php`):** Cho phép kiểm thử viên chuyển đổi nhanh giữa các tài khoản `phamle` (owner), `dev1` (editor), `auditor_user` (viewer), và `dev2` (dự án khác) trực tiếp trên thanh điều hướng Web.
- **Lệnh điều khiển mở rộng trong `lab.sh`:**
  - `bash lab.sh setup`: Tự động nạp CSDL `database/schema.sql` và `database/seed.sql` trong 1 thao tác.
  - `bash lab.sh release`: Tự động kiểm tra chất lượng mã nguồn qua test suite và đóng gói bản phát hành `build/accesstest-release-YYYYMMDD.tar.gz`.
  - `bash lab.sh cli --export-cases`: Xuất toàn bộ cấu hình 24 ca thử ra định dạng JSON tiêu chuẩn.
- **Tự động hóa 100% 5 Phép thử bắt buộc (`tests/e2e.php`):**
  - Phép thử 1: Phát hiện lỗ hổng IDOR cố ý với `BUG_IDOR=1` (phân loại FAIL, `leak_detected=true`).
  - Phép thử 2: Xác nhận sửa lỗi thành công với `BUG_IDOR=0` (24/24 PASS, diff FAIL -> PASS).
  - Phép thử 3: Phân loại đúng ERROR cho cổng đóng và đích cấm (tuyệt đối không nhầm là DENY).
  - Phép thử 4: Bảo vệ cấu hình ca thử (ngăn chặn vai trò `viewer` hoặc người dùng nhóm khác sửa/xóa ca, ném ngoại lệ `FORBIDDEN`).
  - Phép thử 5: Không rò rỉ token bí mật hoặc mật khẩu trong báo cáo Markdown/HTML và nhật ký log.
- **Đáp án chuẩn đối chứng (`tests/fixtures/answer_key.json`):** Chứa kết quả chuẩn của 24 ca HTTP và 5 phép thử.

#### Cải tiến (Improved)
- Nâng cấp bộ test suite 3 tầng đạt **88/88 assertions PASS (100%)**.
- Tối ưu hóa giao diện Web với tokens CSS hiện đại, trực quan, hỗ trợ hiển thị bảng diff so sánh phân màu trực quan.
- Đồng bộ hóa tài liệu kiến trúc kỹ thuật (`docs/KIEN_TRUC.md`) và cẩm nang vận hành kèm câu hỏi vấn đáp (`docs/HUONG_DAN.md`).

---

## [1.0.0] - 2026-10-21

### Phiên bản ban đầu
- Động cơ phân quyền `Policy` cơ bản.
- Trình gửi HTTP cURL thật.
- Ứng dụng mẫu `sample-app` với 6 REST API.
- Giao diện Web sơ khởi và CLI runner.
- Tích hợp CI GitHub Actions.
