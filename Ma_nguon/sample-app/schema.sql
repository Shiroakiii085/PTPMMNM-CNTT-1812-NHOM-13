-- Schema cho ứng dụng mẫu (đối tượng kiểm thử)
-- Sử dụng schema sample_app trong CSDL accesstest_lab

CREATE SCHEMA IF NOT EXISTS sample_app;

CREATE TABLE IF NOT EXISTS sample_app.users (
  id bigserial PRIMARY KEY,
  username varchar(60) NOT NULL UNIQUE,
  password_hash varchar(255) NOT NULL,
  role varchar(40) NOT NULL,
  full_name varchar(100) NOT NULL
);

CREATE TABLE IF NOT EXISTS sample_app.tokens (
  token varchar(128) PRIMARY KEY,
  username varchar(60) NOT NULL REFERENCES sample_app.users(username) ON DELETE CASCADE,
  created_at timestamptz NOT NULL DEFAULT now()
);

CREATE TABLE IF NOT EXISTS sample_app.notes (
  id bigserial PRIMARY KEY,
  owner_username varchar(60) NOT NULL REFERENCES sample_app.users(username) ON DELETE CASCADE,
  title varchar(200) NOT NULL,
  content text NOT NULL,
  created_at timestamptz NOT NULL DEFAULT now()
);

CREATE TABLE IF NOT EXISTS sample_app.announcements (
  id bigserial PRIMARY KEY,
  title varchar(200) NOT NULL,
  body text NOT NULL,
  created_at timestamptz NOT NULL DEFAULT now()
);

-- Dữ liệu mẫu
INSERT INTO sample_app.users (username, password_hash, role, full_name) VALUES
('alice', '$2y$10$w81Bf90iVbF6YqgIqvC5q.sLhO3r63rO3W.K3QxHw4f8Oq2dCgZfe', 'member', 'Alice Nguyễn'),
('bob', '$2y$10$w81Bf90iVbF6YqgIqvC5q.sLhO3r63rO3W.K3QxHw4f8Oq2dCgZfe', 'member', 'Bob Trần'),
('admin1', '$2y$10$w81Bf90iVbF6YqgIqvC5q.sLhO3r63rO3W.K3QxHw4f8Oq2dCgZfe', 'admin', 'Quản trị viên 1'),
('auditor1', '$2y$10$w81Bf90iVbF6YqgIqvC5q.sLhO3r63rO3W.K3QxHw4f8Oq2dCgZfe', 'auditor', 'Kiểm toán viên 1')
ON CONFLICT (username) DO NOTHING;

INSERT INTO sample_app.tokens (token, username) VALUES
('test_token_alice_123', 'alice'),
('test_token_bob_456', 'bob'),
('test_token_admin_789', 'admin1'),
('test_token_auditor_321', 'auditor1')
ON CONFLICT (token) DO NOTHING;

INSERT INTO sample_app.notes (id, owner_username, title, content) VALUES
(1, 'alice', 'Ghi chú 1 của Alice', 'Bi mat cua Alice 1: Kế hoạch mật'),
(2, 'alice', 'Ghi chú 2 của Alice', 'Lịch họp nhóm nghiên cứu mã nguồn mở'),
(3, 'bob', 'Ghi chú 3 của Bob', 'Bi mat cua Bob 3: Nhật ký bảo mật cá nhân'),
(4, 'bob', 'Ghi chú 4 của Bob', 'Báo cáo chi tiêu dự án cá nhân')
ON CONFLICT (id) DO NOTHING;

INSERT INTO sample_app.announcements (id, title, body) VALUES
(1, 'Thông báo bảo trì hệ thống', 'Hệ thống sẽ bảo trì định kỳ vào 00:00 Chủ nhật. Dữ liệu dùng chung cho mọi thành viên.')
ON CONFLICT (id) DO NOTHING;

SELECT setval('sample_app.notes_id_seq', (SELECT COALESCE(MAX(id), 1) FROM sample_app.notes));
SELECT setval('sample_app.announcements_id_seq', (SELECT COALESCE(MAX(id), 1) FROM sample_app.announcements));
