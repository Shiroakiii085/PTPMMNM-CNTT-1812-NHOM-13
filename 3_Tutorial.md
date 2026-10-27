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
7. [Bộ Đôi Nút Tròn Nổi (FABs) & Terminal Điều Khiển](#7-bộ-đôi-nút-tròn-nổi-fabs--terminal-điều-khiển)
8. [Chức Năng Đăng Nhập & Đăng Ký Tài Khoản Y Tế](#8-chức-năng-đăng-nhập--đăng-ký-tài-khoản-y-tế)
9. [Module Quản Trị Người Dùng Toàn Diện Cho Admin (CRUD + Search)](#9-module-quản-trị-người-dùng-toàn-diện-cho-admin-crud--search)
10. [Trung Tâm Giám Sát An Ninh & Kiểm Thử Phân Quyền Cho Tester](#10-trung-tâm-giám-sát-an-ninh--kiểm-thử-phân-quyền-cho-tester)
11. [Hướng Dẫn Từng Bước Vận Hành & Khai Thác Thử Nghiệm](#11-hướng-dẫn-từng-bước-vận-hành--khai-thác-thử-nghiệm)
12. [Tập Lệnh cURL Kiểm Tra Nhanh Độc Lập](#12-tập-lệnh-curl-kiểm-tra-nhanh-độc-lập)
13. [Xử Lý Sự Cố Thường Gặp (Troubleshooting)](#13-xử-lý-sự-cố-thường-gặp-troubleshooting)

---

## 1. BỐI CẢNH NGHIỆP VỤ & GIỚI THIỆU YÊU CẦU KỸ THUẬT SỐ 3

Theo đề cương Đề tài 4, yêu cầu số 3 quy định:
> *"Xây ứng dụng PHP mẫu có các địa chỉ API, dữ liệu riêng và dùng chung; chuẩn bị một lỗi phân quyền có kiểm soát."*

Để đáp ứng tính thực tiễn cao nhất và loại bỏ hoàn toàn các dữ liệu kiểm thử giả định (`alice`, `bob`), hệ thống mẫu được xây dựng thành **Hệ thống Quản lý Bệnh án Điện tử Y tế (MediRecord EMR)** phục vụ khám chữa bệnh thực tế:
- **Bác sĩ khám bệnh:** Tạo lập chẩn đoán lâm sàng, phác đồ điều trị và gửi bệnh án trực tiếp cho bệnh nhân.
- **Bệnh nhân điều trị:** Đăng nhập để theo dõi hồ sơ bệnh án của chính mình (dữ liệu riêng tư cá nhân).
- **Quản trị viên & Kỹ sư kiểm thử an toàn:** Giám sát người dùng, kiểm thử bảo mật tự động và theo dõi trực tiếp các vụ rò rỉ dữ liệu y tế qua Trung tâm Giám sát An ninh.
- **Tích hợp kiểm thử hoàn toàn vào cổng 8205:** Toàn bộ công cụ kiểm thử phân quyền tự động trước đây đã được tích hợp tập trung vào cổng 8205 dưới quyền kiểm soát của Kỹ sư Bảo mật (`tester_sec`), loại bỏ hoàn toàn cổng phụ 8105.

### Thông số kỹ thuật của MediRecord EMR:
- **Mã nguồn:** `Ma_nguon/sample-app/public/index.php` (Giao diện Web trực quan + Bộ REST API + Trung tâm Giám sát Kiểm thử An ninh).
- **Địa chỉ phục vụ:** `http://127.0.0.1:8205`
- **Cơ chế xác thực:** Hỗ trợ song song **Web Session** (người dùng lướt web trên trình duyệt) và **Bearer Token** (công cụ AccessTester và CLI runner gửi yêu cầu kiểm thử tự động).
- **Ranh giới an toàn:** Tự động chặn các yêu cầu ngoài loopback (`127.0.0.1`, `::1`, `localhost`), trả về HTTP 403 `FORBIDDEN_HOST`.

---

## 2. HỆ THỐNG TÀI KHOẢN THẬT & PHÂN QUYỀN VAI TRÒ

Toàn bộ dữ liệu kiểm thử được chuẩn hóa thành 5 tài khoản thật với đầy đủ họ tên, vai trò và chuyên môn:

| Tên tài khoản (`username`) | Vai trò (`role`) | Họ tên hiển thị | Đơn vị / Chuyên khoa | Bearer Token tương ứng | Quyền hạn nghiệp vụ |
| :--- | :--- | :--- | :--- | :--- | :--- |
| `admin_bv` | `admin` | Quản trị viên Bệnh viện Bạch Mai | Phòng CNTT & An Ninh Mạng | `test_token_admin_bv` | Toàn quyền xem mọi bệnh án, quản lý tài khoản người dùng, giám sát an ninh |
| `tester_sec` | `auditor` | Kỹ sư Bảo mật Trần Bảo Nam | Ban An Toàn Thông Tin & Kiểm Toán Y Tế | `test_token_tester_sec` | **Toàn quyền Trung tâm Giám sát An ninh & Kiểm thử**: Xem cảnh báo vi phạm thời gian thực, ma trận 24 test cases, chạy toàn bộ test suite |
| `bs_minh` | `doctor` | BS. CKI Lê Hoàng Minh | Khoa Tim Mạch & Nội Tiết | `test_token_bs_minh` | Lập, gửi bệnh án mới cho bệnh nhân, xem hồ sơ bệnh án |
| `bn_an` | `member` *(patient)* | Bệnh nhân Nguyễn Văn An | Bệnh nhân điều trị ngoại trú | `test_token_bn_an` | Chỉ xem được hồ sơ bệnh án của chính An (`scope = own`) |
| `bn_binh` | `member` *(patient)* | Bệnh nhân Trần Thị Bình | Bệnh nhân điều trị chuyên khoa | `test_token_bn_binh` | Chỉ xem được hồ sơ bệnh án của chính Bình (`scope = own`) |
| `(anon)` | `anon` | Khách vãng lai | Chưa đăng nhập | *(Không gửi token)* | Chỉ xem được thông tin công khai, bị chặn mọi API nội bộ |

> **Tính năng Fast Account Switcher (Nút tròn nổi FAB):** Ở góc dưới bên phải màn hình, phía trên nút Terminal, có một **Nút tròn nổi màu xanh** (biểu tượng người dùng). Bấm vào nút này sẽ mở cửa sổ popup cho phép chuyển đổi 1-click tức thì sang góc nhìn của Quản trị viên (`admin_bv`), Bác sĩ Minh (`bs_minh`), Bệnh nhân An (`bn_an`), Bệnh nhân Bình (`bn_binh`), Kỹ sư Bảo mật (`tester_sec` - hiển thị huy hiệu số vụ vi phạm) hoặc Khách vãng lai (`anon`). Ngoài ra, hệ thống cung cấp đầy đủ chức năng **Đăng Nhập** và **Đăng Ký** tài khoản mới.

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
  - Bệnh nhân An nhập số `3` vào Hộp **"TRA CỨU & MỞ HỒ SƠ BỆNH ÁN THEO MÃ ID"** và bấm **`Tra Cứu Bệnh Án`** (hoặc truy cập `?view=record&id=3`).
  - Màn hình hiển thị đầy đủ thông tin bệnh lý ung bướu và phác đồ hóa trị của Bình kèm **Hộp Cảnh Báo Bảo Mật Đỏ Rực (Pulsing Red Leak Alert Box)**:
    `[CẢNH BÁO BẢO MẬT] PHÁT HIỆN LỖ HỔNG IDOR / BOLA (CWE-639) ĐANG BỊ KHAI THÁC!`
    `Tài khoản hiện tại: bn_an (Bệnh nhân Nguyễn Văn An) | Chủ sở hữu thực sự: bn_binh (Bệnh nhân Trần Thị Bình)`
    `Máy chủ đã bỏ qua kiểm tra quan hệ sở hữu đối tượng và làm rò rỉ dữ liệu y tế nhạy cảm!`
  - **Can thiệp sửa đổi trái phép (IDOR on Update):** An có thể bấm **`Chỉnh Sửa Bệnh Án`** (hoặc `?view=edit_record&id=3`), thay đổi phác đồ chẩn đoán và bấm lưu. Hệ thống cảnh báo đỏ rực: `[CẢNH BÁO VI PHẠM BẢO MẬT] KHAI THÁC LỖ HỔNG IDOR / BOLA THÀNH CÔNG!`.
- **Khi TẮT LỖI (`BUG_IDOR=0`):**
  - Bệnh nhân An nhập ID 3 và bấm tra cứu hoặc chỉnh sửa.
  - Hệ thống lập tức từ chối và chuyển hướng sang **Trang Trả Về Lỗi HTTP 403 Forbidden chuyên biệt**.

---

## 6. TRANG TRẢ VỀ LỖI HTTP 403 FORBIDDEN CHUYÊN BIỆT

Khi vi phạm phân quyền, hệ thống trả về mã trạng thái **HTTP 403 Forbidden** và hiển thị giao diện báo lỗi an ninh mạng chuyên nghiệp:
- **Huy hiệu:** `MÃ LỖI BẢO MẬT: HTTP 403`
- **Tiêu đề:** `TRUY CẬP BỊ TỪ CHỐI (ACCESS DENIED)`
- **Thông điệp hệ thống:** *"Truy cập bị từ chối: Bệnh nhân Nguyễn Văn An (bn_an) không có quyền xem hoặc sửa bệnh án số 03 của Bệnh nhân Trần Thị Bình (bn_binh)!"*
- **Nguyên tắc bảo vệ:** Giải thích chi tiết cơ chế ABAC (Attribute-Based Access Control) chỉ cho phép bệnh nhân sở hữu hoặc bác sĩ điều trị thao tác.
- **Các nút điều hướng nhanh:**
  - `[Quay lại Bệnh án của tôi]` -> Về danh sách hồ sơ cá nhân.
  - `[Đổi sang Bác sĩ Minh để xem]` -> Bác sĩ có thẩm quyền xem hợp lệ.
  - `[Bấm nút tròn Terminal ở góc dưới phải để bật lại lỗi]` -> Trải nghiệm lại chế độ có lỗi.

---

## 7. BỘ ĐÔI NÚT DOCK NỔI ĐIỀU KHIỂN & TERMINAL BẢO MẬT (THIẾT KẾ HIỆN ĐẠI KHÔNG ICON)

Giao diện tích hợp **Bộ đôi Nút Dock Nổi** dạng viên nang (Pill Dock) cố định ở góc dưới bên phải màn hình, thiết kế tối giản, loại bỏ icon thừa, chú trọng trải nghiệm:
1. **Nút VAI TRÒ (Chuyển nhanh tài khoản 1-Click - Nút màu xanh ở trên):**
   - Vị trí: Đặt ở góc dưới bên phải màn hình.
   - Hiển thị: Nhãn `VAI TRÒ`, tên tài khoản đang hoạt động (ví dụ: `bn_an` hoặc `Khách Vãng Lai`) và huy hiệu số lượng sự cố an ninh vi phạm chưa đọc màu đỏ nhấp nháy.
   - Khi bấm: Mở cửa sổ popup `CHUYỂN NHANH VAI TRÒ (1-CLICK)` hiển thị danh sách đầy đủ 5 tài khoản thật với ký hiệu viết tắt chuyên nghiệp (`AD`, `BS`, `AN`, `BI`, `KT`, `--`):
     - `AD`: `admin_bv` (BS. CKII Vũ Quốc Bảo - Quản trị viên Bệnh viện)
     - `BS`: `bs_minh` (BS. CKI Lê Hoàng Minh - Bác sĩ điều trị)
     - `AN`: `bn_an` (Nguyễn Văn An - Bệnh nhân mục tiêu IDOR)
     - `BI`: `bn_binh` (Trần Thị Bình - Bệnh nhân nạn nhân IDOR)
     - `KT`: `tester_sec` (Trần Bảo Nam - Kiểm thử An toàn thông tin)
     - `--`: `anon` (Khách vãng lai - Chưa đăng nhập)
     - Footer điều hướng nhanh: **Đăng Nhập**, **Đăng Ký**, **Đăng Xuất**.
2. **Nút CONSOLE (Terminal Điều khiển An ninh - Nút màu đen ở dưới):**
   - Vị trí: Đặt ở góc dưới cùng bên phải.
   - Hiển thị: Chấm đèn trạng thái (Xanh lá cây: An toàn / Đỏ nhấp nháy: Đang bật lỗi IDOR), nhãn `CONSOLE`, trạng thái `IDOR ON` hoặc `SECURE`.
   - Khi bấm: Cửa sổ terminal trượt lên mượt mà ngay tại chỗ, tự động đóng cửa sổ vai trò.
   - Thao tác 1-click: `Tạo Lỗi (set BUG_IDOR=1)`, `Vá Lỗi (set BUG_IDOR=0)`, `status`, `curl /api/notes/3`, `clear`.
   - Cho phép gõ lệnh tùy ý bằng phím Enter.

---

## 8. CHỨC NĂNG ĐĂNG NHẬP & ĐĂNG KÝ TÀI KHOẢN Y TẾ

Hệ thống hỗ trợ quy trình xác thực người dùng hoàn chỉnh:
1. **Trang Đăng Nhập (`/?view=login`):**
   - Cho phép đăng nhập bằng tài khoản và mật khẩu (mặc định cho tài khoản mẫu: `TestPass@2026`).
   - Tích hợp 5 nút **Điền nhanh tài khoản kiểm thử** (Admin, Bác sĩ Minh, Bệnh nhân An, Bệnh nhân Bình, Tester Nam) giúp giảng viên và người kiểm thử thao tác tức thì chỉ với 1 click.
   - Xử lý thông báo lỗi rõ ràng nếu sai mật khẩu hoặc thiếu trường dữ liệu.
2. **Trang Đăng Ký Tài Khoản Mới (`/?view=register`):**
   - Biểu mẫu đăng ký chuyên nghiệp gồm: Tên đăng nhập (username), Họ và tên, Vai trò (Bệnh nhân hoặc Bác sĩ), Khoa phòng công tác, Mật khẩu và Xác nhận mật khẩu.
   - Kiểm tra trùng lặp tài khoản trong CSDL PostgreSQL và xác thực độ khớp của mật khẩu.
   - Sau khi đăng ký thành công, hệ thống tự động băm mật khẩu (`password_hash`), sinh token API, đăng nhập tự động và chuyển hướng về trang chủ kèm thông báo thành công.

---

## 9. MODULE QUẢN TRỊ NGƯỜI DÙNG TOÀN DIỆN CHO ADMIN (CRUD + SEARCH)

Dành riêng cho Quản trị viên (`admin_bv`) tại đường dẫn `http://localhost:8205/?view=users`:
1. **Tìm kiếm tức thì (Live Search Filter):**
   - Ô nhập tìm kiếm lọc trực tiếp danh sách theo tên tài khoản, họ tên đầy đủ, vai trò hoặc khoa phòng làm việc theo thời gian thực (không cần reload trang).
2. **Thêm người dùng mới (Create):**
   - Modal pop-up form điền tên đăng nhập, mật khẩu, họ tên, vai trò (`doctor`, `member`, `admin`, `auditor`) và khoa phòng. Lưu ngay vào hệ thống.
3. **Chỉnh sửa người dùng (Update):**
   - Modal chỉnh sửa thông tin họ tên, chức vụ, vai trò và chuyên khoa của từng cán bộ/bệnh nhân.
4. **Xóa người dùng (Delete):**
   - Nút xóa với hộp thoại xác nhận an toàn. Tích hợp cơ chế bảo vệ: Tuyệt đối không cho phép Quản trị viên tự xóa chính tài khoản mình đang đăng nhập.
5. **Kiểm soát phân quyền nghiêm ngặt:**
   - Chỉ tài khoản có vai trò `admin` mới được truy cập. Bệnh nhân hoặc Bác sĩ nếu cố tình nhập URL `/?view=users` sẽ bị chặn lập tức bằng trang lỗi HTTP 403 Forbidden!

---

## 10. TRUNG TÂM GIÁM SÁT AN NINH & KIỂM THỬ PHÂN QUYỀN CHO TESTER

Nhằm chuyển giao toàn bộ chức năng kiểm thử cho Kỹ sư Kiểm thử An toàn thông tin (`tester_sec` - Trần Bảo Nam) và loại bỏ hoàn toàn việc phải vận hành cổng 8105, hệ thống đã tích hợp trực tiếp **Trung Tâm Giám Sát An Ninh & Kiểm Thử Phân Quyền (Security Audit & Testing Center)** ngay tại địa chỉ `http://localhost:8205/?view=tester`:

### 10.1. Cơ chế Tự động Bắt và Cảnh Báo Vi Phạm Thời Gian Thực (Live Security Incident Feed)
Hệ thống giám sát an ninh (Event-driven Intrusion Detection) tự động lắng nghe và ghi nhận tức thì mọi hành vi lỗi thao tác hoặc cố tình lợi dụng lỗ hổng phân quyền:
- **Khai thác IDOR Đọc trộm (`IDOR_VIEW_EXPLOIT` / `API_IDOR_EXPLOIT`):** Bệnh nhân An cố tình mở bệnh án #03 của Bình qua hộp tra cứu hoặc API khi cờ lỗi đang bật -> Ghi nhận sự cố mức **`CRITICAL`** (Mã HTTP 200).
- **Can thiệp Sửa đổi Dữ liệu Trái phép (`IDOR_TAMPERING_EXPLOIT`):** Bệnh nhân An lợi dụng lỗ hổng gửi form sửa đổi phác đồ của Bình -> Ghi nhận sự cố mức **`CRITICAL`** (Ghi đè trái phép).
- **Thăm dò Trái phép Bị Chặn (`VIEW_ACCESS_DENIED` / `API_ACCESS_DENIED`):** Khi cờ lỗi tắt, An cố mở bệnh án của Bình nhưng bị cơ chế ABAC chặn đứng -> Ghi nhận cảnh báo mức **`WARNING`** (Mã HTTP 403).
- **Cố tình Leo thang Đặc quyền (`PRIVILEGE_ESCALATION_ATTEMPT` / `API_PRIVILEGE_ESCALATION_DENIED`):** Bệnh nhân hoặc nhân viên không có quyền Admin cố tình mở trang Quản trị người dùng (`/?view=users`) hoặc gọi API danh sách nhân sự -> Ghi nhận sự cố mức **`HIGH`** (Mã HTTP 403).
- **Thay đổi Cấu hình Cờ Lỗi (`FLAG_ACTIVATED` / `FLAG_DEACTIVATED`):** Ghi nhận thao tác kích hoạt hoặc vá lỗi từ Terminal để phục vụ kiểm toán minh bạch.

### 10.2. Tín hiệu Cảnh báo Trực quan (Alert Badges)
- Trên thanh điều hướng Header: Tự động hiển thị mục menu **`🛡️ Giám Sát An Ninh & Kiểm Thử`** kèm huy hiệu số lượng vi phạm màu đỏ rực (VD: `3 vi phạm`).
- Trong Popup Chuyển Tài Khoản (FAB Drawer): Bên cạnh tài khoản `tester_sec` luôn nhấp nháy huy hiệu số sự cố để Kỹ sư kiểm thử nhận biết ngay lập tức.
- Khi người dùng đổi sang `tester_sec`: Hệ thống tự động chuyển hướng thẳng vào trang kiểm toán trung tâm.

### 10.3. Ba Tab Chức năng Kiểm Thử Tích Hợp Chuyên Nghiệp:
1. **Tab 1 - Nhật Ký Cảnh Báo Xâm Phạm Thời Gian Thực (Live Incident Feed):**
   - Bảng nhật ký chi tiết: Mã sự cố (ID), Thời gian, Mức độ (CRITICAL / HIGH / WARNING / INFO), Đối tượng thực hiện (Actor), Mục tiêu nhắm đến (Target), Chi tiết vi phạm và Mã HTTP.
   - Các nút tác vụ nhanh: `[⚡ Mô Phỏng Tấn Công IDOR]` (sinh sự cố mẫu để test cơ chế phát hiện), `[🗑️ Xóa Nhật Ký]`, `[👤 Chuyển sang BN An để thử tấn công]`, `[🛡️ Vá Lỗi / ⚠️ Bật Lỗi]`.
2. **Tab 2 - Ma Trận Kiểm Thử Phân Quyền Tự Động (24 Test Cases Matrix):**
   - Tích hợp toàn diện 24 ca kiểm thử chuẩn (P01-P04, N01-N04, R01-R04, U01-U04, A01-A04, M01-M04).
   - Khi `BUG_IDOR=1`: Bắt chính xác ca kiểm thử `R02` là **FAIL** (23 PASS / 1 FAIL).
   - Khi `BUG_IDOR=0`: Toàn bộ 24 ca kiểm thử đều **PASS** (24 PASS / 0 FAIL).
3. **Tab 3 - Chạy Toàn Bộ Test Suite Hệ Thống (88 Tests CLI):**
   - Cho phép Kỹ sư kiểm thử nhấn nút `[▶ Chạy Toàn Bộ Test Suite]` để thực thi lệnh `bash lab.sh test` trực tiếp trên máy chủ và xem màn hình kết quả 88/88 tests PASS ngay trên giao diện web.

---

## 11. HƯỚNG DẪN TỪNG BƯỚC VẬN HÀNH & KHAI THÁC THỬ NGHIỆM

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
1. Bấm vào **Nút tròn chuyển tài khoản màu xanh** (ở góc dưới bên phải), chọn **`BS. CKI Lê Hoàng Minh`**.
2. Nhấn vào mục menu **`Bác Sĩ Gửi Bệnh Án`** (`/?view=create_record`).
3. Chọn Bệnh nhân nhận: `Bệnh nhân Nguyễn Văn An (bn_an)`.
4. Nhập tiêu đề, khoa phòng, chẩn đoán và nội dung phác đồ.
5. Nhấn **`Lập & Gửi Bệnh Án Cho Bệnh Nhân`** -> Hệ thống tạo bệnh án mới và lưu ngay vào danh sách.

---

### Bước 3: Thử nghiệm Lỗ hổng IDOR (Phép thử 1)
1. Bấm vào **Nút tròn Terminal màu đen** ở góc dưới cùng bên phải.
2. Nhấn nút **`Lệnh: Tạo Lỗi (set BUG_IDOR=1)`** (Đèn FAB chuyển đỏ, banner trên cùng chuyển đỏ: *LỖI IDOR: ĐANG BẬT*).
3. Bấm vào **Nút tròn chuyển tài khoản màu xanh**, chọn **`Nguyễn Văn An (bn_an)`**.
4. Tại ô **"TRA CỨU & MỞ HỒ SƠ BỆNH ÁN THEO MÃ ID"**, gõ số `3` và bấm **`Tra Cứu Bệnh Án`**.
5. **Kết quả quan sát được:** Bệnh nhân An đọc được toàn bộ bệnh án số 03 của Bình kèm Hộp cảnh báo IDOR nhấp nháy đỏ rực.
6. Thử bấm **`Chỉnh Sửa Bệnh Án`** -> An được phép sửa chẩn đoán bệnh án số 03 của Bình và lưu thành công (IDOR on Update).

---

### Bước 4: Kiểm tra Cảnh báo Vi phạm Dưới Góc Nhìn Tester (`tester_sec`)
1. Bấm vào **Nút tròn chuyển tài khoản màu xanh**, chọn **`Trần Bảo Nam (tester_sec)`**.
2. Trình duyệt tự động chuyển hướng đến **Trung Tâm Giám Sát An Ninh & Kiểm Thử** (`/?view=tester`).
3. **Quan sát Tab 1 (Nhật Ký Cảnh Báo):** Sự cố vi phạm của `bn_an` đọc trộm và can thiệp bệnh án #03 hiển thị ngay lập tức ở dòng đầu tiên với mức độ **`CRITICAL`**, có đầy đủ thông tin thời gian, người thực hiện, mục tiêu và mã HTTP 200.
4. **Quan sát Tab 2 (Ma Trận 24 Test Cases):** Ca kiểm thử `R02` báo màu đỏ rực **FAIL** (23 PASS / 1 FAIL).

---

### Bước 5: Vá Lỗi và Xác Thực An Toàn (Phép thử 2)
1. Trong màn hình Tester (hoặc mở Nút tròn Terminal), nhấn nút **`🛡️ Vá Lỗ Hổng Ngay`** (hoặc lệnh `set BUG_IDOR=0`).
2. Chuyển lại tài khoản **`Bệnh nhân An`**, tiếp tục gõ số `3` và bấm **`Tra Cứu Bệnh Án`**.
3. **Kết quả:** Bệnh nhân An bị chặn hoàn toàn, hệ thống render ra **Trang Lỗi HTTP 403 Forbidden chuyên biệt**.
4. Chuyển lại sang **`tester_sec`**:
   - Tab 1 ghi nhận thêm sự cố mức **`WARNING`**: An cố tình thăm dò bệnh án #03 nhưng đã bị chặn với HTTP 403.
   - Tab 2 ma trận kiểm thử chuyển xanh toàn bộ: **24 PASS / 0 FAIL** (100% Đạt chuẩn an toàn).
   - Tab 3: Bấm nút `[▶ Chạy Toàn Bộ Test Suite]` -> Màn hình hiển thị kết quả 88/88 bài test PASS.

---

## 12. TẬP LỆNH CURL KIỂM TRA NHANH ĐỘC LẬP

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

## 13. XỬ LÝ SỰ CỐ THƯỜNG GẶP (TROUBLESHOOTING)

| Hiện tượng | Nguyên nhân | Cách khắc phục |
| :--- | :--- | :--- |
| Báo lỗi `Failed to listen on 127.0.0.1:8205 (Address already in use)` | Cổng 8205 đã được tiến trình PHP khác chạy nền | Máy chủ đã chạy sẵn, bạn chỉ việc mở `http://localhost:8205` trên trình duyệt hoặc dùng `pkill -f 8205` nếu muốn khởi động lại. |
| Có cần chạy cổng 8105 nữa không? | Không cần | Cổng 8105 đã được loại bỏ hoàn toàn. Mọi tính năng kiểm thử, giám sát sự cố và ma trận test case đã được tích hợp tập trung vào cổng 8205 cho nhân viên kiểm thử `tester_sec`. |
| Bấm mở bệnh án của Bình nhưng không thấy cảnh báo đỏ | Cờ `BUG_IDOR` đang ở mức `0` (Đã vá lỗi) | Mở tab Terminal và bấm nút `Lệnh: Tạo Lỗi (set BUG_IDOR=1)` hoặc chạy `./lab.sh target:vuln`. |
| Không thấy nút "Bác Sĩ Gửi Bệnh Án" | Tài khoản hiện tại đang là Bệnh nhân hoặc Khách | Bấm vào nút chuyển tài khoản nhanh (FAB màu xanh) và chọn `BS. CKI Lê Hoàng Minh`. |
| Không thấy mục menu "Giám Sát An Ninh & Kiểm Thử" | Đang đăng nhập dưới quyền Bệnh nhân hoặc Bác sĩ | Chỉ tài khoản `tester_sec` (Kỹ sư Bảo mật) hoặc `admin_bv` mới có thẩm quyền truy cập trung tâm kiểm thử. Bấm nút FAB chuyển sang `tester_sec` để trải nghiệm. |
