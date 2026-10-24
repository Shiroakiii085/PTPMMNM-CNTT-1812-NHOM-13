# HƯỚNG DẪN SỬ DỤNG VÀ THỰC THI KIỂM THỬ: ỨNG DỤNG MẪU VÀ LỖI PHÂN QUYỀN CÓ KIỂM SOÁT
## TÀI LIỆU CHI TIẾT CHO YÊU CẦU 3 (ĐỀ TÀI 4 - NHÓM 13)

- **Học phần:** Phát triển phần mềm mã nguồn mở
- **Đề tài 4:** Xây dựng công cụ kiểm thử tự động quyền truy cập trong ứng dụng Web
- **Sinh viên thực hiện:** Nhóm 13
- **Thành phần tài liệu:** Hướng dẫn kỹ thuật và vận hành cho Chức năng số 3: *Ứng dụng PHP mẫu, các địa chỉ API, phân loại dữ liệu riêng/dùng chung và lỗi phân quyền có kiểm soát (IDOR/BOLA)*.
- **Tập tin tài liệu:** `3_Tutorial.md`

---

## MỤC LỤC
1. [Giới Thiệu Yêu Cầu Kỹ Thuật Số 3](#1-giới-thiệu-yêu-cầu-kỹ-thuật-số-3)
2. [Kiến Trúc & Lược Đồ Dữ Liệu Của Ứng Dụng Mẫu](#2-kiến-trúc--lược-đồ-dữ-liệu-của-ứng-dụng-mẫu)
3. [Bảng Đặc Tả Chi Tiết Các Địa Chỉ API](#3-bảng-đặc-tả-chi-tiết-các-địa-chỉ-api)
4. [Phân Định Dữ Liệu Riêng vs Dữ Liệu Dùng Chung](#4-phân-định-dữ-liệu-riêng-vs-dữ-liệu-dùng-chung)
5. [Cơ Chế Lỗi Phân Quyền Có Kiểm Soát (IDOR Vulnerability)](#5-cơ-chế-lỗi-phân-quyền-có-kiểm-soát-idor-vulnerability)
6. [Ma Trận Ca Kiểm Thử Đối Chiếu Lỗi Phân Quyền](#6-ma-trận-ca-kiểm-thử-đối-chiếu-lỗi-phân-quyền)
7. [Hướng Dẫn Từng Bước Vận Hành & Thực Nghiệm Thực Tế](#7-hướng-dẫn-từng-bước-vận-hành--thực-nghiệm-thực-tế)
8. [Tập Lệnh cURL Kiểm Tra Nhanh Độc Lập](#8-tập-lệnh-curl-kiểm-tra-nhanh-độc-lập)
9. [Xử Lý Sự Cố Thường Gặp (Troubleshooting)](#9-xử-lý-sự-cố-thường-gặp-troubleshooting)

---

## 1. GIỚI THIỆU YÊU CẦU KỸ THUẬT SỐ 3

Theo đề cương Đề tài 4, yêu cầu số 3 quy định:
> *"Xây ứng dụng PHP mẫu có các địa chỉ API, dữ liệu riêng và dùng chung; chuẩn bị một lỗi phân quyền có kiểm soát."*

Ứng dụng mẫu đóng vai trò là **Đối tượng kiểm thử mục tiêu (Target Application)** để công cụ kiểm thử **AccessTester** gửi các yêu cầu HTTP giả lập, phân tích kết quả trả về, đối chiếu với ma trận quyền quy định và phát hiện vi phạm phân quyền.

### Thông số kỹ thuật của Ứng dụng mẫu:
- **Ngôn ngữ:** PHP 8.2+ thuần (Vanilla PHP), không phụ thuộc framework nặng nhằm đảm bảo tốc độ phản hồi tối ưu và minh bạch logic bảo mật.
- **Vị trí mã nguồn:** `Ma_nguon/sample-app/public/index.php`
- **Địa chỉ phục vụ:** `http://127.0.0.1:8205`
- **Cơ chế xác thực:** HTTP Authorization Bearer Token.
- **Ranh giới an toàn:** Tự động chặn các yêu cầu ngoài loopback (`127.0.0.1`, `::1`, `localhost`), trả về HTTP 403 `FORBIDDEN_HOST`.

---

## 2. KIẾN TRÚC & LƯỢC ĐỒ DỮ LIỆU CỦA ỨNG DỤNG MẪU

Dữ liệu của ứng dụng mẫu được thiết kế tại schema `sample_app` trong PostgreSQL (tệp `Ma_nguon/sample-app/schema.sql`). Nếu chưa thiết lập cơ sở dữ liệu, ứng dụng tự động kích hoạt bộ nhớ đệm giả lập (*In-memory Mock Store*) để đảm bảo kiểm thử không bao giờ bị gián đoạn.

### 2.1. Lược đồ các bảng dữ liệu

```sql
-- 1. Bảng người dùng và vai trò (RBAC)
CREATE TABLE sample_app.users (
  id bigserial PRIMARY KEY,
  username varchar(60) NOT NULL UNIQUE,
  password_hash varchar(255) NOT NULL,
  role varchar(40) NOT NULL,          -- 'member', 'admin', 'auditor'
  full_name varchar(100) NOT NULL
);

-- 2. Bảng mã truy cập Bearer Token
CREATE TABLE sample_app.tokens (
  token varchar(128) PRIMARY KEY,
  username varchar(60) NOT NULL REFERENCES sample_app.users(username) ON DELETE CASCADE,
  created_at timestamptz NOT NULL DEFAULT now()
);

-- 3. Bảng dữ liệu riêng: Ghi chú gắn với chủ sở hữu (ABAC - Ownership)
CREATE TABLE sample_app.notes (
  id bigserial PRIMARY KEY,
  owner_username varchar(60) NOT NULL REFERENCES sample_app.users(username) ON DELETE CASCADE,
  title varchar(200) NOT NULL,
  content text NOT NULL,
  created_at timestamptz NOT NULL DEFAULT now()
);

-- 4. Bảng dữ liệu dùng chung: Thông báo công cộng
CREATE TABLE sample_app.announcements (
  id bigserial PRIMARY KEY,
  title varchar(200) NOT NULL,
  body text NOT NULL,
  created_at timestamptz NOT NULL DEFAULT now()
);
```

### 2.2. Danh sách tài khoản thử nghiệm định sẵn

| Tên người dùng (`username`) | Vai trò (`role`) | Họ tên hiển thị | Mã Bearer Token tương ứng | Quyền hạn mặc định |
| :--- | :--- | :--- | :--- | :--- |
| `alice` | `member` | Alice Nguyễn | `test_token_alice_123` | Chỉ thao tác trên dữ liệu thuộc sở hữu của Alice |
| `bob` | `member` | Bob Trần | `test_token_bob_456` | Chỉ thao tác trên dữ liệu thuộc sở hữu của Bob |
| `admin1` | `admin` | Quản trị viên 1 | `test_token_admin_789` | Toàn quyền xem ghi chú, quản lý danh sách người dùng |
| `auditor1` | `auditor` | Kiểm toán viên 1 | `test_token_auditor_321` | Quyền xem toàn bộ ghi chú (chỉ đọc), không có quyền sửa/xóa |
| `(Khách ẩn danh)` | `anon` | Khách vãng lai | *(Không gửi token)* | Chỉ được truy cập tài nguyên công khai, bị chặn mọi API nội bộ |

---

## 3. BẢNG ĐẶC TẢ CHI TIẾT CÁC ĐỊA CHỈ API

Ứng dụng mẫu cung cấp 6 điểm cuối (Endpoints) đại diện cho các kịch bản phân quyền thực tế:

| Phương thức | Đường dẫn API | Xác thực | Vai trò cho phép | Dữ liệu trả về | Mã HTTP chuẩn |
| :---: | :--- | :---: | :---: | :--- | :---: |
| `GET` | `/api/profile` | Bắt buộc | Mọi vai trò đã đăng nhập | Thông tin hồ sơ của tài khoản đang gọi | `200` (Thành công) / `401` (Chưa đăng nhập) |
| `GET` | `/api/notes` | Bắt buộc | `member`, `admin`, `auditor` | `member` chỉ thấy ghi chú của mình; `admin/auditor` thấy toàn bộ | `200` (Thành công) / `401` (Chưa đăng nhập) |
| `GET` | `/api/notes/{id}` | Bắt buộc | Chủ sở hữu, `admin`, `auditor` | Chi tiết bản ghi ghi chú cụ thể. **Điểm kiểm tra IDOR** | `200` (Được phép) / `403` (Bị cấm) / `404` (Không tồn tại) |
| `PUT` | `/api/notes/{id}` | Bắt buộc | Duy nhất chủ sở hữu | Cập nhật tiêu đề/nội dung. Kiểm tra nguyên tắc từ chối mặc định | `200` (Sửa xong) / `403` (Từ chối) / `404` (Không thấy) |
| `GET` | `/api/announcements` | Bắt buộc | Mọi vai trò đã đăng nhập | Danh sách thông báo dùng chung của hệ thống | `200` (Thành công) / `401` (Chưa đăng nhập) |
| `GET` | `/api/admin/users` | Bắt buộc | Duy nhất `admin` | Danh sách tài khoản người dùng và vai trò | `200` (Admin) / `403` (Cấm vai trò khác) / `401` |

---

## 4. PHÂN ĐỊNH DỮ LIỆU RIÊNG VS DỮ LIỆU DÙNG CHUNG

Để công cụ AccessTester có thể kiểm tra rò rỉ dữ liệu (Data Leakage Verification), hệ thống phân tách rõ 3 lớp dữ liệu:

### 4.1. Dữ liệu riêng (Scoped Data - Phân quyền theo Chủ sở hữu)
- **Tài nguyên:** `sample_app.notes`
- **Bản ghi mẫu của Alice:**
  - ID `1`: Tiêu đề *"Ghi chú 1 của Alice"*, nội dung: `Bi mat cua Alice 1: Kế hoạch mật`.
  - ID `2`: Tiêu đề *"Ghi chú 2 của Alice"*, nội dung: `Lịch họp nhóm nghiên cứu mã nguồn mở`.
- **Bản ghi mẫu của Bob:**
  - ID `3`: Tiêu đề *"Ghi chú 3 của Bob"*, nội dung: `Bi mat cua Bob 3: Nhật ký bảo mật cá nhân`.
  - ID `4`: Tiêu đề *"Ghi chú 4 của Bob"*, nội dung: `Báo cáo chi tiêu dự án cá nhân`.
- **Nguyên tắc an toàn:**
  - Khi `alice` gọi API đọc danh sách (`GET /api/notes`), kết quả **tuyệt đối không được chứa chuỗi** `Bi mat cua Bob 3`.
  - Khi `alice` gọi trực tiếp chi tiết ghi chú số 3 (`GET /api/notes/3`), hệ thống bắt buộc phải từ chối (HTTP 403 Forbidden).

### 4.2. Dữ liệu dùng chung (Shared / Public Data)
- **Tài nguyên:** `sample_app.announcements`
- **Bản ghi mẫu:** ID `1`, tiêu đề *"Thông báo bảo trì hệ thống"*, nội dung: *"Hệ thống sẽ bảo trì định kỳ vào 00:00 Chủ nhật. Dữ liệu dùng chung cho mọi thành viên."*
- **Nguyên tắc an toàn:** Mọi người dùng đã xác thực hợp lệ đều có quyền đọc nội dung này bình đẳng như nhau.

### 4.3. Dữ liệu quản trị (Administrative Data)
- **Tài nguyên:** `sample_app.users`
- **Nguyên tắc an toàn:** Chỉ tài khoản có vai trò `admin` (`admin1`) mới có quyền truy cập endpoint `GET /api/admin/users`. Thành viên thường (`alice`, `bob`) hoặc kiểm toán viên (`auditor1`) nếu cố truy cập sẽ nhận về HTTP 403 Forbidden.

---

## 5. CƠ CHẾ LỖI PHÂN QUYỀN CÓ KIỂM SOÁT (IDOR VULNERABILITY)

### 5.1. Bản chất kỹ thuật của lỗi
Lỗ hổng phân quyền được chuẩn bị có kiểm soát là **Lỗi kiểm soát truy cập trực tiếp đối tượng (IDOR - Insecure Direct Object Reference)**, tương ứng với chuẩn OWASP API Security Top 10 - **BOLA (Broken Object Level Authorization)**.

Lỗ hổng xảy ra tại điểm cuối `GET /api/notes/{id}` khi lập trình viên chỉ kiểm tra người dùng đã gửi token hợp lệ (`$currentUser !== null`), nhưng **quên kiểm tra mối quan hệ sở hữu** giữa người đang yêu cầu và bản ghi mục tiêu (`$note['owner_username'] === $currentUser['username']`).

### 5.2. Đoạn mã hiện thực trong `sample-app/public/index.php`

```php
// =========================================================================
// LỖI PHÂN QUYỀN CỐ Ý (IDOR - KHI BUG_IDOR = 1):
// Chỉ kiểm tra đăng nhập, BỎ QUA so sánh người sở hữu!
// Cho phép Alice đọc được ghi chú riêng tư số 3 của Bob.
// =========================================================================
if ($bugIdor && $noteId === 3) {
    echo json_encode([
        'note' => $note,
        'debug_bug_idor' => true,
        'warning' => 'IDOR_VULNERABILITY_ACTIVE'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// =========================================================================
// BẢN ĐÃ SỬA LỖI (KHI BUG_IDOR = 0):
// Kiểm tra nghiêm ngặt: Chỉ chủ sở hữu (owner) hoặc quản trị (admin/auditor) mới được xem.
// =========================================================================
$username = $currentUser['username'];
$role = $currentUser['role'];
$isOwner = ($note['owner_username'] === $username);
$canRead = $isOwner || in_array($role, ['admin', 'auditor'], true);

if (!$canRead) {
    http_response_code(403);
    echo json_encode([
        'error' => 'FORBIDDEN',
        'message' => 'Bạn không có quyền xem ghi chú của người dùng khác'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode(['note' => $note], JSON_UNESCAPED_UNICODE);
exit;
```

### 5.3. Bốn cách bật/tắt lỗi linh hoạt
Hệ thống hỗ trợ 4 cơ chế điều khiển biến cờ `$bugIdor` theo độ ưu tiên:

1. **Header HTTP (Độ ưu tiên cao nhất):** Gửi header `X-Bug-Idor: 1` để bật, `X-Bug-Idor: 0` để tắt. Rất thuận tiện khi kiểm thử từng request riêng biệt.
2. **Biến môi trường hệ thống:** Đặt biến môi trường trước khi chạy tiến trình PHP:
   - `export BUG_IDOR=1` (Bật chế độ có lỗi)
   - `export BUG_IDOR=0` (Bật chế độ an toàn / đã sửa lỗi)
3. **Tệp tin cờ (Flag file):** Ghi giá trị `1` hoặc `0` vào tệp `.local/bug_idor.flag`.
4. **Tham số URL (Query parameter):** Thêm `?bug=1` hoặc `?bug=0` vào cuối địa chỉ URL.

---

## 6. MA TRẬN CA KIỂM THỬ ĐỐI CHIẾU LỖI PHÂN QUYỀN

Nhóm ca thử kiểm tra lỗi IDOR (`R01` đến `R04`) trên endpoint `GET /api/notes/{id}` thể hiện tính chính xác của công cụ kiểm thử:

| Mã ca | Người gọi | URL yêu cầu | Chủ sở hữu | Quyền kỳ vọng | HTTP kỳ vọng | Chuỗi cấm rò rỉ | Kết quả khi `BUG_IDOR=1` | Kết quả khi `BUG_IDOR=0` |
| :---: | :---: | :---: | :---: | :---: | :---: | :---: | :---: | :---: |
| **R01** | `alice` | `/api/notes/1` | `alice` | `ALLOW` | 200 | *(Không)* | **PASS** (Alice đọc đúng note 1 của mình) | **PASS** (Alice đọc đúng note 1 của mình) |
| **R02** | `alice` | `/api/notes/3` | `bob` | **`DENY`** | **403** | `Bi mat cua Bob 3` | **FAIL** (Phát hiện rò rỉ: Máy chủ trả 200 kèm chuỗi bí mật của Bob) | **PASS** (Máy chủ chặn Alice với HTTP 403 Forbidden) |
| **R03** | `admin1`| `/api/notes/3` | `bob` | `ALLOW` | 200 | *(Không)* | **PASS** (Admin có quyền xem mọi ghi chú) | **PASS** (Admin có quyền xem mọi ghi chú) |
| **R04** | `bob` | `/api/notes/1` | `alice` | `DENY` | 403 | `Bi mat cua Alice 1` | **PASS** (Chặn Bob đọc note 1 của Alice) | **PASS** (Chặn Bob đọc note 1 của Alice) |

> **Nhận xét cốt lõi:** Ca kiểm thử **`R02`** chính là thước đo xác thực. Khi có lỗi, công cụ AccessTester phát hiện ngay lập tức tình trạng vi phạm (`leak_detected = true`) và ghi nhận trạng thái **FAIL**. Khi lỗi được khắc phục, ca thử tự động chuyển sang **PASS**.

---

## 7. HƯỚNG DẪN TỪNG BƯỚC VẬN HÀNH & THỰC NGHIỆM THỰC TẾ

### Bước 1: Khởi động Ứng dụng mẫu trên cổng 8205
Mở terminal tại thư mục `lab5/Ma_nguon` và chạy:
```bash
# Cách 1: Sử dụng lệnh lab.sh
./lab.sh target:start

# Cách 2: Chạy trực tiếp qua PHP CLI tích hợp
php -S 127.0.0.1:8205 -t sample-app/public
```
Kiểm tra máy chủ đã sẵn sàng:
```bash
curl -i http://127.0.0.1:8205/api/announcements -H "Authorization: Bearer test_token_alice_123"
```
*(Nếu nhận về mã HTTP 200 và JSON thông báo hệ thống là ứng dụng đã chạy thành công).*

---

### Bước 2: Bật chế độ lỗi (`BUG_IDOR=1`) và chạy kiểm thử (Phép thử 1)
Thiết lập cờ lỗi phân quyền:
```bash
# Sử dụng lab.sh
./lab.sh target:vuln

# Hoặc ghi trực tiếp vào file cờ
mkdir -p .local
echo "1" > .local/bug_idor.flag
```

Thực hiện chạy toàn bộ 24 ca kiểm thử qua CLI Runner:
```bash
php bin/run.php --project=P01 --target=http://127.0.0.1:8205
```
**Kết quả quan sát được:**
- Tổng số ca: `24`
- Đạt chuẩn (`PASS`): `23`
- Vi phạm phân quyền (`FAIL`): `1` (Chính xác tại ca **`R02`**)
- Thông báo lỗi tại ca `R02`:
  ```text
  [FAIL] Ca R02: alice -> GET /api/notes/3
  Quy tac mong doi: DENY (Kỳ vọng HTTP 403)
  Thuc te nhan duoc: HTTP 200 OK
  Phat hien ro ri du lieu: Tim thay chuoi cam 'Bi mat cua Bob 3' trong phan hoi!
  ```

---

### Bước 3: Sửa lỗi phân quyền (`BUG_IDOR=0`) và chạy lại kiểm thử (Phép thử 2)
Chuyển ứng dụng mục tiêu sang trạng thái an toàn:
```bash
# Sử dụng lab.sh
./lab.sh target:safe

# Hoặc cập nhật file cờ
echo "0" > .local/bug_idor.flag
```

Chạy lại đợt kiểm thử mới:
```bash
php bin/run.php --project=P01 --target=http://127.0.0.1:8205
```
**Kết quả quan sát được:**
- Tổng số ca: `24`
- Đạt chuẩn (`PASS`): `24`
- Vi phạm (`FAIL`): `0`
- Toàn bộ các ca thử bao gồm `R02` đều trả về HTTP 403 đúng như ma trận quyền yêu cầu.

---

### Bước 4: Kiểm tra và đối chiếu trên Giao diện Web (AccessTester UI)
1. Khởi động Web UI AccessTester trên cổng 8105:
   ```bash
   php -S 127.0.0.1:8105 -t public
   ```
2. Mở trình duyệt truy cập: `http://localhost:8105`
3. Truy cập mục **Đối chiếu (Compare)** tại thanh điều hướng:
   - Chọn Đợt chạy A: Đợt chạy có lỗi (23 PASS, 1 FAIL).
   - Chọn Đợt chạy B: Đợt chạy đã khắc phục (24 PASS, 0 FAIL).
   - Nhấn nút **Đối chiếu 2 đợt chạy**.
4. Hệ thống sẽ hiển thị bảng so sánh chi tiết:
   - Ca `R02` được gắn nhãn nổi bật: `[LỖ HỔNG -> ĐÃ KHẮC PHỤC] SỬA THÀNH CÔNG`.
   - Biểu đồ cột thể hiện sự dịch chuyển tỷ lệ đạt chuẩn từ 95.8% lên 100%.

---

## 8. TẬP LỆNH CURL KIỂM TRA NHANH ĐỘC LẬP

Bạn có thể sao chép các lệnh cURL sau để kiểm tra trực tiếp phản hồi của Ứng dụng mẫu mà không cần chạy qua giao diện Web:

### 8.1. Kiểm tra đọc dữ liệu dùng chung (Hợp lệ)
```bash
curl -s -X GET http://127.0.0.1:8205/api/announcements \
  -H "Authorization: Bearer test_token_alice_123"
```
*Kết quả:* HTTP 200, danh sách thông báo chung.

### 8.2. Kiểm tra đọc dữ liệu riêng của chính mình (Hợp lệ)
```bash
curl -s -X GET http://127.0.0.1:8205/api/notes/1 \
  -H "Authorization: Bearer test_token_alice_123"
```
*Kết quả:* HTTP 200, hiển thị `Bi mat cua Alice 1`.

### 8.3. Kiểm tra khai thác lỗi IDOR khi bật `BUG_IDOR=1` (Bản có lỗi)
```bash
curl -s -X GET http://127.0.0.1:8205/api/notes/3 \
  -H "Authorization: Bearer test_token_alice_123" \
  -H "X-Bug-Idor: 1"
```
*Kết quả:* HTTP 200 OK, trả về nội dung của Bob:
```json
{
  "note": {
    "id": 3,
    "owner_username": "bob",
    "title": "Ghi chú 3 của Bob",
    "content": "Bi mat cua Bob 3: Nhật ký bảo mật cá nhân"
  },
  "debug_bug_idor": true,
  "warning": "IDOR_VULNERABILITY_ACTIVE"
}
```

### 8.4. Kiểm tra phòng thủ IDOR khi `BUG_IDOR=0` (Bản đã sửa lỗi)
```bash
curl -s -w "\nHTTP_STATUS: %{http_code}\n" -X GET http://127.0.0.1:8205/api/notes/3 \
  -H "Authorization: Bearer test_token_alice_123" \
  -H "X-Bug-Idor: 0"
```
*Kết quả:* Máy chủ chặn lại với mã **HTTP 403 Forbidden**:
```json
{
  "error": "FORBIDDEN",
  "message": "Bạn không có quyền xem ghi chú của người dùng khác"
}
HTTP_STATUS: 403
```

### 8.5. Kiểm tra truy cập trái phép quyền Quản trị viên (Admin endpoint)
```bash
curl -s -w "\nHTTP_STATUS: %{http_code}\n" -X GET http://127.0.0.1:8205/api/admin/users \
  -H "Authorization: Bearer test_token_alice_123"
```
*Kết quả:* Alice có vai trò `member`, máy chủ trả về **HTTP 403 Forbidden**:
```json
{
  "error": "FORBIDDEN",
  "message": "Chỉ dành cho quản trị viên"
}
HTTP_STATUS: 403
```

---

## 9. XỬ LÝ SỰ CỐ THƯỜNG GẶP (TROUBLESHOOTING)

| Hiện tượng | Nguyên nhân | Cách khắc phục |
| :--- | :--- | :--- |
| Toàn bộ ca kiểm thử báo `ERROR: CONNECT_FAILED` | Ứng dụng mẫu trên cổng 8205 chưa được khởi động | Chạy `php -S 127.0.0.1:8205 -t sample-app/public` hoặc kiểm tra port 8205 qua lệnh `netstat -ano \| findstr 8205`. |
| Kết quả cả 2 lần chạy đều là 24 PASS (không thấy lỗi IDOR) | Cờ `BUG_IDOR` chưa được ghi nhận là `1` | Kiểm tra tệp `.local/bug_idor.flag` có số `1`, hoặc chạy kiểm thử kèm cờ môi trường: `BUG_IDOR=1 php bin/run.php ...` |
| Kết quả cả 2 lần chạy đều là 23 PASS / 1 FAIL | Cờ `BUG_IDOR` vẫn đang lưu là `1` chưa chuyển về `0` | Cập nhật file cờ: `echo "0" > .local/bug_idor.flag` hoặc chạy `lab.sh target:safe`. |
| Bị chặn với lỗi `FORBIDDEN_HOST` | Gọi API từ địa chỉ mạng bên ngoài hoặc qua tên miền khác `127.0.0.1` | Chỉ truy cập qua `http://127.0.0.1:8205` hoặc `http://localhost:8205`. |
| Bị trả về HTTP 401 `UNAUTHORIZED` cho mọi request | Header Authorization chưa đúng định dạng Bearer | Đảm bảo header có cú pháp: `Authorization: Bearer <token>`, ví dụ `Authorization: Bearer test_token_alice_123`. |
