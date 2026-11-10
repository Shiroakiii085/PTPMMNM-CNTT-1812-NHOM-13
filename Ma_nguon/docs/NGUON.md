# Nguồn Gốc và Giấy Phép Phần Mềm (Attribution & License)

Đề tài: **Công cụ kiểm thử tự động quyền truy cập Web (AccessTester)**  
Ứng dụng mẫu: **Hệ thống Quản lý Bệnh án Điện tử Y tế (MediRecord EMR)**  
Học phần: Phát triển phần mềm mã nguồn mở  
Sinh viên thực hiện: Nhóm 13 (Repository Nhóm 14)  
Phiên bản tài liệu: 3.0.0  

---

## 1. Nguồn Gốc Mã Nguồn (Upstream Origin)

Mã nguồn của Đề tài xuất phát từ bài thực hành mã nguồn mở:
- **Repository gốc:** [FIT-DNU/Open-Source-Software-Development](https://github.com/FIT-DNU/Open-Source-Software-Development)
- **Nhánh (Branch):** `lab5`
- **Thư mục cơ sở:** `Ma_nguon/`
- **Giấy phép gốc:** MIT License (Được giữ nguyên văn tại [LICENSE](../LICENSE))

---

## 2. Bảng Đối Chiếu Thành Phần Tái Sử Dụng và Phát Triển Mới

| Thành phần | Trạng thái | Mục đích sử dụng |
| :--- | :--- | :--- |
| `src/Database.php` | Tái sử dụng | Quản lý kết nối PostgreSQL bằng hàm `db()` và truy vấn tham số hóa `queryParams()`, nạp cấu hình `.local/db.json`. |
| `tools/db-config.php`, `db-check.php`, `sql.php` | Tái sử dụng & tinh chỉnh | Đổi CSDL mặc định thành `accesstest_lab`, ẩn mật khẩu khi nhập terminal, bảo vệ file `.local/db.json` với quyền `0600`. |
| `src/Session.php`, `src/Input.php` | Tái sử dụng | Bảo vệ phiên làm việc, chống tấn công CSRF (`checkCsrf`) và chống XSS (`e()`). |
| `public/assets/css/tokens.css` | Tái sử dụng | Hệ thống Design Tokens màu sắc chuẩn Material Design 3. |
| `tests/helper.php` | Tái sử dụng | Khung kiểm thử nhỏ gọn với hàm `same()` và `finish()`. |
| `src/RegistrationStats.php` | Hoàn thiện mở rộng | Mẫu thực hành truy vấn thống kê sử dụng `LEFT JOIN`. |
| **`src/Policy.php`** | **Phát triển mới** | Động cơ phân quyền RBAC & ABAC, hỗ trợ scope `any`/`own`, mặc định `DENY`, tích hợp Policy Oracle tự động tính quyền kỳ vọng từ CSDL. |
| **`src/TargetAllowlist.php`** | **Phát triển mới** | Cơ chế kiểm soát danh sách máy chủ và cổng được phép kiểm thử từ `config/targets.json`, chống quét hệ thống bên ngoài (chống SSRF). |
| **`src/HttpClient.php`** | **Phát triển mới** | Gửi HTTP thật, kiểm tra whitelist máy chủ cho phép, phân loại kết quả kiểm thử (`PASS`, `FAIL`, `ERROR`), kiểm tra rò rỉ (`must_not_contain`) và chuỗi bắt buộc (`must_contain`). |
| **`src/Redactor.php`** | **Phát triển mới** | Khử thông tin nhạy cảm (Bearer Token, mật khẩu, khóa bí mật, đăng ký secret động). |
| **`src/Logger.php`** | **Phát triển mới** | Ghi nhật ký an toàn, tự động che mờ thông tin bí mật trước khi ghi ra file. |
| **`src/Auth.php`** | **Phát triển mới** | Xác thực người dùng công cụ, kiểm soát vai trò (`owner`, `editor`, `viewer`) và hỗ trợ chuyển đổi tài khoản trực tiếp trên giao diện để trình diễn Phép thử 4. |
| **`src/Runner.php`** | **Phát triển mới** | Điều phối toàn bộ quy trình kiểm thử 24 ca HTTP thật, tính toán `config_hash` (SHA-256), đăng ký secret động, lưu snapshot kết quả. |
| **`src/Report.php`** | **Phát triển mới** | Xuất báo cáo kết quả kiểm thử định dạng Markdown và HTML độc lập có highlight các ca được sửa lỗi và đối chiếu diff trước/sau. |
| **`src/RunStats.php`** | **Phát triển mới** | Thống kê số lượng kết quả kiểm thử sử dụng `LEFT JOIN` và `COUNT(*) FILTER`. |
| **`src/*Repository.php`** | **Phát triển mới** | Các kho lưu trữ CSDL cho dự án, thành viên, vai trò, tài nguyên, hành động, bản ghi chủ sở hữu, ca thử (kèm xuất JSON) và lần chạy (lưu snapshot). |
| **`sample-app/` (Kiến trúc Modular MVC 36 files)** | **Phát triển mới toàn diện** | Ứng dụng mẫu Quản lý Bệnh án Điện tử Y tế (MediRecord EMR) cổng 8205 gồm 6 REST API chuẩn, công tắc lỗi `BUG_IDOR` cố ý, Trung tâm Giám sát An ninh & Kiểm thử cho `tester_sec` và Trợ lý AI Thông minh. |
| **`sample-app/app/bootstrap/`** | **Phát triển mới** | Khởi tạo môi trường, nạp cấu hình PDO/SQLite fallback, xác thực Session/Bearer, quản lý cờ an ninh và tiện ích hiển thị. |
| **`sample-app/app/services/`** | **Phát triển mới** | Các dịch vụ độc lập: `security_service.php` (live feed sự cố), `test_runner_service.php` (chạy 24 ca ma trận, tính toán delta), `ai_assistant_service.php` (kết nối LLMs, phòng thủ ABAC trong prompt). |
| **`sample-app/app/controllers/`** | **Phát triển mới** | Bộ điều phối luồng: `auth_controller.php`, `record_controller.php` (nơi xử lý IDOR), `user_controller.php`, `announcement_controller.php`, `tester_controller.php`, `ai_controller.php` (SSE streaming). |
| **`sample-app/app/api/api_routes.php`** | **Phát triển mới** | Bộ định tuyến 6 REST API chuẩn phục vụ công cụ cURL và kiểm thử tự động. |
| **`sample-app/app/views/`** | **Phát triển mới** | Hệ thống view phân tầng: `layouts/` (head, navbar, idor_banner), `pages/` (10 trang giao diện), `tester/` (4 tab an ninh), `partials/` (dock 3 nút FAB, modal AI, terminal drawer). |
| **`sample-app/public/assets/`** | **Phát triển mới** | `app.css` (phong cách Glassmorphism SaaS hiện đại), `app.js` (SSE AI client, Hold-to-Confirm modal), `robot-heads.bundle.js` (Robot 3D Canvas Procedural Head tương tác chuột). |
| **`bin/run.php`** | **Phát triển mới** | Giao diện dòng lệnh CLI Runner hỗ trợ chạy test, so sánh diff, xuất báo cáo và xuất JSON ca thử. |
| **`public/*.php`** | **Phát triển mới** | Toàn bộ giao diện Web quản trị cổng 8105 (dự án, ma trận quyền, ca thử, lần chạy, so sánh và xuất báo cáo). |
| **`tests/fixtures/answer_key.json`** | **Phát triển mới** | Dữ liệu và đáp án chuẩn đối chiếu 24 ca thử nghiệm và 5 phép thử bắt buộc. |
| **`tests/e2e.php`** | **Phát triển mới** | Bộ kiểm thử đầu cuối bao quát đủ 5 phép thử bắt buộc với 20 xác minh tự động (trong tổng số 88 test cases pass). |
| **`Dockerfile` & Bản Triển Khai Cloud** | **Phát triển mới** | Cấu hình Docker container hóa và triển khai trực tuyến trên Render.com tại [https://medirecord-emr.abrdns.com](https://medirecord-emr.abrdns.com). |
| **`.github/workflows/ci.yml`** | **Phát triển mới** | Tích hợp liên tục (CI) tự động với dịch vụ PostgreSQL trên GitHub Actions. |

---

## 3. Bản Quyền (Copyright Notice)

Dự án tuân thủ nghiêm ngặt giấy phép mã nguồn mở MIT. Bản quyền các đoạn mã gốc thuộc về các tác giả của kho lưu trữ `FIT-DNU/Open-Source-Software-Development`. Phần mã nguồn mở rộng, kiến trúc Modular MVC và các phân hệ kiểm thử/AI mới được phát triển phục vụ học phần PTPMMNM.
