# Nguồn gốc và Giấy phép Phần mềm (Attribution & License)

Đề tài: **Công cụ kiểm thử tự động quyền truy cập Web (AccessTester)**  
Học phần: Phát triển phần mềm mã nguồn mở  
Người thực hiện: Phạm Lê

---

## 1. Nguồn gốc mã nguồn (Upstream Origin)

Mã nguồn của Đề tài xuất phát từ bài thực hành mã nguồn mở:
- **Repository gốc:** [FIT-DNU/Open-Source-Software-Development](https://github.com/FIT-DNU/Open-Source-Software-Development)
- **Nhánh (Branch):** `lab5`
- **Thư mục cơ sở:** `Ma_nguon/`
- **Giấy phép gốc:** MIT License (Được giữ nguyên văn tại [LICENSE](../LICENSE))

---

## 2. Bảng đối chiếu thành phần tái sử dụng và phát triển mới

| Thành phần | Trạng thái | Mục đích sử dụng |
| :--- | :--- | :--- |
| `src/Database.php` | Tái sử dụng | Quản lý kết nối PostgreSQL bằng hàm `db()` và truy vấn tham số hóa `queryParams()`, nạp cấu hình `.local/db.json`. |
| `tools/db-config.php`, `db-check.php`, `sql.php` | Tái sử dụng & tinh chỉnh | Đổi CSDL mặc định thành `accesstest_lab`, ẩn mật khẩu khi nhập terminal, bảo vệ file `.local/db.json` với quyền `0600`. |
| `src/Session.php`, `src/Input.php` | Tái sử dụng | Bảo vệ phiên làm việc, chống tấn công CSRF (`checkCsrf`) và chống XSS (`e()`). |
| `public/assets/css/tokens.css` | Tái sử dụng | Hệ thống Design Tokens màu sắc chuẩn Material Design 3. |
| `tests/helper.php` | Tái sử dụng | Khung kiểm thử nhỏ gọn với hàm `same()` và `finish()`. |
| `src/RegistrationStats.php` | Hoàn thiện mở rộng | Mẫu thực hành truy vấn thống kê sử dụng `LEFT JOIN`. |
| **`src/Policy.php`** | **Phát triển mới** | Động cơ phân quyền RBAC & ABAC, hỗ trợ scope `any`/`own`, mặc định `DENY`, tích hợp Policy Oracle tự động tính quyền kỳ vọng từ CSDL. |
| **`src/TargetAllowlist.php`** | **Phát triển mới** | Cơ chế kiểm soát danh sách máy chủ và cổng được phép kiểm thử từ `config/targets.json`, chống quét hệ thống bên ngoài. |
| **`src/HttpClient.php`** | **Phát triển mới** | Gửi HTTP thật, kiểm tra whitelist máy chủ cho phép, phân loại kết quả kiểm thử (`PASS`, `FAIL`, `ERROR`), kiểm tra rò rỉ (`must_not_contain`) và chuỗi bắt buộc (`must_contain`). |
| **`src/Redactor.php`** | **Phát triển mới** | Khử thông tin nhạy cảm (Bearer Token, mật khẩu, khóa bí mật, đăng ký secret động). |
| **`src/Logger.php`** | **Phát triển mới** | Ghi nhật ký an toàn, tự động che mờ thông tin bí mật trước khi ghi ra file. |
| **`src/Auth.php`** | **Phát triển mới** | Xác thực người dùng công cụ, kiểm soát vai trò (`owner`, `editor`, `viewer`) và hỗ trợ chuyển đổi tài khoản trực tiếp trên giao diện để trình diễn Phép thử 4. |
| **`src/Runner.php`** | **Phát triển mới** | Điều phối toàn bộ quy trình kiểm thử 24 ca HTTP thật, tính toán `config_hash` (SHA-256), đăng ký secret động, lưu snapshot kết quả. |
| **`src/Report.php`** | **Phát triển mới** | Xuất báo cáo kết quả kiểm thử định dạng Markdown và HTML độc lập có highlight các ca được sửa lỗi và đối chiếu diff trước/sau. |
| **`src/RunStats.php`** | **Phát triển mới** | Thống kê số lượng kết quả kiểm thử sử dụng `LEFT JOIN` và `COUNT(*) FILTER`. |
| **`src/*Repository.php`** | **Phát triển mới** | Các kho lưu trữ CSDL cho dự án, thành viên, vai trò, tài nguyên, hành động, bản ghi chủ sở hữu, ca thử (kèm xuất JSON) và lần chạy (lưu snapshot). |
| **`sample-app/`** | **Phát triển mới** | Ứng dụng mẫu đối tượng kiểm thử gồm 6 API và công tắc lỗi phân quyền cố ý `BUG_IDOR` phục vụ thực nghiệm. |
| **`bin/run.php`** | **Phát triển mới** | Giao diện dòng lệnh CLI Runner hỗ trợ chạy test, so sánh diff, xuất báo cáo và xuất JSON ca thử. |
| **`public/*.php`** | **Phát triển mới** | Toàn bộ giao diện Web quản trị, xem ma trận quyền, quản lý bản ghi chủ sở hữu, khai báo ca thử, chạy test, so sánh và xuất báo cáo. |
| **`tests/fixtures/answer_key.json`** | **Phát triển mới** | Dữ liệu và đáp án chuẩn đối chiếu 24 ca thử nghiệm và 5 phép thử bắt buộc. |
| **`tests/e2e.php`** | **Phát triển mới** | Bộ kiểm thử đầu cuối bao quát đủ 5 phép thử bắt buộc với 20 xác minh tự động. |
| **`.github/workflows/ci.yml`** | **Phát triển mới** | Tích hợp liên tục (CI) tự động với dịch vụ PostgreSQL trên GitHub Actions. |

---

## 3. Bản quyền (Copyright Notice)

Dự án tuân thủ nghiêm ngặt giấy phép mã nguồn mở MIT. Bản quyền các đoạn mã gốc thuộc về các tác giả của kho lưu trữ `FIT-DNU/Open-Source-Software-Development`. Phần mã nguồn mở rộng và tính năng mới được phát triển phục vụ học phần PTPMMNM.
