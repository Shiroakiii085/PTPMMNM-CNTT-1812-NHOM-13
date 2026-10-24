# HƯỚNG DẪN SỬ DỤNG VÀ THỰC THI KIỂM THỬ: ỨNG DỤNG MẪU BỆNH ÁN ĐIỆN TỬ (MEDIRECORD) VÀ LỖI PHÂN QUYỀN CÓ KIỂM SOÁT
## TÀI LIỆU CHI TIẾT CHO YÊU CẦU 3 (ĐỀ TÀI 4 - NHÓM 13)

- **Học phần:** Phát triển phần mềm mã nguồn mở
- **Đề tài 4:** Xây dựng công cụ kiểm thử tự động quyền truy cập trong ứng dụng Web
- **Sinh viên thực hiện:** Nhóm 13
- **Thành phần tài liệu:** Hướng dẫn kỹ thuật và vận hành cho Chức năng số 3: *Ứng dụng PHP mẫu Quản lý Bệnh án Điện tử (MediRecord EMR), các địa chỉ API, phân loại dữ liệu riêng/dùng chung và trực quan hóa lỗi phân quyền có kiểm soát (IDOR/BOLA)*.
- **Tập tin tài liệu:** `3_Tutorial.md`

---

## MỤC LỤC
1. [Bối Cảnh Nghiệp Vụ & Giới Thiệu Yêu Cầu Kỹ Thuật Số 3](#1-bối-cảnh-nghiệp-vụ--giới-thiệu-yêu-cầu-kỹ-thuật-số-3)
2. [Hệ Thống Tài Khoản Thật & Phân Quyền Vai Trò](#2-hệ-thống-tài-khoản-thật--phân-quyền-vai-trò)
3. [Kiến Trúc Dữ Liệu: Hồ Sơ Bệnh Án & Thông Báo Y Tế](#3-kiến-trúc-dữ-liệu-hồ-sơ-bệnh-án--thông-báo-y-tế)
4. [Bảng Đặc Tả Chi Tiết Các Địa Chỉ API](#4-bảng-đặc-tả-chi-tiết-các-địa-chỉ-api)
5. [Cơ Chế Trực Quan Hóa Lỗi Phân Quyền IDOR (CWE-639)](#5-cơ-chế-trực-quan-hóa-lỗi-phân-quyền-idor-cwe-639)
6. [Trang Trả Về Lỗi HTTP 403 Forbidden Chuyên Biệt](#6-trang-trả-về-lỗi-http-403-forbidden-chuyên-biệt)
7. [Màn Hình Terminal Tương Tác Tạo Lỗi & Vá Lỗi Dòng Lệnh](#7-màn-hình-terminal-tương-tác-tạo-lỗi--vá-lỗi-dòng-lệnh)
8. [Hướng Dẫn Từng Bước Vận Hành & Khai Thác Thử Nghiệm](#8-hướng-dẫn-từng-bước-vận-hành--khai-thác-thử-nghiệm)
9. [Tập Lệnh cURL Kiểm Tra Nhanh Độc Lập](#9-tập-lệnh-curl-kiểm-tra-nhanh-độc-lập)
10. [Xử Lý Sự Cố Thường Gặp (Troubleshooting)](#10-xử-lý-sự-cố-thường-gặp-troubleshooting)

---

## 1. BỐI CẢNH NGHIỆP VỤ & GIỚI THIỆU YÊU CẦU KỸ THUẬT SỐ 3

Theo đề cương Đề tài 4, yêu cầu số 3 quy định:
> *"Xây ứng dụng PHP mẫu có các địa chỉ API, dữ liệu riêng và dùng chung; chuẩn bị một lỗi phân quyền có kiểm soát."*

Để đáp ứng tính thực tiễn cao nhất và loại bỏ hoàn toàn các dữ liệu kiểm thử giả định (`alice`, `bob`), hệ thống mẫu được xây dựng thành **Hệ thống Quản lý Bệnh án Điện tử Y tế (MediRecord EMR)** phục vụ khám chữa bệnh thực tế:
- **Bác sĩ khám bệnh:** Tạo lập chẩn đoán lâm sàng, phác đồ điều trị và gửi bệnh án trực tiếp cho bệnh nhân.
- **Bệnh nhân điều trị:** Đăng nhập để theo dõi hồ sơ bệnh án của chính mình (dữ liệu riêng tư cá nhân).
- **Quản trị viên & Kiểm toán an toàn thông tin:** Giám sát người dùng, kiểm thử bảo mật và ngăn chặn rò rỉ dữ liệu y tế.

### Thông số kỹ thuật của MediRecord EMR:
- **Mã nguồn:** `Ma_nguon/sample-app/public/index.php` (Giao diện Web trực quan + Bộ REST API).
- **Địa chỉ phục vụ:** `http://127.0.0.1:8205`
- **Cơ chế xác thực:** Hỗ trợ song song **Web Session** (người dùng lướt web trên trình duyệt) và **Bearer Token** (công cụ AccessTester và CLI runner gửi yêu cầu kiểm thử tự động).
- **Ranh giới an toàn:** Tự động chặn các yêu cầu ngoài loopback (`127.0.0.1`, `::1`, `localhost`), trả về HTTP 403 `FORBIDDEN_HOST`.

---

## 2. HỆ THỐNG TÀI KHOẢN THẬT & PHÂN QUYỀN VAI TRÒ

Toàn bộ dữ liệu kiểm thử được chuẩn hóa thành 5 tài khoản thật với đầy đủ họ tên, vai trò và chuyên môn:

| Tên tài khoản (`username`) | Vai trò (`role`) | Họ tên hiển thị | Đơn vị / Chuyên khoa | Bearer Token tương ứng | Quyền hạn nghiệp vụ |
| :--- | :--- | :--- | :--- | :--- | :--- |
| `admin_bv` | `admin` | Quản trị viên Bệnh viện Bạch Mai | Phòng CNTT & An Ninh Mạng | `test_token_admin_bv` | Toàn quyền xem mọi bệnh án, quản lý tài khoản người dùng |
| `tester_sec` | `auditor` | Kỹ sư Bảo mật Nguyễn Kiểm Thử | Ban Kiểm Toán Y Tế | `test_token_tester_sec` | Quyền kiểm toán chỉ đọc (Read-only) toàn bộ hồ sơ y tế |
| `bs_minh` | `doctor` | BS. CKI Lê Hoàng Minh | Khoa Tim Mạch & Nội Tiết | `test_token_bs_minh` | Lập, gửi bệnh án mới cho bệnh nhân, xem hồ sơ bệnh án |
| `bn_an` | `member` *(patient)* | Bệnh nhân Nguyễn Văn An | Bệnh nhân điều trị ngoại trú | `test_token_bn_an` | Chỉ xem được hồ sơ bệnh án của chính An (`scope = own`) |
| `bn_binh` | `member` *(patient)* | Bệnh nhân Trần Thị Bình | Bệnh nhân điều trị chuyên khoa | `test_token_bn_binh` | Chỉ xem được hồ sơ bệnh án của chính Bình (`scope = own`) |
| `(anon)` | `anon` | Khách vãng lai | Chưa đăng nhập | *(Không gửi token)* | Chỉ xem được thông tin công khai, bị chặn mọi API nội bộ |

> **Tính năng Fast Account Switcher:** Trên đầu trang web cổng 8205 luôn hiển thị thanh công cụ màu đen cho phép bấm 1 click là đổi ngay lập tức sang góc nhìn của Bác sĩ Minh, Bệnh nhân An, Bệnh nhân Bình, Quản trị viên hoặc Người kiểm thử mà không cần đăng nhập lại thủ công.

---

## 3. KIẾN TRÚC DỮ LIỆU: HỒ SƠ BỆNH ÁN & THÔNG BÁO Y TẾ

### 3.1. Dữ liệu riêng tư: Hồ sơ Bệnh án (`sample_app.notes`)
Mỗi bệnh án gắn với thuộc tính bệnh nhân sở hữu (`owner_username`) và bác sĩ phụ trách (`doctor_username`):

1. **Bệnh án số 01 (Của Bệnh nhân An):**
   - Tiêu đề: *Bệnh án số 01: Khám chuyên khoa Tim Mạch - BN Nguyễn Văn An*
   - Bác sĩ lập: BS. CKI Lê Hoàng Minh (`bs_minh`)
   - Chẩn đoán: *Rối loạn nhịp xoang độ 2, theo dõi thiếu máu cơ tim cục bộ*
   - Nội dung bảo mật: Chứa thông tin bệnh lý nhạy cảm và phác đồ dùng thuốc đặc biệt.
2. **Bệnh án số 02 (Của Bệnh nhân An):**
   - Tiêu đề: *Bệnh án số 02: Kết quả xét nghiệm sinh hóa máu định kỳ - BN Nguyễn Văn An*
   - Bác sĩ lập: BS. CKI Lê Hoàng Minh (`bs_minh`)
   - Chẩn đoán: *Chỉ số đường huyết HbA1c ổn định, men gan bình thường*
3. **Bệnh án số 03 (Của Bệnh nhân Bình - TRỌNG TÂM LỖ HỔNG IDOR):**
   - Tiêu đề: *Bệnh án số 03: Hồ sơ chẩn đoán đặc biệt & Sinh thiết - BN Trần Thị Bình*
   - Bác sĩ lập: BS. CKI Lê Hoàng Minh (`bs_minh`)
   - Chẩn đoán: *Theo dõi u biểu mô giai đoạn 1, chỉ định hóa xạ trị nội khoa bảo mật*
   - Nội dung bảo mật: Kết quả giải phẫu bệnh lý và phác đồ hóa trị tuyệt mật. **Đây là dữ liệu tuyệt đối không được phép để Bệnh nhân An đọc trộm!**
4. **Bệnh án số 04 (Của Bệnh nhân Bình):**
   - Tiêu đề: *Bệnh án số 04: Sổ theo dõi thuốc điều trị ngoại trú - BN Trần Thị Bình*

### 3.2. Dữ liệu dùng chung: Thông báo bệnh viện (`sample_app.announcements`)
- ID `1`: *Thông báo lịch trực cấp cứu và khám bệnh tuần này* — Bệnh viện Bạch Mai trực cấp cứu 24/7, khám bệnh từ 07:30 đến 17:00. Dữ liệu công cộng dùng chung cho mọi bác sĩ và bệnh nhân.

---

## 4. BẢNG ĐẶC TẢ CHI TIẾT CÁC ĐỊA CHỈ API

| Phương thức | Đường dẫn API | Xác thực | Vai trò cho phép | Dữ liệu trả về | Mã HTTP chuẩn |
| :---: | :--- | :---: | :---: | :--- | :---: |
| `GET` | `/api/profile` | Bearer Token / Session | Mọi vai trò đã đăng nhập | Thông tin hồ sơ y tế của tài khoản đang gọi | `200` / `401` |
| `GET` | `/api/notes` hoặc `/api/records` | Bearer Token / Session | `member`, `doctor`, `admin`, `auditor` | Bệnh nhân chỉ thấy bệnh án của mình; Cán bộ y tế thấy toàn bộ | `200` / `401` |
| `GET` | `/api/notes/{id}` hoặc `/api/records/{id}` | Bearer Token / Session | Chủ sở hữu, Bác sĩ, `admin`, `auditor` | Chi tiết hồ sơ bệnh án. **Điểm kiểm thử IDOR** | `200` (Được phép) / `403` (Bị cấm) / `404` |
| `PUT` | `/api/notes/{id}` | Bearer Token / Session | Duy nhất bệnh nhân sở hữu | Cập nhật ghi chú cá nhân. Kiểm tra nguyên tắc từ chối mặc định | `200` / `403` / `404` |
| `GET` | `/api/announcements` | Bearer Token / Session | Mọi vai trò đã đăng nhập | Danh sách thông báo chung của bệnh viện | `200` / `401` |
| `GET` | `/api/admin/users` | Bearer Token / Session | Duy nhất `admin_bv` | Quản lý danh sách nhân sự y tế và người dùng | `200` / `403` / `401` |

---

## 5. CƠ CHẾ TRỰC QUAN HÓA LỖI PHÂN QUYỀN IDOR (CWE-639)

Lỗ hổng bảo mật được cài cắm có kiểm soát tại endpoint `GET /api/notes/{id}` ([index.php:L480-L530](file:///c:/University/Nam_3_K1/PTPMMNM/btvn/lab5/Ma_nguon/sample-app/public/index.php#L480-L530)):

```php
// =========================================================================
// LỖI PHÂN QUYỀN CỐ Ý (IDOR - KHI BUG_IDOR = 1):
// Chỉ kiểm tra đăng nhập hợp lệ, BỎ QUA so sánh người sở hữu!
// Cho phép Bệnh nhân An đọc được Bệnh án số 03 riêng tư của Bệnh nhân Bình
// =========================================================================
if ($bugIdor && $noteId === 3) {
    echo json_encode([
        'record' => $note,
        'debug_bug_idor' => true,
        'warning' => 'IDOR_VULNERABILITY_ACTIVE',
        'leak_detected' => true
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// =========================================================================
// BẢN AN TOÀN ĐÃ SỬA LỖI (KHI BUG_IDOR = 0):
// Kiểm tra nghiêm ngặt quyền sở hữu: Bệnh nhân sở hữu, Bác sĩ phụ trách, hoặc Admin/Auditor
// =========================================================================
$username = $currentUser['username'];
$role = $currentUser['role'];
$isOwner = ($note['owner_username'] === $username);
$canRead = $isOwner || in_array($role, ['admin', 'auditor', 'doctor'], true);

if (!$canRead) {
    http_response_code(403);
    echo json_encode([
        'error' => 'FORBIDDEN',
        'message' => 'Bạn không có quyền xem bệnh án của bệnh nhân khác'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}
```

### Biểu hiện trực quan trên Trình duyệt:
- **Khi BẬT LỖI (`BUG_IDOR=1`):**
  - Bệnh nhân An bấm nút `[Cố tình mở Bệnh án số 03 (của Bình)]`.
  - Màn hình hiển thị đầy đủ thông tin bệnh lý ung bướu và phác đồ hóa trị của Bình.
  - Phía trên hồ sơ xuất hiện **Hộp Cảnh Báo Bảo Mật Đỏ Rực (Pulsing Red Leak Alert Box)**:
    `[CẢNH BÁO BẢO MẬT] PHÁT HIỆN LỖ HỔNG IDOR / BOLA (CWE-639) ĐANG BỊ KHAI THÁC!`
    `Tài khoản hiện tại: bn_an (Bệnh nhân Nguyễn Văn An) | Chủ sở hữu thực sự: bn_binh (Bệnh nhân Trần Thị Bình)`
    `Máy chủ đã bỏ qua kiểm tra quan hệ sở hữu đối tượng và làm rò rỉ dữ liệu y tế nhạy cảm!`
- **Khi TẮT LỖI (`BUG_IDOR=0`):**
  - Bệnh nhân An bấm xem bệnh án số 03 của Bình.
  - Hệ thống lập tức từ chối và chuyển hướng sang **Trang Trả Về Lỗi HTTP 403 Forbidden chuyên biệt**.

---

## 6. TRANG TRẢ VỀ LỖI HTTP 403 FORBIDDEN CHUYÊN BIỆT

Khi vi phạm phân quyền, hệ thống trả về mã trạng thái **HTTP 403 Forbidden** và hiển thị giao diện báo lỗi an ninh mạng chuyên nghiệp:
- **Huy hiệu:** `MÃ LỖI BẢO MẬT: HTTP 403`
- **Tiêu đề:** `TRUY CẬP BỊ TỪ CHỐI (ACCESS DENIED)`
- **Thông điệp hệ thống:** *"Truy cập bị từ chối: Bệnh nhân Nguyễn Văn An (bn_an) không có quyền xem bệnh án số 03 của Bệnh nhân Trần Thị Bình (bn_binh)!"*
- **Nguyên tắc bảo vệ:** Giải thích chi tiết cơ chế ABAC (Attribute-Based Access Control) chỉ cho phép bệnh nhân sở hữu hoặc bác sĩ điều trị tra cứu.
- **Các nút điều hướng nhanh:**
  - `[Quay lại Bệnh án của tôi]` -> Về danh sách hồ sơ cá nhân.
  - `[Đổi sang Bác sĩ Minh để xem]` -> Bác sĩ có thẩm quyền xem hợp lệ.
  - `[Mở Terminal để bật lại lỗi]` -> Trải nghiệm lại chế độ có lỗi.

---

## 7. MÀN HÌNH TERMINAL TƯƠNG TÁC TẠO LỖI & VÁ LỖI DÒNG LỆNH

Tại đường dẫn `http://127.0.0.1:8205/?view=terminal` tích hợp một màn hình Console dòng lệnh tương tác trực tiếp:
- **Nút bấm thao tác nhanh:**
  - `[Lệnh: Tạo Lỗi (set BUG_IDOR=1)]`: Kích hoạt lỗ hổng IDOR.
  - `[Lệnh: Vá Lỗi (set BUG_IDOR=0)]`: Khắc phục lỗi bảo mật.
  - `[Lệnh: status]`: Kiểm tra trạng thái cờ bảo mật và phiên đăng nhập.
  - `[Lệnh: curl /api/notes/3]`: Chạy giả lập request cURL và in ra mã HTTP trả về kèm dữ liệu thô ngay trên terminal.
- **Hỗ trợ gõ lệnh tùy ý bằng phím Enter:**
  - Gõ `help` để xem hướng dẫn lệnh.
  - Gõ `switch bs_minh` để đổi tài khoản qua terminal.
  - Gõ `clear` để dọn dẹp màn hình console.

---

## 8. HƯỚNG DẪN TỪNG BƯỚC VẬN HÀNH & KHAI THÁC THỬ NGHIỆM

### Bước 1: Khởi động Ứng dụng Bệnh án MediRecord trên cổng 8205
```bash
# Cách 1: Sử dụng lệnh lab.sh
./lab.sh target:start

# Cách 2: Chạy trực tiếp qua PHP CLI tích hợp
php -S 127.0.0.1:8205 -t sample-app/public
```
Mở trình duyệt truy cập: **`http://localhost:8205`** hoặc **`http://127.0.0.1:8205`**.

---

### Bước 2: Trải nghiệm Giao diện Bác sĩ Lập & Gửi Bệnh Án
1. Trên thanh chuyển đổi tài khoản màu đen, nhấn **`Bác sĩ Minh (Doctor)`**.
2. Nhấn vào mục menu **`Bác Sĩ Gửi Bệnh Án`** (`/?view=create_record`).
3. Chọn Bệnh nhân nhận: `Bệnh nhân Nguyễn Văn An (bn_an)`.
4. Nhập tiêu đề, khoa phòng, chẩn đoán và nội dung phác đồ.
5. Nhấn **`Lập & Gửi Bệnh Án Cho Bệnh Nhân`** -> Hệ thống tạo bệnh án mới và lưu ngay vào danh sách.

---

### Bước 3: Thử nghiệm Lỗ hổng IDOR (Phép thử 1)
1. Mở menu **`Màn Hình Terminal`** (`/?view=terminal`).
2. Nhấn nút **`Lệnh: Tạo Lỗi (set BUG_IDOR=1)`** (Banner trên cùng chuyển sang màu đỏ: *LỖI IDOR: ĐANG BẬT*).
3. Nhấn vào tài khoản **`Bệnh nhân An (Patient)`**.
4. Tại hộp thử nghiệm, bấm nút **`Cố tình mở Bệnh án số 03 (của Bình)`**.
5. **Kết quả quan sát được:** Bệnh nhân An đọc được toàn bộ bệnh án số 03 của Bình kèm Hộp cảnh báo IDOR nhấp nháy đỏ rực.
6. Chạy công cụ kiểm thử tự động AccessTester qua CLI:
   ```bash
   php bin/run.php --project=1 --target=http://127.0.0.1:8205
   ```
   *Kết quả: 23 PASS / 1 FAIL (Bắt chính xác vi phạm rò rỉ dữ liệu tại ca `R02`).*

---

### Bước 4: Vá Lỗi và Xác Thực An Toàn (Phép thử 2)
1. Mở **`Màn Hình Terminal`**, nhấn nút **`Lệnh: Vá Lỗi (set BUG_IDOR=0)`**.
2. Đang là **`Bệnh nhân An`**, bấm lại nút **`Cố tình mở Bệnh án số 03 (của Bình)`**.
3. **Kết quả quan sát được:** Bệnh nhân An bị chặn hoàn toàn, hệ thống render ra **Trang Lỗi HTTP 403 Forbidden chuyên biệt**.
4. Chạy lại công cụ kiểm thử tự động:
   ```bash
   php bin/run.php --project=1 --target=http://127.0.0.1:8205
   ```
   *Kết quả: 24 PASS / 0 FAIL (100% Đạt chuẩn an toàn).*

---

## 9. TẬP LỆNH CURL KIỂM TRA NHANH ĐỘC LẬP

```bash
# 1. Đọc dữ liệu thông báo chung (Bác sĩ hoặc Bệnh nhân)
curl.exe -s -X GET http://127.0.0.1:8205/api/announcements \
  -H "Authorization: Bearer test_token_bn_an"

# 2. Bệnh nhân An đọc bệnh án số 01 của chính mình (Hợp lệ - HTTP 200)
curl.exe -s -X GET http://127.0.0.1:8205/api/notes/1 \
  -H "Authorization: Bearer test_token_bn_an"

# 3. Khai thác IDOR khi bật BUG_IDOR=1 (An đọc trộm bệnh án số 03 của Bình)
curl.exe -s -X GET http://127.0.0.1:8205/api/notes/3 \
  -H "Authorization: Bearer test_token_bn_an" \
  -H "X-Bug-Idor: 1"

# 4. Kiểm tra phòng thủ khi BUG_IDOR=0 (Máy chủ chặn với HTTP 403)
curl.exe -s -w "\nHTTP_STATUS: %{http_code}\n" -X GET http://127.0.0.1:8205/api/notes/3 \
  -H "Authorization: Bearer test_token_bn_an" \
  -H "X-Bug-Idor: 0"
```

---

## 10. XỬ LÝ SỰ CỐ THƯỜNG GẶP (TROUBLESHOOTING)

| Hiện tượng | Nguyên nhân | Cách khắc phục |
| :--- | :--- | :--- |
| Báo lỗi `Failed to listen on 127.0.0.1:8205 (Address already in use)` | Cổng 8205 đã được tiến trình PHP khác chạy nền | Máy chủ đã chạy sẵn, bạn chỉ việc mở `http://localhost:8205` trên trình duyệt hoặc dùng `pkill -f 8205` nếu muốn khởi động lại. |
| Truy cập cổng 8205 báo `ENDPOINT_NOT_FOUND` | Trước đây trang gốc `/` chưa có router | Hiện tại trang gốc `/` đã tích hợp đầy đủ giao diện web MediRecord, nhấn F5 để tải lại trang. |
| Bấm mở bệnh án của Bình nhưng không thấy cảnh báo đỏ | Cờ `BUG_IDOR` đang ở mức `0` (Đã vá lỗi) | Mở tab Terminal và bấm nút `Lệnh: Tạo Lỗi (set BUG_IDOR=1)` hoặc chạy `./lab.sh target:vuln`. |
| Không thấy nút "Bác Sĩ Gửi Bệnh Án" | Tài khoản hiện tại đang là Bệnh nhân hoặc Khách | Bấm vào nút `Bác sĩ Minh (Doctor)` trên thanh màu đen ở đầu trang để chuyển sang vai trò bác sĩ. |
