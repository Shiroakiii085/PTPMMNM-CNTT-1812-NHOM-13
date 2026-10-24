# BÀI TẬP LỚN: CÔNG CỤ KIỂM THỬ TỰ ĐỘNG QUYỀN TRUY CẬP WEB (ACCESSTESTER)

**Học phần:** Phát triển phần mềm mã nguồn mở  
**Đề tài 4:** Xây dựng công cụ kiểm thử tự động quyền truy cập trong ứng dụng Web  
**Sinh viên thực hiện:** Nhóm 13  
**Nền tảng khởi tạo:** Nhánh `lab5` (FIT-DNU/Open-Source-Software-Development)  
**Công nghệ:** PHP 8.1+, PostgreSQL 14+, cURL, HTML5/CSS3, Bash/WSL  
**Giấy phép:** [MIT License](Ma_nguon/LICENSE)  
**Trạng thái kiểm thử:** [![Tests](https://img.shields.io/badge/Tests-88%2F88%20PASS-brightgreen.svg)](Ma_nguon/tests/)

---

## 1. Giới thiệu Đề tài

Trong các hệ thống Web phân tán, kiểm soát truy cập (Access Control) là một trong những phòng tuyến bảo mật quan trọng nhất. Tuy nhiên, lập trình viên thường mắc lỗi bỏ sót kiểm tra quyền sở hữu dữ liệu (lỗ hổng **IDOR - Insecure Direct Object Reference**).

**AccessTester** là một công cụ kiểm thử tự động:
1. Gửi các yêu cầu HTTP cURL độc lập đến ứng dụng mục tiêu được duyệt trong danh sách an toàn (`TargetAllowlist`).
2. Tự động so sánh phản hồi thực tế với **động cơ ma trận phân quyền kép (RBAC & ABAC)** theo quy tắc an toàn cốt lõi: **Mặc định là từ chối (DENY)**.
3. Tự động phát hiện các chuỗi dữ liệu rò rỉ (`must_not_contain`) và phân biệt rõ ràng giữa lỗi phân quyền (`FAIL`) với sự cố mất kết nối mạng (`ERROR`).
4. Lưu trữ lịch sử lần chạy kèm **mã băm cấu hình (`config_hash`)** và **bản chụp độc lập (snapshot)** để bảo toàn tính toàn vẹn dữ liệu.
5. Cung cấp tính năng **đối chiếu so sánh (Diff)** giữa các lần chạy trước và sau khi sửa lỗi, trực quan hóa tiến trình khắc phục lỗ hổng.
6. Hỗ trợ song song cả **Giao diện Web trực quan (Port 8105)** và **Giao diện Dòng lệnh CLI** thích hợp cho đường ống CI/CD.

---

## 2. Bảng Tự Đánh Giá Theo Rubric Chấm Điểm (10.0 / 10.0 Điểm)

| STT | Nhóm tiêu chí | Điểm tối đa | Điểm tự đánh giá | Minh chứng mã nguồn và kết quả thực tế |
| :---: | :--- | :---: | :---: | :--- |
| **I** | **5 Chức năng cốt lõi** | **3.0đ** | **3.0 / 3.0** | - Quản lý dự án, vai trò, tài nguyên, hành động, bản ghi: `public/projects.php`, `src/ProjectRepository.php`.<br>- Khai báo ca thử (tài khoản, HTTP, quyền mong đợi, kết quả kỳ vọng): `public/cases.php`, `src/CaseRepository.php`.<br>- Ứng dụng mẫu có 6 API và lỗi cố ý: `sample-app/public/index.php` (`BUG_IDOR=1/0`).<br>- Gửi HTTP thật, ghi mã trạng thái, phân loại lỗi mạng: `src/HttpClient.php`.<br>- Lưu lần chạy, so sánh trước/sau, xuất báo cáo MD/HTML, chạy CLI: `public/runs.php`, `public/compare.php`, `public/report.php`, `bin/run.php`. |
| **II** | **Yêu cầu kỹ thuật 1** | **1.0đ** | **1.0 / 1.0** | - Kiểm tra kết hợp vai trò (RBAC) và chủ sở hữu (ABAC).<br>- Quyền chưa khai báo hoặc người dùng ẩn danh **mặc định là DENY**: `src/Policy.php` (`decide()`, `computeExpectedForCase()`). |
| **III**| **Yêu cầu kỹ thuật 2** | **1.0đ** | **1.0 / 1.0** | - Gửi cURL HTTP thật đến cổng `8205` của ứng dụng mẫu.<br>- Phát hiện chính xác lỗi IDOR cố ý ở ca `R02` (Alice xem ghi chú Bob) khi `BUG_IDOR=1`, không chỉ so sánh 2 hàm phân quyền nội bộ. |
| **IV** | **Dữ liệu & An toàn** | **1.0đ** | **1.0 / 1.0** | - Bảng `runs` gắn `config_hash` (SHA-256) và snapshot cấu hình.<br>- Bảng `run_results` lưu snapshot độc lập từng ca.<br>- `src/TargetAllowlist.php` chỉ cho phép `127.0.0.1` và `localhost` tại các cổng duyệt.<br>- `src/Redactor.php` loại bỏ 100% token bí mật, mật khẩu khỏi log và báo cáo. |
| **V** | **Kiểm thử & Sửa lỗi** | **1.5đ** | **1.5 / 1.5** | - Đáp án chuẩn: `tests/fixtures/answer_key.json`.<br>- Tự động hóa hoàn toàn **5 phép thử bắt buộc** trong `tests/e2e.php`.<br>- Đạt **88/88 test assertions PASS** (Unit: 39, Integration: 29, E2E: 20).<br>- So sánh lần chạy trước sửa (`FAIL`) và sau sửa (`PASS`). |
| **VI** | **Mã nguồn mở & Bàn giao** | **1.5đ** | **1.5 / 1.5** | - Giấy phép tự do [MIT License](Ma_nguon/LICENSE).<br>- Tài liệu nguồn gốc và ghi công chi tiết tại [Ma_nguon/docs/NGUON.md](Ma_nguon/docs/NGUON.md).<br>- CI tự động GitHub Actions `.github/workflows/ci.yml`.<br>- Lệnh đóng gói bản phát hành tự động `bash lab.sh release`. |
| **VII**| **Đóng góp cá nhân & Vấn đáp** | **1.0đ** | **1.0 / 1.0** | - Tài liệu thiết kế kiến trúc [Ma_nguon/docs/KIEN_TRUC.md](Ma_nguon/docs/KIEN_TRUC.md).<br>- Hướng dẫn thực hành và ngân hàng câu hỏi vấn đáp tại [Ma_nguon/docs/HUONG_DAN.md](Ma_nguon/docs/HUONG_DAN.md). |
| **TỔNG**| **TOÀN BỘ ĐỀ TÀI** | **10.0đ** | **10.0 / 10.0** | **Đạt xuất sắc mọi tiêu chí theo đề cương yêu cầu.** |

---

## 3. Khởi Chạy Nhanh Trong 3 Bước

Tất cả các lệnh đều được thực thi trong thư mục `lab5/Ma_nguon` trên môi trường Ubuntu/WSL:

```bash
cd lab5/Ma_nguon

# 1. Cấu hình CSDL và nạp dữ liệu tự động
bash lab.sh db-config
bash lab.sh setup

# 2. Kiểm tra tính sẵn sàng của môi trường
bash lab.sh check

# 3. Chạy toàn bộ 88 bài kiểm thử tự động
bash lab.sh test
```

---

## 4. Tài Khoản Phân Quyền Thử Nghiệm

Mật khẩu đăng nhập mặc định: **`AccessTest@2026`**

- **`phamle`**: Trưởng nhóm dự án (Role `owner`) — Toàn quyền.
- **`dev1`**: Thành viên phát triển (Role `editor`) — Có quyền sửa ca và chạy test.
- **`auditor_user`**: Kiểm toán độc lập (Role `viewer`) — Chỉ xem, bị chặn mọi thao tác sửa/xóa ca (Phép thử 4).
- **`dev2`**: Thành viên nhóm khác (Role `owner` Dự án B) — Bị từ chối truy cập tài nguyên Dự án A.

---

## 5. Hướng Dẫn Vận Hành Thử Nghiệm

Xem tài liệu hướng dẫn kỹ thuật chi tiết tại:
- [Hướng dẫn kiểm thử & Kết quả mong đợi 24 ca](KiemThu.md)
- [Hướng dẫn vận hành & Ngân hàng câu hỏi bảo vệ](Ma_nguon/docs/HUONG_DAN.md)
- [Tài liệu Thiết kế Kiến trúc Hệ thống](Ma_nguon/docs/KIEN_TRUC.md)
- [Hướng dẫn Mã nguồn & API](Ma_nguon/README.md)
- [Nguồn gốc mã nguồn & Giấy phép ghi công](Ma_nguon/docs/NGUON.md)
