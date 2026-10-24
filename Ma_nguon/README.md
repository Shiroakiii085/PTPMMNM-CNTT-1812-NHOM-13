# Đề tài 4: Công cụ kiểm thử tự động quyền truy cập Web (AccessTester)

[![CI](https://github.com/FIT-DNU/Open-Source-Software-Development/actions/workflows/ci.yml/badge.svg)](.github/workflows/ci.yml)
[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE)
[![PHP Version](https://img.shields.io/badge/PHP-%3E%3D8.1-777bb4.svg)](https://www.php.net/)
[![PostgreSQL](https://img.shields.io/badge/PostgreSQL-%3E%3D14-336791.svg)](https://www.postgresql.org/)
[![Tests](https://img.shields.io/badge/Tests-88%2F88%20PASS-brightgreen.svg)](tests/)

Học phần: **Phát triển phần mềm mã nguồn mở**  
Nhánh khởi tạo: `lab5` (FIT-DNU/Open-Source-Software-Development)  
Sinh viên thực hiện: **Nhóm 13**

---

## 1. Bối cảnh và Mục tiêu Đề tài

Trong các ứng dụng Web hiện đại, hệ thống phân quyền có thể khai báo đúng trên lý thuyết nhưng mã nguồn xử lý thực tế vẫn để lộ dữ liệu nhạy cảm của người dùng khác (lỗ hổng IDOR - *Insecure Direct Object Reference* hoặc kiểm tra quyền thiếu sót).

**AccessTester** là công cụ kiểm thử tự động quyền truy cập (Access Control Automated Testing Tool):
- Tự động gửi các yêu cầu HTTP thực tế tới ứng dụng Web mẫu được duyệt.
- So sánh phản hồi nhận được với ma trận phân quyền chuẩn (kết hợp Role-Based Access Control - RBAC và Attribute-Based Access Control - ABAC theo chủ sở hữu bản ghi).
- Tự động phát hiện các lỗi phân quyền và chuỗi dữ liệu rò rỉ.
- Lưu trữ lịch sử lần chạy bất biến kèm `config_hash` và snapshot độc lập.
- So sánh đối chiếu trực quan kết quả trước và sau khi lập trình viên sửa lỗi (diff FAIL -> PASS).
- Xuất báo cáo kỹ thuật chi tiết dưới dạng HTML và Markdown không rò rỉ thông tin nhạy cảm.

---

## 2. Bảng Đối Chiếu Tiêu Chí Đánh Giá (Rubric 10/10)

| Tiêu chí | Điểm | Mô tả yêu cầu trong đề tài | Triển khai thực tế trong mã nguồn |
| :--- | :---: | :--- | :--- |
| **1. Năm chức năng cốt lõi** | **3.0đ** | 1. Quản lý dự án, vai trò, tài nguyên, hành động, chủ sở hữu bản ghi.<br>2. Khai báo từng ca thử (tài khoản, HTTP, quyền mong đợi, kết quả phản hồi).<br>3. Ứng dụng PHP mẫu có 6 REST API, dữ liệu riêng & chung, lỗi IDOR kiểm soát bằng `BUG_IDOR`.<br>4. Gửi HTTP thật tới local host được duyệt, ghi mã trạng thái, kết quả, phân loại lỗi mạng.<br>5. Lưu lần chạy, so sánh trước/sau sửa lỗi, xuất báo cáo HTML/MD, chạy CLI. | - Giao diện Web: `public/projects.php`, `public/cases.php`, `public/runs.php`, `public/compare.php`, `public/report.php`.<br>- Ứng dụng mẫu: `sample-app/public/index.php` (6 API, `BUG_IDOR=1/0`).<br>- Gửi HTTP: `src/HttpClient.php` với cURL thật.<br>- Lưu snapshot: `src/RunRepository.php`, `src/Runner.php`.<br>- CLI độc lập: `bin/run.php` (`bash lab.sh cli`). |
| **2. Yêu cầu kỹ thuật 1** | **1.0đ** | Kiểm tra cả **vai trò (RBAC)** và **chủ sở hữu dữ liệu (ABAC)**. Nếu quyền chưa được khai báo thì **mặc định là từ chối (DENY)**. | - `src/Policy.php`: `decide()` và `computeExpectedForCase()`.<br>- Ma trận kết hợp scope `own` và `any`.<br>- Mặc định từ chối tường minh `DENY` cho mọi vai trò hoặc hành động chưa có bản ghi cấp phép. |
| **3. Yêu cầu kỹ thuật 2** | **1.0đ** | Gửi yêu cầu HTTP thật tới API của ứng dụng mẫu để phát hiện lỗi cố ý (`BUG_IDOR=1`), không chỉ gọi hai lần hàm phân quyền để so sánh. | - `src/HttpClient.php` thực hiện cURL request thật tới `http://127.0.0.1:8205`.<br>- Phân tích mã HTTP phản hồi và kiểm tra nội dung rò rỉ (`must_not_contain`). Khi `BUG_IDOR=1`, Alice đọc được note của Bob -> phát hiện rò rỉ `leak_detected=true` -> Phân loại `FAIL`. |
| **4. Dữ liệu & An toàn** | **1.0đ** | Kết quả gắn với người dùng, tài nguyên, cấu hình kiểm thử (`config_hash`). Chỉ gửi tới đích được duyệt (`TargetAllowlist`), không để lộ mật khẩu hay token nguyên văn trong báo cáo (`Redactor`). | - Bảng `runs` lưu `config_hash` (SHA-256) và `config_snapshot`.<br>- Bảng `run_results` lưu snapshot độc lập từng ca.<br>- `src/TargetAllowlist.php` kiểm soát cổng và IP duyệt.<br>- `src/Redactor.php` loại bỏ Bearer Token, API Key, password khỏi log và báo cáo. |
| **5. Kiểm thử & Sửa lỗi** | **1.5đ** | Dữ liệu và đáp án chuẩn (`tests/fixtures/answer_key.json`). Tự động hóa 5 phép thử bắt buộc. Sửa lỗi ứng dụng mẫu và chạy lại kiểm thử, kết quả phải thể hiện chức năng cũ đã đạt. | - 5 phép thử tự động hóa 100% trong `tests/e2e.php`.<br>- Bộ test 3 tầng đạt **88/88 assertions PASS**.<br>- So sánh `before-fix` (FAIL tại R02) sang `after-fix` (PASS toàn bộ 24 ca). |
| **6. Mã nguồn mở & Bàn giao** | **1.5đ** | Giấy phép tự do (MIT), tài liệu nguồn gốc `docs/NGUON.md`, hướng dẫn sử dụng, issue/PR template, CI tự động, lệnh `lab.sh release` chạy lại được. | - Giấy phép [MIT](LICENSE).<br>- Ghi công chi tiết tại [docs/NGUON.md](docs/NGUON.md).<br>- Tài liệu [docs/HUONG_DAN.md](docs/HUONG_DAN.md) & [docs/KIEN_TRUC.md](docs/KIEN_TRUC.md).<br>- GitHub Actions `.github/workflows/ci.yml`.<br>- Lệnh đóng gói tự động `bash lab.sh release`. |
| **7. Đóng góp cá nhân** | **1.0đ** | Phân công vai trò, tài liệu kiến trúc, hướng dẫn vấn đáp rõ ràng từng phần. | - Trình bày đầy đủ trong `docs/KIEN_TRUC.md`, `docs/HUONG_DAN.md` và bảng tài khoản phân quyền thực nghiệm. |

---

## 3. Cấu trúc Thư mục Dự án

```
lab5/Ma_nguon/
├── README.md                   # Tài liệu giới thiệu chính
├── LICENSE                     # Giấy phép MIT
├── CHANGELOG.md                # Lịch sử phiên bản
├── CONTRIBUTING.md             # Quy chuẩn đóng góp mã nguồn
├── lab.sh                      # Trình điều khiển Bash: setup | check | run | sample | cli | test | release
├── env.local.sh.example        # Mẫu biến môi trường an toàn
├── .github/
│   ├── workflows/ci.yml        # CI tự động với GitHub Actions & PostgreSQL
│   ├── ISSUE_TEMPLATE/         # Biểu mẫu báo lỗi và đề xuất tính năng
│   └── pull_request_template.md# Biểu mẫu Pull Request chuẩn
├── config/
│   └── targets.json            # Whitelist máy chủ và cổng mạng được phép kiểm thử
├── database/
│   ├── schema.sql              # CSDL hoàn chỉnh: Cấu hình ca thử, runs, run_results, sample_app
│   └── seed.sql                # Dữ liệu mẫu (2 dự án, 24 ca HTTP chuẩn, 4 người dùng công cụ, 4 user sample_app)
├── src/
│   ├── Database.php            # Kết nối PostgreSQL và truy vấn tham số hóa (Parameterized queries)
│   ├── Session.php             # Quản lý phiên và chống tấn công CSRF
│   ├── Input.php               # Làm sạch dữ liệu đầu vào chống XSS
│   ├── Auth.php                # Xác thực người dùng công cụ và chuyển vai trò trực quan
│   ├── Policy.php              # Động cơ phân quyền RBAC/ABAC (Oracle) - Mặc định DENY
│   ├── TargetAllowlist.php     # Kiểm soát danh sách máy chủ an toàn
│   ├── HttpClient.php          # Gửi HTTP cURL thật và phân loại PASS / FAIL / ERROR
│   ├── Redactor.php            # Khử thông tin nhạy cảm (Bearer Token, mật khẩu)
│   ├── Runner.php              # Điều phối chạy kiểm thử, snapshotting và config_hash
│   ├── Report.php              # Xuất báo cáo Markdown và HTML độc lập
│   ├── RunStats.php            # Thống kê kết quả chạy bằng LEFT JOIN
│   ├── ProjectRepository.php   # Quản lý dự án, vai trò, tài nguyên, hành động, ma trận, bản ghi
│   ├── CaseRepository.php      # Quản lý ca thử (chặn vai trò viewer sửa ca)
│   └── RunRepository.php       # Lưu trữ lần chạy, snapshot kết quả, so sánh Full Outer Join
├── public/                     # Giao diện Web công cụ AccessTester (Cổng 8105)
│   ├── index.php               # Bảng điều khiển tổng quan
│   ├── projects.php            # Quản lý dự án, ma trận quyền và thành viên
│   ├── cases.php               # Quản lý danh sách 24 ca thử
│   ├── runs.php                # Thực thi kiểm thử và lịch sử
│   ├── compare.php             # So sánh đối chiếu trước/sau sửa lỗi
│   ├── report.php              # Xem và tải báo cáo (.md / .html)
│   └── assets/css/             # Thiết kế giao diện (tokens.css, app.css)
├── sample-app/                 # Ứng dụng mẫu đối chứng (Cổng 8205)
│   └── public/index.php        # 6 REST API kèm công tắc lỗi cố ý BUG_IDOR
├── bin/
│   └── run.php                 # CLI Runner độc lập
├── tools/
│   ├── db-config.php           # Cấu hình CSDL tương tác
│   ├── db-check.php            # Kiểm tra trạng thái CSDL
│   └── sql.php                 # Mở psql nhanh
├── tests/
│   ├── helper.php              # Khung kiểm thử tự động
│   ├── run.php                 # Unit tests (39 assertions)
│   ├── integration.php         # Integration tests CSDL & RBAC/ABAC (29 assertions)
│   ├── e2e.php                 # E2E tests 5 phép thử bắt buộc (20 assertions)
│   └── fixtures/answer_key.json# Đáp án chuẩn đối chứng 24 ca và 5 phép thử
└── docs/
    ├── KIEN_TRUC.md            # Tài liệu thiết kế kiến trúc hệ thống
    ├── HUONG_DAN.md            # Hướng dẫn chi tiết từng bước và câu hỏi vấn đáp
    └── NGUON.md                # Nguồn gốc mã nguồn và ghi công
```

---

## 4. Tài Khoản Phân Quyền Trong Hệ Thống

Mật khẩu mặc định cho toàn bộ tài khoản người dùng công cụ là: **`AccessTest@2026`**

| Tên đăng nhập | Họ và tên | Vai trò trong Dự án A | Quyền hạn trong công cụ |
| :--- | :--- | :---: | :--- |
| **`phamle`** | Phạm Lê (Trưởng nhóm) | `owner` | Toàn quyền cấu hình, sửa đổi ca thử, chạy kiểm thử, xem báo cáo |
| **`dev1`** | Lập trình viên 1 | `editor` | Chỉnh sửa ca thử nghiệm, chạy kiểm thử, xem báo cáo |
| **`auditor_user`** | Kiểm toán viên | `viewer` | **Chỉ xem** cấu hình và kết quả. Bị chặn khi cố gắng thêm/sửa/xóa ca thử (Phép thử 4) |
| **`dev2`** | Thành viên nhóm khác | `owner` (Dự án B) | Không thuộc Dự án A. Tuyệt đối không xem hoặc sửa được ca của Dự án A |

> **Tính năng chuyển vai trò nhanh trên giao diện Web:**  
> Tại góc trên bên phải của thanh điều hướng, người dùng có thể bấm vào menu chuyển tài khoản để đổi vai trò tức thì mà không cần đăng xuất/đăng nhập lại.

---

## 5. Danh Sách 6 REST API của Ứng Dụng Mẫu (Sample App)

Ứng dụng mẫu (`sample-app/public/index.php`) chạy tại cổng **8205**, phục vụ kiểm thử:

| STT | Phương thức | Đường dẫn API | Mô tả nghiệp vụ | Yêu cầu quyền | Hành vi khi `BUG_IDOR=1` | Hành vi khi `BUG_IDOR=0` |
| :---: | :---: | :--- | :--- | :--- | :--- | :--- |
| 1 | `GET` | `/api/announcements` | Xem thông báo chung | Mọi người dùng (`member`, `admin`, `auditor`) | 200 OK | 200 OK |
| 2 | `GET` | `/api/notes/{id}` | Xem nội dung ghi chú | Chỉ chủ sở hữu ghi chú hoặc `admin` | **Lỗ hổng:** Cho phép Alice đọc ghi chú số 3 của Bob | **Sửa lỗi:** Trả về 403 Forbidden |
| 3 | `POST` | `/api/notes` | Tạo ghi chú mới | `member`, `admin` | 201 Created | 201 Created |
| 4 | `PUT` | `/api/notes/{id}` | Cập nhật ghi chú | Chỉ chủ sở hữu ghi chú | Cấm sửa nếu không phải chủ sở hữu | Cấm sửa nếu không phải chủ sở hữu |
| 5 | `DELETE`| `/api/notes/{id}` | Xóa ghi chú | Chỉ chủ sở hữu ghi chú | Cấm xóa nếu không phải chủ sở hữu | Cấm xóa nếu không phải chủ sở hữu |
| 6 | `GET` | `/api/users` | Xem danh sách người dùng | Chỉ `admin` | Trả về 403 nếu là `member`/`auditor` | Trả về 403 nếu là `member`/`auditor` |

---

## 6. Hướng Dẫn Vận Hành Từng Bước

### Bước 1: Khởi tạo CSDL nhanh (1 lệnh)
```bash
# Nạp cấu hình kết nối (chỉ cần chạy lần đầu)
bash lab.sh db-config

# Nạp tự động Schema và Dữ liệu mẫu (schema.sql + seed.sql)
bash lab.sh setup
```

### Bước 2: Kiểm tra môi trường
```bash
bash lab.sh check
```
*Kết quả xuất hiện dòng `MOI_TRUONG=SAN_SANG`.*

---

### Bước 3: Minh họa 2 Tình Huống Demo (Trước và Sau Sửa Lỗi)

#### Tình huống 1: Phát hiện lỗ hổng IDOR trên bản phát hành lỗi
1. Khởi động Ứng dụng mẫu với chế độ bật lỗi cố ý:
   ```bash
   BUG_IDOR=1 bash lab.sh sample
   ```
2. Mở một terminal khác, nạp biến môi trường và chạy kiểm thử lần 1:
   ```bash
   source env.local.sh
   bash lab.sh cli --project=1 --label=before-fix
   ```
   *Kết quả:* Ca **`R02` (Alice xem ghi chú 3 của Bob)** bị phân loại **`FAIL`** do phát hiện chuỗi dữ liệu rò rỉ (`leak_detected=true`). Tổng kết: 23 PASS, 1 FAIL. Mã thoát CLI là `1`.

#### Tình huống 2: Xác nhận khắc phục thành công sau khi sửa lỗi
1. Khởi động lại Ứng dụng mẫu ở chế độ đã sửa lỗi:
   ```bash
   BUG_IDOR=0 bash lab.sh sample
   ```
2. Chạy kiểm thử lần 2 với nhãn `after-fix`:
   ```bash
   bash lab.sh cli --project=1 --label=after-fix
   ```
   *Kết quả:* Toàn bộ **24/24 ca đạt `PASS`**. Mã thoát CLI là `0`.

3. So sánh đối chiếu hai lần chạy qua dòng lệnh:
   ```bash
   bash lab.sh cli --compare=1,2 --project=1
   ```
   *Bảng diff hiển thị:* Ca `R02` chuyển trạng thái từ `FAIL` -> `PASS`.

4. Xuất báo cáo Markdown hoặc HTML:
   ```bash
   bash lab.sh cli --report=2 --format=md
   bash lab.sh cli --report=2 --format=html
   ```

---

### Bước 4: Vận hành trên Giao diện Web Trực quan
1. Khởi động máy chủ Web công cụ:
   ```bash
   bash lab.sh run
   ```
2. Truy cập trình duyệt: **[http://127.0.0.1:8105](http://127.0.0.1:8105)**
   - Trang **Dự án**: Xem và chỉnh sửa ma trận phân quyền RBAC/ABAC, danh sách bản ghi và phân công thành viên.
   - Trang **Ca kiểm thử**: Xem chi tiết 24 ca HTTP, xuất cấu hình ca ra JSON.
   - Trang **Lần chạy**: Bấm nút **"Chạy kiểm thử ngay"** để thực hiện kiểm thử tự động trực tiếp trên trình duyệt.
   - Trang **So sánh**: Chọn lần chạy trước (`before-fix`) và lần chạy sau (`after-fix`) để thấy bảng đối chiếu highlight màu xanh rõ ràng.
   - Trang **Báo cáo**: Xem báo cáo chi tiết và tải file `.html` hoặc `.md` về máy.

---

### Bước 5: Chạy Bộ Kiểm Thử Tự Động (Test Suite)
Dự án được bảo vệ bởi bộ kiểm thử tự động 3 tầng (Unit, Integration, E2E):
```bash
bash lab.sh test
```
*Kết quả:* **88/88 assertions PASS (100% Đạt)**:
- **Unit Tests:** 39 tests (Policy default DENY, Allowlist, Classifier, Redactor token/password).
- **Integration Tests:** 29 tests (CSDL transactions, RBAC/ABAC, Case authorization, Snapshotting, RunStats).
- **End-to-End Tests:** 20 tests (Tự động hóa toàn bộ 5 phép thử bắt buộc).

---

### Bước 6: Đóng Gói Bản Phát Hành (Release)
Lệnh đóng gói tự động chạy lại toàn bộ test suite và xuất file nén phân phối:
```bash
bash lab.sh release
```
*Tệp phát hành:* `build/accesstest-release-YYYYMMDD.tar.gz`

---

## 7. Báo Cáo 5 Phép Thử Bắt Buộc

| STT | Phép thử bắt buộc | Cách kích hoạt và kiểm chứng | Kết quả thực tế |
| :---: | :--- | :--- | :---: |
| **1** | **Phát hiện lỗi cố ý** | Chạy kiểm thử khi ứng dụng mẫu bật `BUG_IDOR=1`. Ca `R02` (Alice đọc note 3 của Bob) trả về HTTP 200 và nội dung chứa `Bi mat cua Bob 3`. | **FAIL** (Đúng kỳ vọng, gắn cờ `leak_detected=true`) |
| **2** | **Sửa lỗi và kiểm tra lại** | Tắt lỗi với `BUG_IDOR=0`. Ứng dụng mẫu trả về HTTP 403 Forbidden. Chạy lại kiểm thử và so sánh diff. | **PASS** (Ca R02 chuyển từ `FAIL` -> `PASS`, 24/24 ca đạt) |
| **3** | **Cổng đóng hoặc đích cấm** | Gửi yêu cầu tới cổng không mở (ví dụ `8299`) hoặc địa chỉ IP/domain ngoài whitelist (`http://evil.com`). | **ERROR** (Mã `CONNECT_FAILED` hoặc `TARGET_NOT_ALLOWED`. Tuyệt đối không coi là DENY) |
| **4** | **Bảo vệ cấu hình ca thử** | Đăng nhập tài khoản `auditor_user` (vai trò `viewer`) hoặc `dev2` (thuộc dự án khác) gửi yêu cầu sửa/xóa ca thử. | **FORBIDDEN** (Hệ thống chặn cả trên Web UI và ném ngoại lệ tầng CSDL Repository) |
| **5** | **Không rò rỉ token bí mật** | Quét toàn bộ nội dung báo cáo Markdown, HTML và nhật ký `.local/runner.log`. | **AN TOÀN** (Toàn bộ Bearer token và password được thay thế bằng chuỗi `***REDACTED***`) |

---

## 8. Giấy Phép & Ghi Công

- **Bản quyền & Giấy phép:** Mã nguồn phát hành theo giấy phép tự do [MIT License](LICENSE).
- **Tài liệu kiểm thử chi tiết:** Hướng dẫn kiểm thử và kết quả mong đợi từng ca xem tại [KiemThu.md](KiemThu.md).
- **Nguồn gốc kế thừa:** Nền tảng cấu trúc lệnh và kết nối CSDL từ bài thực hành `lab5` (FIT-DNU/Open-Source-Software-Development). Chi tiết ghi nhận công trạng được trình bày đầy đủ tại [docs/NGUON.md](docs/NGUON.md).
