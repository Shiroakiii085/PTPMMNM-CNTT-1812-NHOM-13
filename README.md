# BÀI TẬP LỚN: CÔNG CỤ KIỂM THỬ TỰ ĐỘNG QUYỀN TRUY CẬP WEB (ACCESSTESTER)
## ỨNG DỤNG MẪU BỆNH ÁN ĐIỆN TỬ (MEDIRECORD EMR) & TRỰC QUAN HÓA LỖ HỔNG PHÂN QUYỀN IDOR

**Học phần:** Phát triển phần mềm mã nguồn mở  
**Đề tài 4:** Xây dựng công cụ kiểm thử tự động quyền truy cập trong ứng dụng Web  
**Sinh viên thực hiện:** Nhóm 13 (Repository Nhóm 14)  
**Nền tảng khởi tạo:** Nhánh `lab5` (FIT-DNU/Open-Source-Software-Development)  
**Công nghệ:** PHP 8.1+, PostgreSQL 14+, cURL, HTML5/CSS3 (Google Fonts Plus Jakarta Sans), Bash/WSL  
**Giấy phép:** [MIT License](Ma_nguon/LICENSE)  
**Trạng thái kiểm thử:** [![Tests](https://img.shields.io/badge/Tests-88%2F88%20PASS-brightgreen.svg)](Ma_nguon/tests/) [![PHP Version](https://img.shields.io/badge/PHP-%3E%3D8.1-777bb4.svg)](https://www.php.net/) [![PostgreSQL](https://img.shields.io/badge/PostgreSQL-%3E%3D14-336791.svg)](https://www.postgresql.org/)

---

## 1. Giới thiệu Đề tài & Bối cảnh Thực tiễn

Trong các ứng dụng Web phân tán, kiểm soát truy cập (Access Control) là một trong những phòng tuyến bảo mật quan trọng nhất. Tuy nhiên, lập trình viên thường mắc lỗi bỏ sót kiểm tra quyền sở hữu dữ liệu người dùng (lỗ hổng **IDOR - Insecure Direct Object Reference / BOLA - Broken Object Level Authorization**, xếp vị trí số 1 trong danh mục OWASP API Security Top 10).

Dự án triển khai hoàn chỉnh một hệ sinh thái kiểm thử thực tế gồm 2 thành phần cốt lõi:

### 1.1. Ứng dụng mẫu Quản lý Bệnh án Điện tử Y tế (MediRecord EMR - Cổng 8205)
- **Giao diện người dùng hiện đại:** Áp dụng phong cách SaaS y tế hiện đại, typography Google Fonts *Plus Jakarta Sans*, bảng màu tinh tế, thiết kế dạng thẻ kính mờ (glassmorphism), loại bỏ hoàn toàn các icon/emoji gây rối mắt.
- **Trang Landing Page chuyên nghiệp (`?view=landing`):** Trực quan hóa năng lực hệ thống, bảng đối chiếu hành vi an ninh mạng khi bật/tắt lỗi IDOR, và các thẻ vai trò người dùng có nút 1-click đăng nhập nhanh.
- **Hệ thống phân quyền vai trò lâm sàng:**
  - **Bác sĩ (`bs_minh`):** Thăm khám, chẩn đoán bệnh lý, lập và gửi hồ sơ bệnh án trực tiếp cho bệnh nhân.
  - **Bệnh nhân (`bn_an`, `bn_binh`):** Đăng nhập để theo dõi hồ sơ bệnh án riêng tư của chính mình.
  - **Quản trị viên (`admin_bv`):** Quản trị danh sách người dùng toàn viện (thêm, sửa, tìm kiếm thời gian thực).
  - **Kỹ sư An toàn thông tin (`tester_sec`):** Theo dõi nhật ký rò rỉ dữ liệu y tế thời gian thực tại **Trung tâm Giám sát An ninh** và kích hoạt kiểm thử trực tiếp từ Web UI.
- **Bộ đôi Dock điều khiển nổi (FABs) & Terminal Console:** Cho phép chuyển đổi nhanh 5 tài khoản thực nghiệm chỉ bằng 1 cú nhấp chuột và bật/tắt trực tiếp cờ an ninh `BUG_IDOR=1` / `BUG_IDOR=0`.
- **Cung cấp 6 REST API chuẩn:** `GET /api/me`, `GET /api/notes`, `POST /api/notes`, `GET /api/notes/{id}`, `PUT /api/notes/{id}`, `GET /api/announcements`.

### 1.2. Động cơ Kiểm thử Tự động Quyền truy cập (AccessTester Engine)
1. **Kiểm thử thực nghiệm bằng cURL HTTP thật:** Gửi yêu cầu độc lập tới các API ứng dụng mục tiêu được phê duyệt trong danh sách an toàn (`TargetAllowlist`).
2. **Động cơ ma trận phân quyền kép (RBAC & ABAC):** Kết hợp vai trò người dùng và quyền sở hữu bản ghi theo nguyên tắc cốt lõi: **Mặc định là từ chối (DENY)** nếu quyền chưa được khai báo.
3. **Phát hiện rò rỉ dữ liệu chuyên sâu:** Tự động phát hiện các chuỗi nội dung nhạy cảm (`must_not_contain`), phân biệt rạch ròi giữa lỗi phân quyền (`FAIL` - mã HTTP 200 nhưng dữ liệu rò rỉ) và lỗi kết nối mạng (`ERROR`).
4. **Bảo toàn toàn vẹn dữ liệu:** Lưu trữ lịch sử lần chạy kèm mã băm SHA-256 (`config_hash`) và bản chụp cấu hình (`snapshot`) độc lập từng ca thử.
5. **Đối chiếu kết quả (Diff):** So sánh trực quan sự khác biệt giữa lần chạy trước sửa lỗi (`FAIL` tại ca R02) và sau khi sửa lỗi (`PASS` 100%).
6. **Hỗ trợ đa phương thức vận hành:** Vận hành linh hoạt qua Giao diện Web cổng 8205 (Trung tâm kiểm thử cho Tester), Web UI độc lập cổng 8105, và Giao diện Dòng lệnh CLI (`bin/run.php`) sẵn sàng tích hợp CI/CD.

---

## 2. Bảng Tự Đánh Giá Theo Rubric Chấm Điểm (10.0 / 10.0 Điểm)

| STT | Nhóm tiêu chí | Điểm tối đa | Điểm tự đánh giá | Minh chứng mã nguồn và kết quả thực tế |
| :---: | :--- | :---: | :---: | :--- |
| **I** | **5 Chức năng cốt lõi** | **3.0đ** | **3.0 / 3.0** | - Quản lý dự án, vai trò, tài nguyên, hành động, bản ghi: `public/projects.php`, `src/ProjectRepository.php`.<br>- Khai báo 24 ca thử nghiệm (tài khoản, HTTP, quyền mong đợi, kết quả kỳ vọng): `public/cases.php`, `src/CaseRepository.php`.<br>- Ứng dụng mẫu có 6 REST API và lỗi cố ý: `sample-app/public/index.php` (`BUG_IDOR=1/0`).<br>- Gửi HTTP thật, ghi mã trạng thái, phân loại lỗi mạng: `src/HttpClient.php`.<br>- Lưu lần chạy, so sánh trước/sau, xuất báo cáo HTML/MD, chạy CLI: `public/runs.php`, `public/compare.php`, `public/report.php`, `bin/run.php`. |
| **II** | **Yêu cầu kỹ thuật 1** | **1.0đ** | **1.0 / 1.0** | - Kiểm tra kết hợp vai trò (RBAC) và chủ sở hữu (ABAC).<br>- Quyền chưa khai báo hoặc người dùng ẩn danh **mặc định là DENY**: `src/Policy.php` (`decide()`, `computeExpectedForCase()`). |
| **III**| **Yêu cầu kỹ thuật 2** | **1.0đ** | **1.0 / 1.0** | - Gửi cURL HTTP thật đến cổng `8205` của ứng dụng mẫu MediRecord EMR.<br>- Bắt đúng lỗi IDOR cố ý ở ca `R02` khi `BUG_IDOR=1` (phát hiện chuỗi rò rỉ bí mật), không chỉ so sánh 2 hàm phân quyền nội bộ. |
| **IV** | **Dữ liệu & An toàn** | **1.0đ** | **1.0 / 1.0** | - Bảng `runs` gắn `config_hash` (SHA-256) và snapshot cấu hình.<br>- Bảng `run_results` lưu snapshot độc lập từng ca.<br>- `src/TargetAllowlist.php` chỉ cho phép `127.0.0.1` và `localhost` tại các cổng duyệt.<br>- `src/Redactor.php` loại bỏ 100% token bí mật, mật khẩu khỏi log và báo cáo. |
| **V** | **Kiểm thử & Sửa lỗi** | **1.5đ** | **1.5 / 1.5** | - Đáp án chuẩn: `tests/fixtures/answer_key.json`.<br>- Tự động hóa hoàn toàn **5 phép thử bắt buộc** trong `tests/e2e.php`.<br>- Đạt **88/88 test assertions PASS** (Unit: 39, Integration: 29, E2E: 20).<br>- So sánh lần chạy trước sửa (`FAIL` ca R02) và sau sửa (`PASS` 24/24 ca). |
| **VI** | **Mã nguồn mở & Bàn giao** | **1.5đ** | **1.5 / 1.5** | - Giấy phép tự do [MIT License](Ma_nguon/LICENSE).<br>- Tài liệu nguồn gốc và ghi công chi tiết tại [Ma_nguon/docs/NGUON.md](Ma_nguon/docs/NGUON.md).<br>- CI tự động GitHub Actions `.github/workflows/ci.yml`.<br>- Lệnh đóng gói bản phát hành tự động `bash lab.sh release`. |
| **VII**| **Đóng góp cá nhân & Vấn đáp** | **1.0đ** | **1.0 / 1.0** | - Tài liệu thiết kế kiến trúc [Ma_nguon/docs/KIEN_TRUC.md](Ma_nguon/docs/KIEN_TRUC.md).<br>- Hướng dẫn thực hành & ngân hàng câu hỏi vấn đáp tại [Ma_nguon/docs/HUONG_DAN.md](Ma_nguon/docs/HUONG_DAN.md).<br>- Tài liệu đặc tả Yêu cầu 3 [3_Tutorial.md](3_Tutorial.md). |
| **TỔNG**| **TOÀN BỘ ĐỀ TÀI** | **10.0đ** | **10.0 / 10.0** | **Đạt xuất sắc mọi tiêu chí theo đề cương yêu cầu.** |

---

## 3. Hướng Dẫn Khởi Chạy Nhanh Dự Án

Tất cả các lệnh thực thi trong thư mục `lab5/Ma_nguon` trên môi trường Linux (Ubuntu / WSL):

```bash
cd lab5/Ma_nguon

# 1. Cấu hình CSDL và nạp dữ liệu tự động
bash lab.sh db-config
bash lab.sh setup

# 2. Kiểm tra tính sẵn sàng của môi trường
bash lab.sh check

# 3. Chạy toàn bộ 88 bài kiểm thử tự động (Unit -> Integration -> E2E)
bash lab.sh test

# 4. Khởi chạy Ứng dụng mẫu MediRecord EMR (Cổng 8205)
bash lab.sh sample
# -> Mở trình duyệt tại: http://127.0.0.1:8205

# 5. (Tùy chọn) Khởi chạy Web UI độc lập AccessTester (Cổng 8105)
bash lab.sh run
# -> Mở trình duyệt tại: http://127.0.0.1:8105

# 6. (Tùy chọn) Thực thi kiểm thử bằng công cụ dòng lệnh CLI
bash lab.sh cli --project=1 --label="Kiem thu CLI"
```

---

## 4. Hệ Thống Tài Khoản Thực Nghiệm

### 4.1. Tài khoản Hệ thống Y tế MediRecord EMR (Cổng 8205)
*Mật khẩu chung:* **`TestPass@2026`** *(Hỗ trợ chọn nhanh 1-click tại nút Dock nổi trên màn hình)*

| Tài khoản | Vai trò (Role) | Chức danh & Họ tên | Quyền hạn nghiệp vụ |
| :--- | :--- | :--- | :--- |
| **`admin_bv`** | `admin` | Quản trị viên Bệnh viện Bạch Mai | Toàn quyền, quản trị người dùng (CRUD, tìm kiếm), xem mọi bệnh án |
| **`bs_minh`** | `doctor` | BS. CKI Lê Hoàng Minh | Lập chẩn đoán và gửi bệnh án mới cho bệnh nhân, xem bệnh án |
| **`bn_an`** | `member` | Bệnh nhân Nguyễn Văn An | Chỉ xem và sở hữu bệnh án của bản thân (sở hữu bệnh án #1, #2) |
| **`bn_binh`** | `member` | Bệnh nhân Trần Thị Bình | Chỉ xem và sở hữu bệnh án của bản thân (sở hữu bệnh án #3, #4) |
| **`tester_sec`** | `auditor` | Kỹ sư An ninh Nguyễn Kiểm Thử | Xem bệnh án, truy cập Trung tâm Giám sát An ninh, kích hoạt test |
| *`alice`, `bob`* | `member` | Tài khoản đối chiếu kiểm thử | Tài khoản alias đảm bảo tương thích 100% với bộ test runner |

### 4.2. Tài khoản Công cụ Kiểm thử AccessTester (Cổng 8105 / CLI)
*Mật khẩu chung:* **`AccessTest@2026`**

| Tài khoản | Quyền dự án | Phân loại quyền | Mục đích kiểm thử |
| :--- | :--- | :--- | :--- |
| **`phamle`** | `owner` | Toàn quyền quản trị Dự án A | Quản lý dự án, cấu hình ma trận phân quyền, chạy kiểm thử |
| **`dev1`** | `editor` | Chỉnh sửa ca thử nghiệm | Thêm/sửa ca thử, chạy kiểm thử và xem kết quả đối chiếu |
| **`auditor_user`** | `viewer` | Kiểm toán viên độc lập | Chỉ xem kết quả và báo cáo (bị chặn mọi thao tác sửa/xóa ca) |
| **`dev2`** | `viewer` | Thành viên nhóm khác | Bị từ chối truy cập tài nguyên của Dự án A theo quy tắc ABAC |

---

## 5. Danh Mục Tài Liệu Kỹ Thuật Dự Án

- 📘 [3_Tutorial.md](3_Tutorial.md): Hướng dẫn chi tiết Yêu cầu 3 — Ứng dụng mẫu MediRecord EMR, đặc tả 6 API, cơ chế rò rỉ IDOR, thanh điều khiển Dock FABs và Terminal.
- 📋 [KiemThu.md](KiemThu.md): Bảng ma trận phân quyền 24 ca kiểm thử chi tiết và kết quả mong đợi.
- 📖 [Ma_nguon/docs/HUONG_DAN.md](Ma_nguon/docs/HUONG_DAN.md): Hướng dẫn vận hành chi tiết từng bước & Ngân hàng 10 câu hỏi vấn đáp bảo vệ đồ án.
- 🏛️ [Ma_nguon/docs/KIEN_TRUC.md](Ma_nguon/docs/KIEN_TRUC.md): Tài liệu thiết kế kiến trúc hệ thống C4, sơ đồ luồng dữ liệu & chính sách an toàn.
- 📜 [Ma_nguon/docs/NGUON.md](Ma_nguon/docs/NGUON.md): Nguồn gốc mã nguồn, thành phần thư viện bên thứ ba và giấy phép ghi công.
- 📦 [Ma_nguon/CHANGELOG.md](Ma_nguon/CHANGELOG.md): Nhật ký phiên bản và lịch sử hoàn thiện đề tài.
- 🤝 [Ma_nguon/CONTRIBUTING.md](Ma_nguon/CONTRIBUTING.md): Quy chuẩn đóng góp mã nguồn và quy trình kiểm thử trước khi bàn giao.
