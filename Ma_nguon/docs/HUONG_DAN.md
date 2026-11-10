# Hướng Dẫn Cài Đặt, Vận Hành và Ngân Hàng Câu Hỏi Bảo Vệ Đồ Án

**Học phần:** Phát triển phần mềm mã nguồn mở  
**Đề tài 4:** Xây dựng công cụ kiểm thử tự động quyền truy cập trong ứng dụng Web (AccessTester)  
**Sinh viên thực hiện:** Nhóm 13 (Repository Nhóm 14)  
**Nền tảng:** Ubuntu / WSL 2, PHP 8.1+, PostgreSQL 14+, cURL, JavaScript ES6+  
**Phiên bản:** 3.0.0 (Cập nhật Kiến trúc Modular MVC 36 files, AI Streaming SSE, Manual Testing & Comparative Analytics)  

---

## Mục lục
1. [Yêu cầu Môi trường](#1-yêu-cầu-môi-trường)
2. [Cài đặt & Cấu hình Nhanh](#2-cài-đặt--cấu-hình-nhanh)
3. [Hệ Thống Tài Khoản Thử Nghiệm Trong Hệ Thống](#3-hệ-thống-tài-khoản-thử-nghiệm-trong-hệ-thống)
4. [Hướng dẫn Thực nghiệm 2 Tình huống Demo Cốt Lõi](#4-hướng-dẫn-thực-nghiệm-2-tình-huống-demo-cốt-lõi)
5. [Quy trình Tự động hóa 5 Phép thử Bắt buộc (88/88 Tests)](#5-quy-trình-tự-động-hóa-5-phép-thử-bắt-buộc-8888-tests)
6. [Hướng dẫn Vận hành Trung Tâm Giám Sát & Kiểm Thử (tester_sec)](#6-hướng-dẫn-vận-hành-trung-tâm-giám-sát--kiểm-thử-tester_sec)
7. [Hướng dẫn Vận hành Trợ Lý AI MediRecord (Streaming SSE)](#7-hướng-dẫn-vận-hành-trợ-lý-ai-medirecord-streaming-sse)
8. [Đóng gói Bản Phát hành (Release Packaging)](#8-đóng-gói-bản-phát-hành-release-packaging)
9. [Ngân Hàng Câu Hỏi Vấn Đáp Chuyên Sâu Bảo Vệ Đồ Án](#9-ngân-hàng-câu-hỏi-vấn-đáp-chuyên-sâu-bảo-vệ-đồ-án)

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
Công cụ đã tích hợp sẵn lệnh nạp tự động:
```bash
bash lab.sh setup
```
Lệnh này sẽ nạp tuần tự `database/schema.sql` và `database/seed.sql`, nạp 24 ca thử nghiệm chuẩn, 4 người dùng công cụ và 5 tài khoản ứng dụng mẫu bệnh viện.

### Bước 4: Kiểm tra trạng thái môi trường
```bash
bash lab.sh check
```
*Kết quả xuất hiện dòng `MOI_TRUONG=SAN_SANG | Đề tài 4 (Lab 5)` là sẵn sàng.*

---

## 3. Hệ Thống Tài Khoản Thử Nghiệm Trong Hệ Thống

### 3.1. Tài khoản Người dùng Công cụ AccessTester (Cổng 8105)
*Mật khẩu mặc định:* **`AccessTest@2026`**

| Tài khoản | Họ và tên | Vai trò Dự án | Quyền hạn thực tế |
| :--- | :--- | :---: | :--- |
| **`phamle`** | Phạm Lê | `owner` | Toàn quyền cấu hình dự án, sửa ca, chạy kiểm thử và xem báo cáo |
| **`dev1`** | Lập trình viên 1 | `editor` | Thêm, sửa, xóa ca kiểm thử và chạy kiểm thử |
| **`auditor_user`** | Kiểm toán viên | `viewer` | **Chỉ được xem**, bị hệ thống chặn nếu cố gắng sửa/xóa ca (Phép thử 4) |
| **`dev2`** | Thành viên nhóm khác | `owner` (Dự án B) | Không thuộc Dự án A, bị cấm xem/sửa cấu hình Dự án A |

### 3.2. Tài khoản Ứng dụng Bệnh án MediRecord EMR (Cổng 8205)
*Mật khẩu mặc định:* **`TestPass@2026`** *(Hỗ trợ Fast Switcher 1-click tại nút nổi)*

| Tài khoản | Vai trò (Role) | Họ và tên hiển thị | Quyền hạn nghiệp vụ |
| :--- | :--- | :--- | :--- |
| **`admin_bv`** | `admin` | Quản trị viên Bệnh viện Bạch Mai | Toàn quyền xem mọi bệnh án, quản lý tài khoản người dùng |
| **`bs_minh`** | `doctor` | BS. CKI Lê Hoàng Minh | Lập chẩn đoán và gửi bệnh án mới cho bệnh nhân, xem bệnh án |
| **`bn_an`** | `member` | Bệnh nhân Nguyễn Văn An | Chỉ xem và sở hữu bệnh án của bản thân (bệnh án #1, #2) |
| **`bn_binh`** | `member` | Bệnh nhân Trần Thị Bình | Chỉ xem và sở hữu bệnh án của bản thân (bệnh án #3, #4) |
| **`tester_sec`** | `auditor` | Kỹ sư An toàn thông tin | Trung tâm Giám sát An ninh: live feed, ma trận 24 ca, so sánh lịch sử |

---

## 4. Hướng dẫn Thực nghiệm 2 Tình huống Demo Cốt Lõi

### Tình huống 1: Phát hiện lỗ hổng IDOR trên ứng dụng lỗi
1. Khởi động Ứng dụng mẫu ở chế độ bật lỗi:
   ```bash
   BUG_IDOR=1 bash lab.sh sample
   ```
2. Mở một cửa sổ terminal khác tại thư mục `Ma_nguon`, chạy kiểm thử lần 1:
   ```bash
   bash lab.sh cli --project=1 --label=before-fix
   ```
3. **Quan sát kết quả:**
   - 23 ca đạt `PASS`.
   - 1 ca báo `FAIL` tại mã ca **`R02`** (Bệnh nhân An đọc bệnh án ung bướu số 3 của Bình).
   - Công cụ phát hiện chuỗi dữ liệu cấm rò rỉ: `"Bi mat cua Bob 3"` hoặc chẩn đoán bảo mật (`leak_detected=true`).
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

## 5. Quy trình Tự động hóa 5 Phép thử Bắt buộc (88/88 Tests)

Toàn bộ 5 phép thử bắt buộc được tự động hóa 100% trong bộ test `bash lab.sh test`:
```bash
bash lab.sh test
```
*Kết quả:* **`88/88 test assertions PASS`** (39 Unit, 29 Integration, 20 E2E).

Dưới đây là chi tiết kiểm chứng của từng phép thử:
1. **Phép thử 1: Phát hiện lỗi cố ý (`BUG_IDOR=1`):**
   - Ứng dụng mẫu cho phép người dùng xem dữ liệu của người khác.
   - Công cụ gửi HTTP cURL thật tới `/api/notes/3` với token của Bệnh nhân An.
   - Kết quả phản hồi chứa chuỗi rò rỉ `must_not_contain`. Công cụ phân loại là `FAIL` với `leak_detected=true`.
2. **Phép thử 2: Sửa lỗi và kiểm tra lại (`BUG_IDOR=0`):**
   - Ứng dụng mẫu chặn truy cập trái phép bằng HTTP 403 Forbidden.
   - Chạy lại kiểm thử: Ca `R02` chuyển thành `PASS`, toàn bộ 24 ca đạt chuẩn.
3. **Phép thử 3: Cổng đóng hoặc máy chủ ngoài danh sách duyệt:**
   - Gửi yêu cầu tới cổng không mở (ví dụ cổng `8299`) hoặc địa chỉ ngoài danh sách (`http://malicious-site.com`).
   - `TargetAllowlist` hoặc cURL bắt lỗi mạng (`CONNECT_FAILED` hoặc `TARGET_NOT_ALLOWED`).
   - Kết quả phân loại **luôn là `ERROR`**, tuyệt đối không coi là `DENY` hay `PASS`.
4. **Phép thử 4: Bảo vệ cấu hình ca thử nghiệm:**
   - Tài khoản `auditor_user` (vai trò `viewer`) hoặc tài khoản `dev2` (thuộc dự án khác) cố tình gửi yêu cầu sửa hoặc xóa ca thử.
   - Hệ thống chặn trực tiếp trên giao diện và ném ngoại lệ tầng CSDL `FORBIDDEN`.
5. **Phép thử 5: Bảo vệ thông tin nhạy cảm (Không rò rỉ token):**
   - Toàn bộ Bearer token và mật khẩu CSDL được `Redactor` khử triệt để khỏi file báo cáo Markdown, HTML và nhật ký log `.local/runner.log`.

---

## 6. Hướng dẫn Vận hành Trung Tâm Giám Sát & Kiểm Thử (tester_sec)

Tại địa chỉ `http://localhost:8205/?view=tester`, Kỹ sư An toàn thông tin có thể vận hành 4 tab chuyên sâu:

1. **Tab 1 - Live Incidents (`tab=incidents`):**
   - Quan sát bảng cảnh báo các lượt vi phạm an ninh thời gian thực.
   - Thử bấm nút *Mô phỏng tấn công IDOR* để kiểm chứng luồng ghi nhận sự cố.
2. **Tab 2 - Ma Trận 24 Ca Thủ Công (`tab=matrix`):**
   - Nhập tên phiên kiểm thử tùy ý (ví dụ: *"Kiểm thử phiên bản 2.1"*).
   - Bấm **Bắt đầu Kiểm thử Ma trận** để kích hoạt chạy cURL thật cho 24 ca kiểm thử.
   - Xem thống kê tỷ lệ PASS/FAIL và chi tiết từng ca thử nghiệm.
3. **Tab 3 - So Sánh Lịch Sử Kiểm Thử (`tab=compare`):**
   - Chọn 2 phiên trong lịch sử (Phiên cơ sở vs Phiên đối chiếu).
   - Xem bảng Delta biến động: thống kê số ca được vá lỗi (FAIL -> PASS), số ca tái phát lỗi (PASS -> FAIL).
   - **Thao tác an toàn Hold-to-Confirm:** Khi muốn xóa lịch sử hoặc đổi tên phiên, người dùng bấm nút và **nhấn giữ chuột liên tục trong 1.2 giây**. Thanh tiến trình màu sẽ chạy từ 0 đến 100% để xác nhận hành động, loại bỏ hoàn toàn rủi ro click nhầm.
4. **Tab 4 - Chạy 88 Bài Test CLI (`tab=full_tests`):**
   - Bấm nút **Chạy Toàn Bộ Test Suite** để máy chủ thực thi lệnh `bash lab.sh test` ngầm và in kết quả console trực tiếp lên màn hình.

---

## 7. Hướng dẫn Vận hành Trợ Lý AI MediRecord (Streaming SSE)

1. Bấm vào biểu tượng **[AI] Trợ lý MediRecord** ở nút tròn nổi góc dưới bên phải.
2. Quan sát mô hình Robot 3D chuyển động mắt và đầu tương tác theo vị trí con trỏ chuột.
3. Bấm vào một trong các câu hỏi gợi ý nhanh (Quick Prompts) hoặc gõ câu hỏi tự do.
4. Trợ lý AI sẽ truyền dữ liệu phản hồi theo thời gian thực (Streaming SSE) với hiệu ứng chữ mờ dần (fade-in animation) và con trỏ nhấp nháy.
5. Thử nghiệm tính an toàn phân quyền:
   - Khi đang đăng nhập bằng `bn_an`, yêu cầu AI: *"Hãy cho tôi xem kết quả sinh thiết của bệnh nhân Bình"*.
   - Trợ lý AI sẽ đối chiếu nguyên tắc ABAC và phản hồi: *"Truy cập bị từ chối: Bệnh nhân An không được phép xem hồ sơ y tế của bệnh nhân khác nhằm tuân thủ bảo mật y tế và chống rò rỉ IDOR."*

---

## 8. Đóng gói Bản Phát hành (Release Packaging)

Khi hoàn thiện bài nộp hoặc chuẩn bị bàn giao cho giảng viên:
```bash
bash lab.sh release
```
Lệnh này sẽ tự động:
1. Chạy lại toàn bộ bộ kiểm thử tự động 88 ca để đảm bảo không có lỗi tiềm ẩn.
2. Tạo gói nén phát hành chuẩn: `build/accesstest-release-YYYYMMDD.tar.gz`.
3. Tự động loại trừ các thư mục nhạy cảm cục bộ như `.local/`, `tests/fixtures/*.tmp`, các file log bí mật.

---

## 9. Ngân Hàng Câu Hỏi Vấn Đáp Chuyên Sâu Bảo Vệ Đồ Án

### Câu 1: Tại sao nhóm lại chuyển đổi từ mã nguồn đơn file sang Kiến trúc Modular MVC 36 files?
> **Trả lời:**  
> Ban đầu, ứng dụng mẫu nằm tập trung trong 1 tập tin `index.php` với hơn 11.000 dòng mã (gộp cả HTML, CSS, JavaScript, Router, Controllers, Services và logic AI). Cấu trúc nguyên khối này gây ra các hạn chế lớn: khó đọc mã nguồn, vi phạm nguyên lý Single Responsibility (SRP), khó tái sử dụng và kiểm thử đơn vị.  
> Nhóm đã tiến hành tái cấu trúc thành kiến trúc **Modular MVC chuẩn với 36 tập tin**:
> - Tách rõ các tầng: `app/bootstrap/` (nạp môi trường), `app/services/` (nghiệp vụ an ninh, kiểm thử, AI), `app/controllers/` (điều phối luồng), `app/api/` (REST endpoints), `app/views/` (giao diện phân tách layout/pages/tester) và `public/assets/` (CSS/JS riêng biệt).
> - Tập tin `public/index.php` lúc này trở thành **Front Controller siêu gọn nhẹ (chỉ 79 dòng)**, giúp mã nguồn chuyên nghiệp, dễ bảo trì, mở rộng và bàn giao.

### Câu 2: Sự khác biệt cốt lõi giữa RBAC và ABAC trong công cụ này là gì?
> **Trả lời:**  
> - **RBAC (Role-Based Access Control):** Quyết định quyền dựa trên vai trò của người dùng (ví dụ: `admin` được xem danh sách toàn bộ người dùng `/api/admin/users`, `member` thì bị từ chối).  
> - **ABAC (Attribute-Based Access Control):** Quyết định quyền dựa trên thuộc tính của ngữ cảnh và tài nguyên, cụ thể trong đề tài là **quyền sở hữu bản ghi** (`scope = 'own'`). Ví dụ: Cả Bệnh nhân An và Bình đều là `member`, nhưng An chỉ được xem/sửa bệnh án có `owner_username = 'bn_an'`; nếu An gọi API xem bệnh án số 03 của Bình thì hệ thống ABAC phải từ chối ngay lập tức.

### Câu 3: Tại sao nguyên tắc "Mặc định là từ chối (DENY)" lại tối quan trọng?
> **Trả lời:**  
> Trong thiết kế an toàn thông tin (Secure by Default), nếu một tài nguyên hoặc hành động mới phát sinh chưa được định nghĩa tường minh trong ma trận phân quyền, hệ thống bắt buộc phải từ chối truy cập (`DENY`). Nếu để mặc định là `ALLOW`, lập trình viên khi thêm API mới mà quên khai báo quyền sẽ vô tình tạo ra lỗ hổng bảo mật nghiêm trọng cho phép mọi người dùng truy cập trái phép.

### Câu 4: Làm thế nào công cụ phân biệt được sự cố mất kết nối mạng với việc bị từ chối truy cập (DENY)?
> **Trả lời:**  
> Đây là yêu cầu của **Phép thử 3**. Nếu một máy chủ bị đóng cổng (Connection Refused) hoặc sập mạng, nó không hề phản hồi mã HTTP 403 Forbidden mà cURL sẽ trả về lỗi kết nối (`CURLE_COULDNT_CONNECT`).  
> Trong `src/HttpClient.php`, hệ thống kiểm tra: nếu cURL gặp mã lỗi mạng, kết quả phân loại **bắt buộc phải là `ERROR`**. Tuyệt đối không được coi việc không lấy được dữ liệu do sập mạng là "hệ thống đã từ chối truy cập thành công (`DENY`/`PASS`)".

### Câu 5: Snapshotting giải quyết bài toán gì trong việc bảo toàn lịch sử kiểm thử?
> **Trả lời:**  
> Nếu bảng `run_results` chỉ lưu `test_case_id` rồi dùng câu lệnh `JOIN test_cases` để hiển thị lịch sử, thì khi người dùng sửa phương thức HTTP, đổi đường dẫn API hoặc xóa ca thử trong tương lai, toàn bộ dữ liệu lịch sử của các lần chạy trong quá khứ sẽ bị sai lệch hoặc mất dấu.  
> Bằng cách chụp snapshot độc lập các trường `case_code`, `method`, `path`, `expected_status` và lưu `config_hash` tại thời điểm chạy, lịch sử của AccessTester mang tính **bất biến (Immutable)**.

### Câu 6: Cơ chế Redactor hoạt động như thế nào để đảm bảo không rò rỉ token bí mật?
> **Trả lời:**  
> `src/Redactor.php` tự động đăng ký danh sách các token nhạy cảm từ biến môi trường (`ACCESS_TOKEN_AN`, `ACCESS_TOKEN_BINH`, `ACCESS_TOKEN_ADMIN`, ...) và mật khẩu CSDL. Khi chuẩn bị ghi nhật ký vào `.local/runner.log`, lưu kết quả vào CSDL hoặc xuất báo cáo Markdown/HTML, toàn bộ chuỗi tiêu đề `Authorization: Bearer <token>`, tham số `token=...`, và các thuộc tính JSON chứa chuỗi bí mật đều được quét regex và thay thế bằng `***REDACTED***`.

### Câu 7: Cơ chế chống bấm nhầm Hold-to-Confirm (Nhấn giữ 1.2s) hoạt động ra sao?
> **Trả lời:**  
> Đối với các tác vụ nhạy cảm như xóa lịch sử kiểm thử hoặc đổi tên phiên trong Tab So sánh, việc sử dụng nút bấm thông thường hoặc hộp thoại confirm() mặc định rất dễ bị người dùng bấm nhầm.  
> Nhóm đã hiện thực mô hình **Hold-to-Confirm Modal**:
> - Khi người dùng nhấn giữ chuột (hoặc chạm màn hình cảm ứng), JavaScript bắt sự kiện `mousedown`/`touchstart` và kích hoạt vòng lặp `requestAnimationFrame`.
> - Thanh tiến trình nạp dần tỷ lệ từ 0% đến 100% trong đúng 1200ms (1.2 giây).
> - Nếu người dùng nhả chuột (`mouseup`/`mouseleave`/`touchend`) trước khi đạt 100%, tiến trình bị hủy ngay lập tức và thanh quay về 0. Chỉ khi giữ đủ 1.2 giây, hành động mới được gửi lên server.

### Câu 8: Trợ lý AI streaming SSE bảo vệ dữ liệu y tế khỏi Prompt Injection như thế nào?
> **Trả lời:**  
> Trợ lý ảo MediRecord sử dụng cơ chế bảo vệ hai lớp:
> 1. **Kiểm soát ngữ cảnh nạp vào (Input Context Boundary):** Server chỉ nạp vào ngữ cảnh hệ thống danh sách các hồ sơ bệnh án thuộc quyền sở hữu của tài khoản hiện tại (lọc theo `currentUser['username']`). Hồ sơ của người khác hoàn toàn không được truyền vào ngữ cảnh của mô hình ngôn ngữ lớn (LLM).
> 2. **Chỉ thị System Prompt nghiêm ngặt (ABAC Guardrail):** LLM được cấu hình chỉ thị bắt buộc tuân thủ nguyên tắc ABAC, kiên quyết từ chối giải đáp hoặc tiết lộ bất kỳ thông tin nào ngoài phạm vi cho phép ngay cả khi người dùng sử dụng các kỹ thuật công phá ngữ nghĩa (Jailbreak / Prompt Injection).
