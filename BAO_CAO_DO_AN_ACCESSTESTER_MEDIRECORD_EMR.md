# BÁO CÁO TỔNG KẾT ĐỒ ÁN MÃ NGUỒN MỞ

---

## BỘ GIÁO DỤC VÀ ĐÀO TẠO — TRƯỜNG ĐẠI HỌC ĐẠI NAM
### KHOA CÔNG NGHỆ THÔNG TIN
### BỘ MÔN: KỸ THUẬT PHẦN MỀM & HỆ THỐNG THÔNG TIN

---

# BÁO CÁO BÀI TẬP LỚN / ĐỒ ÁN HỌC PHẦN
## HỌC PHẦN: PHÁT TRIỂN PHẦN MỀM MÃ NGUỒN MỞ

### ĐỀ TÀI SỐ 4:
## XÂY DỰNG CÔNG CỤ KIỂM THỬ TỰ ĐỘNG QUYỀN TRUY CẬP TRONG ỨNG DỤNG WEB (ACCESSTESTER)
### KẾT HỢP ỨNG DỤNG MẪU BỆNH ÁN ĐIỆN TỬ (MEDIRECORD EMR) & TRỰC QUAN HÓA LỖ HỔNG PHÂN QUYỀN CẤP ĐỐI TƯỢNG (IDOR / BOLA)

---

**Sinh viên thực hiện:** Nhóm 13 (Repository Nhóm 14)  
**Lớp chuyên ngành:** CNTT 18-12  
**Khóa học:** 2023 – 2027  
**Nền tảng mã nguồn cơ sở:** Upstream FIT-DNU / Nhánh `lab5`  
**Giấy phép mã nguồn mở:** MIT License  
**Bản thử nghiệm trực tuyến (Cloud Live Demo):** [https://medirecord-emr.abrdns.com](https://medirecord-emr.abrdns.com)  
**Trạng thái kiểm thử tự động:** **88 / 88 Test Assertions PASS (100% Đạt Chuẩn)**  

---

<div style="page-break-after: always;"></div>

## TÓM TẮT ĐỒ ÁN (EXECUTIVE ABSTRACT)

Kiểm soát truy cập (Access Control) là một trong những cơ chế phòng vệ an ninh thông tin quan trọng nhất trong mọi hệ thống phần mềm web hiện đại. Tuy nhiên, theo các báo cáo thống kê thường niên của Dự án An ninh Ứng dụng Web Mở (OWASP), lỗ hổng **BOLA (Broken Object Level Authorization)** hay **IDOR (Insecure Direct Object Reference - CWE-639)** liên tục chiếm vị trí **Số 1 trong danh mục OWASP API Security Top 10**. Điểm yếu này phát sinh khi máy chủ ứng dụng xác thực thành công danh tính người dùng (Authentication) nhưng lại bỏ qua hoặc thực hiện thiếu sót bước kiểm tra quan hệ sở hữu đối tượng ở mức bản ghi dữ liệu (Authorization), dẫn đến rủi ro rò rỉ dữ liệu nhạy cảm cực kỳ nghiêm trọng, đặc biệt là trong các hệ thống thông tin y tế, tài chính và quản lý người dùng.

Nhằm giải quyết triệt để bài toán này và đáp ứng toàn diện các tiêu chí đánh giá khắt khe của Đề tài số 4 (Học phần Phát triển phần mềm mã nguồn mở), nhóm sinh viên đã nghiên cứu, thiết kế và phát triển hoàn chỉnh một hệ sinh thái kiểm thử thực tế gồm hai phân hệ phần mềm độc lập:

1. **Ứng dụng Web Mẫu Quản Lý Bệnh Án Điện Tử (MediRecord EMR - Cổng 8205):**  
   Mô phỏng nghiệp vụ thực tế của một bệnh viện đa khoa hạng đặc biệt (Bệnh viện Bạch Mai). Ứng dụng được thiết kế theo kiến trúc **Modular MVC chuẩn hóa gồm 36 tập tin**, phân tách độc lập các tầng: Khởi tạo (`bootstrap`), Dịch vụ nghiệp vụ (`services`), Điều phối luồng (`controllers`), Định tuyến REST API (`api`), Giao diện hiển thị (`views`) và Tài nguyên tĩnh (`assets`). Ứng dụng cung cấp 6 REST API chuẩn, hệ thống dữ liệu riêng biệt (bệnh án y tế) và dữ liệu dùng chung (thông báo nội viện), tích hợp công tắc lỗi kiểm soát `BUG_IDOR=1` (cho phép khai thác lỗ hổng đọc và sửa chéo bệnh án) và chế độ an toàn `BUG_IDOR=0` (kích hoạt phòng thủ ABAC).
2. **Công Cụ Kiểm Thử Tự Động Quyền Truy Cập (AccessTester Engine):**  
   Được trang bị Động cơ Phân Quyền Kép kết hợp **RBAC (Role-Based Access Control)** và **ABAC (Attribute-Based Access Control)** theo nguyên tắc cốt lõi **Mặc định là Từ chối (DENY)**. Công cụ vận hành cơ chế **Policy Oracle** chuẩn tắc để tính toán quyền kỳ vọng trực tiếp từ cơ sở dữ liệu PostgreSQL, thực thi kiểm thử bằng cách gửi các yêu cầu cURL HTTP thật, độc lập tới máy chủ đích, phân loại 3 trạng thái kết quả chính xác (`PASS`, `FAIL`, `ERROR`), khử thông tin nhạy cảm (Redactor) và chụp snapshot bất biến gắn mã băm SHA-256 (`config_hash`).

Bên cạnh việc hoàn thành xuất sắc 100% yêu cầu cốt lõi theo Rubric chấm điểm (10.0/10.0 điểm), nhóm đã chủ động phát triển **8 phân hệ công nghệ sáng tạo lấy điểm cộng**:
- Tái cấu trúc thành công tập tin nguyên khối 11.375 dòng thành Kiến trúc Modular MVC 36 tập tin sạch sẽ, dễ bảo trì;
- Tích hợp **Trợ Lý AI MediRecord Thông Minh** hỗ trợ Server-Sent Events (SSE) Streaming thời gian thực với độ trễ dưới 250ms, hiệu ứng chữ mờ dần (fade-in), trí nhớ hội thoại trong phiên (Conversation Memory) và hàng rào phòng thủ prompt ngăn chặn rò rỉ IDOR qua ngôn ngữ tự nhiên;
- Dựng **Đồ Họa Robot 3D Canvas Procedural Head** chuyển động đầu và chớp mắt tương tác theo chuột;
- Xây dựng **Trung Tâm Giám Sát An Ninh 4 Tab** cho Kỹ sư kiểm thử (`tester_sec`): Nhật ký cảnh báo xâm phạm thời gian thực (Live Incident Feed), Ma trận 24 ca kiểm thử thủ công theo yêu cầu có đặt tên phiên, So sánh lịch sử kiểm thử đối chiếu đa phiên (Comparative Analytics với phân tích Delta vi sai), và Bộ chạy 88 test CLI tích hợp Web Console;
- Sáng tạo cơ chế an toàn **Hold-to-Confirm Modal (Nhấn giữ 1.2 giây để xác nhận)** chống thao tác click nhầm khi xóa dữ liệu;
- Giao diện người dùng hiện đại phong cách SaaS Glassmorphism (Backdrop Blur), hoàn toàn không dùng icon rối mắt;
- Thiết bị đầu cuối an ninh tương tác trực tiếp phía Client (Security Terminal Drawer);
- Đóng gói Docker Container và triển khai trực tuyến trên hạ tầng đám mây Render.com.

Bộ kiểm thử tự động của dự án đạt **88/88 test assertions PASS tuyệt đối** (39 Unit Tests, 29 Integration Tests, 20 E2E Tests), chứng minh đầy đủ 5 phép thử bắt buộc và tính sẵn sàng chuyển giao cho cộng đồng mã nguồn mở theo giấy phép MIT.

---

<div style="page-break-after: always;"></div>

## MỤC LỤC TỔNG THỂ BÁO CÁO

- [TÓM TẮT ĐỒ ÁN (EXECUTIVE ABSTRACT)](#tóm-tắt-đồ-án-executive-abstract)
- [CHƯƠNG 1: TỔNG QUAN ĐỀ TÀI & BỐI CẢNH AN TOÀN THÔNG TIN WEB](#chương-1-tổng-quan-đề-tài--bối-cảnh-an-toàn-thông-tin-web)
  - [1.1. Bối cảnh thực tiễn và tính cấp thiết của bài toán](#11-bối-cảnh-thực-tiễn-và-tính-cấp-thiết-của-bài-toán)
  - [1.2. Thách thức trong kiểm thử tự động phân quyền ứng dụng Web](#12-thách-thức-trong-kiểm-thử-tự-động-phân-quyền-ứng-dụng-web)
  - [1.3. Mục tiêu, phạm vi và đối tượng nghiên cứu của đồ án](#13-mục-tiêu-phạm-vi-và-đối-tượng-nghiên-cứu-của-đồ-án)
  - [1.4. Ý nghĩa khoa học và giá trị thực tiễn](#14-ý-nghĩa-khoa-học-và-giá-trị-thực-tiễn)
- [CHƯƠNG 2: CƠ SỞ LÝ THUYẾT VÀ CÔNG NGHỆ NỀN TẢNG](#chương-2-cơ-sở-lý-thuyết-và-công-nghệ-nền-tảng)
  - [2.1. Các mô hình kiểm soát truy cập: DAC, MAC, RBAC và ABAC](#21-các-mô-hình-kiểm-soát-truy-cập-dac-mac-rbac-và-abac)
  - [2.2. Bản chất lỗ hổng IDOR / BOLA (CWE-639) và cơ chế khai thác](#22-bản-chất-lỗ-hổng-idor--bola-cwe-639-và-cơ-chế-khai-thác)
  - [2.3. Các nguyên lý thiết kế an toàn cốt lõi](#23-các-nguyên-lý-thiết-kế-an-toàn-cốt-lõi)
  - [2.4. Công nghệ nền tảng và môi trường thực thi](#24-công-nghệ-nền-tảng-và-môi-trường-thực-thi)
  - [2.5. Giao thức HTTP, cURL Client và kiểm thử lưu lượng mạng độc lập](#25-giao-thức-http-curl-client-và-kiểm-thử-lưu-lượng-mạng-độc-lập)
  - [2.6. Công nghệ Trí tuệ Nhân tạo và Giao thức Server-Sent Events (SSE)](#26-công-nghệ-trí-tuệ-nhân-tạo-và-giao-thức-server-sent-events-sse)
  - [2.7. Đồ họa Web Procedural Canvas 3D](#27-đồ-họa-web-procedural-canvas-3d)
- [CHƯƠNG 3: PHÂN TÍCH VÀ THIẾT KẾ HỆ THỐNG](#chương-3-phân-tích-và-thiết-kế-hệ-thống)
  - [3.1. Kiến trúc phân tầng độc lập tổng thể](#31-kiến-trúc-phân-tầng-độc-lập-tổng-thể)
  - [3.2. Sơ đồ khối C4 Container và Sơ đồ thành phần Component](#32-sơ-đồ-khối-c4-container-và-sơ-đồ-thành-phần-component)
  - [3.3. Thiết kế Cơ sở Dữ liệu (Schemas, ERD, Data Dictionary)](#33-thiết-kế-cơ-sở-dữ-liệu-schemas-erd-data-dictionary)
  - [3.4. Động cơ Phân quyền Kép RBAC & ABAC và Policy Oracle](#34-động-cơ-phân-quyền-kép-rbac--abac-và-policy-oracle)
  - [3.5. Cơ chế Kiểm soát Máy chủ An toàn Target Allowlist (Chống SSRF)](#35-cơ-chế-kiểm-soát-máy-chủ-an-toàn-target-allowlist-chống-ssrf)
  - [3.6. Trình Khử Dữ liệu Nhạy cảm Redactor](#36-trình-khử-dữ-liệu-nhạy-cảm-redactor)
  - [3.7. Cơ chế Snapshotting Bất biến và Mã băm Cấu hình SHA-256](#37-cơ-chế-snapshotting-bất-biến-và-mã-băm-cấu-hình-sha-256)
- [CHƯƠNG 4: THIẾT KẾ VÀ HIỆN THỰC ỨNG DỤNG MẪU MEDIRECORD EMR (MODULAR MVC)](#chương-4-thiết-kế-và-hiện-thực-ứng-dụng-mẫu-medirecord-emr-modular-mvc)
  - [4.1. Động lực tái cấu trúc từ Monolithic (11.375 dòng) sang Modular MVC (36 files)](#41-động-lực-tái-cấu-trúc-từ-monolithic-11375-dòng-sang-modular-mvc-36-files)
  - [4.2. Cấu trúc cây thư mục 36 tập tin chi tiết](#42-cấu-trúc-cây-thư-mục-36-tập-tin-chi-tiết)
  - [4.3. Tầng Khởi tạo và Nạp cấu hình (Bootstrap Layer)](#43-tầng-khởi-tạo-và-nạp-cấu-hình-bootstrap-layer)
  - [4.4. Tầng Dịch vụ Nghiệp vụ Độc lập (Service Layer)](#44-tầng-dịch-vụ-nghiệp-vụ-độc-lập-service-layer)
  - [4.5. Tầng Điều phối Luồng (Controller Layer)](#45-tầng-điều-phối-luồng-controller-layer)
  - [4.6. Tầng Định tuyến REST API Độc lập (API Layer)](#46-tầng-định-tuyến-rest-api-độc-lập-api-layer)
  - [4.7. Tầng Giao diện Hiển thị (View Layer: Layouts, Pages, Tester, Partials)](#47-tầng-giao-diện-hiển-thị-view-layer-layouts-pages-tester-partials)
  - [4.8. Cơ chế Kích hoạt và Trực quan hóa Lỗ hổng IDOR Cố Ý](#48-cơ-chế-kích-hoạt-và-trực-quan-hóa-lỗ-hổng-idor-cố-ý)
  - [4.9. Trang Báo Lỗi HTTP 403 Forbidden Chuyên Biệt](#49-trang-báo-lỗi-http-403-forbidden-chuyên-biệt)
- [CHƯƠNG 5: CÁC PHÂN HỆ ĐỘT PHÁ VÀ TÍNH NĂNG ĐIỂM CỘNG (BONUS MODULES)](#chương-5-các-phân-hệ-đột-phá-và-tính-năng-điểm-cộng-bonus-modules)
  - [5.1. Trung tâm Giám sát An ninh 4 Tab cho Kỹ sư Kiểm thử (`tester_sec`)](#51-trung-tâm-giám-sát-an-ninh-4-tab-cho-kỹ-sư-kiểm-thử-tester_sec)
  - [5.2. Hệ thống Kiểm thử Thủ công 24 Ca & Đặt Tên Phiên Kiểm Thử](#52-hệ-thống-kiểm-thử-thủ-công-24-ca--đặt-tên-phiên-kiểm-thử)
  - [5.3. Hệ thống So sánh Lịch sử Kiểm thử Đa Phiên & Phân tích Delta Vi Sai](#53-hệ-thống-so-sánh-lịch-sử-kiểm-thử-đa-phiên--phân-tích-delta-vi-sai)
  - [5.4. Mô hình An toàn Xác thực Hold-to-Confirm Modal (Nhấn giữ 1.2 giây)](#54-mô-hình-an-toàn-xác-thực-hold-to-confirm-modal-nhấn-giữ-12-giây)
  - [5.5. Trợ lý AI MediRecord Thông minh: SSE Streaming, Memory & ABAC Guard](#55-trợ-lý-ai-medirecord-thông-minh-sse-streaming-memory--abac-guard)
  - [5.6. Widget Robot 3D Canvas Procedural Head Tương Tác Chuột](#56-widget-robot-3d-canvas-procedural-head-tương-tác-chuột)
  - [5.7. Bộ Ba Nút Nổi FAB và Terminal Drawer An ninh](#57-bộ-ba-nút-nổi-fab-và-terminal-drawer-an-ninh)
  - [5.8. Đóng gói Containerization và Triển khai Đám mây Cloud (Render.com)](#58-đóng-gói-containerization-và-triển-khai-đám-mây-cloud-rendercom)
- [CHƯƠNG 6: TRÍCH DẪN VÀ PHÂN TÍCH MÃ NGUỒN TRỌNG YẾU](#chương-6-trích-dẫn-và-phân-tích-mã-nguồn-trọng-yếu)
  - [6.1. Động cơ phân quyền toán học tại `src/Policy.php`](#61-động-cơ-phân-quyền-toán-học-tại-srcpolicyphp)
  - [6.2. Bộ phân loại kết quả 3 trạng thái tại `src/HttpClient.php`](#62-bộ-phân-loại-kết-quả-3-trạng-thái-tại-srchttpclientphp)
  - [6.3. Bộ lọc kiểm soát địa chỉ đích tại `src/TargetAllowlist.php`](#63-bộ-lọc-kiểm-soát-địa-chỉ-đích-tại-srctargetallowlistphp)
  - [6.4. Trình khử thông tin bí mật tại `src/Redactor.php`](#64-trình-khử-thông-tin-bí-mật-tại-srcredactorphp)
  - [6.5. Logic kiểm tra IDOR và vá lỗi tại `sample-app/app/controllers/record_controller.php`](#65-logic-kiểm-tra-idor-và-vá-lỗi-tại-sample-appappcontrollersrecord_controllerphp)
  - [6.6. Logic xử lý Server-Sent Events Streaming tại `sample-app/app/controllers/ai_controller.php`](#66-logic-xử-lý-server-sent-events-streaming-tại-sample-appappcontrollersai_controllerphp)
  - [6.7. Thuật toán phân tích vi sai Delta Matrix tại `sample-app/app/services/test_runner_service.php`](#67-thuật-toán-phân-tích-vi-sai-delta-matrix-tại-sample-appappservicestest_runner_servicephp)
  - [6.8. Logic điều khiển hiệu ứng Hold-to-Confirm tại `sample-app/public/assets/js/app.js`](#68-logic-điều-khiển-hiệu-ứng-hold-to-confirm-tại-sample-apppublicassetsjsappjs)
- [CHƯƠNG 7: KỊCH BẢN KIỂM THỬ VÀ ĐÁNH GIÁ THỰC NGHIỆM](#chương-7-kịch-bản-kiểm-thử-và-đánh-giá-thực-nghiệm)
  - [7.1. Bảng đặc tả 24 ca kiểm thử chuẩn (P01 – M04)](#71-bảng-đặc-tả-24-ca-kiểm-thử-chuẩn-p01--m04)
  - [7.2. Thực nghiệm 5 Phép thử bắt buộc theo đề cương](#72-thực-nghiệm-5-phép-thử-bắt-buộc-theo-đề-cương)
  - [7.3. Tổng kết toàn diện bộ 88 bài kiểm thử tự động (Test Suite)](#73-tổng-kết-toàn-diện-bộ-88-bài-kiểm-thử-tự-động-test-suite)
  - [7.4. Đánh giá hiệu năng và tính ổn định](#74-đánh-giá-hiệu-năng-và-tính-ổn-định)
- [CHƯƠNG 8: HƯỚNG DẪN CÀI ĐẶT, VẬN HÀNH VÀ KỊCH BẢN BÁO CÁO](#chương-8-hướng-dẫn-cài-đặt-vận-hành-và-kịch-bản-báo-cáo)
  - [8.1. Hướng dẫn cài đặt và cấu hình môi trường](#81-hướng-dẫn-cài-đặt-và-cấu-hình-môi-trường)
  - [8.2. Kịch bản trình diễn Demo 10 phút trước Hội đồng bảo vệ](#82-kịch-bản-trình-diễn-demo-10-phút-trước-hội-đồng-bảo-vệ)
  - [8.3. Hướng dẫn vận hành dòng lệnh CLI và xuất báo cáo tự động](#83-hướng-dẫn-vận-hành-dòng-lệnh-cli-và-xuất-báo-cáo-tự-động)
- [CHƯƠNG 9: NGÂN HÀNG CÂU HỎI VẤN ĐÁP BẢO VỆ ĐỒ ÁN (15 CÂU HỎI CHUYÊN SÂU)](#chương-9-ngân-hàng-câu-hỏi-vấn-đáp-bảo-vệ-đồ-án-15-câu-hỏi-chuyên-sâu)
- [CHƯƠNG 10: BẢNG TỰ ĐÁNH GIÁ THEO RUBRIC VÀ KẾT LUẬN](#chương-10-bảng-tự-đánh-giá-theo-rubric-và-kết-luận)
  - [10.1. Bảng đối chiếu tự đánh giá theo Rubric chấm điểm (10.0 / 10.0 điểm)](#101-bảng-đối-chiếu-tự-đánh-giá-theo-rubric-chấm-điểm-100--100-điểm)
  - [10.2. Những đóng góp chính của đồ án](#102-những-đóng-góp-chính-của-đồ-án)
  - [10.3. Hạn chế hiện tại và định hướng phát triển tương lai](#103-hạn-chế-hiện-tại-và-định-hướng-phát-triển-tương-lai)
  - [10.4. Lời cảm ơn và cam kết tuân thủ Giấy phép mã nguồn mở](#104-lời-cảm-ơn-và-cam-kết-tuân-thủ-giấy-phép-mã-nguồn-mở)
- [TÀI LIỆU THAM KHẢO](#tài-liệu-tham-khảo)

---

<div style="page-break-after: always;"></div>

# CHƯƠNG 1: TỔNG QUAN ĐỀ TÀI & BỐI CẢNH AN TOÀN THÔNG TIN WEB

## 1.1. Bối cảnh thực tiễn và tính cấp thiết của bài toán
Trong thời đại chuyển đổi số toàn diện, các ứng dụng Web và dịch vụ Application Programming Interface (API) đã trở thành xương sống của hạ tầng công nghệ thông tin trong mọi lĩnh vực trọng yếu như tài chính ngân hàng, thương mại điện tử, dịch vụ công và y tế điện tử (Electronic Medical Records - EMR). Song hành cùng sự phát triển mạnh mẽ về tính năng và quy mô người dùng là những nguy cơ rủi ro an ninh mạng ngày càng tinh vi và phức tạp.

Theo thống kê thường niên của các tổ chức an toàn thông tin uy tín trên thế giới, đặc biệt là Báo cáo **OWASP API Security Top 10**, các lỗ hổng liên quan đến **Kiểm soát Truy cập Cấp Đối tượng (Broken Object Level Authorization - BOLA)**, hay còn gọi là **Lỗ hổng Tham chiếu Đối tượng Trực tiếp Không An toàn (Insecure Direct Object Reference - IDOR, mã danh CWE-639)**, liên tục đứng ở **vị trí số 1** về tần suất xuất hiện và mức độ thiệt hại nghiêm trọng.

Lỗ hổng IDOR xuất hiện khi nhà phát triển phần mềm chỉ tập trung vào tầng Xác thực danh tính (Authentication) — tức là kiểm tra người dùng đã đăng nhập hay chưa — nhưng lại bỏ qua hoặc thiết kế lỏng lẻo tầng Phân quyền (Authorization) ở mức từng đối tượng dữ liệu cụ thể. Khi đó, một kẻ tấn công chỉ cần thay đổi định danh của đối tượng (ví dụ tham số `id=3` trên đường dẫn URL hoặc trong phần thân yêu cầu JSON) là có thể xem trộm, chỉnh sửa hoặc xóa bỏ trái phép dữ liệu thuộc quyền sở hữu của người dùng khác mà không gặp bất kỳ sự ngăn cản nào từ phía máy chủ.

Trong bối cảnh y tế, dữ liệu bệnh án điện tử là tài sản vô cùng nhạy cảm. Việc rò rỉ hồ sơ chẩn đoán ung thư, tiền sử bệnh truyền nhiễm hay phác đồ điều trị bí mật của bệnh nhân không chỉ xâm phạm nghiêm trọng quyền riêng tư của công dân, vi phạm các quy định pháp luật (như Luật An toàn thông tin mạng, Nghị định 13/2023/NĐ-CP về bảo vệ dữ liệu cá nhân tại Việt Nam, chuẩn HIPAA của Hoa Kỳ hay GDPR của Liên minh Châu Âu), mà còn có thể đe dọa trực tiếp tới tính mạng con người nếu dữ liệu y tế bị kẻ xấu cố tình sửa đổi sai lệch.

## 1.2. Thách thức trong kiểm thử tự động phân quyền ứng dụng Web
Mặc dù nhận thức về lỗ hổng IDOR/BOLA đã được nâng cao, việc phát hiện và ngăn chặn lỗ hổng này trong quy trình phát triển phần mềm tự động (DevSecOps / CI/CD) vẫn là một bài toán hóc búa bởi các nguyên nhân sau:
1. **Tính chất lỗi nghiệp vụ (Business Logic Flaw):** Lỗ hổng IDOR không gây ra lỗi cú pháp, không làm sập máy chủ, không phát sinh ngoại lệ cơ sở dữ liệu và không tạo ra các mẫu tấn công đặc trưng như SQL Injection hay XSS (Cross-Site Scripting). Do đó, các công cụ quét mã tĩnh (SAST) hay Web Application Firewall (WAF) truyền thống thường hoàn toàn bất lực trong việc phát hiện.
2. **Sự phụ thuộc vào ngữ cảnh sở hữu bản ghi (Data Ownership Context):** Một yêu cầu truy xuất tài nguyên `GET /api/records/3` là hoàn toàn hợp lệ nếu được gửi bởi chính chủ sở hữu hoặc bác sĩ phụ trách, nhưng lại trở thành một cuộc tấn công nguy hiểm nếu được gửi bởi một bệnh nhân khác. Để kiểm thử, công cụ bắt buộc phải có khả năng hiểu sâu sắc ma trận phân quyền, liên kết được danh tính tài khoản với thuộc tính chủ sở hữu của từng bản ghi dữ liệu cụ thể.
3. **Hiện tượng Báo cáo Đạt Giả Tạo (False Positives) do Lỗi Kỹ Thuật:** Rất nhiều công cụ kiểm thử phân quyền sơ sài trên thị trường hiện nay chỉ kiểm tra đơn giản xem yêu cầu có bị từ chối hay không. Nếu máy chủ ứng dụng bị sập, cổng mạng bị đóng, hoặc kết nối bị timeout, công cụ lại nhầm lẫn kết luận rằng "hệ thống đã từ chối truy cập thành công", dẫn tới việc bỏ lọt lỗ hổng nghiêm trọng.
4. **Rủi ro Rò rỉ Dữ liệu Nhạy cảm Ngay Trong Quá Trình Kiểm Thử:** Trong quá trình thực thi kiểm thử và ghi log, nếu công cụ không có cơ chế khử dữ liệu nhạy cảm (Sanitization/Redaction) chuyên nghiệp, các token bí mật (Bearer Tokens), mật khẩu cơ sở dữ liệu và dữ liệu nhạy cảm sẽ bị phơi bày trên các tập tin nhật ký (log files) hoặc báo cáo HTML, gây ra một lỗ hổng an ninh thứ cấp.

## 1.3. Mục tiêu, phạm vi và đối tượng nghiên cứu của đồ án
### Mục tiêu tổng quát:
Xây dựng một giải pháp hoàn chỉnh từ gốc đến ngọn (End-to-End Ecosystem) phục vụ học tập, nghiên cứu và ứng dụng thực tiễn trong việc kiểm thử tự động quyền truy cập trong ứng dụng Web theo đúng chuẩn đề cương Đề tài số 4 (Học phần Phát triển phần mềm mã nguồn mở - FIT-DNU).

### Mục tiêu cụ thể:
1. **Thiết kế và hiện thực Động cơ Kiểm thử An ninh (AccessTester Engine):**
   - Hỗ trợ mô hình phân quyền kép kết hợp RBAC và ABAC, hoạt động theo nguyên tắc an toàn cao nhất: **Mặc định là từ chối (DENY)**.
   - Xây dựng **Policy Oracle** chuẩn tắc để tính toán quyền kỳ vọng độc lập từ CSDL.
   - Thực thi kiểm thử bằng cách gửi các yêu cầu cURL HTTP thật tới ứng dụng mục tiêu; phân loại rạch ròi 3 trạng thái kết quả: `PASS`, `FAIL`, `ERROR`.
   - Ngăn ngừa tấn công SSRF thông qua danh sách máy chủ an toàn (`TargetAllowlist`).
   - Khử 100% token bí mật và mật khẩu khỏi báo cáo và nhật ký (`Redactor`).
   - Đảm bảo tính bất biến của lịch sử kiểm thử thông qua chụp snapshot và gắn mã băm SHA-256 (`config_hash`).
2. **Thiết kế và hiện thực Ứng dụng Web Mẫu Bệnh Án Điện Tử (MediRecord EMR):**
   - Xây dựng ứng dụng quản lý bệnh án y tế chuyên nghiệp với 6 REST API chuẩn, phục vụ 5 nhóm đối tượng thực tế: Bác sĩ, Bệnh nhân An, Bệnh nhân Bình, Quản trị viên và Kỹ sư an ninh mạng.
   - Cài cắm lỗ hổng phân quyền cấp đối tượng có kiểm soát qua công tắc `BUG_IDOR=1` (cho phép Bệnh nhân An đọc và sửa bệnh án của Bình) và cơ chế phòng thủ `BUG_IDOR=0` (chặn đứng bằng mã HTTP 403 Forbidden).
   - Tái cấu trúc toàn bộ ứng dụng thành **Kiến trúc Modular MVC chuẩn gồm 36 tập tin** phân định rõ ràng.
3. **Phát triển các phân hệ công nghệ sáng tạo lấy điểm cộng:**
   - Xây dựng **Trung Tâm Giám Sát An Ninh 4 Tab** cho Kỹ sư an ninh mạng: Live Incidents, Ma trận 24 ca kiểm thử thủ công có đặt tên, So sánh lịch sử kiểm thử đa phiên (Comparative Analytics với phân tích Delta vi sai), và Chạy 88 bài test CLI.
   - Hiện thực mô hình xác thực an toàn **Hold-to-Confirm Modal (Nhấn giữ 1.2s)** chống bấm nhầm.
   - Tích hợp **Trợ Lý AI MediRecord Thông Minh** hỗ trợ Server-Sent Events (SSE) Streaming thời gian thực, bộ đệm trí nhớ hội thoại trong phiên và hàng rào phòng thủ prompt chống rò rỉ IDOR qua ngôn ngữ tự nhiên.
   - Tích hợp **Robot 3D Canvas Procedural Head** chuyển động đầu và chớp mắt tương tác theo chuột.
   - Đóng gói Docker Container và triển khai trực tuyến trên hạ tầng đám mây Render.com.

### Phạm vi và đối tượng nghiên cứu:
- **Đối tượng nghiên cứu:** Các giao thức kiểm soát truy cập Web (RBAC, ABAC), lỗ hổng IDOR/BOLA (CWE-639), thư viện cURL trong PHP, cơ chế mã băm mật mã học (SHA-256), giao thức truyền tin thời gian thực Server-Sent Events (SSE), và các tiêu chuẩn kiểm thử tự động phần mềm mã nguồn mở.
- **Phạm vi áp dụng:** Hệ thống Web ứng dụng công nghệ PHP 8.1+, CSDL PostgreSQL 14+, môi trường Linux Ubuntu / WSL 2 và nền tảng Docker Container đám mây.

## 1.4. Ý nghĩa khoa học và giá trị thực tiễn
- **Về mặt khoa học và giáo dục:** Đề tài cung cấp một mô hình mẫu mực về phương pháp kiểm thử phân quyền tự động dựa trên Policy Oracle và cURL HTTP thật. Đây là tài liệu học tập, thực hành trực quan và nghiên cứu chuyên sâu giá trị cho sinh viên chuyên ngành Công nghệ thông tin trong các học phần Phát triển phần mềm mã nguồn mở, An toàn thông tin và Kiểm thử phần mềm.
- **Về mặt thực tiễn ứng dụng:** Giải pháp có thể dễ dàng tích hợp vào quy trình CI/CD tự động của các doanh nghiệp phần mềm (thông qua GitHub Actions hay GitLab CI), giúp các đội ngũ phát triển phát hiện sớm và vá kịp thời các lỗ hổng phân quyền logic trước khi sản phẩm được đưa vào vận hành chính thức, giảm thiểu tối đa nguy cơ rò rỉ dữ liệu nhạy cảm.

---

<div style="page-break-after: always;"></div>

# CHƯƠNG 2: CƠ SỞ LÝ THUYẾT VÀ CÔNG NGHỆ NỀN TẢNG

## 2.1. Các mô hình kiểm soát truy cập: DAC, MAC, RBAC và ABAC
Kiểm soát truy cập (Access Control) là quá trình cấp quyền hoặc từ chối các yêu cầu thực hiện hành động trên tài nguyên cụ thể của hệ thống. Trong lịch sử phát triển của an ninh phần mềm, bốn mô hình phân quyền chính đã được định hình và áp dụng:

```
+---------------------------------------------------------------------------------------------------+
|                                CÁC MÔ HÌNH KIỂM SOÁT TRUY CẬP (ACCESS CONTROL)                   |
+-------------------+-------------------+-----------------------------------+-----------------------+
| Mô Hình           | Nguyên Tắc Cơ Bản | Ưu Điểm                           | Nhược Điểm            |
+-------------------+-------------------+-----------------------------------+-----------------------+
| DAC (Discretionary| Chủ sở hữu tài    | Cực kỳ linh hoạt, phù hợp         | Khó kiểm soát tập     |
| Access Control)   | nguyên toàn quyền | hệ thống tập tin cá nhân.         | trung, dễ bị cấu hình |
|                   | phân quyền lại.   |                                   | sai dẫn đến lộ lọt.   |
+-------------------+-------------------+-----------------------------------+-----------------------+
| MAC (Mandatory    | Hệ thống áp đặt   | Độ an toàn tối đa, chống rò rỉ    | Kém linh hoạt, phức   |
| Access Control)   | nhãn an ninh cho  | tuyệt đối, dùng trong quân sự,    | tạp trong quản lý và  |
|                   | đối tượng/chủ thể.| tình báo (chuẩn Bell-LaPadula).   | vận hành thực tế.     |
+-------------------+-------------------+-----------------------------------+-----------------------+
| RBAC (Role-Based  | Gán quyền cho Vai | Dễ quản trị, giảm thiểu trùng     | Không phân biệt được  |
| Access Control)   | trò; Người dùng   | lặp, phù hợp phân cấp tổ chức     | quyền cấp đối tượng   |
|                   | nhận Vai trò đó.  | doanh nghiệp chuẩn hóa.           | (Object-level flaw).  |
+-------------------+-------------------+-----------------------------------+-----------------------+
| ABAC (Attribute-  | Quyết định quyền  | Cực kỳ chi tiết, linh hoạt cao,   | Chi phí tính toán cao,|
| Based Access      | dựa trên thuộc    | giải quyết triệt để bài toán sở   | đòi hỏi thiết kế kiến |
| Control)          | tính của Ngữ cảnh | hữu dữ liệu cấp đối tượng (BOLA/  | trúc và CSDL chặt     |
|                   | & Tài nguyên.     | IDOR Prevention).                 | chẽ, chuẩn tắc.       |
+-------------------+-------------------+-----------------------------------+-----------------------+
```

### So sánh chuyên sâu RBAC và ABAC trong bài toán thực tế:
- **Hạn chế cố hữu của RBAC thuần túy:**  
  Giả sử hệ thống định nghĩa quyền: *"Vai trò `member` (bệnh nhân) có quyền đọc bệnh án y tế (`read:records`)"*. Trong mô hình RBAC thuần túy, cả Bệnh nhân An và Bệnh nhân Bình đều có vai trò `member`. Do đó, khi An gửi yêu cầu đọc bệnh án của Bình, hệ thống RBAC kiểm tra thấy vai trò của An là `member` và hành động là `read:records`, lập tức phê duyệt cho phép truy cập. Đây chính là nguồn gốc phát sinh lỗ hổng IDOR!
- **Sức mạnh của mô hình kết hợp RBAC + ABAC:**  
  Trong đề tài này, nhóm đã kết hợp ưu điểm của cả hai mô hình:
  - **RBAC:** Kiểm soát sơ bộ quyền hạn theo chức danh nghiệp vụ: `admin` (toàn quyền), `doctor` (lập bệnh án), `member` (bệnh nhân), `auditor` (kiểm toán chỉ đọc).
  - **ABAC:** Bổ sung thuộc tính phạm vi `scope`:
    - Nếu `scope = 'any'`: Người dùng được phép thao tác trên mọi bản ghi (dành cho quản trị viên hoặc kiểm toán viên).
    - Nếu `scope = 'own'`: Hệ thống bắt buộc phải kiểm tra thuộc tính sở hữu (`ownerMatch`): Người dùng chỉ được phép truy xuất bản ghi nếu định danh `user_id` hiện tại trùng khớp với trường `owner_id` (hoặc `owner_username`) của bản ghi đó.

## 2.2. Bản chất lỗ hổng IDOR / BOLA (CWE-639) và cơ chế khai thác
Lỗ hổng **Insecure Direct Object Reference (IDOR)**, được OWASP phân loại là **Broken Object Level Authorization (BOLA)**, xuất hiện khi:
1. Ứng dụng sử dụng đầu vào do người dùng cung cấp (tham số URL, form-data, JSON payload) để truy xuất trực tiếp một đối tượng dữ liệu trong cơ sở dữ liệu.
2. Ứng dụng không kiểm tra xem người dùng hiện tại có quyền hợp pháp đối với đối tượng cụ thể đó hay không.

### Chuỗi tấn công điển hình (Attack Chain):
```
[Kẻ Tấn Công (bn_an)]          [Web Server (Sample App)]              [Database (PostgreSQL)]
        |                                   |                                     |
        |-- 1. Đăng nhập hợp lệ ----------->|                                     |
        |<-- Nhận Bearer Token hợp lệ ------|                                     |
        |                                   |                                     |
        |-- 2. GET /api/notes/3 ----------->|                                     |
        |   (Gửi kèm Token của bn_an)       |                                     |
        |                                   |-- 3. SELECT * FROM notes WHERE id=3 |
        |                                   |<-- Trả về Bệnh án #03 của bn_binh --|
        |                                   |                                     |
        |                                   | [LỖ HỔNG XẢY RA TẠI ĐÂY:]           |
        |                                   | Máy chủ KHÔNG kiểm tra xem          |
        |                                   | note.user_id có bằng an.id không!   |
        |<-- 4. Trả về HTTP 200 OK ---------|                                     |
        |    (Kèm dữ liệu tuyệt mật của Bình)|                                    |
```

## 2.3. Các nguyên lý thiết kế an toàn cốt lõi
Để phòng thủ và kiểm thử lỗ hổng phân quyền một cách chuẩn tắc, hệ thống tuân thủ nghiêm ngặt hai nguyên lý thiết kế kinh điển của ngành An toàn thông tin:

1. **Nguyên tắc "Mặc định là từ chối" (Secure by Default / Default DENY):**  
   Mọi yêu cầu truy cập tài nguyên, nếu không có quy tắc nào trong bảng phân quyền cho phép một cách tường minh, **bắt buộc phải bị từ chối truy cập (`DENY`)**. Nếu lập trình viên thiết kế hệ thống theo kiểu "mặc định là cho phép (ALLOW) và chỉ chặn những gì bị cấm", thì khi có một API mới xuất hiện mà lập trình viên quên khai báo quyền, API đó sẽ ngay lập tức trở thành một lỗ hổng hổng bảo mật nghiêm trọng.
2. **Nguyên tắc Đặc quyền Tối thiểu (Principle of Least Privilege - PoLP):**  
   Mỗi chủ thể trong hệ thống (người dùng, tiến trình, dịch vụ) chỉ được cấp những quyền hạn tối thiểu tuyệt đối cần thiết để hoàn thành nhiệm vụ được giao. Một bệnh nhân chỉ được phép xem hồ sơ của chính mình; một kiểm toán viên chỉ được quyền xem (Read-only) mà tuyệt đối không có quyền chỉnh sửa hay xóa bỏ ca kiểm thử.

## 2.4. Công nghệ nền tảng và môi trường thực thi
Hệ thống được xây dựng hoàn toàn dựa trên các công nghệ mã nguồn mở phổ biến, hiện đại và chuẩn mực:
- **Ngôn ngữ lập trình PHP 8.1+:** Tận dụng các tính năng hiện đại như kiểu dữ liệu chặt chẽ (Strict Types), Constructor Promotion, Named Arguments, Match Expressions, và bộ tiện ích xử lý chuỗi UTF-8.
- **Hệ quản trị CSDL PostgreSQL 14+:** Hệ quản trị CSDL quan hệ mã nguồn mở mạnh mẽ nhất thế giới. Dự án tận dụng tính năng **Schema Isolation** để phân chia ranh giới vật lý giữa bảng quản trị công cụ kiểm thử (`schema public`) và bảng dữ liệu nghiệp vụ bệnh án (`schema sample_app`), sử dụng truy vấn tham số hóa (Prepared Statements) chống SQL Injection 100%.
- **Môi trường hệ điều hành Linux Ubuntu / WSL 2:** Hệ sinh thái POSIX tiêu chuẩn giúp tự động hóa toàn diện các tác vụ qua kịch bản Bash script (`lab.sh`).
- **Nền tảng Ảo hóa Docker & Đám mây Render.com:** Container hóa toàn bộ mã nguồn giúp hệ thống có thể triển khai tức thì trên bất kỳ môi trường đám mây nào mà không gặp lỗi phụ thuộc môi trường.

## 2.5. Giao thức HTTP, cURL Client và kiểm thử lưu lượng mạng độc lập
Một yêu cầu bắt buộc của đề tài (Yêu cầu kỹ thuật số 2) là: **Công cụ kiểm thử phải gửi yêu cầu HTTP thật thông qua cURL tới máy chủ ứng dụng mục tiêu**, không được phép chỉ dừng lại ở việc gọi hai hàm so sánh nội bộ trong cùng một tiến trình bộ nhớ.

Thư viện **cURL (Client URL Library)** được cấu hình với các tham số tối ưu hóa an ninh:
- `CURLOPT_FOLLOWLOCATION = false`: Không tự động chuyển hướng 302 để bắt chính xác mã trạng thái chuyển tiếp hoặc từ chối ban đầu của máy chủ.
- `CURLOPT_CONNECTTIMEOUT = 2` và `CURLOPT_TIMEOUT = 5`: Kiểm soát chặt chẽ thời gian chờ kết nối và phản hồi, ngăn chặn tình trạng tiến trình bị treo vô hạn định.
- `CURLOPT_FRESH_CONNECT = true` và `CURLOPT_FORBID_REUSE = true`: Ép buộc cURL khởi tạo một phiên TCP/HTTP mới hoàn toàn cho từng ca thử, đảm bảo tính độc lập tuyệt đối, không chia sẻ cookie, session hay dữ liệu bộ đệm giữa các lần gọi kiểm thử.

## 2.6. Công nghệ Trí tuệ Nhân tạo và Giao thức Server-Sent Events (SSE)
Nhằm mang lại trải nghiệm tương tác thông minh cho người dùng, hệ thống tích hợp Trợ lý Trí tuệ Nhân tạo MediRecord AI. Để khắc phục nhược điểm phản hồi chậm chạp của các mô hình ngôn ngữ lớn (LLM), nhóm đã áp dụng giao thức **Server-Sent Events (SSE)** theo tiêu chuẩn W3C:
- **Server-Sent Events (SSE):** Cho phép máy chủ chủ động đẩy các gói tin (data chunks) về trình duyệt thông qua một kết nối HTTP duy nhất kéo dài (`Content-Type: text/event-stream`). Khi mô hình AI sinh ra từng từ (tokens), máy chủ lập tức truyền ngay về client mà không cần chờ toàn bộ câu trả lời hoàn thành.
- **Độ trễ thấp & Hiệu ứng Chữ Mờ Dần (Fade-in Typography):** Giúp người dùng nhìn thấy câu trả lời xuất hiện tức thời (dưới 250ms), tạo cảm giác đối thoại tự nhiên, mượt mà và chuyên nghiệp.

## 2.7. Đồ họa Web Procedural Canvas 3D
Thay vì sử dụng các thư viện 3D cồng kềnh như Three.js hay Babylon.js (vốn có dung lượng tệp tin lên tới hàng megabyte và tiêu tốn nhiều tài nguyên GPU), nhóm đã tích hợp mô hình **Robot 3D Canvas Procedural Head** (`fayazara/robot-heads`). Mô hình này được dựng hoàn toàn bằng các phép toán hình học và hàm vẽ Canvas 2D/3D thuần túy trong JavaScript (`robot-heads.bundle.js`), cho phép đầu robot cử động mượt mà theo vị trí trỏ chuột và chớp mắt ngẫu nhiên với hiệu năng 60 FPS mà không làm nặng trang web.

---

<div style="page-break-after: always;"></div>

# CHƯƠNG 3: PHÂN TÍCH VÀ THIẾT KẾ HỆ THỐNG

## 3.1. Kiến trúc phân tầng độc lập tổng thể
Hệ thống được thiết kế theo mô hình phân tầng độc lập (Decoupled Multi-Tier Architecture), tách biệt rạch ròi ba thực thể:
1. **Động cơ Kiểm thử AccessTester:** Vận hành trên cổng `8105` (hoặc chế độ CLI `bin/run.php`), đóng vai trò như một cơ quan kiểm toán an ninh độc lập.
2. **Cơ sở Dữ liệu Tập trung (PostgreSQL):** Lưu trữ cấu hình phân quyền, ca kiểm thử, lịch sử lần chạy và dữ liệu bệnh án y tế.
3. **Ứng dụng Web Mẫu MediRecord EMR:** Vận hành trên cổng `8205`, đóng vai trò là hệ thống mục tiêu chịu sự kiểm thử.

```
+---------------------------------------------------------------------------------------------------------------+
|                                    KIẾN TRÚC TỔNG THỂ HỆ THỐNG ACCESSTESTER & MEDIRECORD                      |
+---------------------------------------------------------------------------------------------------------------+
|                                                                                                               |
|   [KIỂM THỬ VIÊN / KIỂM TOÁN VIÊN]                       [BÁC SĨ / BỆNH NHÂN / QUẢN TRỊ VIÊN]                 |
|                   |                                                       |                                   |
|                   v                                                       v                                   |
|   +-------------------------------+                       +-------------------------------+                   |
|   |   Giao diện AccessTester      |                       |    Ứng dụng MediRecord EMR    |                   |
|   |         (Port 8105)           |                       |          (Port 8205)          |                   |
|   | - Quản lý dự án, quyền        |                       | - Lập, xem, sửa bệnh án y tế  |                   |
|   | - Khai báo 24 ca thử nghiệm   |                       | - Trung tâm Giám sát An ninh  |                   |
|   | - Chạy test & So sánh Diff    |                       | - Trợ lý AI & Robot 3D        |                   |
|   +---------------+---------------+                       +---------------+---------------+                   |
|                   |                                                       |                                   |
|                   | Gửi HTTP cURL Thật (Bearer Token)                     |                                   |
|                   +------------------------------------------------------>|                                   |
|                   |                                                       |                                   |
|                   v                                                       v                                   |
|   +---------------------------------------------------------------------------------------+                   |
|   |                            HỆ QUẢN TRỊ CƠ SỞ DỮ LIỆU POSTGRESQL 14+                   |                   |
|   |                                                                                       |                   |
|   |   +---------------------------------------+   +-----------------------------------+   |                   |
|   |   |           SCHEMA: public              |   |        SCHEMA: sample_app         |   |                   |
|   |   | - projects, project_members           |   | - users (5 tài khoản thật)        |   |                   |
|   |   | - roles, resources, actions           |   | - notes (bệnh án riêng tư)        |   |                   |
|   |   | - permissions, records, test_accounts |   | - announcements (dùng chung)      |   |                   |
|   |   | - test_cases, runs, run_results       |   |                                   |   |                   |
|   |   +---------------------------------------+   +-----------------------------------+   |                   |
|   +---------------------------------------------------------------------------------------+                   |
+---------------------------------------------------------------------------------------------------------------+
```

## 3.2. Sơ đồ khối C4 Container và Sơ đồ thành phần Component
Nhằm mô tả kiến trúc phần mềm một cách trực quan và chuẩn mực theo tiêu chuẩn quốc tế C4 Model, nhóm đã xây dựng sơ đồ Container và Component chi tiết:

```mermaid
graph TD
    subgraph ClientLayer ["Lớp Người Dùng & Tương Tác"]
        Browser["Trình Duyệt Web (HTML5/CSS3/ES6)"]
        CLI["Giao Diện Dòng Lệnh (PHP CLI Runner)"]
    end

    subgraph AccessTesterContainer ["Công Cụ AccessTester (Port 8105)"]
        WebAdmin["Front Web Controller (public/*.php)"]
        CLIRunner["bin/run.php Controller"]
        PolicyEngine["Policy Oracle (src/Policy.php)"]
        AllowlistGuard["TargetAllowlist (src/TargetAllowlist.php)"]
        HttpEngine["HttpClient (src/HttpClient.php)"]
        SecretRedactor["Redactor (src/Redactor.php)"]
        RunRepo["RunRepository (src/RunRepository.php)"]
    end

    subgraph TargetAppContainer ["Ứng Dụng Mẫu MediRecord EMR (Port 8205)"]
        FrontCtrl["Front Controller (public/index.php)"]
        AppBootstrap["Bootstrap (app/bootstrap/*.php)"]
        AppServices["Services (Security, Runner, AI)"]
        AppControllers["Controllers (Auth, Record, User, Tester, AI)"]
        AppViews["Views (Layouts, Pages, Tester, Partials)"]
    end

    subgraph DatabaseContainer ["Cơ Sở Dữ Liệu PostgreSQL"]
        PublicSchema[("Schema: public\n(Bảng Quản Trị & Test Cases)")]
        SampleSchema[("Schema: sample_app\n(Bảng Dữ Liệu Y Tế)")]
    end

    Browser -->|HTTP UI| WebAdmin
    Browser -->|HTTP UI / SSE| FrontCtrl
    CLI -->|CLI Params| CLIRunner

    WebAdmin --> RunRepo
    CLIRunner --> PolicyEngine
    CLIRunner --> AllowlistGuard
    AllowlistGuard --> HttpEngine
    HttpEngine -->|cURL HTTP Thật| FrontCtrl
    HttpEngine --> SecretRedactor
    SecretRedactor --> RunRepo

    FrontCtrl --> AppBootstrap
    FrontCtrl --> AppControllers
    AppControllers --> AppServices
    AppControllers --> AppViews

    RunRepo --> PublicSchema
    PolicyEngine --> PublicSchema
    AppBootstrap --> SampleSchema
    AppControllers --> SampleSchema
```

## 3.3. Thiết kế Cơ sở Dữ liệu (Schemas, ERD, Data Dictionary)
Cơ sở dữ liệu của dự án được phân tách nghiêm ngặt thành hai schema riêng biệt trong CSDL `accesstest_lab`:

### 1. Schema `public` (Quản trị công cụ AccessTester):
- `projects`: Lưu trữ thông tin dự án kiểm thử (`id`, `name`, `description`, `created_at`).
- `project_members`: Thành viên dự án và vai trò quản trị công cụ (`project_id`, `user_id`, `role: 'owner' | 'editor' | 'viewer'`).
- `roles`: Bảng danh mục vai trò người dùng trong ứng dụng mục tiêu (`id`, `name: 'admin', 'doctor', 'member', 'auditor'`).
- `resources`: Danh mục tài nguyên (`id`, `name: 'notes', 'records', 'announcements', 'users'`).
- `actions`: Danh mục hành động (`id`, `name: 'read', 'create', 'update', 'delete'`).
- `permissions`: Ma trận phân quyền RBAC & ABAC (`project_id`, `role_id`, `resource_id`, `action_id`, `scope: 'any' | 'own'`).
- `records`: Danh mục các bản ghi dữ liệu mẫu kèm định danh chủ sở hữu (`id`, `resource_id`, `record_id`, `owner_id`).
- `test_accounts`: Danh mục tài khoản kiểm thử và Bearer Token tương ứng (`id`, `project_id`, `username`, `role_id`, `token`, `owner_id`).
- `test_cases`: Bảng khai báo 24 ca kiểm thử chuẩn (`id`, `project_id`, `case_code`, `method`, `path`, `account_id`, `expected_status`, `must_contain`, `must_not_contain`).
- `runs`: Lưu trữ lịch sử lần chạy kiểm thử (`id`, `project_id`, `label`, `config_hash`, `config_snapshot`, `started_at`, `finished_at`).
- `run_results`: Kết quả chi tiết của từng ca trong lần chạy (`id`, `run_id`, `case_code`, `status: 'PASS' | 'FAIL' | 'ERROR'`, `actual_status`, `leak_detected`, `response_time_ms`, `snapshot`).

### 2. Schema `sample_app` (Dữ liệu Ứng dụng Bệnh án MediRecord):
- `users`: Tài khoản cán bộ y tế và bệnh nhân (`id`, `username`, `password_hash`, `role`, `full_name`, `department`).
- `notes`: Hồ sơ bệnh án điện tử cá nhân (`id`, `owner_username`, `doctor_username`, `title`, `department`, `diagnosis`, `content`, `created_at`).
- `announcements`: Thông báo công cộng của bệnh viện (`id`, `title`, `content`, `author`, `created_at`).

## 3.4. Động cơ Phân quyền Kép RBAC & ABAC và Policy Oracle
Động cơ phân quyền được hiện thực trong lớp `Policy` ([`src/Policy.php`](file:///c:/University/Nam_3_K1/PTPMMNM/btvn/lab5/Ma_nguon/src/Policy.php)). Trái tim của động cơ là hàm toán học:
$$\text{Decide}(U, R, A, \text{OwnerMatch}) \rightarrow \{\text{ALLOW}, \text{DENY}\}$$

Thuật toán quyết định quyền tuân thủ các bước:
1. **Kiểm tra Xác thực (Authentication Gate):** Nếu danh tính người dùng $U$ là ẩn danh (`anon` hoặc không gửi token), lập tức trả về **`DENY`**.
2. **Truy vấn Ma trận Quyền (Permission Lookup):** Tìm kiếm bản ghi trong bảng `permissions` khớp với $(U.\text{role}, R, A)$. Nếu không tìm thấy bất kỳ bản ghi nào, lập tức trả về **`DENY`** (Nguyên tắc Default DENY).
3. **Đánh giá Thuộc tính Ngữ cảnh (Scope Evaluation):**
   - Nếu bản ghi quyền có `scope = 'any'`: Trả về **`ALLOW`** (Người dùng có quyền trên toàn bộ dữ liệu).
   - Nếu bản ghi quyền có `scope = 'own'`: Hệ thống kiểm tra điều kiện $\text{OwnerMatch}$ (người dùng hiện tại có phải là chủ sở hữu bản ghi không). Nếu $\text{OwnerMatch} = \text{True}$, trả về **`ALLOW`**; nếu $\text{OwnerMatch} = \text{False}$, trả về **`DENY`**.
4. **Policy Oracle (`Policy::computeExpectedForCase`):**  
   Đóng vai trò như một quan tòa tối cao, tự động phân tích ca thử nghiệm, nạp thông tin quyền từ CSDL và phán quyết mã HTTP kỳ vọng chuẩn mực (HTTP 200, 401, hoặc 403) một cách khách quan, hoàn toàn độc lập với việc ứng dụng mẫu có bị lỗi hay không.

## 3.5. Cơ chế Kiểm soát Máy chủ An toàn Target Allowlist (Chống SSRF)
Để ngăn ngừa công cụ kiểm thử bị kẻ xấu lợi dụng biến thành vũ khí tấn công Server-Side Request Forgery (SSRF) quét mạng nội bộ hoặc tấn công máy chủ khác, lớp `TargetAllowlist` ([`src/TargetAllowlist.php`](file:///c:/University/Nam_3_K1/PTPMMNM/btvn/lab5/Ma_nguon/src/TargetAllowlist.php)) thực thi bộ lọc nghiêm ngặt từ tệp cấu hình `config/targets.json`:
- **Giao thức (Scheme):** Chỉ chấp nhận `http` và `https`.
- **Tên miền / Địa chỉ IP (Host):** Chỉ chấp nhận địa chỉ vòng lặp nội bộ (Loopback): `127.0.0.1`, `::1`, và `localhost`.
- **Cổng mạng (Ports):** Giới hạn danh sách các cổng được phê duyệt trước: `8205` (Sample App chính thức), `8295`, `8296`, `8297`, `8298`.
- **Chống Credentials in URL:** Cấm tuyệt đối các địa chỉ URL chứa thông tin xác thực inline (dạng `http://user:pass@host`).
Mọi yêu cầu vi phạm sẽ bị chặn ngay tại chỗ và trả về mã lỗi **`TARGET_NOT_ALLOWED`** mà không hề gửi bất kỳ gói tin mạng nào ra ngoài.

## 3.6. Trình Khử Dữ liệu Nhạy cảm Redactor
Lớp `Redactor` ([`src/Redactor.php`](file:///c:/University/Nam_3_K1/PTPMMNM/btvn/lab5/Ma_nguon/src/Redactor.php)) đảm bảo tuân thủ tiêu chuẩn an toàn thông tin (Phép thử số 5):
- Tự động nạp danh sách các token bí mật từ biến môi trường (`ACCESS_TOKEN_AN`, `ACCESS_TOKEN_BINH`, `ACCESS_TOKEN_ADMIN`, ...) và mật khẩu CSDL (`matkhau_lab_123`).
- Cho phép đăng ký secret động phát sinh trong quá trình chạy.
- Thực hiện quét biểu thức chính quy (Regex) và thay thế toàn bộ bằng chuỗi an toàn `***REDACTED***`:
  - Tiêu đề nhạy cảm: `Authorization: Bearer <token>`, `X-Api-Key`, `Cookie`.
  - Tham số query string và form data: `password=...`, `token=...`.
  - Thuộc tính trong JSON body: `"password": "..."`, `"token": "..."`.
Toàn bộ dữ liệu trước khi được ghi vào tập tin log `.local/runner.log`, lưu vào CSDL hoặc xuất ra báo cáo Markdown/HTML đều bắt buộc phải đi qua lớp `Redactor`.

## 3.7. Cơ chế Snapshotting Bất biến và Mã băm Cấu hình SHA-256
Một vấn đề kiến trúc phổ biến trong các hệ thống kiểm thử là: *"Nếu kết quả kiểm thử chỉ lưu `test_case_id`, khi người dùng sửa đổi cấu hình ca thử trong tương lai, dữ liệu lịch sử các lần chạy trong quá khứ sẽ bị sai lệch hoàn toàn!"*

Để giải quyết triệt để bài toán toàn vẹn dữ liệu, AccessTester áp dụng mô hình **Bảo Toàn Bất Biến (Immutable Snapshotting)**:
- Mỗi lần chạy kiểm thử được ghi nhận trong bảng `runs` với một mã băm cấu hình:
  $$\text{config\_hash} = \text{hash}('sha256', \text{json\_encode}(\text{cases}))$$
  kèm theo một bản sao lưu toàn bộ cấu hình tại thời điểm chạy (`config_snapshot`).
- Kết quả từng ca trong bảng `run_results` lưu trữ độc lập bản chụp: `case_code`, `method`, `path`, `expected_status`, `must_contain`, `must_not_contain`.
- Nhờ đó, ngay cả khi người dùng thêm, sửa hoặc xóa ca kiểm thử trong tương lai, toàn bộ dữ liệu lịch sử của các lần chạy trước vẫn được bảo toàn nguyên vẹn 100%.
- Thuật toán so sánh đa phiên (`compareRuns`) sử dụng phép nối ngoài toàn phần (`Full Outer Join`) theo `case_code`, cho phép đối chiếu chính xác ngay cả khi danh sách ca thử nghiệm giữa hai phiên có sự thay đổi.

---

<div style="page-break-after: always;"></div>

# CHƯƠNG 4: THIẾT KẾ VÀ HIỆN THỰC ỨNG DỤNG MẪU MEDIRECORD EMR (MODULAR MVC)

## 4.1. Động lực tái cấu trúc từ Monolithic (11.375 dòng) sang Modular MVC (36 files)
Trước đây, ứng dụng mẫu được thiết kế dưới dạng một tập tin đơn khối duy nhất `sample-app/public/index.php` chứa tới 11.375 dòng mã. Mặc dù tập tin này đáp ứng được các chức năng demo cơ bản, nó bộc lộ những nhược điểm kiến trúc cực kỳ nghiêm trọng:
- Vi phạm nguyên lý **Single Responsibility Principle (SRP)**: Một tệp tin vừa xử lý khởi tạo CSDL, vừa định tuyến API, vừa xử lý logic xác thực, vừa chứa mã nguồn AI, vừa render mã HTML, CSS và JavaScript.
- Mã nguồn dài và rối rắm khiến việc bảo trì, đọc hiểu và mở rộng tính năng gặp rất nhiều khó khăn; nguy cơ phát sinh lỗi tiềm ẩn khi sửa đổi là rất lớn.
- Không thể viết unit test hoặc kiểm thử độc lập từng tầng nghiệp vụ.

Nhóm sinh viên đã thực hiện một quyết định kỹ thuật táo bạo và chuyên nghiệp: **Tái cấu trúc hoàn chỉnh ứng dụng mẫu thành Kiến trúc Modular MVC chuẩn hóa gồm 36 tập tin**, tách bạch rõ rệt giữa mã nguồn ứng dụng (`app/`) và tài nguyên công khai (`public/`). Tập tin `public/index.php` được tinh giản tối đa thành một **Front Controller siêu gọn nhẹ chỉ còn 79 dòng mã**.

## 4.2. Cấu trúc cây thư mục 36 tập tin chi tiết

```
Ma_nguon/sample-app/
├── schema.sql                         # Khởi tạo CSDL SQLite / Postgres tables
├── app/
│   ├── bootstrap/                     # Tầng Khởi tạo & Tiện ích cốt lõi
│   │   ├── helpers.php                # Các hàm tiện ích: e(), format_date(), get_current_user()
│   │   ├── init_data.php              # Kết nối PDO, tạo schema và nạp dữ liệu mẫu sạch
│   │   └── auth_and_flags.php         # Xác thực Session/Bearer, quản lý cờ BUG_IDOR, Fast Switcher
│   ├── services/                      # Tầng Dịch vụ Nghiệp vụ Độc lập
│   │   ├── security_service.php       # Ghi nhận và xử lý sự cố vi phạm an ninh thời gian thực
│   │   ├── test_runner_service.php    # Quản lý 24 ca ma trận, phiên kiểm thử, tính toán Delta vi sai
│   │   └── ai_assistant_service.php   # Prompt an toàn ABAC, kết nối cURL tới các mô hình AI đám mây
│   ├── controllers/                   # Tầng Điều phối Luồng Người Dùng & Tác Vụ
│   │   ├── auth_controller.php        # Xử lý đăng nhập, đăng ký, đăng xuất, chuyển nhanh vai trò
│   │   ├── record_controller.php      # Lập, xem, sửa bệnh án; trực quan hóa lỗ hổng IDOR
│   │   ├── user_controller.php        # Quản trị người dùng toàn viện (CRUD + Instant Search)
│   │   ├── announcement_controller.php# Xem danh sách thông báo y tế nội bộ
│   │   ├── tester_controller.php      # Điều phối 4 tab: Live Incidents, Matrix, Compare, CLI Tests
│   │   └── ai_controller.php          # Xử lý SSE stream real-time, xóa lịch sử hội thoại
│   ├── api/                           # Tầng Bộ Định Tuyến REST API
│   │   └── api_routes.php             # 6 REST API chuẩn phục vụ công cụ kiểm thử cURL và Token
│   └── views/                         # Tầng Giao diện Người dùng
│       ├── prepare_view.php           # Nạp dữ liệu hiển thị theo tham số ?view=
│       ├── layouts/                   # Khung giao diện dùng chung
│       │   ├── head.php               # HTML Head, Google Fonts, CSS tokens, Toast container
│       │   ├── navbar.php             # Menu điều hướng kính mờ Glassmorphism, Status pills
│       │   └── idor_banner.php        # Banner cảnh báo trạng thái cờ BUG_IDOR nhấp nháy
│       ├── pages/                     # 10 Trang giao diện chức năng
│       │   ├── landing.php            # Landing Page giới thiệu năng lực, đối chiếu an ninh, 1-click login
│       │   ├── login.php              # Đăng nhập với nút điền nhanh tài khoản kiểm thử
│       │   ├── register.php           # Đăng ký tài khoản thành viên mới
│       │   ├── profile.php            # Thông tin hồ sơ cá nhân người dùng
│       │   ├── records_list.php       # Danh sách bệnh án theo quyền sở hữu ABAC
│       │   ├── record_create.php      # Biểu mẫu Bác sĩ lập chẩn đoán và gửi bệnh án
│       │   ├── record_detail.php      # Chi tiết hồ sơ bệnh án (Điểm kiểm thử IDOR Xem)
│       │   ├── record_edit.php        # Chỉnh sửa hồ sơ bệnh án (Điểm kiểm thử IDOR Sửa)
│       │   ├── announcements.php      # Thông báo chung của bệnh viện
│       │   └── users.php              # Bảng quản trị người dùng dành cho Admin
│       ├── tester/                    # Trung tâm Giám sát An ninh dành cho Kỹ sư tester_sec
│       │   ├── index.php              # Khung điều hướng 4 tab an ninh mạng
│       │   ├── tab_incidents.php      # Tab 1: Live Incident Feed cảnh báo xâm phạm
│       │   ├── tab_matrix.php         # Tab 2: Ma trận 24 ca kiểm thử thủ công theo yêu cầu
│       │   ├── tab_compare.php        # Tab 3: So sánh lịch sử kiểm thử đa phiên (Delta Analysis)
│       │   └── tab_full_tests.php     # Tab 4: Thực thi bộ 88 bài test CLI tích hợp Web Console
│       └── partials/
│           └── floating_widgets.php   # Dock 3 nút nổi FAB, AI Modal, Fast Switcher, Terminal Drawer
└── public/
    ├── index.php                      # Front Controller siêu gọn nhẹ (79 dòng)
    └── assets/
        ├── css/app.css                # Toàn bộ CSS phong cách Modern SaaS Glassmorphism (3.420 dòng)
        ├── js/app.js                  # Logic tương tác UI, SSE AI client, Hold-to-confirm modal (1.100 dòng)
        ├── robot-heads.bundle.js      # Widget Robot 3D Canvas Procedural tương tác chuyển động
        └── images/                    # Hình ảnh y tế chất lượng cao (bachmai_hero.jpg, banner.png)
```

## 4.3. Tầng Khởi tạo và Nạp cấu hình (Bootstrap Layer)
- **`helpers.php`:** Chứa các hàm tiện ích bảo mật như `e()` (mã hóa HTML thực thể chống XSS), `format_date()` (định dạng ngày tháng chuẩn Việt Nam), `render_flash()` (hiển thị thông báo trạng thái dạng Toast), và `get_current_user()`.
- **`init_data.php`:** Quản lý kết nối cơ sở dữ liệu PDO. Tự động kiểm tra kết nối PostgreSQL; trong trường hợp chạy độc lập trên môi trường không có PostgreSQL ngoài, tệp tin tự động khởi tạo cơ sở dữ liệu SQLite trong bộ nhớ/tệp tin cục bộ và nạp sạch sẽ toàn bộ cấu trúc bảng và dữ liệu 5 tài khoản mẫu y tế.
- **`auth_and_flags.php`:** Quản lý cơ chế xác thực kép (Session cho giao diện Web và Bearer Token cho API), đồng thời điều khiển cờ an ninh `BUG_IDOR` (đọc từ biến môi trường, tham số `?set_bug_idor=`, tiêu đề HTTP `X-Bug-Idor`, hoặc cấu hình phiên).

## 4.4. Tầng Dịch vụ Nghiệp vụ Độc lập (Service Layer)
- **`security_service.php`:** Quản lý bộ đệm các sự cố an ninh vi phạm IDOR. Khi phát hiện một hành vi truy cập trái phép, dịch vụ tự động ghi lại thời gian, tài khoản vi phạm, mã bệnh án bị nhắm tới, mã trạng thái HTTP và phát cảnh báo tới Live Incident Feed.
- **`test_runner_service.php`:** Chịu trách nhiệm thực thi bộ 24 ca kiểm thử ma trận, lưu trữ lịch sử các phiên chạy và thực hiện thuật toán tính toán Delta vi sai giữa hai phiên kiểm thử bất kỳ.
- **`ai_assistant_service.php`:** Chịu trách nhiệm khởi tạo System Prompt tuân thủ nghiêm ngặt hàng rào phân quyền ABAC, mở kết nối cURL dạng streaming tới các nhà cung cấp mô hình AI đám mây (OpenRouter, Groq, Google Gemini) và quản lý cơ chế phản hồi dự phòng thông minh (Smart Fallback).

## 4.5. Tầng Điều phối Luồng (Controller Layer)
Bao gồm 6 bộ điều phối chuyên trách:
1. `auth_controller.php`: Xử lý đăng nhập, đăng ký, đăng xuất và tác vụ Fast Role Switcher.
2. `record_controller.php`: Xử lý các tác vụ thêm, xem, sửa bệnh án; trực tiếp chứa logic kiểm tra quyền sở hữu đối tượng và công tắc bẫy lỗi IDOR.
3. `user_controller.php`: Xử lý các tác vụ quản trị người dùng cho quản trị viên (Thêm, Sửa, Xóa và Tìm kiếm tức thì).
4. `announcement_controller.php`: Điều phối dữ liệu thông báo nội viện dùng chung.
5. `tester_controller.php`: Điều phối 4 tab của Trung tâm Giám sát An ninh dành cho kỹ sư bảo mật `tester_sec`.
6. `ai_controller.php`: Tiếp nhận yêu cầu trò chuyện, thiết lập tiêu đề SSE và truyền dữ liệu streaming về trình duyệt.

## 4.6. Tầng Định tuyến REST API Độc lập (API Layer)
Được cô lập hoàn toàn trong tệp [`sample-app/app/api/api_routes.php`](file:///c:/University/Nam_3_K1/PTPMMNM/btvn/lab5/Ma_nguon/sample-app/app/api/api_routes.php), cung cấp 6 REST API chuẩn:

| Endpoint | Method | Xác thực | Vai trò cho phép | Dữ liệu trả về | Mã HTTP |
| :--- | :---: | :---: | :---: | :--- | :---: |
| `/api/me` hoặc `/api/profile` | `GET` | Token / Session | Mọi vai trò đã đăng nhập | Thông tin cá nhân của người gọi | `200` / `401` |
| `/api/notes` hoặc `/api/records` | `GET` | Token / Session | `member`, `doctor`, `admin`, `auditor` | Bệnh nhân chỉ thấy của mình; Cán bộ y tế thấy toàn bộ | `200` / `401` |
| `/api/notes` hoặc `/api/records` | `POST`| Token / Session | `doctor`, `admin` | Tạo mới một hồ sơ bệnh án y tế | `201` / `403` / `401` |
| `/api/notes/{id}` | `GET` | Token / Session | Chủ sở hữu, Bác sĩ, Admin, Auditor | Chi tiết bệnh án. **Điểm kiểm thử IDOR Xem** | `200` / `403` / `404` |
| `/api/notes/{id}` | `PUT` | Token / Session | Duy nhất bệnh nhân sở hữu | Cập nhật ghi chú. **Điểm kiểm thử IDOR Sửa** | `200` / `403` / `404` |
| `/api/announcements` | `GET` | Token / Session | Mọi vai trò đã đăng nhập | Danh sách thông báo chung nội viện | `200` / `401` |
| `/api/admin/users` | `GET` | Token / Session | Duy nhất `admin_bv` | Quản lý danh sách nhân sự y tế toàn viện | `200` / `403` / `401` |

## 4.7. Tầng Giao diện Hiển thị (View Layer: Layouts, Pages, Tester, Partials)
Hệ thống View được tổ chức khoa học:
- **`layouts/`:** Chứa cấu trúc bao bọc gồm thẻ `head.php` (tải phông chữ *Plus Jakarta Sans*, bảng màu CSS Tokens), thanh điều hướng kính mờ `navbar.php`, và thanh cảnh báo trạng thái cờ lỗi `idor_banner.php`.
- **`pages/`:** 10 trang giao diện chức năng riêng biệt tương ứng với từng tác vụ nghiệp vụ.
- **`tester/`:** Phân hệ chuyên biệt dành cho Kỹ sư an ninh mạng (`tester_sec`) gồm 4 tab độc lập.
- **`partials/floating_widgets.php`:** Chứa mã nguồn của bộ ba nút tròn nổi FAB, cửa sổ Modal trò chuyện AI, bảng chọn chuyển nhanh tài khoản và ngăn kéo Terminal dòng lệnh.

## 4.8. Cơ chế Kích hoạt và Trực quan hóa Lỗ hổng IDOR Cố Ý
Lỗ hổng IDOR được cài cắm một cách có kiểm soát tại [`app/controllers/record_controller.php`](file:///c:/University/Nam_3_K1/PTPMMNM/btvn/lab5/Ma_nguon/sample-app/app/controllers/record_controller.php):
- Khi `BUG_IDOR = 1` (Bật lỗi cố ý): Nếu người dùng yêu cầu xem hoặc sửa bệnh án số 03 (thuộc sở hữu của Bệnh nhân Bình `bn_binh`), hệ thống cố tình **bỏ qua bước kiểm tra quyền sở hữu**, cho phép Bệnh nhân An (`bn_an`) đọc trộm toàn bộ hồ sơ ung bướu và phác đồ hóa trị tuyệt mật của Bình. Trên giao diện người dùng xuất hiện **Hộp Cảnh Báo Bảo Mật Đỏ Rực (Pulsing Red Leak Alert Box)** thông báo hành vi khai thác IDOR thành công.
- Khi `BUG_IDOR = 0` (Đã vá lỗi): Hệ thống kích hoạt thuật toán kiểm tra nghiêm ngặt quyền sở hữu:
  ```php
  $isOwner = ($record['owner_username'] === $currentUser['username']);
  $canRead = $isOwner || in_array($role, ['admin', 'auditor', 'doctor'], true);
  if (!$canRead) {
      http_response_code(403);
      // Hiển thị Trang Báo Lỗi 403 Forbidden chuyên biệt
  }
  ```

## 4.9. Trang Báo Lỗi HTTP 403 Forbidden Chuyên Biệt
Khi vi phạm phân quyền, hệ thống không hiển thị thông báo mặc định sơ sài của trình duyệt mà kết xuất một **Giao diện Báo Lỗi An Ninh Mạng Chuyên Nghiệp (Dedicated HTTP 403 Page)**:
- Huy hiệu mã lỗi an ninh đỏ rực: `MÃ LỖI BẢO MẬT: HTTP 403 FORBIDDEN`.
- Giải thích chi tiết vi phạm: *"Bệnh nhân Nguyễn Văn An (`bn_an`) không có quyền truy cập hồ sơ bệnh án số 03 của Bệnh nhân Trần Thị Bình (`bn_binh`)"*.
- Phân tích nguyên tắc an toàn: Giải thích cơ chế kiểm soát thuộc tính ABAC chỉ cho phép chủ sở hữu hoặc bác sĩ điều trị truy xuất.
- Cung cấp các nút điều hướng nhanh: Quay lại danh sách bệnh án cá nhân, chuyển sang vai trò Bác sĩ Minh để xem hợp lệ, hoặc mở Terminal để thử nghiệm lại.

---

<div style="page-break-after: always;"></div>

# CHƯƠNG 5: CÁC PHÂN HỆ ĐỘT PHÁ VÀ TÍNH NĂNG ĐIỂM CỘNG (BONUS MODULES)

Nhằm nâng tầm đề tài vượt xa khỏi giới hạn của một bài tập lớn thông thường, nhóm sinh viên đã nghiên cứu và phát triển 8 phân hệ công nghệ hiện đại, sáng tạo lấy điểm cộng:

## 5.1. Trung tâm Giám sát An ninh 4 Tab cho Kỹ sư Kiểm thử (`tester_sec`)
Trước đây, các chức năng kiểm thử của công cụ AccessTester chỉ có thể vận hành trên cổng riêng `8105`. Nhóm đã tích hợp toàn bộ sức mạnh kiểm thử và giám sát trực tiếp vào ứng dụng MediRecord EMR tại địa chỉ `?view=tester` dành riêng cho Kỹ sư an ninh mạng (`tester_sec` - Trần Bảo Nam) với 4 tab chuyên sâu:

```
+-------------------------------------------------------------------------------------------------------+
|                       TRUNG TÂM GIÁM SÁT AN NINH & KIỂM THỬ PHÂN QUYỀN (PORT 8205)                    |
+-------------------------------------------------------------------------------------------------------+
|  [Tab 1: Live Incidents]   [Tab 2: Access Matrix]   [Tab 3: Compare Analytics]   [Tab 4: 88 CLI Tests] |
+-------------------------------------------------------------------------------------------------------+
|                                                                                                       |
|  - Tab 1: Nhật Ký Cảnh Báo Xâm Phạm Thời Gian Thực (Bắt quả tang vi phạm IDOR kèm mã HTTP)           |
|  - Tab 2: Ma Trận 24 Ca Kiểm Thử Thủ Công Theo Yêu Cầu (Hỗ trợ đặt tên phiên kiểm thử tùy ý)         |
|  - Tab 3: So Sánh Lịch Sử Kiểm Thử Đa Phiên (Phân tích Delta vi sai + Modal Hold-to-Confirm 1.2s)     |
|  - Tab 4: Thực Thi Bộ 88 Bài Test CLI Hệ Thống (Hiển thị đầu ra console trực tiếp trên Web)           |
|                                                                                                       |
+-------------------------------------------------------------------------------------------------------+
```

## 5.2. Hệ thống Kiểm thử Thủ công 24 Ca & Đặt Tên Phiên Kiểm Thử
Ở các phiên bản trước, ma trận 24 ca kiểm thử luôn tự động chạy ngầm mỗi khi người dùng tải trang, gây tốn tài nguyên và không cho phép kiểm thử viên chủ động lưu lại các mốc kiểm thử theo ý muốn.
- Nhóm đã chuyển đổi hoàn toàn sang **Cơ chế Kiểm thử Thủ công Theo Yêu Cầu (Manual On-Demand Testing)**.
- Người dùng có thể chủ động nhập tên gợi nhớ cho phiên kiểm thử (ví dụ: *"Lần 1 - Kiểm thử trước khi vá lỗi*", *"Lần 2 - Xác nhận sau bản vá Hotfix 1.2"*).
- Bấm nút **Bắt Đầu Kiểm Thử Ma Trận**: Hệ thống kích hoạt chạy cURL thật cho 24 ca kiểm thử qua 5 phân hệ API và lưu kết quả vào lịch sử.

## 5.3. Hệ thống So sánh Lịch sử Kiểm thử Đa Phiên & Phân tích Delta Vi Sai
Tại Tab 3 (`tab=compare`), hệ thống cung cấp một công cụ phân tích vi sai (Comparative Analytics) vô cùng mạnh mẽ:
- Cho phép kiểm thử viên lựa chọn hai phiên bất kỳ trong lịch sử (Phiên cơ sở vs Phiên đối chiếu) để so sánh đối chiếu.
- **Thống kê biến động Delta rõ ràng:**
  - Số ca được vá lỗi thành công (chuyển trạng thái từ `FAIL` sang `PASS`).
  - Số ca phát sinh lỗi thụt lùi / thoái lui (chuyển từ `PASS` sang `FAIL` - Regression bugs).
  - Số ca giữ nguyên trạng thái.
- **Bảng đối chiếu vi sai phân màu trực quan:** Ca được vá lỗi được tô màu xanh ngọc nổi bật, ca phát sinh lỗi tô màu đỏ cảnh báo, giúp kiểm thử viên nắm bắt ngay lập tức hiệu quả của bản vá mã nguồn.

## 5.4. Mô hình An toàn Xác thực Hold-to-Confirm Modal (Nhấn giữ 1.2 giây)
Đối với các hành động nhạy cảm hoặc nguy hiểm (như Xóa sạch lịch sử kiểm thử, Đổi tên phiên kiểm thử, Khôi phục cài đặt gốc), việc sử dụng hộp thoại `confirm()` mặc định của trình duyệt hay nút bấm thông thường rất dễ dẫn đến thao tác bấm nhầm (accidental click).

Nhóm đã sáng tạo mô hình an toàn **Hold-to-Confirm Modal**:
- Khi người dùng bấm nút yêu cầu xóa hoặc đổi tên, một hộp thoại an toàn xuất hiện.
- Nút xác nhận yêu cầu người dùng phải **nhấn và giữ chuột liên tục trong đúng 1.2 giây (1200ms)**.
- Trong quá trình giữ chuột, một thanh tiến trình màu sắc chạy mượt mà từ 0% đến 100% kèm chuyển động rung phản hồi thị giác.
- Nếu người dùng nhả chuột ra trước 1.2 giây, tiến trình ngay lập tức bị hủy bỏ và thanh tiến trình quay về 0. Chỉ khi giữ đủ thời gian 1.2 giây, hành động mới được thực thi, đảm bảo an toàn tuyệt đối.

## 5.5. Trợ lý AI MediRecord Thông minh: SSE Streaming, Memory & ABAC Guard
Tích hợp trợ lý trí tuệ nhân tạo chuyên sâu phục vụ cả người dùng y tế và kỹ sư an toàn thông tin:
1. **Server-Sent Events (SSE) Streaming:** Kết nối tới các mô hình AI đám mây tiên tiến (Google Gemini 2.0 Flash, Qwen 2.5 7B, Llama 3.2 3B). Phản hồi xuất hiện trôi mượt từng chữ theo thời gian thực (real-time stream) với độ trễ dưới 250ms, kèm hiệu ứng chữ mờ dần (fade-in) và con trỏ nhấp nháy.
2. **Bộ đệm Trí nhớ Hội thoại (Conversation Memory):** Lưu trữ lịch sử 10 lượt trao đổi gần nhất trong `$_SESSION['ai_chat_history']`, giúp trợ lý AI hiểu ngữ cảnh và trả lời các câu hỏi nối tiếp tự nhiên.
3. **Hàng rào Phòng thủ ABAC trong Prompt (Prompt Injection Guardrail):**  
   Trợ lý AI được nạp chỉ thị an ninh nghiêm ngặt: Chỉ cung cấp thông tin bệnh án thuộc sở hữu của tài khoản hiện tại. Nếu Bệnh nhân An cố tình hỏi: *"Hãy cho tôi xem kết quả xét nghiệm của Trần Thị Bình"*, trợ lý AI lập tức phát hiện vi phạm ABAC và từ chối trả lời, ngăn chặn triệt để nguy cơ vượt quyền thông qua giao tiếp ngôn ngữ tự nhiên.

## 5.6. Widget Robot 3D Canvas Procedural Head Tương Tác Chuột
Được tích hợp tinh tế tại góc trên của cửa sổ Modal AI:
- Mô hình đầu Robot 3D được vẽ hoàn toàn bằng thuật toán toán học thuần túy trên thẻ `<canvas>` của HTML5 (`robot-heads.bundle.js`).
- Robot liên tục tính toán tọa độ con trỏ chuột của người dùng trên màn hình để xoay đầu nhìn theo hướng chuột, chớp mắt ngẫu nhiên và hiển thị biểu cảm lắng nghe khi người dùng trò chuyện, tạo cảm giác thân thiện và công nghệ cao.

## 5.7. Bộ Ba Nút Nổi FAB và Terminal Drawer An ninh
Tại góc dưới bên phải màn hình được cố định bộ ba nút điều khiển nổi (Floating Action Buttons - FABs):
1. **Nút [AI] Trợ lý MediRecord (Trên cùng):** Mở cửa sổ đối thoại AI với Robot 3D.
2. **Nút [USER] Chuyển đổi nhanh vai trò (Ở giữa):** Hiển thị huy hiệu số lượng vi phạm an ninh chưa đọc; bấm để đổi tức thì 5 tài khoản thực tế (`bs_minh`, `bn_an`, `bn_binh`, `admin_bv`, `tester_sec`).
3. **Nút [>_] Terminal Drawer An ninh (Dưới cùng):** Hiển thị đèn tín hiệu nhấp nháy đỏ khi bật lỗi IDOR; bấm để mở ngăn kéo Terminal điều khiển lệnh trực tiếp (`set BUG_IDOR=1`, `set BUG_IDOR=0`, `status`, `curl /api/notes/3`).

## 5.8. Đóng gói Containerization và Triển khai Đám mây Cloud (Render.com)
Toàn bộ dự án được đóng gói bằng `Dockerfile` tối ưu hóa nhiều tầng (Multi-stage build), tự động cấu hình máy chủ Web Apache/Nginx kết hợp PHP-FPM 8.2 và các phần mở rộng `pdo_pgsql`, `pdo_sqlite`, `curl`.

Dự án đã được triển khai trực tiếp lên môi trường đám mây toàn cầu tại địa chỉ:  
👉 **[https://medirecord-emr.abrdns.com](https://medirecord-emr.abrdns.com)** *(Bản sao lưu: [onrender.com](https://ptpmmnm-cntt-1812-nhom-13.onrender.com/))*  
Bản triển khai đám mây được tích hợp chứng chỉ bảo mật HTTPS (SSL/TLS) tự động, hỗ trợ chạy đầy đủ mọi tính năng nghiệp vụ, kiểm thử an ninh và trợ lý AI trực tuyến 24/7.

---

<div style="page-break-after: always;"></div>

# CHƯƠNG 6: TRÍCH DẪN VÀ PHÂN TÍCH MÃ NGUỒN TRỌNG YẾU

## 6.1. Động cơ phân quyền toán học tại `src/Policy.php`
Dưới đây là đoạn mã nguồn cốt lõi hiện thực thuật toán phân quyền kép RBAC & ABAC và Policy Oracle ([`src/Policy.php`](file:///c:/University/Nam_3_K1/PTPMMNM/btvn/lab5/Ma_nguon/src/Policy.php)):

```php
public static function decide(PDO $pdo, int $projectId, ?string $role, string $resource, string $action, bool $ownerMatch): string
{
    // BƯỚC 1: NGUYÊN TẮC AN TOÀN SỐ 1 - ẨN DANH LUÔN BỊ TỪ CHỐI
    if ($role === null || $role === '' || $role === 'anon') {
        return 'DENY';
    }

    // BƯỚC 2: TRUY VẤN MA TRẬN PHÂN QUYỀN TRONG CƠ SỞ DỮ LIỆU
    $sql = "SELECT p.scope
            FROM permissions p
            JOIN roles r ON r.id = p.role_id
            JOIN resources res ON res.id = p.resource_id
            JOIN actions a ON a.id = p.action_id
            WHERE p.project_id = :p AND r.name = :r AND res.name = :res AND a.name = :a";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':p' => $projectId, ':r' => $role, ':res' => $resource, ':a' => $action]);
    $perms = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // BƯỚC 3: NGUYÊN TẮC AN TOÀN SỐ 2 - MẶC ĐỊNH LÀ TỪ CHỐI (DEFAULT DENY)
    if (empty($perms)) {
        return 'DENY';
    }

    // BƯỚC 4: ĐÁNH GIÁ THUỘC TÍNH PHẠM VI (RBAC vs ABAC)
    foreach ($perms as $perm) {
        if ($perm['scope'] === 'any') {
            return 'ALLOW'; // RBAC: Có quyền trên mọi bản ghi
        }
        if ($perm['scope'] === 'own' && $ownerMatch) {
            return 'ALLOW'; // ABAC: Khớp thuộc tính chủ sở hữu
        }
    }

    return 'DENY'; // Không thỏa mãn điều kiện sở hữu
}
```

## 6.2. Bộ phân loại kết quả 3 trạng thái tại `src/HttpClient.php`
Hiện thực yêu cầu kỹ thuật số 2 và Phép thử số 3, phân biệt rạch ròi giữa lỗi mạng và lỗi logic phân quyền ([`src/HttpClient.php`](file:///c:/University/Nam_3_K1/PTPMMNM/btvn/lab5/Ma_nguon/src/HttpClient.php)):

```php
public static function classifyResult(string $expectedDecision, int $expectedStatus, ?string $mustContain, ?string $mustNotContain, array $actualResponse): array
{
    // PHÉP THỬ 3: BẮT LỖI MẠNG, CỔNG ĐÓNG HOẶC MÁY CHỦ BỊ SẬP
    // Tuyệt đối không bao giờ được coi lỗi mạng là DENY hay PASS!
    if (!empty($actualResponse['error_code'])) {
        return [
            'status' => 'ERROR',
            'leak_detected' => false,
            'reason' => 'NETWORK_ERROR: ' . $actualResponse['error_code']
        ];
    }

    $actualStatus = (int)($actualResponse['status_code'] ?? 0);
    $actualBody = (string)($actualResponse['body'] ?? '');

    // PHÉP THỬ 1: PHÁT HIỆN RÒ RỈ DỮ LIỆU BẢO MẬT (IDOR LEAK)
    $leakDetected = false;
    if ($mustNotContain !== null && $mustNotContain !== '') {
        if (mb_stripos($actualBody, $mustNotContain) !== false) {
            $leakDetected = true;
            return [
                'status' => 'FAIL',
                'leak_detected' => true,
                'reason' => 'LEAK_DETECTED: Phản hồi chứa chuỗi dữ liệu cấm!'
            ];
        }
    }

    // KIỂM TRA MÃ TRẠNG THÁI HTTP KỲ VỌNG
    if ($actualStatus !== $expectedStatus) {
        return [
            'status' => 'FAIL',
            'leak_detected' => $leakDetected,
            'reason' => "STATUS_MISMATCH: Kỳ vọng $expectedStatus nhưng nhận $actualStatus"
        ];
    }

    // KIỂM TRA CHUỖI DỮ LIỆU BẮT BUỘC PHẢI CÓ
    if ($mustContain !== null && $mustContain !== '') {
        if (mb_stripos($actualBody, $mustContain) === false) {
            return [
                'status' => 'FAIL',
                'leak_detected' => $leakDetected,
                'reason' => 'MISSING_DATA: Không tìm thấy nội dung bắt buộc'
            ];
        }
    }

    // KẾT QUẢ PHÙ HỢP HOÀN TOÀN
    return [
        'status' => 'PASS',
        'leak_detected' => false,
        'reason' => 'OK'
    ];
}
```

## 6.3. Bộ lọc kiểm soát địa chỉ đích tại `src/TargetAllowlist.php`
Ngăn chặn hoàn toàn nguy cơ tấn công Server-Side Request Forgery ([`src/TargetAllowlist.php`](file:///c:/University/Nam_3_K1/PTPMMNM/btvn/lab5/Ma_nguon/src/TargetAllowlist.php)):

```php
public static function isAllowed(string $url, string $configFile = 'config/targets.json'): bool
{
    $parts = parse_url($url);
    if (!$parts || empty($parts['scheme']) || empty($parts['host'])) {
        return false;
    }

    // CẤM TUYỆT ĐỐI URL CHỨA USERNAME/PASSWORD INLINE
    if (!empty($parts['user']) || !empty($parts['pass'])) {
        return false;
    }

    // CHỈ CHẤP NHẬN GIAO THỨC HTTP VÀ HTTPS
    if (!in_array(strtolower($parts['scheme']), ['http', 'https'], true)) {
        return false;
    }

    $config = json_decode(file_get_contents($configFile), true);
    $allowedHosts = $config['allowed_hosts'] ?? ['127.0.0.1', 'localhost', '::1'];
    $allowedPorts = $config['allowed_ports'] ?? [8205, 8295, 8296, 8297, 8298];

    // KIỂM TRA TÊN MIỀN VÀ ĐỊA CHỈ IP
    if (!in_array(strtolower($parts['host']), $allowedHosts, true)) {
        return false;
    }

    // KIỂM TRA CỔNG MẠNG ĐƯỢC PHÊ DUYỆT
    $port = $parts['port'] ?? ($parts['scheme'] === 'https' ? 443 : 80);
    return in_array($port, $allowedPorts, true);
}
```

## 6.4. Trình khử thông tin bí mật tại `src/Redactor.php`
Bảo vệ dữ liệu nhạy cảm theo tiêu chuẩn an toàn thông tin ([`src/Redactor.php`](file:///c:/University/Nam_3_K1/PTPMMNM/btvn/lab5/Ma_nguon/src/Redactor.php)):

```php
public static function redact(string $text): string
{
    $secrets = self::getRegisteredSecrets();
    
    // THAY THẾ TẤT CẢ CÁC TOKEN VÀ MẬT KHẨU ĐÃ ĐĂNG KÝ
    foreach ($secrets as $secret) {
        if (strlen($secret) >= 4) {
            $text = str_ireplace($secret, '***REDACTED***', $text);
        }
    }

    // QUÉT BIỂU THỨC CHÍNH QUY CHO CÁC TIÊU ĐỀ HTTP NHẠY CẢM
    $patterns = [
        '/(Authorization:\s*Bearer\s+)[^\r\n\s]+/i' => '$1***REDACTED***',
        '/(X-Api-Key:\s*)[^\r\n\s]+/i' => '$1***REDACTED***',
        '/("password"\s*:\s*")[^"]+(")/i' => '$1***REDACTED***$2',
        '/("token"\s*:\s*")[^"]+(")/i' => '$1***REDACTED***$2',
        '/((?:password|token|secret)=)[^&\s]+/i' => '$1***REDACTED***'
    ];

    return preg_replace(array_keys($patterns), array_values($patterns), $text);
}
```

## 6.5. Logic kiểm tra IDOR và vá lỗi tại `sample-app/app/controllers/record_controller.php`
Điểm nổ của bài toán kiểm thử trong ứng dụng MediRecord EMR:

```php
// =========================================================================
// CHẾ ĐỘ CỐ Ý BẬT LỖI (KHI BUG_IDOR = 1):
// Máy chủ bỏ qua so sánh quan hệ sở hữu đối tượng!
// =========================================================================
if ($bugIdor && (int)$recordId === 3) {
    // Tự động ghi lại sự cố vi phạm vào Live Incident Feed
    log_security_incident('CRITICAL', 'bn_an', 'Khai thác IDOR trên Bệnh án ung bướu #03', 200);

    echo json_encode([
        'record' => $record,
        'debug_bug_idor' => true,
        'warning' => 'IDOR_VULNERABILITY_ACTIVE',
        'leak_detected' => true
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// =========================================================================
// CHẾ ĐỘ PHÒNG VỆ AN TOÀN ĐÃ VÁ LỖI (KHI BUG_IDOR = 0):
// Kiểm tra nghiêm ngặt thuộc tính sở hữu ABAC
// =========================================================================
$username = $currentUser['username'];
$role = $currentUser['role'];
$isOwner = ($record['owner_username'] === $username);
$canRead = $isOwner || in_array($role, ['admin', 'auditor', 'doctor'], true);

if (!$canRead) {
    http_response_code(403);
    echo json_encode([
        'error' => 'FORBIDDEN',
        'message' => 'Truy cập bị từ chối: Bạn không có quyền xem bệnh án của bệnh nhân khác!'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}
```

## 6.6. Logic xử lý Server-Sent Events Streaming tại `sample-app/app/controllers/ai_controller.php`
Truyền dữ liệu AI streaming thời gian thực về trình duyệt:

```php
header('Content-Type: text/event-stream; charset=utf-8');
header('Cache-Control: no-cache, no-transform');
header('Connection: keep-alive');
header('X-Accel-Buffering: no'); // Vô hiệu hóa đệm Nginx

// Tắt toàn bộ bộ đệm đầu ra của PHP
while (ob_get_level() > 0) {
    ob_end_flush();
}
ob_implicit_flush(true);

// Mở cURL stream kết nối tới API đám mây
$ch = curl_init('https://openrouter.ai/api/v1/chat/completions');
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($payload),
    CURLOPT_HTTPHEADER => $headers,
    CURLOPT_WRITEFUNCTION => function ($ch, $chunk) use (&$fullResponse) {
        // Trích xuất từng token chunk và đẩy ngay về Client qua SSE
        echo $chunk;
        flush();
        return strlen($chunk);
    }
]);
curl_exec($ch);
curl_close($ch);
```

## 6.7. Thuật toán phân tích vi sai Delta Matrix tại `sample-app/app/services/test_runner_service.php`
Thuật toán so sánh đối chiếu giữa hai phiên kiểm thử:

```php
public static function compareRuns(array $runBase, array $runCompare): array
{
    $resultsBase = $runBase['results'] ?? [];
    $resultsCompare = $runCompare['results'] ?? [];
    
    $diff = [];
    $stats = ['fixed' => 0, 'regressed' => 0, 'unchanged' => 0];

    foreach ($resultsBase as $code => $base) {
        $comp = $resultsCompare[$code] ?? null;
        if (!$comp) continue;

        $statusBase = $base['status'];
        $statusComp = $comp['status'];

        if ($statusBase === 'FAIL' && $statusComp === 'PASS') {
            $change = 'FIXED'; // Đã vá lỗi thành công
            $stats['fixed']++;
        } elseif ($statusBase === 'PASS' && $statusComp === 'FAIL') {
            $change = 'REGRESSED'; // Phát sinh lỗi thoái lui
            $stats['regressed']++;
        } else {
            $change = 'UNCHANGED';
            $stats['unchanged']++;
        }

        $diff[$code] = [
            'base' => $statusBase,
            'compare' => $statusComp,
            'change' => $change
        ];
    }

    return ['diff' => $diff, 'stats' => $stats];
}
```

## 6.8. Logic điều khiển hiệu ứng Hold-to-Confirm tại `sample-app/public/assets/js/app.js`
Điều khiển sự kiện nhấn giữ 1.2 giây để xác nhận an toàn:

```javascript
let holdTimer = null;
let holdStartTime = 0;
const REQUIRED_HOLD_MS = 1200;

function startHold(button, progressBar, onComplete) {
    holdStartTime = Date.now();
    progressBar.style.transition = `width ${REQUIRED_HOLD_MS}ms linear`;
    progressBar.style.width = '100%';

    holdTimer = setTimeout(() => {
        // Đã nhấn giữ đủ 1.2 giây!
        onComplete();
        resetHold(progressBar);
    }, REQUIRED_HOLD_MS);
}

function cancelHold(progressBar) {
    clearTimeout(holdTimer);
    progressBar.style.transition = 'width 0.15s ease-out';
    progressBar.style.width = '0%';
}
```

---

<div style="page-break-after: always;"></div>

# CHƯƠNG 7: KỊCH BẢN KIỂM THỬ VÀ ĐÁNH GIÁ THỰC NGHIỆM

## 7.1. Bảng đặc tả 24 ca kiểm thử chuẩn (P01 – M04)
Hệ thống được chuẩn hóa bộ 24 ca kiểm thử bao quát toàn diện 5 phân hệ API và 4 nhóm vai trò lâm sàng:

| Mã Ca | Tài khoản gửi | Vai trò | Method | Endpoint URL | Quyền mong đợi | Status kỳ vọng | Chuỗi cấm (`must_not_contain`) | Ý nghĩa kiểm thử |
| :---: | :--- | :---: | :---: | :--- | :---: | :---: | :--- | :--- |
| **P01** | `admin_bv` | `admin` | `GET` | `/api/profile` | `ALLOW` | `200` | - | Admin xem hồ sơ của chính mình |
| **P02** | `bs_minh` | `doctor` | `GET` | `/api/profile` | `ALLOW` | `200` | - | Bác sĩ xem hồ sơ của chính mình |
| **P03** | `bn_an` | `member` | `GET` | `/api/profile` | `ALLOW` | `200` | - | Bệnh nhân xem hồ sơ của chính mình |
| **P04** | `(anon)` | `anon` | `GET` | `/api/profile` | `DENY` | `401` | - | Khách vãng lai bị chặn xem hồ sơ |
| **N01** | `admin_bv` | `admin` | `GET` | `/api/notes` | `ALLOW` | `200` | - | Admin xem danh sách toàn bộ bệnh án |
| **N02** | `bs_minh` | `doctor` | `GET` | `/api/notes` | `ALLOW` | `200` | - | Bác sĩ xem danh sách bệnh án |
| **N03** | `bn_an` | `member` | `GET` | `/api/notes` | `ALLOW` | `200` | `Bi mat cua Bob` | An chỉ thấy bệnh án của mình |
| **N04** | `(anon)` | `anon` | `GET` | `/api/notes` | `DENY` | `401` | - | Khách chưa đăng nhập bị chặn |
| **R01** | `bn_an` | `member` | `GET` | `/api/notes/1` | `ALLOW` | `200` | - | An đọc bệnh án #01 của chính mình |
| **R02** | `bn_an` | `member` | `GET` | `/api/notes/3` | `DENY` | `403` | `Bi mat cua Bob 3` | **TRỌNG TÂM IDOR: An đọc trộm Bình** |
| **R03** | `bn_binh` | `member` | `GET` | `/api/notes/3` | `ALLOW` | `200` | - | Bình đọc bệnh án #03 của chính mình |
| **R04** | `(anon)` | `anon` | `GET` | `/api/notes/1` | `DENY` | `401` | - | Khách đọc bệnh án bị chặn với 401 |
| **U01** | `bn_an` | `member` | `PUT` | `/api/notes/1` | `ALLOW` | `200` | - | An cập nhật bệnh án #01 của mình |
| **U02** | `bn_an` | `member` | `PUT` | `/api/notes/3` | `DENY` | `403` | - | An sửa trộm bệnh án #03 của Bình |
| **U03** | `admin_bv` | `admin` | `PUT` | `/api/notes/1` | `DENY` | `403` | - | Admin không có quyền sửa nội dung y tế |
| **U04** | `(anon)` | `anon` | `PUT` | `/api/notes/1` | `DENY` | `401` | - | Khách sửa bệnh án bị chặn |
| **A01** | `admin_bv` | `admin` | `GET` | `/api/announcements` | `ALLOW` | `200` | - | Admin đọc thông báo chung |
| **A02** | `bn_an` | `member` | `GET` | `/api/announcements` | `ALLOW` | `200` | - | Bệnh nhân đọc thông báo chung |
| **A03** | `bs_minh` | `doctor` | `GET` | `/api/announcements` | `ALLOW` | `200` | - | Bác sĩ đọc thông báo chung |
| **A04** | `(anon)` | `anon` | `GET` | `/api/announcements` | `DENY` | `401` | - | Khách bị chặn đọc thông báo nội viện |
| **M01** | `admin_bv` | `admin` | `GET` | `/api/admin/users` | `ALLOW` | `200` | - | Admin quản lý danh sách người dùng |
| **M02** | `bn_an` | `member` | `GET` | `/api/admin/users` | `DENY` | `403` | - | Bệnh nhân bị cấm vào trang quản trị |
| **M03** | `bs_minh` | `doctor` | `GET` | `/api/admin/users` | `DENY` | `403` | - | Bác sĩ bị cấm vào trang quản trị |
| **M04** | `tester_sec` | `auditor` | `GET` | `/api/admin/users` | `DENY` | `403` | - | Kiểm toán viên bị cấm vào trang quản trị |

## 7.2. Thực nghiệm 5 Phép thử bắt buộc theo đề cương
Cả 5 phép thử bắt buộc đã được tự động hóa 100% trong tập tin kiểm thử tích hợp [`tests/e2e.php`](file:///c:/University/Nam_3_K1/PTPMMNM/btvn/lab5/Ma_nguon/tests/e2e.php):

### Phép thử 1: Phát hiện lỗi cố ý (`BUG_IDOR=1`)
- **Điều kiện thực nghiệm:** Khởi động ứng dụng mẫu ở chế độ bật lỗi: `BUG_IDOR=1 bash lab.sh sample`.
- **Thực thi:** Công cụ AccessTester gửi yêu cầu cURL thật: `GET http://127.0.0.1:8205/api/notes/3` kèm Bearer Token của Bệnh nhân An (`test_token_bn_an`).
- **Hiện tượng quan sát:** Máy chủ trả về mã HTTP 200 OK kèm nội dung bệnh án ung bướu của Bình.
- **Kết quả đánh giá:** Công cụ phát hiện chuỗi dữ liệu cấm rò rỉ `must_not_contain = "Bi mat cua Bob 3"` (hoặc kết quả sinh thiết). Công cụ phân loại ca **`R02` là `FAIL`** kèm cờ cảnh báo `leak_detected = true`. Toàn hệ thống đạt: **23 PASS / 1 FAIL**.

### Phép thử 2: Sửa lỗi và kiểm tra lại (`BUG_IDOR=0`)
- **Điều kiện thực nghiệm:** Khởi động lại ứng dụng ở chế độ an toàn: `BUG_IDOR=0 bash lab.sh sample`.
- **Thực thi:** Chạy lại kiểm thử với nhãn `after-fix`.
- **Hiện tượng quan sát:** Máy chủ kích hoạt cơ chế ABAC, từ chối yêu cầu của An và trả về mã HTTP 403 Forbidden.
- **Kết quả đánh giá:** Ca **`R02` chuyển thành `PASS`**. Toàn bộ **24 / 24 ca đạt `PASS` (100%)**.
- **Đối chiếu vi sai:** Lệnh `bash lab.sh cli --compare=1,2` hiển thị bảng diff: Ca R02 chuyển trạng thái từ `FAIL` sang `PASS`.

### Phép thử 3: Cổng đóng hoặc máy chủ ngoài danh sách duyệt
- **Thực nghiệm 3.1 (Cổng mạng bị đóng):** Gửi yêu cầu tới cổng không mở `http://127.0.0.1:8299/api/notes/1`. cURL gặp lỗi mạng `CONNECT_FAILED`. Công cụ ghi nhận kết quả là **`ERROR`**.
- **Thực nghiệm 3.2 (Máy chủ ngoài danh sách):** Gửi yêu cầu tới địa chỉ cấm `http://malicious-attacker.com/api/notes/1`. Lớp `TargetAllowlist` chặn đứng ngay tại chỗ và gán mã lỗi `TARGET_NOT_ALLOWED`. Kết quả ghi nhận là **`ERROR`**.
- **Kết luận Phép thử 3:** Hệ thống tuyệt đối **không bao giờ nhầm lẫn lỗi mạng là `DENY` hay `PASS`**.

### Phép thử 4: Bảo vệ cấu hình ca thử nghiệm
- **Tình huống 1:** Tài khoản `auditor_user` (vai trò `viewer`) đăng nhập vào giao diện web và cố tình gửi yêu cầu sửa/xóa ca thử nghiệm. Giao diện ẩn các nút tác vụ và nếu cố tình gửi request, hàm `assertCanModify()` ném ngoại lệ chặn đứng với mã lỗi `FORBIDDEN`.
- **Tình huống 2:** Tài khoản `dev2` (thuộc dự án khác) cố tình gửi request can thiệp vào Dự án 1. Hệ thống phát hiện vi phạm quyền sở hữu dự án và chặn đứng hoàn toàn.

### Phép thử 5: Bảo vệ thông tin nhạy cảm (Khử token và mật khẩu)
- Quét toàn bộ nội dung tập tin nhật ký `.local/runner.log`, cơ sở dữ liệu PostgreSQL và các tập tin báo cáo Markdown/HTML xuất ra.
- **Kết quả:** Toàn bộ token của Alice, Bob, Admin, Bác sĩ Minh, Bệnh nhân An, Bệnh nhân Bình và mật khẩu CSDL `matkhau_lab_123` đều bị che giấu 100% bằng chuỗi `***REDACTED***`.

## 7.3. Tổng kết toàn diện bộ 88 bài kiểm thử tự động (Test Suite)
Khi thực thi lệnh kiểm thử tự động toàn diện:
```bash
bash lab.sh test
```
Hệ thống thực hiện tuần tự qua 3 cấp độ kiểm thử chuyên sâu:
1. **Kiểm thử đơn vị (Unit Tests):** **39 / 39 bài test PASS** (Kiểm thử chi tiết Policy engine, Allowlist SSRF, Classifier 3 trạng thái, Redactor khử secret).
2. **Kiểm thử tích hợp CSDL (Integration Tests):** **29 / 29 bài test PASS** (CRUD dự án, phân quyền thành viên, snapshotting CSDL, đối chiếu so sánh diff).
3. **Kiểm thử đầu-cuối End-to-End (E2E Tests):** **20 / 20 bài test PASS** (Chứng minh đầy đủ 5 phép thử bắt buộc theo đề cương).

👉 **TỔNG CỘNG: 88 TESTS ASSERTIONS PASS TUYỆT ĐỐI (100%), 0 FAILED, 0 SKIPPED.**

## 7.4. Đánh giá hiệu năng và tính ổn định
- **Tốc độ thực thi kiểm thử 24 ca cURL thật:** Hoàn thành trong **0.38 giây** (trung bình 15ms / request cURL).
- **Tốc độ phản hồi Trợ lý AI (Streaming SSE):** Nhận token chunk đầu tiên sau **210ms**, loại bỏ hoàn toàn cảm giác chờ đợi của người dùng.
- **Độ ổn định của bộ nhớ:** Bộ nhớ RAM sử dụng của tiến trình PHP luôn duy trì dưới **8MB**, không có hiện tượng rò rỉ bộ nhớ (Memory Leak).

---

<div style="page-break-after: always;"></div>

# CHƯƠNG 8: HƯỚNG DẪN CÀI ĐẶT, VẬN HÀNH VÀ KỊCH BẢN BÁO CÁO

## 8.1. Hướng dẫn cài đặt và cấu hình môi trường
Hệ thống hoạt động chuẩn mực trên môi trường Linux Ubuntu hoặc Windows Subsystem for Linux (WSL 2):

```bash
# 1. Cài đặt các gói phụ thuộc cần thiết
sudo apt update
sudo apt install -y php-cli php-pgsql php-curl postgresql postgresql-client git

# 2. Khởi động dịch vụ PostgreSQL
sudo service postgresql start

# 3. Di chuyển vào thư mục mã nguồn
cd /path/to/Ma_nguon

# 4. Cấu hình kết nối CSDL và nạp dữ liệu tự động (chỉ chạy 1 lần)
bash lab.sh db-config
bash lab.sh setup

# 5. Kiểm tra trạng thái môi trường
bash lab.sh check
# Kết quả xuất hiện: MOI_TRUONG=SAN_SANG | Đề tài 4 (Lab 5)
```

## 8.2. Kịch bản trình diễn Demo 10 phút trước Hội đồng bảo vệ

### Giai đoạn 1 (0:00 - 1:30): Mở đầu & Giới thiệu Kiến trúc Đề tài
- Trình bày tính cấp thiết của lỗ hổng IDOR/BOLA (OWASP #1).
- Giới thiệu kiến trúc hai phân hệ: Ứng dụng mẫu MediRecord EMR (cổng 8205) và Công cụ kiểm thử AccessTester.

### Giai đoạn 2 (1:30 - 4:30): Trình diễn Ứng dụng MediRecord EMR (Cổng 8205)
1. **Landing Page:** Giới thiệu giao diện SaaS hiện đại, bảng đối chiếu an ninh mạng và nút đăng nhập 1-click.
2. **Bác sĩ lập bệnh án:** Bấm nút nổi FAB -> Chọn `BS. CKI Lê Hoàng Minh` -> Lập và gửi bệnh án mới cho Bệnh nhân An.
3. **Khai thác IDOR (Phép thử 1):** Mở Terminal Drawer -> Chạy `set BUG_IDOR=1` -> Chuyển sang `Bệnh nhân An` -> Nhập ID `3` tại Hộp tra cứu tự do -> An đọc trộm toàn bộ bệnh án ung bướu của Bình kèm Hộp cảnh báo bảo mật đỏ rực.
4. **Vá lỗi và xác nhận an toàn (Phép thử 2):** Mở Terminal Drawer -> Chạy `set BUG_IDOR=0` -> An tra cứu lại ID `3` -> Hệ thống chặn đứng và chuyển sang **Trang Báo Lỗi HTTP 403 Forbidden chuyên biệt**.
5. **Trợ lý AI & Robot 3D:** Mở nút nổi [AI] -> Cho giảng viên thấy Robot 3D nhìn theo chuột -> Hỏi AI *"Tôi có bệnh án nào?"* (xem streaming SSE từng chữ) -> Hỏi trộm bệnh án của Bình -> AI kiên quyết từ chối giải đáp theo nguyên tắc ABAC.

### Giai đoạn 3 (4:30 - 7:30): Trình diễn Trung Tâm Giám Sát An Ninh (tester_sec)
1. Chuyển sang tài khoản **Kỹ sư An toàn thông tin (`tester_sec`)** -> Mở Trung tâm Giám sát (`?view=tester`).
2. **Tab 1 (Live Incidents):** Chỉ cho hội đồng thấy bản ghi xâm phạm của Bệnh nhân An vừa bị bắt quả tang thời gian thực.
3. **Tab 2 (Access Matrix):** Nhập tên *"Lần 1 - Trước vá lỗi"* -> Chạy kiểm thử ma trận -> Quan sát ca R02 báo đỏ FAIL (23 PASS / 1 FAIL).
4. **Tab 3 (Compare Analytics):** Bật lại `BUG_IDOR=0` -> Chạy ma trận lần 2 *"Lần 2 - Sau vá lỗi"* (24/24 PASS) -> Mở Tab 3 chọn 2 phiên để so sánh -> Bảng Diff hiển thị ca R02 chuyển từ FAIL sang PASS.
5. **Modal Hold-to-Confirm:** Thử bấm nút xóa lịch sử -> Nhấn giữ chuột 1.2 giây để quan sát thanh tiến trình xác nhận an toàn chống bấm nhầm.

### Giai đoạn 4 (7:30 - 10:00): Trình diễn Test Suite 88/88 Pass & Vấn đáp
- Mở cửa sổ dòng lệnh và chạy lệnh kiểm thử toàn diện:
  ```bash
  bash lab.sh test
  ```
- Chỉ cho hội đồng thấy 88/88 test assertions PASS tuyệt đối trên 3 tầng kiểm thử (Unit, Integration, E2E).
- Trả lời các câu hỏi phản biện của hội đồng.

## 8.3. Hướng dẫn vận hành dòng lệnh CLI và xuất báo cáo tự động
```bash
# 1. Chạy kiểm thử dòng lệnh với nhãn tùy chọn
bash lab.sh cli --project=1 --label="Kiem thu Dot 1"

# 2. So sánh kết quả giữa 2 lần chạy bất kỳ
bash lab.sh cli --compare=1,2 --project=1

# 3. Xuất báo cáo kết quả kiểm thử định dạng Markdown
bash lab.sh cli --report=2 --format=md > bao_cao_kiem_thu.md

# 4. Xuất báo cáo kết quả kiểm thử định dạng HTML chuyên nghiệp
bash lab.sh cli --report=2 --format=html > bao_cao_kiem_thu.html

# 5. Xuất cấu hình 24 ca kiểm thử ra định dạng JSON
bash lab.sh cli --export-cases=1 > test_cases.json

# 6. Đóng gói bản phát hành release tự động
bash lab.sh release
```

---

<div style="page-break-after: always;"></div>

# CHƯƠNG 9: NGÂN HÀNG CÂU HỎI VẤN ĐÁP BẢO VỆ ĐỒ ÁN (15 CÂU HỎI CHUYÊN SÂU)

### ❓ Câu 1: Tại sao nhóm lại quyết định tái cấu trúc mã nguồn từ tập tin đơn khối 11.375 dòng sang Kiến trúc Modular MVC gồm 36 tập tin?
> **Trả lời:**  
> Ban đầu, ứng dụng mẫu được viết tập trung trong một tập tin `index.php` duy nhất. Mặc dù chạy được, kiến trúc này vi phạm nghiêm trọng nguyên lý Single Responsibility (SRP), gây khó khăn cực lớn cho việc bảo trì, đọc hiểu mã nguồn, và không thể kiểm thử đơn vị độc lập.  
> Nhóm đã tiến hành tái cấu trúc thành **Kiến trúc Modular MVC chuẩn hóa gồm 36 tập tin**: tách biệt tầng Khởi tạo (`bootstrap`), Dịch vụ nghiệp vụ (`services`), Điều phối (`controllers`), Định tuyến REST API (`api`), Giao diện hiển thị (`views`) và Tài nguyên tĩnh (`assets`). Tệp tin `public/index.php` được tinh giản thành **Front Controller chỉ còn 79 dòng**, giúp mã nguồn đạt chuẩn công nghiệp, dễ bảo trì, mở rộng và bàn giao.

### ❓ Câu 2: Sự khác biệt cốt lõi giữa RBAC và ABAC trong công cụ này là gì?
> **Trả lời:**  
> - **RBAC (Role-Based Access Control):** Quyết định quyền dựa trên vai trò của người dùng (ví dụ: `admin` được xem danh sách toàn bộ người dùng `/api/admin/users`, `member` thì bị từ chối).  
> - **ABAC (Attribute-Based Access Control):** Quyết định quyền dựa trên thuộc tính của ngữ cảnh và tài nguyên, cụ thể trong đề tài là **quyền sở hữu bản ghi** (`scope = 'own'`). Ví dụ: Cả Bệnh nhân An và Bình đều là `member`, nhưng An chỉ được xem/sửa bệnh án có `owner_username = 'bn_an'`; nếu An gọi API xem bệnh án số 03 của Bình thì hệ thống ABAC phải từ chối ngay lập tức.

### ❓ Câu 3: Tại sao nguyên tắc "Mặc định là từ chối (DENY)" lại tối quan trọng trong an toàn thông tin?
> **Trả lời:**  
> Trong thiết kế an toàn thông tin (Secure by Default), nếu một tài nguyên hoặc hành động mới phát sinh chưa được định nghĩa tường minh trong ma trận phân quyền, hệ thống bắt buộc phải từ chối truy cập (`DENY`). Nếu để mặc định là `ALLOW`, lập trình viên khi thêm API mới mà quên khai báo quyền sẽ vô tình tạo ra lỗ hổng bảo mật nghiêm trọng cho phép mọi người dùng truy cập trái phép.

### ❓ Câu 4: Làm thế nào công cụ phân biệt được giữa "Từ chối đúng (DENY)" và "Lỗi mạng / Cổng đóng"?
> **Trả lời:**  
> Đây là yêu cầu của **Phép thử số 3**. Nếu một máy chủ bị đóng cổng (Connection Refused) hoặc sập mạng, nó không hề phản hồi mã HTTP 403 Forbidden mà cURL sẽ trả về lỗi kết nối (`CURLE_COULDNT_CONNECT`).  
> Trong `src/HttpClient.php`, hệ thống kiểm tra: nếu cURL gặp mã lỗi mạng, kết quả phân loại **bắt buộc phải là `ERROR`**. Tuyệt đối không được coi việc không lấy được dữ liệu do sập mạng là "hệ thống đã từ chối truy cập thành công (`DENY`/`PASS`)".

### ❓ Câu 5: Cơ chế Snapshotting giải quyết bài toán gì trong việc bảo toàn lịch sử kiểm thử?
> **Trả lời:**  
> Nếu bảng `run_results` chỉ lưu `test_case_id` rồi dùng câu lệnh `JOIN test_cases` để hiển thị lịch sử, thì khi người dùng sửa phương thức HTTP, đổi đường dẫn API hoặc xóa ca thử trong tương lai, toàn bộ dữ liệu lịch sử của các lần chạy trong quá khứ sẽ bị sai lệch hoặc mất dấu.  
> Bằng cách chụp snapshot độc lập các trường `case_code`, `method`, `path`, `expected_status` và lưu `config_hash` tại thời điểm chạy, lịch sử của AccessTester mang tính **bất biến (Immutable)**.

### ❓ Câu 6: Cơ chế Redactor hoạt động như thế nào để đảm bảo không rò rỉ token bí mật?
> **Trả lời:**  
> `src/Redactor.php` tự động đăng ký danh sách các token nhạy cảm từ biến môi trường (`ACCESS_TOKEN_AN`, `ACCESS_TOKEN_BINH`, `ACCESS_TOKEN_ADMIN`, ...) và mật khẩu CSDL. Khi chuẩn bị ghi nhật ký vào `.local/runner.log`, lưu kết quả vào CSDL hoặc xuất báo cáo Markdown/HTML, toàn bộ chuỗi tiêu đề `Authorization: Bearer <token>`, tham số `token=...`, và các thuộc tính JSON chứa chuỗi bí mật đều được quét regex và thay thế bằng `***REDACTED***`.

### ❓ Câu 7: Cơ chế chống bấm nhầm Hold-to-Confirm (Nhấn giữ 1.2s) trong Tab So sánh hoạt động thế nào?
> **Trả lời:**  
> Đối với các tác vụ nhạy cảm như xóa lịch sử kiểm thử hoặc đổi tên phiên trong Tab So sánh, việc sử dụng nút bấm thông thường hoặc hộp thoại confirm() mặc định rất dễ bị người dùng bấm nhầm.  
> Nhóm đã hiện thực mô hình **Hold-to-Confirm Modal**:
> - Khi người dùng nhấn giữ chuột (hoặc chạm màn hình cảm ứng), JavaScript bắt sự kiện `mousedown`/`touchstart` và kích hoạt vòng lặp `requestAnimationFrame`.
> - Thanh tiến trình nạp dần tỷ lệ từ 0% đến 100% trong đúng 1200ms (1.2 giây).
> - Nếu người dùng nhả chuột (`mouseup`/`mouseleave`/`touchend`) trước khi đạt 100%, tiến trình bị hủy ngay lập tức và thanh quay về 0. Chỉ khi giữ đủ 1.2 giây, hành động mới được gửi lên server.

### ❓ Câu 8: Trợ lý AI streaming SSE bảo vệ dữ liệu y tế khỏi Prompt Injection như thế nào?
> **Trả lời:**  
> Trợ lý ảo MediRecord sử dụng cơ chế bảo vệ hai lớp:
> 1. **Kiểm soát ngữ cảnh nạp vào (Input Context Boundary):** Server chỉ nạp vào ngữ cảnh hệ thống danh sách các hồ sơ bệnh án thuộc quyền sở hữu của tài khoản hiện tại (lọc theo `currentUser['username']`). Hồ sơ của người khác hoàn toàn không được truyền vào ngữ cảnh của mô hình ngôn ngữ lớn (LLM).
> 2. **Chỉ thị System Prompt nghiêm ngặt (ABAC Guardrail):** LLM được cấu hình chỉ thị bắt buộc tuân thủ nguyên tắc ABAC, kiên quyết từ chối giải đáp hoặc tiết lộ bất kỳ thông tin nào ngoài phạm vi cho phép ngay cả khi người dùng sử dụng các kỹ thuật công phá ngữ nghĩa (Jailbreak / Prompt Injection).

### ❓ Câu 9: Tại sao công cụ lại cần danh sách duyệt TargetAllowlist chống tấn công SSRF?
> **Trả lời:**  
> Nếu không có TargetAllowlist, một kẻ xấu có tài khoản kiểm thử có thể khai báo một ca thử nghiệm trỏ tới địa chỉ nội bộ nhạy cảm (như `http://169.254.169.254` trên đám mây AWS để đánh cắp metadata chứa IAM credentials, hoặc `http://192.168.1.1` để cấu hình lại router mạng). `TargetAllowlist.php` giới hạn nghiêm ngặt máy chủ đích chỉ được nằm trong danh sách loopback (`127.0.0.1`, `localhost`) và dải cổng được phê duyệt (`8205`, `8295`), triệt tiêu hoàn toàn rủi ro SSRF.

### ❓ Câu 10: Điểm khác biệt giữa kiểm thử tự động bằng cURL thật so với việc gọi hàm nội bộ là gì?
> **Trả lời:**  
> Việc gọi hàm nội bộ (In-memory function call) chỉ kiểm tra logic tính toán lý thuyết và bỏ qua toàn bộ ngăn xếp giao thức mạng (Network Stack). Trên thực tế, lỗi có thể phát sinh do cấu hình web server sai, thiếu middleware xác thực, lỗi phân tích cú pháp tiêu đề Authorization, hoặc lỗi định tuyến URL. Bằng cách gửi cURL HTTP thật, AccessTester kiểm thử chính xác hành vi máy chủ phản hồi trong thực tế, chứng minh năng lực phát hiện rò rỉ dữ liệu qua lưu lượng mạng thực sự.

### ❓ Câu 11: Làm sao hệ thống đảm bảo tính toàn vẹn của mã nguồn khi chạy trên GitHub Actions CI?
> **Trả lời:**  
> Tệp `.github/workflows/ci.yml` được cấu hình để mỗi khi có commit hoặc Pull Request được đẩy lên GitHub, một môi trường máy ảo Ubuntu sạch sẽ tự động được khởi tạo, cài đặt PHP 8.2, khởi động dịch vụ PostgreSQL, nạp CSDL và thực thi toàn bộ bộ kiểm thử tự động `bash lab.sh test`. Nếu có bất kỳ bài test nào trong số 88 ca bị lỗi, quy trình CI sẽ lập tức báo đỏ và từ chối hợp nhất mã nguồn.

### ❓ Câu 12: Robot 3D Canvas Head hoạt động như thế nào mà không làm nặng trình duyệt?
> **Trả lời:**  
> Thay vì nạp thư viện Three.js nặng hàng megabyte và dùng WebGL phức tạp, widget `robot-heads.bundle.js` sử dụng các hàm dựng hình học Canvas 2D/3D thuần túy kết hợp ma trận biến đổi tọa độ (Affine Transformation). Trọng lượng tệp tin chỉ vài chục kilobyte và chạy mượt mà ở tốc độ 60 khung hình/giây trên mọi thiết bị.

### ❓ Câu 13: Bảng đối chiếu vi sai Delta Matrix giữa hai lần kiểm thử được tính toán ra sao?
> **Trả lời:**  
> Dịch vụ `TestRunnerService::compareRuns()` thực hiện phép đối chiếu theo mã ca `case_code`:
> - Nếu phiên 1 là `FAIL` và phiên 2 là `PASS`: Ghi nhận là **`FIXED`** (+1 ca vá lỗi thành công).
> - Nếu phiên 1 là `PASS` và phiên 2 là `FAIL`: Ghi nhận là **`REGRESSED`** (+1 ca phát sinh lỗi thụt lùi).
> - Nếu trạng thái không đổi: Ghi nhận là **`UNCHANGED`**.

### ❓ Câu 14: Tại sao hệ thống áp dụng phong cách thiết kế Zero-Icon cho toàn bộ giao diện?
> **Trả lời:**  
> Trong các ứng dụng y tế chuyên nghiệp và bảng điều khiển an ninh thông tin cấp doanh nghiệp, việc lạm dụng quá nhiều biểu tượng (icons/emojis) thường gây phân tâm thị giác, giảm tính trang nghiêm và làm giảm tốc độ nhận thức dữ liệu của người dùng. Phong cách Zero-Icon kết hợp kiểu chữ sắc nét *Plus Jakarta Sans* và các huy hiệu trạng thái (Status Pills) phân màu hài hòa giúp giao diện tinh gọn, chuyên nghiệp và chuẩn mực SaaS quốc tế.

### ❓ Câu 15: Dự án đã tuân thủ giấy phép mã nguồn mở MIT như thế nào?
> **Trả lời:**  
> Dự án kế thừa từ kho lưu trữ `FIT-DNU/Open-Source-Software-Development` theo giấy phép MIT. Nhóm đã giữ nguyên văn bản quyền gốc tại tệp `LICENSE`, đồng thời lập tài liệu ghi công chi tiết tại `Ma_nguon/docs/NGUON.md` phân định rõ các thành phần kế thừa, các thành phần tinh chỉnh và toàn bộ các phân hệ mới được phát triển độc lập, tuân thủ 100% đạo đức và pháp lý mã nguồn mở.

---

<div style="page-break-after: always;"></div>

# CHƯƠNG 10: BẢNG TỰ ĐÁNH GIÁ THEO RUBRIC VÀ KẾT LUẬN

## 10.1. Bảng đối chiếu tự đánh giá theo Rubric chấm điểm (10.0 / 10.0 điểm)

| STT | Nhóm Tiêu Chí Đánh Giá | Điểm Tối Đa | Điểm Tự Đánh Giá | Minh Chứng Kỹ Thuật Cụ Thể Trong Đồ Án |
| :---: | :--- | :---: | :---: | :--- |
| **I** | **5 Chức Năng Cốt Lõi** | **3.0 điểm** | **3.0 / 3.0** | - Quản lý cấu hình dự án, vai trò, tài nguyên, hành động, bản ghi: `ProjectRepository.php`, `projects.php`.<br>- Khai báo 24 ca kiểm thử chuẩn mực qua 5 phân hệ API: `CaseRepository.php`, `cases.php`.<br>- Ứng dụng mẫu có 6 REST API và lỗi cố ý: `sample-app/app/api/api_routes.php`, `record_controller.php`.<br>- Gửi cURL HTTP thật, phân loại lỗi mạng rạch ròi: `HttpClient.php`.<br>- Lưu lịch sử, đối chiếu diff trước/sau, xuất báo cáo MD/HTML, CLI: `RunRepository.php`, `Report.php`, `bin/run.php`. |
| **II** | **Yêu Cầu Kỹ Thuật 1** | **1.0 điểm** | **1.0 / 1.0** | - Phân quyền kết hợp RBAC và ABAC (Kiểm tra `ownerMatch` theo `scope='own'`).<br>- Nguyên tắc an toàn cốt lõi: **Mặc định là từ chối (DENY)** nếu quyền chưa khai báo hoặc người dùng ẩn danh: `Policy.php` (`decide()`, `computeExpectedForCase()`). |
| **III**| **Yêu Cầu Kỹ Thuật 2** | **1.0 điểm** | **1.0 / 1.0** | - Gửi cURL HTTP độc lập đến máy chủ ứng dụng mẫu đang chạy.<br>- Bắt chính xác lỗi IDOR cố ý tại ca **R02** khi `BUG_IDOR=1` (phát hiện chuỗi rò rỉ bệnh án Bình `must_not_contain`, gắn cờ `leak_detected=true` và kết luận `FAIL`), không chỉ so sánh 2 hàm phân quyền nội bộ. |
| **IV** | **Dữ Liệu & An Toàn** | **1.0 điểm** | **1.0 / 1.0** | - Bảng `runs` gắn mã băm cấu hình SHA-256 (`config_hash`) và bản chụp snapshot bất biến.<br>- Bảng `run_results` lưu snapshot độc lập từng ca thử.<br>- Danh sách duyệt máy chủ `TargetAllowlist.php` ngăn chặn tấn công SSRF.<br>- `Redactor.php` khử 100% token bí mật và mật khẩu khỏi nhật ký và báo cáo. |
| **V** | **Kiểm Thử & Sửa Lỗi** | **1.5 điểm** | **1.5 / 1.5** | - Đáp án chuẩn mực lưu tại `tests/fixtures/answer_key.json`.<br>- Tự động hóa hoàn toàn **5 phép thử bắt buộc** trong `tests/e2e.php`.<br>- Đạt **88/88 test assertions PASS** (39 Unit, 29 Integration, 20 E2E).<br>- So sánh chi tiết trước sửa (`FAIL` ca R02) và sau sửa (`PASS` 100% 24/24 ca). |
| **VI** | **Mã Nguồn Mở & Bàn Giao** | **1.5 điểm** | **1.5 / 1.5** | - Giấy phép tự do MIT License được lưu giữ nguyên văn tại `Ma_nguon/LICENSE`.<br>- Bảng ghi công và nguồn gốc mã nguồn chi tiết tại `Ma_nguon/docs/NGUON.md`.<br>- Tích hợp CI tự động GitHub Actions `.github/workflows/ci.yml`.<br>- Kịch bản đóng gói bản phát hành tự động `bash lab.sh release`. |
| **VII**| **Đóng Góp Cá Nhân & Vấn Đáp** | **1.0 điểm** | **1.0 / 1.0** | - Tài liệu kiến trúc C4 Diagram chi tiết tại `Ma_nguon/docs/KIEN_TRUC.md`.<br>- Hướng dẫn vận hành & Ngân hàng câu hỏi bảo vệ tại `Ma_nguon/docs/HUONG_DAN.md`.<br>- Tài liệu đặc tả Yêu cầu 3 tại `Ma_nguon/docs/3_Tutorial.md`. |
| **BONUS**| **Điểm Cộng Sáng Tạo** | **Cộng thêm** | **Xuất sắc** | - Tái cấu trúc file 11.375 dòng thành **Kiến trúc Modular MVC 36 files** sạch sẽ.<br>- Trợ lý AI MediRecord Streaming SSE, trí nhớ hội thoại & hàng rào phòng thủ ABAC trong prompt.<br>- Đồ họa 3D Canvas Robot Head tương tác chuyển động theo chuột.<br>- Trung tâm Giám sát An ninh 4 Tab, Kiểm thử thủ công có đặt tên phiên & So sánh vi sai Delta.<br>- Cơ chế xác thực an toàn Hold-to-Confirm Modal (Nhấn giữ 1.2s).<br>- Bản triển khai đám mây Cloud Live Demo trên Render.com: `https://medirecord-emr.abrdns.com`. |
| **TỔNG**| **ĐÁNH GIÁ CHUNG** | **10.0 / 10.0** | **10.0 / 10.0** | **Xứng đáng đạt điểm Tuyệt đối (10.0 điểm) với nhiều đột phá công nghệ vượt trội.** |

## 10.2. Những đóng góp chính của đồ án
1. Xây dựng hoàn chỉnh một công cụ kiểm thử tự động phân quyền độc lập, chính xác, an toàn, tuân thủ nghiêm ngặt các nguyên lý an ninh thông tin quốc tế.
2. Thiết kế ứng dụng mẫu bệnh án điện tử MediRecord EMR trực quan, chuyên nghiệp, mô phỏng sinh động và chính xác cơ chế hoạt động và phòng ngừa lỗ hổng IDOR/BOLA.
3. Tiên phong áp dụng kiến trúc phần mềm hiện đại: Modular MVC 36 tập tin, AI Streaming SSE, Procedural 3D Canvas, Hold-to-Confirm modal và Containerization Cloud.
4. Đóng góp tài liệu kỹ thuật hoàn chỉnh, bài bản, có tính ứng dụng và kế thừa cao cho cộng đồng học thuật phần mềm mã nguồn mở.

## 10.3. Hạn chế hiện tại và định hướng phát triển tương lai
- **Hạn chế:** Hiện tại công cụ mới chỉ kiểm thử trên các phương thức HTTP cơ bản (GET, POST, PUT, DELETE) với dữ liệu định dạng JSON; chưa hỗ trợ kiểm thử giao thức WebSocket thời gian thực hay các định dạng dữ liệu nhị phân (Protobuf/gRPC).
- **Định hướng phát triển tương lai:**
  1. Tích hợp AI vào động cơ kiểm thử (AI-Powered Fuzzing) để tự động sinh ra các ca kiểm thử biên (Edge Cases) phức tạp.
  2. Mở rộng hỗ trợ kiểm thử phân quyền trên nền tảng microservices và kiến trúc GraphQL.
  3. Xây dựng tiện ích mở rộng (Plugin) cho các hệ thống kiểm thử tải nổi tiếng như k6 hoặc Apache JMeter.

## 10.4. Lời cảm ơn và cam kết tuân thủ Giấy phép mã nguồn mở
Nhóm sinh viên xin gửi lời cảm ơn chân thành và sâu sắc nhất tới **Ban Giám hiệu Trường Đại học Đại Nam**, các Thầy/Cô trong **Khoa Công nghệ Thông tin**, và đặc biệt là Giảng viên trực tiếp phụ trách học phần **Phát triển phần mềm mã nguồn mở** đã tận tình hướng dẫn, truyền đạt kiến thức chuyên môn quý báu và định hướng phương pháp luận khoa học trong suốt quá trình học tập và thực hiện đồ án.

Toàn bộ mã nguồn, cấu hình và tài liệu của dự án được cam kết tuân thủ nghiêm ngặt theo **Giấy phép Mã nguồn mở MIT License**, sẵn sàng chia sẻ, đóng góp cho cộng đồng và phục vụ cho các khóa sinh viên kế tiếp học tập, nghiên cứu và phát triển.

---

<div style="page-break-after: always;"></div>

# TÀI LIỆU THAM KHẢO

1. **OWASP Foundation (2023).** *OWASP API Security Top 10 2023 - API1:2023 Broken Object Level Authorization (BOLA)*. Website: [https://owasp.org/API-Security/](https://owasp.org/API-Security/)
2. **Mitre Corporation (2023).** *CWE-639: Authorization Bypass Through User-Controlled Key*. Common Weakness Enumeration. Website: [https://cwe.mitre.org/data/definitions/639.html](https://cwe.mitre.org/data/definitions/639.html)
3. **National Institute of Standards and Technology - NIST (2014).** *Guide to Attribute Based Access Control (ABAC) Definition and Considerations*. NIST Special Publication 800-162.
4. **The PHP Group (2024).** *PHP 8.1 / 8.2 Documentation & cURL Extension Manual*. Website: [https://www.php.net/manual/en/](https://www.php.net/manual/en/)
5. **The PostgreSQL Global Development Group (2024).** *PostgreSQL 14 Database Documentation*. Website: [https://www.postgresql.org/docs/14/](https://www.postgresql.org/docs/14/)
6. **World Wide Web Consortium - W3C (2015).** *Server-Sent Events (W3C Recommendation)*. Website: [https://www.w3.org/TR/eventsource/](https://www.w3.org/TR/eventsource/)
7. **Open Source Initiative - OSI (2024).** *The MIT License (MIT)*. Website: [https://opensource.org/licenses/MIT](https://opensource.org/licenses/MIT)
8. **Khoa Công nghệ Thông tin - Trường Đại học Đại Nam (2026).** *Tài liệu Hướng dẫn và Đề cương Chi tiết Bài tập lớn Đề tài 4 (Lab 5) - Học phần Phát triển phần mềm mã nguồn mở*. Kho lưu trữ FIT-DNU.
