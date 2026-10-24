# HƯỚNG DẪN THỰC HIỆN VÀ VẬN HÀNH BÀI TẬP LỚN ĐỀ TÀI 4

**Học phần:** Phát triển phần mềm mã nguồn mở  
**Đề tài 4:** Xây dựng công cụ kiểm thử tự động quyền truy cập trong ứng dụng Web (AccessTester)  
**Sinh viên thực hiện:** Nhóm 13  
**Nền tảng:** Ubuntu / WSL 2, PHP 8.1+, PostgreSQL 14+

---

## I. MỤC TIÊU VÀ ĐỐI TƯỢNG ĐỀ TÀI

### 1. Bối cảnh
Trong các ứng dụng Web, hệ thống phân quyền có thể được khai báo đúng trên lý thuyết nhưng mã nguồn xử lý thực tế vẫn để lộ dữ liệu của người khác (lỗ hổng IDOR - *Insecure Direct Object Reference*).

### 2. Người sử dụng
- **Người xây dựng bộ kiểm thử:** Khai báo dự án, vai trò, tài nguyên, hành động, ma trận phân quyền và các ca thử HTTP.
- **Thành viên phát triển / Quản trị viên:** Chạy kiểm thử tự động, so sánh kết quả trước/sau khi sửa lỗi, xuất báo cáo kỹ thuật.
- **Kiểm toán viên / Người quan sát:** Xem báo cáo đối soát độc lập mà không có quyền thay đổi cấu hình ca thử.

---

## II. QUY TRÌNH KHỞI TẠO VÀ CHẠY THỬ NGHIỆM

Mở terminal Ubuntu/WSL tại thư mục `lab5/Ma_nguon`.

### Bước 1: Khởi động dịch vụ PostgreSQL
```bash
sudo service postgresql start
```

### Bước 2: Tạo CSDL `accesstest_lab` (nếu chưa có)
```bash
sudo -u postgres psql -c "CREATE DATABASE accesstest_lab;"
sudo -u postgres psql -c "CREATE USER accesstest_lab WITH ENCRYPTED PASSWORD 'matkhau_lab_123';"
sudo -u postgres psql -c "GRANT ALL PRIVILEGES ON DATABASE accesstest_lab TO accesstest_lab;"
sudo -u postgres psql -c "ALTER DATABASE accesstest_lab OWNER TO accesstest_lab;"
```

### Bước 3: Cấu hình và nạp dữ liệu tự động (1 lệnh duy nhất)
```bash
bash lab.sh db-config
bash lab.sh setup
```

### Bước 4: Kiểm tra môi trường
```bash
bash lab.sh check
```
*Kết quả xuất hiện `MOI_TRUONG=SAN_SANG`.*

### Bước 5: Chạy toàn bộ 88 bài kiểm thử tự động
```bash
bash lab.sh test
```
*Kết quả: **TESTS=88 FAILED=0 (100% ĐẠT)**.*

---

## III. MINH HỌA 2 TÌNH HUỐNG THỬ NGHIỆM (DEMO SỬA LỖI IDOR)

### Tình huống 1: Phát hiện lỗ hổng IDOR trên ứng dụng lỗi
1. Chạy Ứng dụng mẫu ở chế độ bật lỗi:
   ```bash
   BUG_IDOR=1 bash lab.sh sample
   ```
2. Mở terminal thứ 2, nạp token và chạy kiểm thử:
   ```bash
   source env.local.sh
   bash lab.sh cli --project=1 --label=before-fix
   ```
   *Kết quả:* Phát hiện ca **`R02` (Alice xem note 3 của Bob)** bị **`FAIL`** do rò rỉ dữ liệu `Bi mat cua Bob 3`.

### Tình huống 2: Xác nhận sửa lỗi thành công
1. Chạy Ứng dụng mẫu ở chế độ đã sửa lỗi:
   ```bash
   BUG_IDOR=0 bash lab.sh sample
   ```
2. Chạy kiểm thử lần 2 với nhãn `after-fix`:
   ```bash
   bash lab.sh cli --project=1 --label=after-fix
   ```
   *Kết quả:* Toàn bộ **24/24 ca đều đạt `PASS`**.

3. So sánh 2 lần chạy để thấy tiến trình khắc phục:
   ```bash
   bash lab.sh cli --compare=1,2 --project=1
   ```
   *Kết quả:* Bảng diff hiển thị rõ ràng: Ca `R02` chuyển từ `FAIL` sang `PASS`.

4. Xuất báo cáo kết quả kiểm thử:
   ```bash
   bash lab.sh cli --report=2 --format=md
   bash lab.sh cli --report=2 --format=html
   ```

---

## IV. VẬN HÀNH TRÊN GIAO DIỆN WEB TRỰC QUAN

Khởi động máy chủ Web công cụ:
```bash
bash lab.sh run
```
Mở trình duyệt: **[http://127.0.0.1:8105](http://127.0.0.1:8105)**

### Các tài khoản đăng nhập (Mật khẩu: `AccessTest@2026`):
- **`phamle`**: Trưởng nhóm kiểm thử (Role `owner`) — Toàn quyền cấu hình, sửa ca, chạy test.
- **`dev1`**: Lập trình viên (Role `editor`) — Có quyền sửa ca và chạy test.
- **`auditor_user`**: Kiểm toán viên (Role `viewer`) — Chỉ xem, bị chặn sửa/xóa ca (Phép thử 4).
- **`dev2`**: Thành viên nhóm khác — Bị chặn truy cập Dự án A.

---

## V. ĐÓNG GÓI BẢN PHÁT HÀNH BÀN GIAO

```bash
bash lab.sh release
```
Tệp nén phân phối chuẩn được tạo tại: `build/accesstest-release-YYYYMMDD.tar.gz`.

---

## VI. TÀI LIỆU CHI TIẾT THAM KHẢO

1. [Tài liệu Thiết kế Kiến trúc Hệ thống](Ma_nguon/docs/KIEN_TRUC.md)
2. [Hướng dẫn Chi tiết & Ngân hàng Câu hỏi Vấn đáp](Ma_nguon/docs/HUONG_DAN.md)
3. [Đặc tả Mã nguồn và API](Ma_nguon/README.md)
4. [Tài liệu Nguồn gốc & Ghi công Giấy phép MIT](Ma_nguon/docs/NGUON.md)
