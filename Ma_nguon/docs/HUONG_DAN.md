# Hướng dẫn Cài đặt, Vận hành và Ngân hàng Câu hỏi Bảo vệ Đề tài

**Học phần:** Phát triển phần mềm mã nguồn mở  
**Đề tài 4:** Xây dựng công cụ kiểm thử tự động quyền truy cập trong ứng dụng Web (AccessTester)  
**Người thực hiện:** Phạm Lê — Nhóm 14  
**Nền tảng:** Ubuntu / WSL 2, PHP 8.1+, PostgreSQL 14+

---

## Mục lục
1. [Yêu cầu Môi trường](#1-yêu-cầu-môi-trường)
2. [Cài đặt & Cấu hình Nhanh](#2-cài-đặt--cấu-hình-nhanh)
3. [Tài khoản Thử nghiệm Trong Hệ thống](#3-tài-khoản-thử-nghiệm-trong-hệ-thống)
4. [Hướng dẫn Thực nghiệm 2 Tình huống Demo](#4-hướng-dẫn-thực-nghiệm-2-tình-huống-demo)
5. [Quy trình Tự động hóa 5 Phép thử Bắt buộc](#5-quy-trình-tự-động-hóa-5-phép-thử-bắt-buộc)
6. [Hướng dẫn Vận hành Giao diện Web Trực quan](#6-hướng-dẫn-vận-hành-giao-diện-web-trực-quan)
7. [Đóng gói Bản Phát hành (Release)](#7-đóng-gói-bản-phát-hành-release)
8. [Ngân hàng Câu hỏi Vấn đáp Bảo vệ Đề tài](#8-ngân-hàng-câu-hỏi-vấn-đáp-bảo-vệ-đề-tài)

---

## 1. Yêu cầu Môi trường

Hệ thống hoạt động chuẩn mực trên môi trường Linux Ubuntu hoặc Windows Subsystem for Linux (WSL 2). Các gói phụ thuộc cần thiết gồm:
```bash
sudo apt update
sudo apt install -y php-cli php-pgsql php-curl postgresql postgresql-client git
```

Đảm bảo dịch vụ PostgreSQL đang hoạt động:
```bash
sudo service postgresql start
```

---

## 2. Cài đặt & Cấu hình Nhanh

### Bước 1: Khởi tạo Cơ sở dữ liệu PostgreSQL
Chạy `psql` với tài khoản quản trị hệ thống `postgres`:
```bash
sudo -u postgres psql
```
Thực thi các lệnh SQL sau:
```sql
CREATE DATABASE accesstest_lab;
CREATE USER accesstest_lab WITH ENCRYPTED PASSWORD 'matkhau_lab_123';
GRANT ALL PRIVILEGES ON DATABASE accesstest_lab TO accesstest_lab;
ALTER DATABASE accesstest_lab OWNER TO accesstest_lab;
\q
```

### Bước 2: Cấu hình kết nối công cụ (chỉ chạy 1 lần)
Tại thư mục `Ma_nguon`, thực hiện:
```bash
bash lab.sh db-config
```
- Nhấn `Enter` để chọn mặc định máy chủ `127.0.0.1`, cổng `5432`, CSDL `accesstest_lab`, tài khoản `accesstest_lab`.
- Khi đến phần mật khẩu, gõ: `matkhau_lab_123` rồi nhấn `Enter`.

### Bước 3: Nạp cấu trúc bảng và dữ liệu mẫu (1 lệnh duy nhất)
Công cụ đã tích hợp sẵn lệnh nạp tự động không cần mở `psql` thủ công:
```bash
bash lab.sh setup
```
Lệnh này sẽ nạp tuần tự `database/schema.sql` và `database/seed.sql`, nạp 24 ca thử nghiệm chuẩn, 4 người dùng công cụ và 4 tài khoản ứng dụng mẫu.

### Bước 4: Kiểm tra trạng thái môi trường
```bash
bash lab.sh check
```
*Kết quả xuất hiện dòng `MOI_TRUONG=SAN_SANG | Đề tài 4 (Lab 5)` là sẵn sàng.*

---

## 3. Tài khoản Thử nghiệm Trong Hệ thống

Mật khẩu đăng nhập mặc định cho toàn bộ người dùng công cụ là: **`AccessTest@2026`**

| Tài khoản | Họ và tên | Vai trò Dự án A | Quyền hạn thực tế |
| :--- | :--- | :---: | :--- |
| **`phamle`** | Phạm Lê | `owner` | Toàn quyền cấu hình dự án, sửa ca, chạy kiểm thử và xem báo cáo |
| **`dev1`** | Lập trình viên 1 | `editor` | Thêm, sửa, xóa ca kiểm thử và chạy kiểm thử |
| **`auditor_user`** | Kiểm toán viên | `viewer` | **Chỉ được xem**, bị hệ thống chặn nếu cố gắng sửa/xóa ca (Phép thử 4) |
| **`dev2`** | Thành viên nhóm khác | `owner` (Dự án B) | Không thuộc Dự án A, bị cấm xem/sửa cấu hình Dự án A |

> **Mẹo trên Web:** Sử dụng menu chuyển tài khoản ở góc trên cùng bên phải giao diện Web để chuyển đổi qua lại giữa các vai trò tức thì mà không cần đăng nhập lại.

---

## 4. Hướng dẫn Thực nghiệm 2 Tình huống Demo

### Tình huống 1: Phát hiện lỗ hổng IDOR trên ứng dụng lỗi
1. Khởi động Ứng dụng mẫu ở chế độ bật lỗi:
   ```bash
   BUG_IDOR=1 bash lab.sh sample
   ```
2. Mở một cửa sổ terminal khác tại thư mục `Ma_nguon`, nạp biến môi trường và chạy kiểm thử lần 1:
   ```bash
   source env.local.sh
   bash lab.sh cli --project=1 --label=before-fix
   ```
3. **Quan sát kết quả:**
   - 23 ca đạt `PASS`.
   - 1 ca báo `FAIL` tại mã ca **`R02`** (Alice đọc ghi chú số 3 của Bob).
   - Công cụ phát hiện chuỗi dữ liệu cấm rò rỉ: `"Bi mat cua Bob 3"` (`leak_detected=true`).
   - Mã thoát của lệnh CLI là `1` (báo hiệu phát hiện lỗ hổng phân quyền).

### Tình huống 2: Xác nhận khắc phục lỗ hổng sau khi sửa lỗi
1. Khởi động lại Ứng dụng mẫu ở chế độ đã sửa lỗi:
   ```bash
   BUG_IDOR=0 bash lab.sh sample
   ```
2. Chạy kiểm thử lần 2 với nhãn `after-fix`:
   ```bash
   bash lab.sh cli --project=1 --label=after-fix
   ```
3. **Quan sát kết quả:**
   - Toàn bộ **24/24 ca đạt `PASS`**.
   - Ca `R02` lúc này trả về HTTP 403 Forbidden đúng như thiết kế của Policy Oracle.
   - Mã thoát của lệnh CLI là `0` (báo hiệu toàn bộ ca thử đều đạt chuẩn).

4. **So sánh đối chiếu trước và sau sửa lỗi:**
   ```bash
   bash lab.sh cli --compare=1,2 --project=1
   ```
   *Bảng diff hiển thị rõ ràng: Ca `R02` chuyển trạng thái từ `FAIL` sang `PASS`.*

5. **Xuất báo cáo kết quả:**
   ```bash
   bash lab.sh cli --report=2 --format=md
   bash lab.sh cli --report=2 --format=html
   ```

---

## 5. Quy trình Tự động hóa 5 Phép thử Bắt buộc

Toàn bộ 5 phép thử bắt buộc được tự động hóa 100% trong tệp kiểm thử `tests/e2e.php`. Bạn có thể chạy toàn bộ bộ kiểm thử với 1 lệnh:
```bash
bash lab.sh test
```
*Kết quả: **88/88 test assertions PASS**.*

Dưới đây là chi tiết kiểm chứng của từng phép thử:

1. **Phép thử 1: Phát hiện lỗi cố ý (`BUG_IDOR=1`):**
   - Ứng dụng mẫu cho phép người dùng xem dữ liệu của người khác.
   - Công cụ gửi HTTP cURL thật tới `/api/notes/3` với token của Alice.
   - Kết quả phản hồi chứa chuỗi rò rỉ `must_not_contain = 'Bi mat cua Bob 3'`.
   - Công cụ phân loại ca là `FAIL` với `leak_detected=true`.

2. **Phép thử 2: Sửa lỗi và kiểm tra lại (`BUG_IDOR=0`):**
   - Ứng dụng mẫu chặn truy cập trái phép bằng HTTP 403 Forbidden.
   - Chạy lại kiểm thử: Ca `R02` chuyển thành `PASS`, toàn bộ 24 ca đạt.
   - Truy vấn so sánh hiển thị rõ ràng dòng chuyển biến từ `FAIL` sang `PASS`.

3. **Phép thử 3: Cổng đóng hoặc máy chủ ngoài danh sách duyệt:**
   - Gửi yêu cầu tới cổng không mở (ví dụ cổng `8299`) hoặc địa chỉ ngoài danh sách (`http://malicious-site.com`).
   - `TargetAllowlist` hoặc cURL bắt lỗi mạng (`CONNECT_FAILED` hoặc `TARGET_NOT_ALLOWED`).
   - Kết quả phân loại **luôn là `ERROR`**, tuyệt đối không coi là `DENY` hay `PASS`.

4. **Phép thử 4: Bảo vệ cấu hình ca thử nghiệm:**
   - Tài khoản `auditor_user` (vai trò `viewer`) hoặc tài khoản `dev2` (thuộc dự án khác) cố tình gửi yêu cầu sửa hoặc xóa ca thử.
   - Hệ thống chặn trực tiếp trên giao diện và ném ngoại lệ tầng CSDL `FORBIDDEN`.

5. **Phép thử 5: Bảo vệ thông tin nhạy cảm (Không rò rỉ token):**
   - Toàn bộ Bearer token của Alice, Bob, Admin, Auditor và mật khẩu CSDL được `Redactor` khử triệt để khỏi file báo cáo Markdown, HTML và nhật ký log `.local/runner.log`.

---

## 6. Hướng dẫn Vận hành Giao diện Web Trực quan

Khởi động máy chủ Web công cụ:
```bash
bash lab.sh run
```
Mở trình duyệt tại địa chỉ: **[http://127.0.0.1:8105](http://127.0.0.1:8105)**

1. **Trang Dự án (`projects.php`):**
   - Xem thông tin dự án, danh sách thành viên và ma trận phân quyền RBAC/ABAC.
   - Bảng ánh xạ quyền hỗ trợ cấu hình quyền theo phạm vi `any` (toàn bộ) hoặc `own` (chỉ bản ghi sở hữu).
2. **Trang Ca kiểm thử (`cases.php`):**
   - Xem 24 ca thử được lọc theo tài khoản hoặc phương thức HTTP.
   - Hỗ trợ xuất cấu hình ca ra JSON với nút "Xuất JSON".
3. **Trang Lần chạy (`runs.php`):**
   - Bấm nút **"Chạy kiểm thử ngay"** để công cụ kích hoạt chạy toàn bộ ca thử HTTP thật trong nền và hiển thị thanh tiến độ kết quả.
   - Xem lịch sử các lần chạy kèm mã băm `config_hash`.
4. **Trang So sánh (`compare.php`):**
   - Chọn lần chạy trước (`before-fix`) và lần chạy sau (`after-fix`).
   - Nhấn **"So sánh ngay"** để xem bảng diff phân màu trực quan.
5. **Trang Báo cáo (`report.php`):**
   - Xem báo cáo tổng hợp tỷ lệ PASS/FAIL/ERROR.
   - Tải file báo cáo định dạng Markdown (`.md`) hoặc HTML (`.html`).

---

## 7. Đóng gói Bản Phát hành (Release)

Khi hoàn thiện bài nộp hoặc chuẩn bị bàn giao cho giảng viên:
```bash
bash lab.sh release
```
Lệnh này sẽ tự động:
1. Chạy lại toàn bộ bộ kiểm thử tự động 88 ca để đảm bảo không có lỗi tiềm ẩn.
2. Tạo gói nén phát hành chuẩn: `build/accesstest-release-YYYYMMDD.tar.gz`.
3. Tự động loại trừ các thư mục nhạy cảm cục bộ như `.local/`, `tests/fixtures/*.tmp`, các file log bí mật.

---

## 8. Ngân hàng Câu hỏi Vấn đáp Bảo vệ Đề tài

### Câu 1: Sự khác biệt cốt lõi giữa RBAC và ABAC trong công cụ này là gì?
> **Trả lời:**  
> - **RBAC (Role-Based Access Control):** Quyết định quyền dựa trên vai trò của người dùng (ví dụ: `admin` được xem danh sách toàn bộ người dùng `/api/users`, `member` thì bị từ chối).  
> - **ABAC (Attribute-Based Access Control):** Quyết định quyền dựa trên thuộc tính của ngữ cảnh và tài nguyên, cụ thể trong đề tài là **quyền sở hữu bản ghi** (`scope = 'own'`). Ví dụ: Cả Alice và Bob đều là `member`, nhưng Alice chỉ được xem/sửa ghi chú có `user_id = 1` của chính mình; nếu Alice gọi API xem ghi chú có `user_id = 2` của Bob thì hệ thống ABAC phải từ chối ngay lập tức.

### Câu 2: Tại sao nguyên tắc "Mặc định là từ chối (DENY)" lại tối quan trọng?
> **Trả lời:**  
> Trong thiết kế an toàn thông tin (Secure by Default), nếu một tài nguyên hoặc hành động mới phát sinh chưa được định nghĩa tường minh trong ma trận phân quyền, hệ thống bắt buộc phải từ chối truy cập (`DENY`). Nếu để mặc định là `ALLOW`, lập trình viên khi thêm API mới mà quên khai báo quyền sẽ vô tình tạo ra lỗ hổng bảo mật nghiêm trọng cho phép mọi người dùng truy cập trái phép.

### Câu 3: Làm thế nào công cụ phân biệt được sự cố mất kết nối mạng với việc bị từ chối truy cập (DENY)?
> **Trả lời:**  
> Đây là yêu cầu của **Phép thử 3**. Nếu một máy chủ bị đóng cổng (Connection Refused) hoặc sập mạng, nó không hề phản hồi mã HTTP 403 Forbidden mà cURL sẽ trả về lỗi kết nối (`CURLE_COULDNT_CONNECT`).  
> Trong `src/HttpClient.php`, hệ thống kiểm tra: nếu cURL gặp mã lỗi mạng, kết quả phân loại **bắt buộc phải là `ERROR`**. Tuyệt đối không được coi việc không lấy được dữ liệu do sập mạng là "hệ thống đã từ chối truy cập thành công (`DENY`/`PASS`)".

### Câu 4: Snapshotting giải quyết bài toán gì trong việc bảo toàn lịch sử kiểm thử?
> **Trả lời:**  
> Nếu bảng `run_results` chỉ lưu `test_case_id` rồi dùng câu lệnh `JOIN test_cases` để hiển thị lịch sử, thì khi người dùng sửa phương thức HTTP, đổi đường dẫn API hoặc xóa ca thử trong tương lai, toàn bộ dữ liệu lịch sử của các lần chạy trong quá khứ sẽ bị sai lệch hoặc mất dấu.  
> Bằng cách chụp snapshot độc lập các trường `case_code`, `method`, `path`, `expected_status` và lưu `config_hash` tại thời điểm chạy, lịch sử của AccessTester mang tính **bất biến (Immutable)**.

### Câu 5: Cơ chế Redactor hoạt động như thế nào để đảm bảo không rò rỉ token bí mật?
> **Trả lời:**  
> `src/Redactor.php` tự động đăng ký danh sách các token nhạy cảm từ biến môi trường (`ACCESS_TOKEN_ALICE`, `ACCESS_TOKEN_BOB`, `ACCESS_TOKEN_ADMIN`, ...) và mật khẩu CSDL. Khi chuẩn bị ghi nhật ký vào `.local/runner.log`, lưu kết quả vào CSDL hoặc xuất báo cáo Markdown/HTML, toàn bộ chuỗi tiêu đề `Authorization: Bearer <token>`, tham số `token=...`, và các thuộc tính JSON chứa chuỗi bí mật đều được quét regex và thay thế bằng `***REDACTED***`.
