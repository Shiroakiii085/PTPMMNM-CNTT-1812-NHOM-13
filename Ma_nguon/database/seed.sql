-- Dữ liệu mẫu cho Công cụ kiểm thử (AccessTester) và Ứng dụng mẫu
-- Nạp bằng: bash lab.sh setup   (hoặc trong psql: \i database/seed.sql)
-- Chạy lại nhiều lần an toàn.

-- ==========================================================
-- 0. Người dùng công cụ (đăng nhập Web). Mật khẩu DEMO chung: AccessTest@2026
--    Chỉ dùng trên máy cục bộ; đổi bằng: bash lab.sh user <tên>
-- ==========================================================
INSERT INTO app_users (username, display_name, password_hash) VALUES
('phamle',       'Phạm Lê (trưởng nhóm A)',        '$2y$12$DNJuWODx6CAs/B4.UFqSzurpA1BdqEz6rsPnD3rGDdOnCv7ZIj8Q.'),
('dev1',         'Dev 1 (người viết ca – nhóm A)', '$2y$12$DNJuWODx6CAs/B4.UFqSzurpA1BdqEz6rsPnD3rGDdOnCv7ZIj8Q.'),
('auditor_user', 'Kiểm toán (chỉ xem báo cáo)',    '$2y$12$DNJuWODx6CAs/B4.UFqSzurpA1BdqEz6rsPnD3rGDdOnCv7ZIj8Q.'),
('dev2',         'Dev 2 (trưởng nhóm B)',          '$2y$12$DNJuWODx6CAs/B4.UFqSzurpA1BdqEz6rsPnD3rGDdOnCv7ZIj8Q.')
ON CONFLICT (username) DO NOTHING;

-- ==========================================================
-- 1. Dự án A (mặc định) – kiểm thử Ứng dụng mẫu ghi chú
-- ==========================================================
INSERT INTO projects (id, name, base_url, description)
VALUES (1, 'Hệ thống quản lý ghi chú (Sample App)', 'http://127.0.0.1:8205',
        'Nhóm A: 3 vai trò, 6 API, 24 ca HTTP; có lỗi IDOR cố ý ở GET /api/notes/{id}')
ON CONFLICT (id) DO UPDATE SET description = EXCLUDED.description;

-- Thành viên công cụ: owner / editor / viewer. dev2 (nhóm B) chỉ được XEM dự án A.
INSERT INTO project_members (project_id, username, member_role) VALUES
(1, 'phamle', 'owner'),
(1, 'dev1', 'editor'),
(1, 'auditor_user', 'viewer'),
(1, 'dev2', 'viewer')
ON CONFLICT (project_id, username) DO NOTHING;

-- Vai trò của ứng dụng mẫu
INSERT INTO roles (id, project_id, name) VALUES
(1, 1, 'admin'),
(2, 1, 'member'),
(3, 1, 'auditor')
ON CONFLICT (project_id, name) DO NOTHING;

-- Tài nguyên (owner_scoped = có khái niệm chủ sở hữu)
INSERT INTO resources (id, project_id, name, owner_scoped) VALUES
(1, 1, 'profile', true),
(2, 1, 'notes', true),
(3, 1, 'announcements', false),
(4, 1, 'users', false)
ON CONFLICT (project_id, name) DO NOTHING;

INSERT INTO actions (id, project_id, name) VALUES
(1, 1, 'read'),
(2, 1, 'create'),
(3, 1, 'update'),
(4, 1, 'delete')
ON CONFLICT (project_id, name) DO NOTHING;

-- Ma trận quyền – THIẾU DÒNG = DENY
-- admin  : profile.read(own), notes.read(any), announcements.read(any), users.read(any)  [KHÔNG có notes.update]
-- member : profile.read(own), notes.read(own), notes.create(any), notes.update(own), announcements.read(any)
-- auditor: profile.read(own), notes.read(any), announcements.read(any)
INSERT INTO permissions (role_id, resource_id, action_id, scope) VALUES
(1, 1, 1, 'own'), (1, 2, 1, 'any'), (1, 3, 1, 'any'), (1, 4, 1, 'any'),
(2, 1, 1, 'own'), (2, 2, 1, 'own'), (2, 2, 2, 'any'), (2, 2, 3, 'own'), (2, 3, 1, 'any'),
(3, 1, 1, 'own'), (3, 2, 1, 'any'), (3, 3, 1, 'any')
ON CONFLICT (role_id, resource_id, action_id) DO UPDATE SET scope = EXCLUDED.scope;

-- Tài khoản thử: chỉ lưu TÊN biến môi trường chứa token
INSERT INTO test_accounts (id, project_id, label, role_id, owner_key, credential_env) VALUES
(1, 1, 'alice', 2, 'alice', 'TOKEN_ALICE'),
(2, 1, 'bob', 2, 'bob', 'TOKEN_BOB'),
(3, 1, 'admin1', 1, 'admin1', 'TOKEN_ADMIN'),
(4, 1, 'auditor1', 3, 'auditor1', 'TOKEN_AUDITOR'),
(5, 1, 'anon', NULL, NULL, NULL)
ON CONFLICT (project_id, label) DO NOTHING;

-- Bản ghi và chủ sở hữu (hai tài khoản alice / bob có dữ liệu riêng; announcements dùng chung)
INSERT INTO records (project_id, resource_id, record_key, owner_key, description) VALUES
(1, 1, 'alice', 'alice', 'Hồ sơ của alice'),
(1, 1, 'bob', 'bob', 'Hồ sơ của bob'),
(1, 1, 'admin1', 'admin1', 'Hồ sơ của admin1'),
(1, 1, 'auditor1', 'auditor1', 'Hồ sơ của auditor1'),
(1, 2, '1', 'alice', 'Ghi chú riêng #1 của alice'),
(1, 2, '2', 'alice', 'Ghi chú riêng #2 của alice'),
(1, 2, '3', 'bob', 'Ghi chú riêng #3 của bob'),
(1, 2, '4', 'bob', 'Ghi chú riêng #4 của bob'),
(1, 3, '1', NULL, 'Thông báo dùng chung #1')
ON CONFLICT (project_id, resource_id, record_key) DO UPDATE
  SET owner_key = EXCLUDED.owner_key, description = EXCLUDED.description;

-- Bộ 24 ca HTTP chuẩn (6 API x 4 ca)
INSERT INTO test_cases (project_id, code, account_id, resource_id, action_id, method, path, target_owner, expected, expected_status, must_contain, must_not_contain, request_body) VALUES
-- GET /api/profile
(1, 'P01', 1, 1, 1, 'GET', '/api/profile', 'alice',    'ALLOW', 200, '"username":"alice"', NULL, NULL),
(1, 'P02', 3, 1, 1, 'GET', '/api/profile', 'admin1',   'ALLOW', 200, '"username":"admin1"', NULL, NULL),
(1, 'P03', 4, 1, 1, 'GET', '/api/profile', 'auditor1', 'ALLOW', 200, '"username":"auditor1"', NULL, NULL),
(1, 'P04', 5, 1, 1, 'GET', '/api/profile', NULL,       'DENY',  401, NULL, NULL, NULL),
-- GET /api/notes (danh sách)
(1, 'N01', 1, 2, 1, 'GET', '/api/notes', 'alice',    'ALLOW', 200, NULL, 'Bi mat cua Bob 3', NULL),
(1, 'N02', 3, 2, 1, 'GET', '/api/notes', 'admin1',   'ALLOW', 200, NULL, NULL, NULL),
(1, 'N03', 4, 2, 1, 'GET', '/api/notes', 'auditor1', 'ALLOW', 200, NULL, NULL, NULL),
(1, 'N04', 5, 2, 1, 'GET', '/api/notes', NULL,       'DENY',  NULL, NULL, 'Bi mat', NULL),
-- GET /api/notes/{id}  (R02, R04 phát hiện lỗi IDOR cố ý)
(1, 'R01', 1, 2, 1, 'GET', '/api/notes/1', 'alice', 'ALLOW', 200, 'Bi mat cua Alice 1', NULL, NULL),
(1, 'R02', 1, 2, 1, 'GET', '/api/notes/3', 'bob',   'DENY',  NULL, NULL, 'Bi mat cua Bob 3', NULL),
(1, 'R03', 3, 2, 1, 'GET', '/api/notes/3', 'bob',   'ALLOW', 200, NULL, NULL, NULL),
(1, 'R04', 2, 2, 1, 'GET', '/api/notes/1', 'alice', 'DENY',  NULL, NULL, 'Bi mat cua Alice 1', NULL),
-- PUT /api/notes/{id}  (U03, U04: quyền chưa khai báo phải bị từ chối)
(1, 'U01', 1, 2, 3, 'PUT', '/api/notes/1', 'alice', 'ALLOW', 200, NULL, NULL, '{"title":"Ghi chú 1 của Alice"}'),
(1, 'U02', 1, 2, 3, 'PUT', '/api/notes/3', 'bob',   'DENY',  NULL, NULL, NULL, '{"title":"alice sửa trái phép"}'),
(1, 'U03', 3, 2, 3, 'PUT', '/api/notes/3', 'bob',   'DENY',  NULL, NULL, NULL, '{"title":"admin sửa trái phép"}'),
(1, 'U04', 4, 2, 3, 'PUT', '/api/notes/1', 'alice', 'DENY',  NULL, NULL, NULL, '{"title":"auditor sửa trái phép"}'),
-- GET /api/announcements (dữ liệu dùng chung)
(1, 'A01', 1, 3, 1, 'GET', '/api/announcements', NULL, 'ALLOW', 200, 'announcements', NULL, NULL),
(1, 'A02', 2, 3, 1, 'GET', '/api/announcements', NULL, 'ALLOW', 200, 'announcements', NULL, NULL),
(1, 'A03', 4, 3, 1, 'GET', '/api/announcements', NULL, 'ALLOW', 200, 'announcements', NULL, NULL),
(1, 'A04', 5, 3, 1, 'GET', '/api/announcements', NULL, 'DENY',  401, NULL, NULL, NULL),
-- GET /api/admin/users (M02, M03: quyền chưa khai báo)
(1, 'M01', 3, 4, 1, 'GET', '/api/admin/users', NULL, 'ALLOW', 200, '"users"', 'password', NULL),
(1, 'M02', 1, 4, 1, 'GET', '/api/admin/users', NULL, 'DENY',  403, NULL, NULL, NULL),
(1, 'M03', 4, 4, 1, 'GET', '/api/admin/users', NULL, 'DENY',  403, NULL, NULL, NULL),
(1, 'M04', 5, 4, 1, 'GET', '/api/admin/users', NULL, 'DENY',  401, NULL, NULL, NULL)
ON CONFLICT (project_id, code) DO UPDATE SET
  account_id = EXCLUDED.account_id,
  resource_id = EXCLUDED.resource_id,
  action_id = EXCLUDED.action_id,
  method = EXCLUDED.method,
  path = EXCLUDED.path,
  target_owner = EXCLUDED.target_owner,
  expected = EXCLUDED.expected,
  expected_status = EXCLUDED.expected_status,
  must_contain = EXCLUDED.must_contain,
  must_not_contain = EXCLUDED.must_not_contain,
  request_body = EXCLUDED.request_body;

-- Gắn ca với bản ghi đích (chủ sở hữu lấy từ bảng records)
UPDATE test_cases tc SET record_id = r.id
  FROM records r
 WHERE tc.project_id = 1 AND r.project_id = 1 AND r.resource_id = tc.resource_id
   AND tc.resource_id IN (1, 2)
   AND r.record_key = CASE WHEN tc.resource_id = 2 AND tc.path ~ '^/api/notes/[0-9]+$'
                           THEN substring(tc.path from '[0-9]+$')
                           WHEN tc.resource_id = 1 THEN tc.target_owner END;

-- ==========================================================
-- 2. Dự án B ("nhóm khác") – dùng kiểm tra phép thử 4
-- ==========================================================
INSERT INTO projects (name, base_url, description)
VALUES ('Nhóm B - Kiểm thử thông báo', 'http://127.0.0.1:8205',
        'Dự án của nhóm B. Thành viên nhóm A không được sửa bộ ca của nhóm này.')
ON CONFLICT (name) DO NOTHING;

INSERT INTO project_members (project_id, username, member_role)
SELECT id, 'dev2', 'owner' FROM projects WHERE name = 'Nhóm B - Kiểm thử thông báo'
ON CONFLICT (project_id, username) DO NOTHING;

INSERT INTO roles (project_id, name)
SELECT id, 'member' FROM projects WHERE name = 'Nhóm B - Kiểm thử thông báo'
ON CONFLICT (project_id, name) DO NOTHING;
INSERT INTO resources (project_id, name, owner_scoped)
SELECT id, 'announcements', false FROM projects WHERE name = 'Nhóm B - Kiểm thử thông báo'
ON CONFLICT (project_id, name) DO NOTHING;
INSERT INTO actions (project_id, name)
SELECT id, 'read' FROM projects WHERE name = 'Nhóm B - Kiểm thử thông báo'
ON CONFLICT (project_id, name) DO NOTHING;

INSERT INTO permissions (role_id, resource_id, action_id, scope)
SELECT ro.id, res.id, act.id, 'any'
  FROM projects p
  JOIN roles ro ON ro.project_id = p.id AND ro.name = 'member'
  JOIN resources res ON res.project_id = p.id AND res.name = 'announcements'
  JOIN actions act ON act.project_id = p.id AND act.name = 'read'
 WHERE p.name = 'Nhóm B - Kiểm thử thông báo'
ON CONFLICT (role_id, resource_id, action_id) DO NOTHING;

INSERT INTO test_accounts (project_id, label, role_id, owner_key, credential_env)
SELECT p.id, 'bob', ro.id, 'bob', 'TOKEN_BOB'
  FROM projects p JOIN roles ro ON ro.project_id = p.id AND ro.name = 'member'
 WHERE p.name = 'Nhóm B - Kiểm thử thông báo'
ON CONFLICT (project_id, label) DO NOTHING;
INSERT INTO test_accounts (project_id, label, role_id, owner_key, credential_env)
SELECT id, 'anon', NULL, NULL, NULL FROM projects WHERE name = 'Nhóm B - Kiểm thử thông báo'
ON CONFLICT (project_id, label) DO NOTHING;

INSERT INTO test_cases (project_id, code, account_id, resource_id, action_id, method, path, expected, expected_status)
SELECT p.id, v.code, acc.id, res.id, act.id, 'GET', '/api/announcements', v.expected, v.status
  FROM projects p
  JOIN (VALUES ('B01', 'bob', 'ALLOW', 200), ('B02', 'anon', 'DENY', 401)) AS v(code, label, expected, status) ON true
  JOIN test_accounts acc ON acc.project_id = p.id AND acc.label = v.label
  JOIN resources res ON res.project_id = p.id AND res.name = 'announcements'
  JOIN actions act ON act.project_id = p.id AND act.name = 'read'
 WHERE p.name = 'Nhóm B - Kiểm thử thông báo'
ON CONFLICT (project_id, code) DO NOTHING;

-- Cập nhật sequence sau khi chèn id tường minh
SELECT setval('projects_id_seq', (SELECT COALESCE(MAX(id), 1) FROM projects));
SELECT setval('roles_id_seq', (SELECT COALESCE(MAX(id), 1) FROM roles));
SELECT setval('resources_id_seq', (SELECT COALESCE(MAX(id), 1) FROM resources));
SELECT setval('actions_id_seq', (SELECT COALESCE(MAX(id), 1) FROM actions));
SELECT setval('test_accounts_id_seq', (SELECT COALESCE(MAX(id), 1) FROM test_accounts));
SELECT setval('test_cases_id_seq', (SELECT COALESCE(MAX(id), 1) FROM test_cases));

-- ==========================================================
-- 3. Dữ liệu của Ứng dụng mẫu (sample_app)
--    Token dưới đây là token GIẢ chỉ dùng cho máy cục bộ; công cụ đọc chúng từ env.local.sh
-- ==========================================================
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
ON CONFLICT (id) DO UPDATE SET owner_username = EXCLUDED.owner_username,
  title = EXCLUDED.title, content = EXCLUDED.content;

INSERT INTO sample_app.announcements (id, title, body) VALUES
(1, 'Thông báo bảo trì hệ thống', 'Hệ thống sẽ bảo trì định kỳ vào 00:00 Chủ nhật. Dữ liệu dùng chung cho mọi thành viên.')
ON CONFLICT (id) DO NOTHING;

SELECT setval('sample_app.notes_id_seq', (SELECT COALESCE(MAX(id), 1) FROM sample_app.notes));
SELECT setval('sample_app.announcements_id_seq', (SELECT COALESCE(MAX(id), 1) FROM sample_app.announcements));
