# Tài liệu Thiết kế Kiến trúc Hệ thống – AccessTester

**Học phần:** Phát triển phần mềm mã nguồn mở  
**Đề tài 4:** Xây dựng công cụ kiểm thử tự động quyền truy cập trong ứng dụng Web  
**Tác giả:** Phạm Lê — Nhóm 14  
**Phiên bản:** 2.0.0

---

## 1. Tổng Quan Kiến Trúc Hệ Thống

Hệ thống **AccessTester** được thiết kế theo kiến trúc module hóa phân tầng rõ rệt, tách rời giữa **Công cụ kiểm thử (Test Engine & Controller)**, **Cơ sở dữ liệu lưu trữ (PostgreSQL)** và **Ứng dụng Web mẫu bị kiểm thử (Sample App Target)**:

```
+-----------------------------------------------------------------------------------------+
|                              1. CÔNG CỤ KIỂM THỬ (ACCESSTESTER)                         |
|                                                                                         |
|   +--------------------------+                         +----------------------------+   |
|   |   Giao diện Web (8105)   |                         |    Dòng lệnh CLI Runner    |   |
|   |      (public/*.php)      |                         |       (bin/run.php)        |   |
|   +------------+-------------+                         +--------------+-------------+   |
|                |                                                      |                 |
|                +--------------------------+---------------------------+                 |
|                                           |                                             |
|                                           v                                             |
|                             +---------------------------+                               |
|                             |      Runner Controller    |                               |
|                             |     (Tính config_hash)    |                               |
|                             +-------------+-------------+                               |
|                                           |                                             |
|           +-------------------------------+-------------------------------+             |
|           |                               |                               |             |
|           v                               v                               v             |
|   +---------------+               +---------------+               +---------------+     |
|   | Policy Oracle |               | TargetAllowlist|              |    Redactor   |     |
|   |  (RBAC/ABAC)  |               | (Duyệt Host)  |               | (Khử bí mật)  |     |
|   +-------+-------+               +-------+-------+               +-------+-------+     |
|           |                               |                               |             |
|           +-------------------------------+-------------------------------+             |
|                                           |                                             |
|                                           v                                             |
|                             +---------------------------+                               |
|                             |        HttpClient         |                               |
|                             |   (cURL thật, độc lập)    |                               |
|                             +-------------+-------------+                               |
+-------------------------------------------|---------------------------------------------+
                                            |
                     +----------------------+----------------------+
                     | Gửi HTTP cURL thật                          | Lưu trữ & Snapshotting
                     | (Xác thực Bearer Token)                     | (Truy vấn tham số hóa)
                     v                                             v
+-------------------------------------------+   +-----------------------------------------+
|     3. ỨNG DỤNG MẪU (PORT 8205)           |   |       2. CƠ SỞ DỮ LIỆU (POSTGRESQL)     |
|                                           |   |                                         |
| - 6 REST APIs chuẩn                       |   | - Database: accesstest_lab              |
| - Dữ liệu dùng chung & dữ liệu riêng      |   | - Schema public:                        |
| - Công tắc lỗ hổng IDOR cố ý (BUG_IDOR)   |   |   + projects, project_members           |
| - Cơ chế kiểm tra quyền truy cập cục bộ:  |   |   + roles, resources, actions           |
|   + BUG_IDOR=1: Bỏ qua kiểm tra owner     |   |   + permissions, records, test_accounts |
|   + BUG_IDOR=0: Kiểm tra chặt chẽ owner   |   |   + test_cases, runs, run_results       |
|                                           |   | - Schema sample_app:                    |
|                                           |   |   + users, notes, announcements         |
+-------------------------------------------+   +-----------------------------------------+
```

---

## 2. Chi Tiết Các Tầng và Mô-đun Thành Phần

### 2.1. Động cơ Phân Quyền Kép (Policy Oracle – `src/Policy.php`)
- **Nguyên tắc an toàn cốt lõi (Yêu cầu kỹ thuật 1):** Mặc định luôn là **`DENY`**.
- Mọi vai trò hoặc tài khoản không có bản ghi tương ứng trong bảng `permissions`, hoặc các yêu cầu từ người dùng chưa xác thực (ẩn danh) khi gọi API yêu cầu bảo mật, đều bị từ chối truy cập.
- **Mô hình kết hợp RBAC & ABAC:**
  - **RBAC (Role-Based Access Control):** Phân quyền dựa trên vai trò của tài khoản (`admin`, `member`, `auditor`).
  - **ABAC (Attribute-Based Access Control):** Phân quyền theo thuộc tính chủ sở hữu (`scope = 'own'`). Khi truy cập tài nguyên kiểu `own`, hệ thống kiểm tra `ownerMatch` giữa `user_id` hiện tại và trường `owner_id` của bản ghi trong bảng `records`.
- Hàm `Policy::computeExpectedForCase($pdo, $case)` hoạt động như một **Oracle chuẩn**, tự động truy vấn CSDL để xác định kết quả kỳ vọng chuẩn mực cho bất kỳ ca thử nào mà không bị ảnh hưởng bởi lỗi ở phía ứng dụng Web mục tiêu.

### 2.2. Kiểm Soát Máy Chủ An Toàn (Target Allowlist – `src/TargetAllowlist.php`)
- Ngăn ngừa công cụ bị lợi dụng để tấn công Server-Side Request Forgery (SSRF) hoặc quét trái phép mạng ngoài.
- Nạp cấu hình từ `config/targets.json`:
  - Chỉ chấp nhận scheme `http` và `https`.
  - Chỉ chấp nhận IP `127.0.0.1`, `::1` hoặc tên miền `localhost`.
  - Giới hạn các cổng được phê duyệt: `8205` (Sample App chính thức), `8295`, `8296`, `8297`, `8298`.
  - Tuyệt đối cấm URL chứa thông tin xác thực inline (dạng `http://user:pass@host`).
  - Mọi yêu cầu vi phạm sẽ bị từ chối ngay lập tức với mã lỗi `TARGET_NOT_ALLOWED`.

### 2.3. Trình Gửi HTTP Thực Tế & Phân Loại Kết Quả (`src/HttpClient.php`)
- **Yêu cầu kỹ thuật 2:** Gửi yêu cầu HTTP thật bằng thư viện cURL của PHP, tạo phiên độc lập hoàn toàn cho từng ca thử (không chia sẻ cookie hay cache giữa các lần gọi).
- Thiết lập `CURLOPT_FOLLOWLOCATION = false` để tránh việc ứng dụng tự ý chuyển hướng 302 làm sai lệch mã trạng thái HTTP nhận được.
- Xử lý các ngoại lệ mạng: timeout kết nối (`CURLOPT_CONNECTTIMEOUT`), timeout phản hồi (`CURLOPT_TIMEOUT`).
- **Phân loại kết quả (Phép thử 3):**
  - **`ERROR`:** Gặp lỗi mạng (`CONNECT_FAILED`), quá thời gian (`TIMEOUT`), máy chủ đích không nằm trong danh sách duyệt (`TARGET_NOT_ALLOWED`), hoặc máy chủ lỗi nội bộ HTTP 500 (`SERVER_ERROR`).
    > *Lưu ý quan trọng:* Sự cố lỗi mạng hoặc máy chủ đóng cổng **tuyệt đối không bao giờ được phân loại là DENY hay PASS**.
  - **`FAIL`:** Máy chủ phản hồi mã HTTP không khớp với kỳ vọng, hoặc phản hồi chứa chuỗi rò rỉ dữ liệu bị cấm (`must_not_contain`) ngay cả khi trả về HTTP 200.
  - **`PASS`:** Kết quả phản hồi thực tế khớp hoàn toàn với mong đợi của Policy Oracle và không chứa bất kỳ chuỗi rò rỉ nào.

### 2.4. Khử Dữ Liệu Nhạy Cảm (Redactor – `src/Redactor.php`)
- Bảo vệ dữ liệu nhạy cảm theo tiêu chuẩn an toàn thông tin (Phép thử 5).
- Tự động nạp danh sách các token bí mật từ biến môi trường và quá trình chạy.
- Thực hiện quét và thay thế bằng chuỗi an toàn `***REDACTED***`:
  - Các tiêu đề HTTP nhạy cảm: `Authorization: Bearer <token>`, `X-Api-Key`, `Cookie`.
  - Mật khẩu người dùng trong payload JSON, form-data hoặc query string: `password`, `matkhau_lab_123`.
  - Dữ liệu trước khi ghi ra nhật ký `.local/runner.log`, lưu vào CSDL hoặc xuất ra file báo cáo Markdown/HTML đều bắt buộc phải đi qua `Redactor`.

### 2.5. Bảo Toàn Dữ Liệu Độc Lập (Snapshotting & Runs – `src/RunRepository.php`)
- Mỗi lần chạy kiểm thử được ghi nhận trong bảng `runs` với một mã băm cấu hình `config_hash = hash('sha256', json_encode($cases))` và bản chụp `config_snapshot`.
- Kết quả từng ca thử được lưu vào bảng `run_results` với đầy đủ bản chụp thông tin cấu hình tại thời điểm chạy: `case_code`, `method`, `path`, `expected_status`, `must_contain`, `must_not_contain`.
- **Lợi ích kiến trúc:** Đảm bảo tính bất biến (idempotent). Nếu trong tương lai người dùng thêm, sửa hoặc xóa ca thử trong bảng `test_cases`, lịch sử của các lần chạy trước vẫn được bảo toàn nguyên vẹn 100%, không bị sai lệch dữ liệu.
- **Thuật toán so sánh:** Sử dụng phép nối ngoài toàn phần (`Full Outer Join`) theo `case_code` để đối chiếu hai lần chạy bất kỳ, ngay cả khi cấu hình ca thử giữa hai lần chạy có sự thay đổi.

### 2.6. Phân Quyền Người Dùng Công Cụ (`src/Auth.php` & `src/CaseRepository.php`)
- Hệ thống hỗ trợ 3 vai trò thành viên dự án:
  - `owner`: Trưởng nhóm kiểm thử — toàn quyền quản trị dự án, thành viên, cấu hình và thực thi.
  - `editor`: Lập trình viên — có quyền khai báo/sửa đổi ca thử và chạy kiểm thử.
  - `viewer`: Kiểm toán viên hoặc người quan sát — **chỉ được xem** báo cáo và cấu hình.
- **Bảo vệ 2 tầng cho Phép thử 4:**
  - **Tầng giao diện Web:** Ẩn các nút "Thêm", "Sửa", "Xóa" và hiển thị cảnh báo chỉ xem nếu người dùng hiện tại có vai trò `viewer`.
  - **Tầng CSDL (Repository Guard):** Hàm `CaseRepository::assertCanModify($projectId, $userId)` kiểm tra quyền sở hữu và vai trò. Nếu tài khoản có vai trò `viewer` hoặc thuộc dự án khác cố tình gửi request sửa/xóa ca thử, hệ thống lập tức chặn lại và ném ngoại lệ `FORBIDDEN`.

---

## 3. Quy Trình Vận Hành Kiểm Thử Tự Động

```mermaid
sequenceDiagram
    autonumber
    actor User as Kiểm thử viên (Web/CLI)
    participant Runner as Runner Controller
    participant Allowlist as TargetAllowlist
    participant Oracle as Policy Oracle
    participant Redactor as Redactor
    participant Http as HttpClient (cURL)
    participant Target as Sample App (Port 8205)
    participant DB as PostgreSQL (accesstest_lab)

    User->>Runner: Kích hoạt kiểm thử (project_id = 1)
    Runner->>DB: Nạp 24 ca kiểm thử chuẩn
    Runner->>Runner: Tính config_hash (SHA-256) & snapshot
    Runner->>DB: Tạo bản ghi lần chạy mới trong bảng `runs`
    
    loop Lặp qua từng ca kiểm thử (R01 -> R24)
        Runner->>Allowlist: Kiểm tra URL máy chủ mục tiêu
        alt Máy chủ không hợp lệ / Cổng cấm
            Allowlist-->>Runner: Từ chối (TARGET_NOT_ALLOWED)
            Runner->>DB: Ghi kết quả ERROR (Lỗi cấu hình mạng)
        else Máy chủ hợp lệ
            Runner->>Oracle: Xác định kết quả mong đợi chuẩn (RBAC + ABAC)
            Runner->>Http: Gửi request HTTP thật (Header, Bearer Token)
            Http->>Target: Gửi cURL request tới API
            Target-->>Http: Phản hồi HTTP (Status Code, Body JSON)
            Http->>Runner: Phân loại kết quả (PASS, FAIL, ERROR)
            Runner->>Redactor: Khử token & mật khẩu khỏi request/response
            Redactor-->>Runner: Dữ liệu an toàn
            Runner->>DB: Lưu snapshot kết quả vào bảng `run_results`
        end
    end
    
    Runner->>DB: Cập nhật thời gian hoàn thành (finished_at)
    Runner-->>User: Hiển thị thống kê tổng quan (PASS / FAIL / ERROR)
```

---

## 4. Đặc Tả Ứng Dụng Mẫu (Sample App) và Cơ Chế Tái Hiện Lỗi

Ứng dụng mẫu nằm trong thư mục `sample-app/public/index.php`, chạy trên cổng độc lập `8205` và sở hữu schema CSDL riêng `sample_app` gồm:
- Bảng `sample_app.users`: Chứa 4 tài khoản thử nghiệm (`admin`, `alice`, `bob`, `auditor`).
- Bảng `sample_app.notes`: Chứa các ghi chú cá nhân với trường `user_id` sở hữu.
- Bảng `sample_app.announcements`: Chứa các thông báo dùng chung.

### Cơ chế công tắc lỗi `BUG_IDOR`:
```php
// Đoạn mã kiểm soát tại sample-app/public/index.php:
if ($bugIdor) {
    // KHI BẬT LỖI CỐ Ý:
    // Ứng dụng chỉ kiểm tra người dùng đã đăng nhập hay chưa,
    // hoàn toàn bỏ qua việc đối chiếu chủ sở hữu (user_id != note.user_id)
    echo json_encode(['status' => 'success', 'data' => $note]);
} else {
    // KHI ĐÃ SỬA LỖI:
    // Kiểm tra chặt chẽ: chỉ chủ sở hữu hoặc quản trị viên (admin) mới được xem
    if ($currentUser['role'] !== 'admin' && $note['user_id'] !== $currentUser['id']) {
        http_response_code(403);
        echo json_encode(['error' => 'Forbidden']);
        exit;
    }
    echo json_encode(['status' => 'success', 'data' => $note]);
}
```

Nhờ cơ chế này, công cụ AccessTester chứng minh được năng lực phát hiện lỗ hổng logic nghiệp vụ thông qua lưu lượng mạng thực tế chứ không dừng lại ở mức so sánh hai hàm tính toán tĩnh.
