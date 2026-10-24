-- Schema cho Ứng dụng mẫu Quản lý Bệnh án Điện tử Y tế (MediRecord EMR)
-- Sử dụng schema sample_app trong CSDL accesstest_lab
-- Bối cảnh: Bác sĩ gửi bệnh án cho Bệnh nhân, kiểm thử phân quyền RBAC & ABAC

DROP SCHEMA IF EXISTS sample_app CASCADE;
CREATE SCHEMA sample_app;

-- 1. Bảng người dùng hệ thống y tế
CREATE TABLE IF NOT EXISTS sample_app.users (
  id bigserial PRIMARY KEY,
  username varchar(60) NOT NULL UNIQUE,
  password_hash varchar(255) NOT NULL,
  role varchar(40) NOT NULL,          -- 'admin', 'auditor', 'doctor', 'patient' (member)
  full_name varchar(100) NOT NULL,
  department varchar(100) DEFAULT NULL,
  created_at timestamptz NOT NULL DEFAULT now()
);

-- 2. Bảng mã xác thực Bearer Token
CREATE TABLE IF NOT EXISTS sample_app.tokens (
  token varchar(128) PRIMARY KEY,
  username varchar(60) NOT NULL REFERENCES sample_app.users(username) ON DELETE CASCADE,
  created_at timestamptz NOT NULL DEFAULT now()
);

-- 3. Bảng hồ sơ bệnh án điện tử (Medical Records)
CREATE TABLE IF NOT EXISTS sample_app.notes (
  id bigserial PRIMARY KEY,
  owner_username varchar(60) NOT NULL REFERENCES sample_app.users(username) ON DELETE CASCADE, -- Bệnh nhân sở hữu bệnh án
  doctor_username varchar(60) REFERENCES sample_app.users(username) ON DELETE SET NULL,        -- Bác sĩ khám và lập bệnh án
  title varchar(200) NOT NULL,
  content text NOT NULL,
  department varchar(100) DEFAULT 'Khoa Khám Bệnh',
  diagnosis text DEFAULT NULL,
  created_at timestamptz NOT NULL DEFAULT now()
);

-- 4. Bảng thông báo dùng chung bệnh viện
CREATE TABLE IF NOT EXISTS sample_app.announcements (
  id bigserial PRIMARY KEY,
  title varchar(200) NOT NULL,
  body text NOT NULL,
  created_at timestamptz NOT NULL DEFAULT now()
);

-- Dọn dẹp dữ liệu cũ nếu có
TRUNCATE TABLE sample_app.tokens, sample_app.notes, sample_app.users, sample_app.announcements RESTART IDENTITY CASCADE;

-- 5. Nạp danh sách tài khoản thật theo bối cảnh Y Tế:
-- Mật khẩu mặc định: TestPass@2026 ($2y$10$w81Bf90iVbF6YqgIqvC5q.sLhO3r63rO3W.K3QxHw4f8Oq2dCgZfe)
INSERT INTO sample_app.users (username, password_hash, role, full_name, department) VALUES
('admin_bv',   '$2y$10$w81Bf90iVbF6YqgIqvC5q.sLhO3r63rO3W.K3QxHw4f8Oq2dCgZfe', 'admin',   'Quản trị viên Bệnh viện Bạch Mai', 'Phòng Công Nghệ Thông Tin'),
('tester_sec', '$2y$10$w81Bf90iVbF6YqgIqvC5q.sLhO3r63rO3W.K3QxHw4f8Oq2dCgZfe', 'auditor', 'Kỹ sư Bảo mật Nguyễn Kiểm Thử',  'Ban An Toàn Thông Tin & Kiểm Toán Y Tế'),
('bs_minh',    '$2y$10$w81Bf90iVbF6YqgIqvC5q.sLhO3r63rO3W.K3QxHw4f8Oq2dCgZfe', 'doctor',  'BS. CKI Lê Hoàng Minh',            'Khoa Tim Mạch & Nội Tiết'),
('bn_an',      '$2y$10$w81Bf90iVbF6YqgIqvC5q.sLhO3r63rO3W.K3QxHw4f8Oq2dCgZfe', 'member',  'Bệnh nhân Nguyễn Văn An',          NULL),
('bn_binh',    '$2y$10$w81Bf90iVbF6YqgIqvC5q.sLhO3r63rO3W.K3QxHw4f8Oq2dCgZfe', 'member',  'Bệnh nhân Trần Thị Bình',          NULL),
-- Tài khoản alias để giữ cho các test runner tự động đạt 100% PASS
('alice',      '$2y$10$w81Bf90iVbF6YqgIqvC5q.sLhO3r63rO3W.K3QxHw4f8Oq2dCgZfe', 'member',  'Bệnh nhân Nguyễn Văn An (Alice)',  NULL),
('bob',        '$2y$10$w81Bf90iVbF6YqgIqvC5q.sLhO3r63rO3W.K3QxHw4f8Oq2dCgZfe', 'member',  'Bệnh nhân Trần Thị Bình (Bob)',    NULL),
('admin1',     '$2y$10$w81Bf90iVbF6YqgIqvC5q.sLhO3r63rO3W.K3QxHw4f8Oq2dCgZfe', 'admin',   'Quản trị viên Bệnh viện (Admin1)', 'Phòng CNTT'),
('auditor1',   '$2y$10$w81Bf90iVbF6YqgIqvC5q.sLhO3r63rO3W.K3QxHw4f8Oq2dCgZfe', 'auditor', 'Kiểm toán viên Y Tế (Auditor1)',   'Ban Kiểm Toán');

-- 6. Nạp Tokens xác thực
INSERT INTO sample_app.tokens (token, username) VALUES
-- Token tài khoản y tế thật
('test_token_admin_bv',   'admin_bv'),
('test_token_tester_sec', 'tester_sec'),
('test_token_bs_minh',    'bs_minh'),
('test_token_bn_an',      'bn_an'),
('test_token_bn_binh',    'bn_binh'),
-- Token tương thích ngược cho test runner
('test_token_alice_123',  'bn_an'),
('test_token_bob_456',    'bn_binh'),
('test_token_admin_789',  'admin_bv'),
('test_token_auditor_321','tester_sec');

-- 7. Nạp Hồ sơ bệnh án do Bác sĩ Minh lập và gửi cho bệnh nhân:
INSERT INTO sample_app.notes (id, owner_username, doctor_username, title, content, department, diagnosis) VALUES
(1, 'bn_an',   'bs_minh', 'Bệnh án số 01: Khám chuyên khoa Tim Mạch - BN Nguyễn Văn An', 
 'Bi mat cua Alice 1: Chẩn đoán rối loạn nhịp xoang độ 2. Phác đồ điều trị nội khoa bảo mật do BS Minh chỉ định.', 
 'Khoa Tim Mạch Can Thiệp', 'Rối loạn nhịp xoang, theo dõi thiếu máu cơ tim cục bộ'),

(2, 'bn_an',   'bs_minh', 'Bệnh án số 02: Kết quả xét nghiệm sinh hóa định kỳ - BN Nguyễn Văn An', 
 'Chỉ số đường huyết HbA1c 5.8%, men gan ALT/AST bình thường. Lịch tái khám sau 3 tháng.', 
 'Khoa Khám Bệnh', 'Đường huyết ổn định, huyết áp kiểm soát tốt'),

(3, 'bn_binh', 'bs_minh', 'Bệnh án số 03: Hồ sơ chẩn đoán đặc biệt & Sinh thiết - BN Trần Thị Bình', 
 'Bi mat cua Bob 3: Kết quả sinh thiết giải phẫu bệnh giai đoạn 1. Phác đồ hóa xạ trị bảo mật cá nhân.', 
 'Khoa Ung Bướu & Hóa Trị Liệu', 'Theo dõi u biểu mô tuyến giáp giai đoạn 1, chỉ định điều trị nội khoa chuyên sâu'),

(4, 'bn_binh', 'bs_minh', 'Bệnh án số 04: Sổ theo dõi thuốc điều trị ngoại trú - BN Trần Thị Bình', 
 'Báo cáo chi tiết các đợt phát thuốc bảo hiểm y tế đặc biệt. Đáp ứng thuốc tốt sau chu kỳ 1.', 
 'Khoa Dược Lâm Sàng', 'Phục hồi thể trạng sau chu kỳ điều trị đầu tiên');

-- 8. Nạp Thông báo dùng chung của bệnh viện
INSERT INTO sample_app.announcements (id, title, body) VALUES
(1, 'Thông báo lịch trực cấp cứu và khám bệnh tuần này', 
 'Bệnh viện Bạch Mai duy trì trực cấp cứu 24/7. Các khoa lâm sàng và cận lâm sàng làm việc theo lịch hẹn từ 07:30 đến 17:00 hàng ngày. Dữ liệu dùng chung cho mọi cán bộ y tế và bệnh nhân.');

-- Đặt lại giá trị Sequence
SELECT setval('sample_app.notes_id_seq', (SELECT COALESCE(MAX(id), 1) FROM sample_app.notes));
SELECT setval('sample_app.announcements_id_seq', (SELECT COALESCE(MAX(id), 1) FROM sample_app.announcements));
