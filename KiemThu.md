# TÀI LIỆU HƯỚNG DẪN KIỂM THỬ VÀ KẾT QUẢ MONG ĐỢI CHI TIẾT
## CÔNG CỤ KIỂM THỬ TỰ ĐỘNG QUYỀN TRUY CẬP WEB (ACCESSTESTER)

- **Học phần:** Phát triển phần mềm mã nguồn mở
- **Đề tài 4:** Xây dựng công cụ kiểm thử tự động quyền truy cập trong ứng dụng Web
- **Sinh viên thực hiện:** Nhóm 13
- **Đối tượng kiểm thử:** Ứng dụng mẫu Quản lý ghi chú (Sample App - Cổng 8205)
- **Công cụ thực thi:** AccessTester (Web UI: Cổng 8105 | CLI Runner)

---

## MỤC LỤC
1. [Nguyên Tắc Kiểm Thử & Tiêu Chí Phân Loại](#1-nguyên-tắc-kiểm-thử--tiêu-chí-phân-loại)
2. [Danh Mục 6 Chức Năng & Kết Quả Mong Đợi Từng Ca (24 Ca)](#2-danh-mục-6-chức-năng--kết-quả-mong-đợi-từng-ca-24-ca)
   - [Chức năng 1: Xem hồ sơ cá nhân (GET /api/profile)](#chức-năng-1-xem-hồ-sơ-cá-nhân-get-apiprofile)
   - [Chức năng 2: Xem danh sách ghi chú (GET /api/notes)](#chức-năng-2-xem-danh-sách-ghi-chú-get-apinotes)
   - [Chức năng 3: Xem chi tiết ghi chú (GET /api/notes/{id}) — Trọng tâm IDOR](#chức-năng-3-xem-chi-tiết-ghi-chú-get-apinotesid--trọng-tâm-idor)
   - [Chức năng 4: Cập nhật ghi chú (PUT /api/notes/{id}) — Kiểm tra Quyền chưa khai báo](#chức-năng-4-cập-nhật-ghi-chú-put-apinotesid--kiểm-tra-quyền-chưa-khai-báo)
   - [Chức năng 5: Xem thông báo dùng chung (GET /api/announcements)](#chức-năng-5-xem-thông-báo-dùng-chung-get-apiannouncements)
   - [Chức năng 6: Quản lý danh sách người dùng (GET /api/admin/users)](#chức-năng-6-quản-lý-danh-sách-người-dùng-get-apiadminusers)
3. [Bảng Ma Trận Tổng Hợp 24 Ca: Trước vs Sau Khi Sửa Lỗi IDOR](#3-bảng-ma-trận-tổng-hợp-24-ca-trước-vs-sau-khi-sửa-lỗi-idor)
4. [Hướng Dẫn Chi Tiết 5 Phép Thử Bắt Buộc Theo Rubric](#4-hướng-dẫn-chi-tiết-5-phép-thử-bắt-buộc-theo-rubric)
5. [Quy Trình Các Bước Thực Hiện Kiểm Thử Thực Tế](#5-quy-trình-các-bước-thực-hiện-kiểm-thử-thực-tế)

---

## 1. NGUYÊN TẮC KIỂM THỬ & TIÊU CHÍ PHÂN LOẠI

### 1.1. Nguyên tắc phân quyền (RBAC kết hợp ABAC)
1. **RBAC (Role-Based Access Control):** Kiểm tra vai trò của tài khoản (`admin`, `member`, `auditor`, hoặc `anon` - ẩn danh).
2. **ABAC (Attribute-Based Access Control):** Kiểm tra quan hệ sở hữu đối với tài nguyên có phạm vi `own` (người dùng chỉ được thao tác trên bản ghi do mình sở hữu).
3. **Mặc định là từ chối (`DENY`):** Bất kỳ quyền nào chưa được khai báo tường minh trong ma trận quyền `permissions` hoặc tài khoản ẩn danh gọi API yêu cầu đăng nhập đều phải bị từ chối truy cập.

### 1.2. Tiêu chí phân loại kết quả phản hồi HTTP
- **`PASS` (Đạt):**
  - Khi kỳ vọng là `ALLOW`: Máy chủ trả về mã HTTP 2xx, phản hồi chứa chuỗi bắt buộc (`must_contain`) và **không** chứa chuỗi rò rỉ dữ liệu (`must_not_contain`).
  - Khi kỳ vọng là `DENY`: Máy chủ trả về mã HTTP 401 (Unauthorized) hoặc 403 (Forbidden) hoặc 404 (Not Found), và không làm rò rỉ dữ liệu nhạy cảm.
- **`FAIL` (Không đạt - Lỗi phân quyền hoặc rò rỉ dữ liệu):**
  - Kỳ vọng `DENY` nhưng máy chủ lại trả về HTTP 200 OK và trả về nội dung dữ liệu nhạy cảm của người khác (`leak_detected = true`).
  - Kỳ vọng `ALLOW` nhưng máy chủ lại chặn nhầm (trả về 401/403/404).
  - Phản hồi thiếu chuỗi bắt buộc `must_contain` hoặc chứa chuỗi cấm `must_not_contain`.
- **`ERROR` (Lỗi kỹ thuật / Mạng):**
  - Không kết nối được đến máy chủ (cổng đóng, máy chủ tắt: `CONNECT_FAILED`).
  - Hết thời gian chờ phản hồi (`TIMEOUT`).
  - Địa chỉ đích không nằm trong danh sách máy chủ được duyệt (`TARGET_NOT_ALLOWED`).
  - Lỗi sập máy chủ nội bộ (HTTP 500 `SERVER_ERROR`).
  > **Quy tắc an toàn sống còn:** Lỗi mạng hoặc cổng đóng **tuyệt đối không bao giờ được coi là `DENY` hay `PASS`**.

---

## 2. DANH MỤC 6 CHỨC NĂNG & KẾT QUẢ MONG ĐỢI TỪNG CA (24 CA)

### Chức năng 1: Xem hồ sơ cá nhân (`GET /api/profile`)
- **Mô tả:** Người dùng đã đăng nhập được xem thông tin hồ sơ của chính mình (chế độ `own`). Người chưa đăng nhập (`anon`) bị từ chối.
- **Phương thức:** `GET`
- **Địa chỉ:** `http://127.0.0.1:8205/api/profile`

| Mã ca | Tài khoản thử | Vai trò | Quyền mong đợi | Mã HTTP kỳ vọng | Chuỗi bắt buộc (`must_contain`) | Chuỗi cấm rò rỉ (`must_not_contain`) | Kết quả mong đợi | Giải thích nghiệp vụ |
| :---: | :---: | :---: | :---: | :---: | :---: | :---: | :---: | :--- |
| **P01** | `alice` | `member` | `ALLOW` | 200 | `"username":"alice"` | *(Không)* | **PASS** | Alice xem thông tin hồ sơ của chính mình. |
| **P02** | `admin1` | `admin` | `ALLOW` | 200 | `"username":"admin1"` | *(Không)* | **PASS** | Admin xem thông tin hồ sơ của chính mình. |
| **P03** | `auditor1`| `auditor`| `ALLOW` | 200 | `"username":"auditor1"`| *(Không)* | **PASS** | Auditor xem thông tin hồ sơ của chính mình. |
| **P04** | `anon` | Khách | `DENY` | 401 | *(Không)* | *(Không)* | **PASS** | Khách chưa đăng nhập bị chặn với HTTP 401. |

---

### Chức năng 2: Xem danh sách ghi chú (`GET /api/notes`)
- **Mô tả:** Lấy danh sách ghi chú. Thành viên thường (`member`) chỉ được thấy các ghi chú của mình. Quản trị viên (`admin`) và Kiểm toán (`auditor`) có quyền xem toàn bộ (`scope = 'any'`).
- **Phương thức:** `GET`
- **Địa chỉ:** `http://127.0.0.1:8205/api/notes`

| Mã ca | Tài khoản thử | Vai trò | Quyền mong đợi | Mã HTTP kỳ vọng | Chuỗi bắt buộc (`must_contain`) | Chuỗi cấm rò rỉ (`must_not_contain`) | Kết quả mong đợi | Giải thích nghiệp vụ |
| :---: | :---: | :---: | :---: | :---: | :---: | :---: | :---: | :--- |
| **N01** | `alice` | `member` | `ALLOW` | 200 | *(Không)* | `Bi mat cua Bob 3` | **PASS** | Alice chỉ thấy danh sách ghi chú của mình, tuyệt đối không thấy bí mật của Bob. |
| **N02** | `admin1` | `admin` | `ALLOW` | 200 | *(Không)* | *(Không)* | **PASS** | Admin có quyền `notes.read(any)`, xem được toàn bộ ghi chú. |
| **N03** | `auditor1`| `auditor`| `ALLOW` | 200 | *(Không)* | *(Không)* | **PASS** | Auditor có quyền `notes.read(any)`, xem được toàn bộ ghi chú phục vụ kiểm toán. |
| **N04** | `anon` | Khách | `DENY` | 401 | *(Không)* | `Bi mat` | **PASS** | Khách chưa đăng nhập bị từ chối truy cập. |

---

### Chức năng 3: Xem chi tiết ghi chú (`GET /api/notes/{id}`) — Trọng tâm IDOR
- **Mô tả:** Xem nội dung chi tiết của 1 ghi chú cụ thể. Đây là nơi xảy ra lỗi bảo mật kiểm soát truy cập trực tiếp đối tượng (IDOR) trong ứng dụng mẫu.
- **Phương thức:** `GET`
- **Địa chỉ:** `http://127.0.0.1:8205/api/notes/{id}`

| Mã ca | Tài khoản thử | Target URL | Chủ sở hữu bản ghi | Quyền mong đợi | Kỳ vọng HTTP | Chuỗi kiểm tra | Kết quả khi `BUG_IDOR=1` (Bản Lỗi) | Kết quả khi `BUG_IDOR=0` (Bản Sửa) |
| :---: | :---: | :---: | :---: | :---: | :---: | :---: | :---: | :---: |
| **R01** | `alice` | `/api/notes/1` | `alice` (Ghi chú #1) | `ALLOW` | 200 | `must_contain = 'Bi mat cua Alice 1'` | **PASS** (Alice đọc đúng note của mình) | **PASS** (Alice đọc đúng note của mình) |
| **R02** | `alice` | `/api/notes/3` | `bob` (Ghi chú #3) | `DENY` | 403 | `must_not_contain = 'Bi mat cua Bob 3'` | ❌ **FAIL** (Phát hiện rò rỉ: Server trả 200 kèm chuỗi bí mật của Bob, `leak_detected=true`) |  **PASS** (Server đã kiểm tra owner và trả về HTTP 403 Forbidden) |
| **R03** | `admin1` | `/api/notes/3` | `bob` (Ghi chú #3) | `ALLOW` | 200 | *(Không)* | **PASS** (Admin có quyền xem mọi bản ghi) | **PASS** (Admin có quyền xem mọi bản ghi) |
| **R04** | `bob` | `/api/notes/1` | `alice` (Ghi chú #1) | `DENY` | 403 | `must_not_contain = 'Bi mat cua Alice 1'` | ❌ **FAIL** / Cảnh báo IDOR |  **PASS** (Server chặn Bob đọc note của Alice với HTTP 403) |

> **Điểm nhấn bảo mật:** Ca kiểm thử **`R02`** chính là bằng chứng xác thực cho Yêu cầu kỹ thuật 2 và Phép thử 1, 2.

---

### Chức năng 4: Cập nhật ghi chú (`PUT /api/notes/{id}`) — Kiểm tra Quyền chưa khai báo
- **Mô tả:** Cập nhật nội dung ghi chú. Chỉ chủ sở hữu ghi chú (`member` sở hữu bản ghi) mới có quyền sửa.
- **Nguyên tắc kiểm tra:** Quản trị viên (`admin`) và Kiểm toán (`auditor`) **chưa được khai báo quyền `notes.update`** trong ma trận phân quyền -> Theo nguyên tắc an toàn, hệ thống phải **mặc định là DENY**.
- **Phương thức:** `PUT` (Payload JSON)
- **Địa chỉ:** `http://127.0.0.1:8205/api/notes/{id}`

| Mã ca | Tài khoản thử | Target URL | Chủ sở hữu | Quyền mong đợi | Mã HTTP kỳ vọng | Payload gửi đi | Kết quả mong đợi | Giải thích nghiệp vụ |
| :---: | :---: | :---: | :---: | :---: | :---: | :---: | :---: | :--- |
| **U01** | `alice` | `/api/notes/1` | `alice` | `ALLOW` | 200 | `{"title":"Ghi chú 1 của Alice"}` | **PASS** | Alice sửa ghi chú số 1 của chính mình -> Được phép. |
| **U02** | `alice` | `/api/notes/3` | `bob` | `DENY` | 403 | `{"title":"alice sửa trái phép"}` | **PASS** | Alice cố tình sửa ghi chú của Bob -> Bị từ chối (HTTP 403). |
| **U03** | `admin1` | `/api/notes/3` | `bob` | `DENY` | 403 | `{"title":"admin sửa trái phép"}` | **PASS** | Admin chưa có quyền `notes.update` -> **Mặc định DENY**. |
| **U04** | `auditor1`| `/api/notes/1` | `alice` | `DENY` | 403 | `{"title":"auditor sửa trái phép"}` | **PASS** | Auditor chỉ có quyền đọc, không có quyền sửa -> **Mặc định DENY**. |

---

### Chức năng 5: Xem thông báo dùng chung (`GET /api/announcements`)
- **Mô tả:** Tài nguyên dùng chung công khai cho tất cả người dùng trong hệ thống (`scope = 'any'`).
- **Phương thức:** `GET`
- **Địa chỉ:** `http://127.0.0.1:8205/api/announcements`

| Mã ca | Tài khoản thử | Vai trò | Quyền mong đợi | Mã HTTP kỳ vọng | Chuỗi bắt buộc (`must_contain`) | Kết quả mong đợi | Giải thích nghiệp vụ |
| :---: | :---: | :---: | :---: | :---: | :---: | :---: | :--- |
| **A01** | `alice` | `member` | `ALLOW` | 200 | `announcements` | **PASS** | Alice xem thông báo dùng chung thành công. |
| **A02** | `bob` | `member` | `ALLOW` | 200 | `announcements` | **PASS** | Bob xem thông báo dùng chung thành công. |
| **A03** | `auditor1`| `auditor`| `ALLOW` | 200 | `announcements` | **PASS** | Auditor xem thông báo dùng chung thành công. |
| **A04** | `anon` | Khách | `DENY` | 401 | *(Không)* | **PASS** | Khách chưa xác thực bị chặn truy cập. |

---

### Chức năng 6: Quản lý danh sách người dùng (`GET /api/admin/users`)
- **Mô tả:** Tài nguyên quản trị hệ thống mức cao. Chỉ duy nhất vai trò `admin` được cấp quyền truy cập. Các vai trò khác chưa được khai báo quyền nên mặc định bị từ chối (`DENY`).
- **Phương thức:** `GET`
- **Địa chỉ:** `http://127.0.0.1:8205/api/admin/users`

| Mã ca | Tài khoản thử | Vai trò | Quyền mong đợi | Mã HTTP kỳ vọng | Chuỗi bắt buộc (`must_contain`) | Chuỗi cấm rò rỉ (`must_not_contain`) | Kết quả mong đợi | Giải thích nghiệp vụ |
| :---: | :---: | :---: | :---: | :---: | :---: | :---: | :---: | :--- |
| **M01** | `admin1` | `admin` | `ALLOW` | 200 | `"users"` | `password` | **PASS** | Admin xem danh sách người dùng, không được để lộ mật khẩu trong JSON. |
| **M02** | `alice` | `member` | `DENY` | 403 | *(Không)* | *(Không)* | **PASS** | Member không có quyền xem trang quản trị -> Bị chặn (HTTP 403). |
| **M03** | `auditor1`| `auditor`| `DENY` | 403 | *(Không)* | *(Không)* | **PASS** | Auditor chưa được cấp quyền quản trị user -> Bị chặn (HTTP 403). |
| **M04** | `anon` | Khách | `DENY` | 401 | *(Không)* | *(Không)* | **PASS** | Khách chưa đăng nhập bị chặn (HTTP 401). |

---

## 3. BẢNG MA TRẬN TỔNG HỢP 24 CA: TRƯỚC VS SAU KHI SỬA LỖI IDOR

| Nhóm ca | Mã ca | Yêu cầu HTTP | Tài khoản | Kỳ vọng Quyền | Kết quả khi `BUG_IDOR=1` (Trước sửa lỗi) | Kết quả khi `BUG_IDOR=0` (Sau sửa lỗi) | Chuyển biến trạng thái |
| :--- | :---: | :--- | :---: | :---: | :---: | :---: | :---: |
| **Profile** | `P01` | GET /api/profile | alice | ALLOW | **PASS** | **PASS** | Giữ nguyên |
| | `P02` | GET /api/profile | admin1 | ALLOW | **PASS** | **PASS** | Giữ nguyên |
| | `P03` | GET /api/profile | auditor1 | ALLOW | **PASS** | **PASS** | Giữ nguyên |
| | `P04` | GET /api/profile | anon | DENY | **PASS** | **PASS** | Giữ nguyên |
| **Notes List** | `N01` | GET /api/notes | alice | ALLOW | **PASS** | **PASS** | Giữ nguyên |
| | `N02` | GET /api/notes | admin1 | ALLOW | **PASS** | **PASS** | Giữ nguyên |
| | `N03` | GET /api/notes | auditor1 | ALLOW | **PASS** | **PASS** | Giữ nguyên |
| | `N04` | GET /api/notes | anon | DENY | **PASS** | **PASS** | Giữ nguyên |
| **Note Detail** | `R01` | GET /api/notes/1 | alice | ALLOW | **PASS** | **PASS** | Giữ nguyên |
| *(Trọng tâm)* | **`R02`**| **GET /api/notes/3** | **alice** | **DENY** | ❌ **FAIL** *(Lộ bí mật Bob)* |  **PASS** *(Trả về 403)* |  **FAIL -> PASS** |
| | `R03` | GET /api/notes/3 | admin1 | ALLOW | **PASS** | **PASS** | Giữ nguyên |
| | `R04` | GET /api/notes/1 | bob | DENY | **PASS** | **PASS** | Giữ nguyên |
| **Note Update**| `U01` | PUT /api/notes/1 | alice | ALLOW | **PASS** | **PASS** | Giữ nguyên |
| | `U02` | PUT /api/notes/3 | alice | DENY | **PASS** | **PASS** | Giữ nguyên |
| | `U03` | PUT /api/notes/3 | admin1 | DENY | **PASS** | **PASS** | Giữ nguyên |
| | `U04` | PUT /api/notes/1 | auditor1 | DENY | **PASS** | **PASS** | Giữ nguyên |
| **Announce** | `A01` | GET /api/announcements | alice | ALLOW | **PASS** | **PASS** | Giữ nguyên |
| | `A02` | GET /api/announcements | bob | ALLOW | **PASS** | **PASS** | Giữ nguyên |
| | `A03` | GET /api/announcements | auditor1 | ALLOW | **PASS** | **PASS** | Giữ nguyên |
| | `A04` | GET /api/announcements | anon | DENY | **PASS** | **PASS** | Giữ nguyên |
| **Admin Users**| `M01` | GET /api/admin/users | admin1 | ALLOW | **PASS** | **PASS** | Giữ nguyên |
| | `M02` | GET /api/admin/users | alice | DENY | **PASS** | **PASS** | Giữ nguyên |
| | `M03` | GET /api/admin/users | auditor1 | DENY | **PASS** | **PASS** | Giữ nguyên |
| | `M04` | GET /api/admin/users | anon | DENY | **PASS** | **PASS** | Giữ nguyên |
| **TỔNG KẾT** | **24 ca** | | | | **23 PASS - 1 FAIL** | **24 PASS - 0 FAIL** | **SỬA LỖI THÀNH CÔNG** |

---

## 4. HƯỚNG DẪN CHI TIẾT 5 PHÉP THỬ BẮT BUỘC THEO RUBRIC

Tất cả 5 phép thử được tự động hóa 100% trong tệp `tests/e2e.php` và có thể kiểm chứng độc lập:

### Phép thử 1: Phát hiện API mẫu cho người dùng xem dữ liệu người khác (IDOR)
- **Mục tiêu:** Chứng minh công cụ phát hiện được lỗi logic phân quyền qua HTTP thực tế chứ không bỏ lọt.
- **Kịch bản:** Chạy kiểm thử khi ứng dụng mẫu bật `BUG_IDOR=1`.
- **Hành vi thực tế:** Alice gửi `GET /api/notes/3` (ghi chú của Bob). Ứng dụng mẫu trả về HTTP 200 kèm chuỗi `"Bi mat cua Bob 3"`.
- **Kết quả mong đợi:** Ca `R02` bị phân loại là **`FAIL`** với thuộc tính `leak_detected = true`.

### Phép thử 2: Sửa lỗi ứng dụng mẫu và kiểm tra lại kết quả
- **Mục tiêu:** Chứng minh sau khi lập trình viên vá lỗi, công cụ xác nhận chức năng cũ đã đạt và đối chiếu được diff.
- **Kịch bản:** Tắt lỗi bằng `BUG_IDOR=0` và chạy lại kiểm thử với nhãn `after-fix`.
- **Hành vi thực tế:** Ứng dụng mẫu kiểm tra `user_id` và chặn Alice với HTTP 403 Forbidden.
- **Kết quả mong đợi:** Ca `R02` chuyển từ `FAIL` sang **`PASS`**, toàn bộ 24/24 ca đạt `PASS`. Khi chạy so sánh diff: dòng `R02` đổi màu highlight xanh lá thể hiện khắc phục thành công.

### Phép thử 3: Phân loại đúng ERROR khi cổng đóng hoặc địa chỉ cấm
- **Mục tiêu:** Đảm bảo khi mất mạng, cổng đóng hoặc gặp máy chủ ngoài danh sách duyệt, hệ thống phân loại là `ERROR`, không nhầm lẫn là từ chối đúng (`DENY`/`PASS`).
- **Kịch bản:** Gửi yêu cầu HTTP đến cổng đóng `8299` hoặc địa chỉ cấm `http://evil.com`.
- **Hành vi thực tế:** cURL bắt mã lỗi `CURLE_COULDNT_CONNECT` hoặc `TargetAllowlist` từ chối host.
- **Kết quả mong đợi:** Kết quả phân loại bắt buộc là **`ERROR`** (mã `CONNECT_FAILED` hoặc `TARGET_NOT_ALLOWED`), mã trạng thái HTTP là `0`. Tuyệt đối không bao giờ được ghi nhận là `PASS`.

### Phép thử 4: Bảo vệ cấu hình ca thử (Ngăn chặn sửa trái phép)
- **Mục tiêu:** Kiểm toán viên (`viewer`) hoặc thành viên thuộc dự án khác không được phép can thiệp, chỉnh sửa bộ ca kiểm thử.
- **Kịch bản:** Đăng nhập tài khoản `auditor_user` (vai trò `viewer`) hoặc tài khoản `dev2` (thuộc Dự án B), gửi yêu cầu sửa/xóa ca của Dự án A.
- **Hành vi thực tế:** Tầng Web UI ẩn nút bấm thao tác và hiển thị cảnh báo; Tầng Repository (`CaseRepository::assertCanModify`) chặn đứng và ném ngoại lệ `FORBIDDEN`.
- **Kết quả mong đợi:** Thao tác bị chặn hoàn toàn, trả về lỗi `403 Forbidden`. Cấu hình ca thử của Dự án A được bảo toàn nguyên vẹn.

### Phép thử 5: Không rò rỉ token bí mật và mật khẩu
- **Mục tiêu:** Đảm bảo các token xác thực nhạy cảm và mật khẩu CSDL không xuất hiện nguyên văn trong báo cáo hay nhật ký lưu trữ.
- **Kịch bản:** Quét toàn bộ nội dung file báo cáo Markdown (`.md`), HTML (`.html`) và tệp nhật ký `.local/runner.log`.
- **Hành vi thực tế:** Module `Redactor` quét regex tất cả các header `Authorization: Bearer <token>`, trường `password` và thay thế bằng `***REDACTED***`.
- **Kết quả mong đợi:** 100% không tìm thấy bất kỳ chuỗi token thực tế nào của Alice, Bob, Admin hay mật khẩu `matkhau_lab_123`.

---

## 5. QUY TRÌNH CÁC BƯỚC THỰC HIỆN KIỂM THỬ THỰC TẾ

Mở terminal Ubuntu/WSL tại thư mục `lab5/Ma_nguon`:

### Bước 1: Khởi động CSDL và nạp dữ liệu chuẩn
```bash
# Nạp CSDL tự động 1 lệnh duy nhất
bash lab.sh setup

# Kiểm tra môi trường
bash lab.sh check
```

### Bước 2: Chạy bộ kiểm thử tự động 88 assertions
```bash
bash lab.sh test
```
*Kết quả kỳ vọng:*
```
=== KIỂM THỬ ĐƠN VỊ (UNIT TESTS) ===
TESTS=39 FAILED=0
=== KIỂM THỬ TÍCH HỢP CSDL (INTEGRATION TESTS) ===
TESTS=29 FAILED=0
=== KIỂM THỬ ĐẦU-CUỐI END-TO-END (5 PHÉP THỬ BẮT BUỘC) ===
TESTS=20 FAILED=0
```

### Bước 3: Thực hiện kiểm thử phát hiện lỗi IDOR (Tình huống 1)
```bash
# Terminal 1: Chạy sample app bật lỗi IDOR
BUG_IDOR=1 bash lab.sh sample

# Terminal 2: Chạy kiểm thử CLI lần 1
source env.local.sh
bash lab.sh cli --project=1 --label=before-fix
```
*Kết quả:* 23 PASS, 1 FAIL (tại ca `R02`).

### Bước 4: Khắc phục lỗi và kiểm tra lại (Tình huống 2)
```bash
# Terminal 1: Khởi động lại sample app đã sửa lỗi
BUG_IDOR=0 bash lab.sh sample

# Terminal 2: Chạy kiểm thử CLI lần 2
bash lab.sh cli --project=1 --label=after-fix
```
*Kết quả:* 24/24 PASS (100% Đạt).

### Bước 5: Kiểm thử trực tiếp trên Giao diện Web (Khuyên dùng khi bảo vệ)
1. Mở Terminal 1: Khởi động Ứng dụng mẫu: `bash lab.sh sample`
2. Mở Terminal 2: Khởi động Web UI: `bash lab.sh run`
3. Mở trình duyệt tại **`http://127.0.0.1:8105/runs.php`**:
   - Giao diện Web tự động hiển thị trạng thái kết nối tới máy chủ mẫu.
   - Tại mục **"Chế độ kiểm thử (Kịch bản)"**:
     + Chọn **"Tái hiện lỗi IDOR (Kỳ vọng: 23 PASS, 1 FAIL)"** -> Nhấn **Bắt đầu kiểm thử HTTP thật** -> Kết quả đợt 1: **23 PASS, 1 FAIL** (tại ca `R02`).
     + Chọn **"Bản đã sửa lỗi (Kỳ vọng: 24 PASS, 0 FAIL)"** -> Nhấn **Bắt đầu kiểm thử HTTP thật** -> Kết quả đợt 2: **24 PASS, 0 FAIL** (100% Đạt).
   - Truy cập trang **So sánh (`compare.php`)**: Chọn đợt 1 (`before-fix`) và đợt 2 (`after-fix`) -> Nhấn **So sánh ngay** để thấy bảng diff chuyển trạng thái `FAIL` -> `PASS` nổi bật.

### Bước 6: So sánh đối chiếu diff và xuất báo cáo qua CLI
```bash
# So sánh 2 lần chạy
bash lab.sh cli --compare=1,2 --project=1

# Xuất báo cáo Markdown và HTML
bash lab.sh cli --report=2 --format=md
bash lab.sh cli --report=2 --format=html
```

### Bước 7: Đóng gói bàn giao
```bash
bash lab.sh release
```
*Tệp nén phân phối chuẩn:* `build/accesstest-release-YYYYMMDD.tar.gz`.
