# HƯỚNG DẪN KỊCH BẢN DEMO & BÁO CÁO BẢO VỆ ĐỒ ÁN
## ĐỀ TÀI 4: XÂY DỰNG CÔNG CỤ KIỂM THỬ TỰ ĐỘNG QUYỀN TRUY CẬP TRONG ỨNG DỤNG WEB (ACCESSTESTER)

- **Học phần:** Phát triển phần mềm mã nguồn mở
- **Đơn vị:** Trường Đại học Đại Nam - Khoa Công nghệ Thông tin
- **Sinh viên thực hiện:** Nhóm 13
- **Tập tin tài liệu Word (.docx):** [`Huong_Dan_Demo_Bao_Cao.docx`](file:///c:/University/Nam_3_K1/PTPMMNM/btvn/lab5/Huong_Dan_Demo_Bao_Cao.docx)

---

## MỤC LỤC
1. [Tổng Quan Hệ Thống & Những Thành Tựu Đã Đạt Được](#1-tổng-quan-hệ-thống--những-thành-tựu-đã-đạt-được)
2. [Danh Mục Chuẩn Bị Môi Trường Trước Khi Báo Cáo](#2-danh-mục-chuẩn-bị-môi-trường-trước-khi-báo-cáo)
3. [Kịch Bản Trình Diễn Demo Từng Bước (Step-by-Step Script)](#3-kịch-bản-trình-diễn-demo-từng-bước-step-by-step-script)
   - [Giai đoạn 1 (0:00 - 1:30): Mở đầu, Đặt vấn đề & Giới thiệu Kiến trúc](#giai-đoạn-1-000---130-mở-đầu-đặt-vấn-đề--giới-thiệu-kiến-trúc)
   - [Giai đoạn 2 (1:30 - 5:00): Trình diễn Ứng dụng Y tế MediRecord EMR (Cổng 8205)](#giai-đoạn-2-130---500-trình-diễn-ứng-dụng-y-tế-medirecord-emr-cổng-8205)
   - [Giai đoạn 3 (5:00 - 8:30): Trình diễn Công cụ Kiểm thử Tự động AccessTester (Cổng 8105)](#giai-đoạn-3-500---830-trình-diễn-công-cụ-kiểm-thử-tự-động-accesstester-cổng-8105)
   - [Giai đoạn 4 (8:30 - 10:00): Trình diễn Kiểm thử Dòng lệnh & Test Suite 88/88 Pass](#giai-đoạn-4-830---1000-trình-diễn-kiểm-thử-dòng-lệnh--test-suite-8888-pass)
4. [Bộ Câu Hỏi Phản Biện Thường Gặp Của Giảng Viên & Cách Trả Lời Xuất Sắc](#4-bộ-câu-hỏi-phản-biện-thường-gặp-của-giảng-viên--cách-trả-lời-xuất-sắc)
5. [Bảng Tra Cứu Tài Khoản Thật & Lệnh Điều Khiển Nhanh](#5-bảng-tra-cứu-tài-khoản-thật--lệnh-điều-khiển-nhanh)

---

## 1. TỔNG QUAN HỆ THỐNG & NHỮNG THÀNH TỰU ĐÃ ĐẠT ĐƯỢC

Đề tài giải quyết bài toán an toàn thông tin cấp thiết: *"Một trang Web có thể khai báo phân quyền đúng nhưng trong mã nguồn vẫn cho người dùng xem hoặc sửa dữ liệu nhạy cảm của người khác (Lỗ hổng BOLA / IDOR - CWE-639)"*. Nhóm 13 đã xây dựng hoàn chỉnh hệ sinh thái kiểm thử độc lập gồm 2 thành phần phần mềm chuyên nghiệp:

| Thành phần | Địa chỉ / Cổng | Vai trò & Tính năng cốt lõi |
| :--- | :--- | :--- |
| **Ứng dụng Mục tiêu (Target App)<br>MediRecord EMR** | `http://localhost:8205`<br>*(127.0.0.1:8205)* | - Hệ thống quản lý bệnh án điện tử y tế thực tế.<br>- **Bộ đôi Nút tròn nổi FAB ở góc dưới bên phải màn hình**: Nút tròn xanh chuyển đổi tài khoản 1-Click popup (đặt bên trên) và Nút tròn đen Terminal điều khiển cờ lỗi bảo mật (đặt bên dưới).<br>- **Quy trình Đăng Nhập & Đăng Ký** tài khoản y tế hoàn chỉnh với các nút điền nhanh tài khoản kiểm thử.<br>- Bác sĩ Lê Hoàng Minh (`bs_minh`) lập và gửi bệnh án cho Bệnh nhân An, Bệnh nhân Bình.<br>- Hộp Tra Cứu & Can Thiệp Bệnh Án Theo ID Tự Do (Test IDOR trực tiếp cả Xem và Sửa).<br>- Module Quản Trị Người Dùng Toàn Diện (Thêm, Sửa, Xóa, Tìm kiếm) dành riêng cho Quản trị viên.<br>- Trang trả về mã lỗi bảo mật HTTP 403 Forbidden chuyên biệt. |
| **Công cụ Kiểm thử Tự động<br>AccessTester Engine & Web UI** | `http://localhost:8105`<br>*(127.0.0.1:8105)* | - Quản trị ma trận phân quyền RBAC & ABAC (Ownership scope).<br>- Khai báo và thực thi tự động 24 ca kiểm thử HTTP chuẩn.<br>- Cơ chế phân loại 3 trạng thái: PASS, FAIL (Lỗ hổng), ERROR (Mạng/kỹ thuật).<br>- Bộ lọc địa chỉ an toàn (Target Allowlist) chống tấn công SSRF.<br>- Cơ chế Redactor tự động khử thông tin nhạy cảm (token, mật khẩu).<br>- Giao diện Đối chiếu 2 Đợt Chạy (Compare Runs) kèm biểu đồ Chart.js trực quan. |

---

## 2. DANH MỤC CHUẨN BỊ MÔI TRƯỜNG TRƯỚC KHI BÁO CÁO

Trước khi giảng viên yêu cầu demo, sinh viên thực hiện mở sẵn các dịch vụ nền:

> 📌 **MẸO PHÒNG BÁO CÁO:** Mở sẵn 2 tab trình duyệt: Tab 1 tại `http://localhost:8205` (MediRecord EMR) và Tab 2 tại `http://localhost:8105` (AccessTester) để chuyển đổi nhanh khi thuyết trình.

- **Bước 1: Khởi động CSDL PostgreSQL (WSL/Linux):**
  ```bash
  sudo service postgresql start
  ```
- **Bước 2: Nạp cấu hình và dữ liệu mẫu sạch sẽ (1 lệnh duy nhất):**
  ```bash
  cd /mnt/c/University/Nam_3_K1/PTPMMNM/btvn/lab5/Ma_nguon
  bash lab.sh setup
  ```
- **Bước 3: Kiểm tra trạng thái môi trường:**
  ```bash
  bash lab.sh check
  # Kết quả: MOI_TRUONG=SAN_SANG
  ```
- **Bước 4: Khởi động Ứng dụng Y tế MediRecord (Cổng 8205):**
  ```bash
  bash lab.sh target:start
  # Hoặc: php -S 127.0.0.1:8205 -t sample-app/public
  ```
- **Bước 5: Khởi động Công cụ AccessTester Web UI (Cổng 8105):**
  ```bash
  php -S 127.0.0.1:8105 -t public
  ```

---

## 3. KỊCH BẢN TRÌNH DIỄN DEMO TỪNG BƯỚC (STEP-BY-STEP SCRIPT)

### Giai đoạn 1 (0:00 - 1:30): Mở đầu, Đặt vấn đề & Giới thiệu Kiến trúc
- **Lời thoại gợi ý:**
  > *"Kính thưa Thầy/Cô, trong các hệ thống Web hiện đại, phân quyền vai trò (RBAC) thường được khai báo chặt chẽ, tuy nhiên lỗi phân quyền cấp đối tượng (BOLA / IDOR) vẫn là lỗ hổng an ninh mạng đứng số 1 theo OWASP API Security. Một người dùng đã đăng nhập hợp lệ nhưng nếu hệ thống không kiểm tra quan hệ sở hữu bản ghi thì người đó hoàn toàn có thể xem hoặc đánh cắp dữ liệu riêng tư của người khác. Nhóm 13 đã giải quyết trọn vẹn bài toán này bằng việc xây dựng: Thứ nhất là ứng dụng y tế MediRecord (cổng 8205) để mô phỏng dữ liệu bệnh án thật; và Thứ hai là công cụ kiểm thử tự động AccessTester (cổng 8105) có khả năng tự động rà quét, phát hiện rò rỉ và đối chiếu kết quả trước/sau khi vá lỗi."*

---

### Giai đoạn 2 (1:30 - 5:00): Trình diễn Ứng dụng Y tế MediRecord EMR (Cổng 8205)
*Mở tab trình duyệt tại `http://localhost:8205`:*

1. **Bước 2.1: Giới thiệu giao diện, Bộ đôi Nút tròn nổi FABs & Đăng Nhập / Đăng Ký:**
   - Chỉ vào giao diện tối ưu: Thanh điều hướng gọn gàng, hiển thị trạng thái bảo mật, thông tin tài khoản hiện tại và các chức năng chính.
   - Giới thiệu **Bộ đôi Nút tròn nổi FAB ở góc dưới bên phải màn hình**:
     - **Nút tròn xanh (ở trên):** Nút Chuyển Đổi Nhanh Tài Khoản (1-Click Switcher). Bấm vào sẽ mở popup chọn nhanh 5 tài khoản thật (`admin_bv`, `bs_minh`, `bn_an`, `bn_binh`, `tester_sec`) và Khách vãng lai, kèm liên kết nhanh đến Đăng nhập, Đăng ký.
     - **Nút tròn đen (ở dưới):** Nút Terminal Console (ký hiệu `>_`) điều khiển trạng thái cờ bảo mật.
   - Thử bấm **`Đăng Nhập`**: Trình diễn trang đăng nhập với các nút **Điền nhanh tài khoản kiểm thử**, giúp đăng nhập tức thì chỉ với 1 click.
   - Thử bấm **`Đăng Ký`**: Trình diễn biểu mẫu đăng ký thành viên mới (Bệnh nhân/Bác sĩ) có xác thực trùng lặp và tự động đăng nhập.
2. **Bước 2.2: Bác sĩ Lê Hoàng Minh lập & gửi bệnh án mới cho Bệnh nhân:**
   - Bấm nút tròn xanh FAB -> chọn **`BS. CKI Lê Hoàng Minh`** (hoặc đăng nhập bằng `bs_minh`).
   - Nhấn nút **`Bác Sĩ Gửi Bệnh Án`** trên menu (`/?view=create_record`).
   - Chọn bệnh nhân nhận là `bn_an` (Bệnh nhân An), nhập chẩn đoán lâm sàng và bấm **`Lập & Gửi Bệnh Án Cho Bệnh Nhân`**.
   - Hồ sơ mới được ghi nhận ngay lập tức vào cơ sở dữ liệu và hiển thị trong danh sách.
3. **Bước 2.3: Bật lỗi IDOR bằng Nút tròn nổi FAB Terminal:**
   - Bấm vào **Nút tròn đen FAB Terminal** ở góc dưới cùng bên phải -> Cửa sổ Drawer Terminal mở ra -> Bấm nút **`Lệnh: Tạo Lỗi (set BUG_IDOR=1)`**.
   - Hệ thống cập nhật ngay lập tức: Đèn tín hiệu trên nút tròn chuyển sang màu đỏ và thanh thông báo báo đỏ rực: *LỖI IDOR: ĐANG BẬT*.
4. **Bước 2.4: Trực quan hóa Lỗ hổng IDOR qua Hộp Tra Cứu & Can Thiệp Theo Mã ID Tự Do:**
   - Bấm nút tròn xanh FAB -> chuyển sang **`Bệnh nhân An (bn_an)`** (An chỉ sở hữu hồ sơ số 01 và 02).
   - Tại Hộp Tra Cứu **"TRA CỨU & MỞ HỒ SƠ BỆNH ÁN THEO MÃ ID"**, sinh viên nhập tự do số `3` (Mã bệnh án ung bướu của Bình) và bấm **`Tra Cứu Bệnh Án`**.
   - **KẾT QUẢ XEM TRÁI PHÉP:** Màn hình hiển thị toàn bộ hồ sơ bệnh án ung bướu tuyệt mật của Bình kèm **Hộp Cảnh Báo Bảo Mật Đỏ Rực** thông báo: *Bệnh nhân An đang xem trái phép dữ liệu của Bệnh nhân Bình!*
   - **KẾT QUẢ SỬA TRÁI PHÉP (IDOR on Update):** An tiếp tục bấm nút **`Chỉnh Sửa Bệnh Án`** (hoặc truy cập `?view=edit_record&id=3`) -> Mở form sửa đổi chẩn đoán của Bình -> Sửa thành công và hệ thống cảnh báo đỏ rực: *`[CẢNH BÁO VI PHẠM BẢO MẬT] KHAI THÁC LỖ HỔNG IDOR / BOLA THÀNH CÔNG!`*.
5. **Bước 2.5: Trực quan hóa Khắc phục Lỗ hổng & Trang Lỗi HTTP 403 Forbidden:**
   - Bấm vào Nút tròn đen FAB Terminal, nhấn nút **`Lệnh: Vá Lỗi (set BUG_IDOR=0)`**. Đèn trạng thái chuyển xanh an toàn.
   - Vẫn trong vai Bệnh nhân An, tiếp tục gõ ID `3` và bấm **`Tra Cứu Bệnh Án`** hoặc **`Chỉnh Sửa Bệnh Án`**.
   - **KẾT QUẢ SHOW GIẢNG VIÊN:** Hệ thống lập tức chặn đứng và chuyển sang **Trang Trả Về Lỗi HTTP 403 Forbidden chuyên biệt**, giải thích cơ chế ABAC từ chối quyền truy cập do không phải chủ sở hữu.
6. **Bước 2.6: Phân quyền Quản Trị Viên (admin_bv) & Module Quản Trị Người Dùng Toàn Diện:**
   - Bấm nút tròn xanh FAB -> chuyển sang **`Quản trị viên (admin_bv)`** -> Bấm vào menu **`Quản Trị Người Dùng`** (`/?view=users`).
   - **Tìm kiếm tức thì:** Gõ từ khóa vào ô tìm kiếm (ví dụ 'Minh' hoặc 'Bác sĩ') -> Danh sách lọc kết quả ngay lập tức mà không cần reload trang.
   - **Thêm người dùng:** Bấm *`Thêm Người Dùng Mới`*, điền tài khoản, vai trò, khoa phòng -> Lưu thành công vào hệ thống.
   - **Sửa & Xóa người dùng:** Thử bấm nút *Sửa* thông tin và nút *Xóa* tài khoản (có hộp thoại xác nhận an toàn, có cơ chế cấm tự xóa chính mình).
   - **Kiểm soát truy cập:** Nếu chuyển sang Bệnh nhân An mà cố tình gõ URL `?view=users`, hệ thống sẽ trả về ngay trang lỗi HTTP 403 Forbidden!

---

### Giai đoạn 3 (5:00 - 8:30): Trình diễn Công cụ Kiểm thử Tự động AccessTester (Cổng 8105)
*Chuyển sang tab trình duyệt tại `http://localhost:8105`:*

1. **Bước 3.1: Giới thiệu Quản lý Dự án & Danh mục 24 Ca kiểm thử HTTP:**
   - Mở mục **Dự án** và **Bộ ca thử**: Giải thích ma trận 24 ca kiểm thử bao quát đầy đủ 6 API x 4 ca thử nghiệm (`P01-P04`, `N01-N04`, `R01-R04`, `U01-U04`, `A01-A04`, `M01-M04`).
   - Nhấn mạnh ca kiểm thử **`R02`** chính là ca thử nghiệm bẫy phát hiện IDOR.
2. **Bước 3.2: Chạy kiểm thử Đợt 1 (Khi ứng dụng còn Lỗi `BUG_IDOR=1`):**
   - Nhấn nút **Chạy kiểm thử ngay** (chọn Dự án #1). Hệ thống gửi 24 yêu cầu HTTP song song.
   - **KẾT QUẢ SHOW GIẢNG VIÊN:** Đợt chạy ghi nhận: **23 PASS, 1 FAIL, 0 ERROR**. Bấm vào chi tiết ca `R02`: Hiển thị cờ `leak_detected = true` do máy chủ trả về HTTP 200 kèm chuỗi nhạy cảm của Bob/Bình.
3. **Bước 3.3: Chạy kiểm thử Đợt 2 (Sau khi đã vá lỗi `BUG_IDOR=0`):**
   - Sau khi ứng dụng mục tiêu được vá lỗi, bấm chạy đợt kiểm thử mới.
   - **KẾT QUẢ SHOW GIẢNG VIÊN:** Đợt chạy ghi nhận hoàn hảo: **24 PASS, 0 FAIL, 0 ERROR (100% ĐẠT CHUẨN)**. Ca `R02` đã chuyển sang trạng thái PASS vì máy chủ đã chặn đúng mã HTTP 403 Forbidden.
4. **Bước 3.4: Trình diễn Tính năng "Đối chiếu 2 Đợt Chạy" (Compare Runs):**
   - Mở menu **Đối chiếu** tại thanh điều hướng:
     - Chọn Đợt chạy A: Đợt có lỗi (23 PASS, 1 FAIL).
     - Chọn Đợt chạy B: Đợt an toàn (24 PASS, 0 FAIL).
     - Bấm nút **Đối chiếu 2 đợt chạy**.
   - **KẾT QUẢ SHOW GIẢNG VIÊN:** Màn hình hiển thị Biểu đồ cột so sánh tỉ lệ PASS/FAIL và bảng chi tiết làm nổi bật duy nhất ca `R02` với nhãn: **`[LỖ HỔNG -> ĐÃ KHẮC PHỤC] SỬA THÀNH CÔNG`**.
5. **Bước 3.5: Trình diễn Báo cáo Kiểm thử & Cơ chế Redactor Khử Dữ liệu Nhạy Cảm:**
   - Mở Báo cáo kiểm thử: Chỉ cho giảng viên thấy toàn bộ mã Bearer Token và mật khẩu CSDL đã được bộ lọc Redactor tự động băm che chắn (chỉ hiện dấu sao hoặc mã giả lập), đảm bảo an toàn tuyệt đối khi gửi báo cáo cho các bên liên quan.

---

### Giai đoạn 4 (8:30 - 10:00): Trình diễn Kiểm thử Dòng lệnh & Test Suite 88/88 Pass
Mở terminal WSL / Linux và chạy lệnh kiểm thử toàn diện:
```bash
bash lab.sh test
```
Chỉ cho giảng viên thấy kết quả chạy tự động qua 3 cấp độ kiểm thử chuyên sâu:
1. **KIỂM THỬ ĐƠN VỊ (UNIT TESTS):** 39/39 bài test PASS (Policy engine, Allowlist SSRF, Classifier, Redactor).
2. **KIỂM THỬ TÍCH HỢP CSDL (INTEGRATION TESTS):** 29/29 bài test PASS (CRUD Dự án, phân quyền Owner/Editor/Viewer, Snapshot, Compare).
3. **KIỂM THỬ ĐẦU-CUỐI E2E (END-TO-END TESTS):** 20/20 bài test PASS (Chứng minh 5 phép thử bắt buộc theo đề cương).
=> **TỔNG CỘNG: 88 TESTS, 0 FAILED (100% ĐẠT CHUẨN).**

---

## 4. BỘ CÂU HỎI PHẢN BIỆN THƯỜNG GẶP CỦA GIẢNG VIÊN & CÁCH TRẢ LỜI

### ❓ Câu 1: Lỗ hổng IDOR trong đề tài là gì và nhóm đã sửa trong mã nguồn như thế nào?
💬 **Trả lời:** IDOR (Insecure Direct Object Reference) hay BOLA xảy ra khi máy chủ chỉ kiểm tra người dùng đã đăng nhập (Authentication) nhưng bỏ quên bước kiểm tra quan hệ sở hữu đối tượng (Authorization). Trong mã nguồn `sample-app/public/index.php`, ở bản lỗi, khi nhận được `id=3`, server trả về ngay dữ liệu của Bob/Bình mà không xét ai đang gửi yêu cầu. Nhóm đã sửa lỗi bằng cách kiểm tra:
```php
$isOwner = ($record['owner_username'] === $currentUser['username']);
$canRead = $isOwner || in_array($role, ['admin', 'auditor', 'doctor'], true);
if (!$canRead) {
    http_response_code(403);
    echo json_encode(['error' => 'FORBIDDEN', 'message' => 'Bạn không có quyền xem bệnh án của bệnh nhân khác']);
    exit;
}
```

### ❓ Câu 2: Làm sao công cụ phân biệt được giữa "Từ chối đúng (DENY)" và "Lỗi mạng / Cổng đóng"?
💬 **Trả lời:** Đây là nguyên tắc cốt lõi của đề tài (Phép thử 3). `HttpClient` trong `src/HttpClient.php` phân loại kết quả thành 3 nhóm rõ rệt: `PASS`, `FAIL` và `ERROR`. Nếu cổng đóng (`CONNECT_FAILED`) hoặc quá thời gian chờ (`TIMEOUT`), kết quả **bắt buộc ghi nhận là ERROR kỹ thuật**, tuyệt đối không bao giờ được coi là máy chủ chặn đúng (`DENY`). Nhờ đó không xảy ra tình trạng báo cáo đạt giả tạo (False Positive).

### ❓ Câu 3: Phân quyền của công cụ AccessTester kết hợp giữa RBAC và ABAC ra sao?
💬 **Trả lời:** RBAC (Role-Based) kiểm soát dựa trên vai trò như Admin, Doctor, Member, Auditor. ABAC (Attribute-Based) bổ sung thuộc tính phạm vi: người dùng có vai trò Member chỉ được thao tác trên tài nguyên có thuộc tính phạm vi `own` (thuộc sở hữu của chính mình). Bất kỳ quyền nào chưa được khai báo tường minh trong ma trận quyền `permissions` mặc định đều bị từ chối (`DENY`).

### ❓ Câu 4: Công cụ bảo vệ an toàn chống lại các nguy cơ tấn công như thế nào?
💬 **Trả lời:** Công cụ tích hợp 2 lớp phòng vệ chủ động:
1. **TargetAllowlist:** Chỉ cho phép gửi request tới danh sách IP loopback (`127.0.0.1`, `localhost`) và dải cổng được duyệt (`8205`, `8295`), ngăn chặn hoàn toàn kẻ xấu lợi dụng công cụ để quét cổng nội bộ hoặc tấn công SSRF ra mạng ngoài.
2. **Redactor:** Tự động phát hiện và che giấu toàn bộ chuỗi Bearer token, password CSDL trong nhật ký và báo cáo HTML, không để lọt bí mật ra ngoài.

---

## 5. BẢNG TRA CỨU TÀI KHOẢN THẬT & LỆNH ĐIỀU KHIỂN NHANH

| Tên đăng nhập | Vai trò (Role) | Bearer Token kiểm thử | Họ tên & Quyền hạn |
| :--- | :--- | :--- | :--- |
| `bs_minh` | `doctor` (Bác sĩ) | `test_token_bs_minh` | BS. CKI Lê Hoàng Minh (Lập bệnh án, xem hồ sơ) |
| `bn_an` | `member` (Bệnh nhân) | `test_token_bn_an` | Bệnh nhân Nguyễn Văn An (Sở hữu bệnh án 1, 2) |
| `bn_binh` | `member` (Bệnh nhân) | `test_token_bn_binh` | Bệnh nhân Trần Thị Bình (Sở hữu bệnh án 3, 4) |
| `admin_bv` | `admin` (Quản trị) | `test_token_admin_bv` | Quản trị viên Bệnh viện (Toàn quyền, quản lý user) |
| `tester_sec` | `auditor` (Kiểm toán) | `test_token_tester_sec` | Kỹ sư Bảo mật Nguyễn Kiểm Thử (Kiểm toán chỉ đọc) |

---
*Tài liệu Word hoàn chỉnh kèm hình ảnh chất lượng cao đã sẵn sàng tại:* [**`Huong_Dan_Demo_Bao_Cao.docx`**](file:///c:/University/Nam_3_K1/PTPMMNM/btvn/lab5/Huong_Dan_Demo_Bao_Cao.docx).
