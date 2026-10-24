-- CSDL Đề tài 4: Công cụ kiểm thử tự động quyền truy cập Web (AccessTester)
-- Chạy trên CSDL: accesstest_lab  (bash lab.sh setup  hoặc  \i database/schema.sql)
-- Quy ước: khóa chính bigserial, ràng buộc UNIQUE, CHECK, FOREIGN KEY đặt trong CSDL.
-- Tệp chạy lại nhiều lần an toàn (idempotent): CSDL cũ được nâng cấp ở phần "NÂNG CẤP".

-- ==========================================================
-- 1. Người dùng công cụ (đăng nhập Web) và dự án
-- ==========================================================
CREATE TABLE IF NOT EXISTS app_users (
  id bigserial PRIMARY KEY,
  username varchar(60) NOT NULL UNIQUE,
  display_name varchar(100) NOT NULL,
  password_hash varchar(255) NOT NULL,
  created_at timestamptz NOT NULL DEFAULT now()
);

CREATE TABLE IF NOT EXISTS projects (
  id bigserial PRIMARY KEY,
  name varchar(120) NOT NULL UNIQUE,
  base_url varchar(200) NOT NULL,
  description varchar(300),
  created_at timestamptz NOT NULL DEFAULT now()
);

-- Vai trò trong CÔNG CỤ: owner (quản trị), editor (sửa ca, chạy), viewer (chỉ xem báo cáo)
CREATE TABLE IF NOT EXISTS project_members (
  project_id bigint NOT NULL REFERENCES projects(id) ON DELETE CASCADE,
  username varchar(60) NOT NULL,
  member_role varchar(10) NOT NULL CHECK (member_role IN ('owner','editor','viewer')),
  PRIMARY KEY (project_id, username)
);

-- ==========================================================
-- 2. Mô hình quyền của ỨNG DỤNG ĐƯỢC KIỂM THỬ
-- ==========================================================
CREATE TABLE IF NOT EXISTS roles (
  id bigserial PRIMARY KEY,
  project_id bigint NOT NULL REFERENCES projects(id) ON DELETE CASCADE,
  name varchar(40) NOT NULL,
  UNIQUE (project_id, name)
);

CREATE TABLE IF NOT EXISTS resources (
  id bigserial PRIMARY KEY,
  project_id bigint NOT NULL REFERENCES projects(id) ON DELETE CASCADE,
  name varchar(60) NOT NULL,
  owner_scoped boolean NOT NULL DEFAULT false,
  UNIQUE (project_id, name)
);

CREATE TABLE IF NOT EXISTS actions (
  id bigserial PRIMARY KEY,
  project_id bigint NOT NULL REFERENCES projects(id) ON DELETE CASCADE,
  name varchar(20) NOT NULL CHECK (name IN ('read','create','update','delete')),
  UNIQUE (project_id, name)
);

-- Không có dòng = DENY. scope 'any' = mọi bản ghi; 'own' = chỉ bản ghi của chính mình.
CREATE TABLE IF NOT EXISTS permissions (
  role_id bigint NOT NULL REFERENCES roles(id) ON DELETE CASCADE,
  resource_id bigint NOT NULL REFERENCES resources(id) ON DELETE CASCADE,
  action_id bigint NOT NULL REFERENCES actions(id) ON DELETE CASCADE,
  scope varchar(5) NOT NULL CHECK (scope IN ('any','own')),
  PRIMARY KEY (role_id, resource_id, action_id)
);

-- Tài khoản thử: chỉ lưu TÊN biến môi trường chứa token, không lưu token.
CREATE TABLE IF NOT EXISTS test_accounts (
  id bigserial PRIMARY KEY,
  project_id bigint NOT NULL REFERENCES projects(id) ON DELETE CASCADE,
  label varchar(40) NOT NULL,
  role_id bigint REFERENCES roles(id) ON DELETE SET NULL,
  owner_key varchar(60),
  credential_env varchar(60) CHECK (credential_env IS NULL OR credential_env ~ '^[A-Z][A-Z0-9_]*$'),
  UNIQUE (project_id, label)
);

-- Bản ghi của ứng dụng mẫu và chủ sở hữu (owner_key NULL = dữ liệu dùng chung)
CREATE TABLE IF NOT EXISTS records (
  id bigserial PRIMARY KEY,
  project_id bigint NOT NULL REFERENCES projects(id) ON DELETE CASCADE,
  resource_id bigint NOT NULL REFERENCES resources(id) ON DELETE CASCADE,
  record_key varchar(60) NOT NULL,
  owner_key varchar(60),
  description varchar(200),
  UNIQUE (project_id, resource_id, record_key)
);

-- ==========================================================
-- 3. Ca thử
-- ==========================================================
CREATE TABLE IF NOT EXISTS test_cases (
  id bigserial PRIMARY KEY,
  project_id bigint NOT NULL REFERENCES projects(id) ON DELETE CASCADE,
  code varchar(30) NOT NULL,
  account_id bigint NOT NULL REFERENCES test_accounts(id) ON DELETE CASCADE,
  resource_id bigint NOT NULL REFERENCES resources(id) ON DELETE CASCADE,
  action_id bigint NOT NULL REFERENCES actions(id) ON DELETE CASCADE,
  method varchar(6) NOT NULL CHECK (method IN ('GET','POST','PUT','DELETE')),
  path varchar(200) NOT NULL CHECK (path LIKE '/%'),
  record_id bigint REFERENCES records(id) ON DELETE SET NULL,
  target_owner varchar(60),
  request_body text,
  expected varchar(5) NOT NULL CHECK (expected IN ('ALLOW','DENY')),
  expected_status integer CHECK (expected_status IS NULL OR expected_status BETWEEN 100 AND 599),
  must_contain varchar(200),
  must_not_contain varchar(200),
  updated_by varchar(60),
  updated_at timestamptz DEFAULT now(),
  UNIQUE (project_id, code)
);

-- ==========================================================
-- 4. Lần chạy và kết quả (kết quả lưu SNAPSHOT để không phụ thuộc cấu hình sửa sau)
-- ==========================================================
CREATE TABLE IF NOT EXISTS runs (
  id bigserial PRIMARY KEY,
  project_id bigint NOT NULL REFERENCES projects(id) ON DELETE CASCADE,
  label varchar(60) NOT NULL,
  config_hash varchar(64) NOT NULL,
  source varchar(5) NOT NULL CHECK (source IN ('web','cli')),
  started_by varchar(60),
  target_base_url varchar(200),
  config_snapshot jsonb,
  started_at timestamptz NOT NULL DEFAULT now(),
  finished_at timestamptz
);

CREATE TABLE IF NOT EXISTS run_results (
  id bigserial PRIMARY KEY,
  run_id bigint NOT NULL REFERENCES runs(id) ON DELETE CASCADE,
  test_case_id bigint REFERENCES test_cases(id) ON DELETE SET NULL,
  case_code varchar(30),
  account_label varchar(40),
  role_name varchar(40),
  owner_key varchar(60),
  resource_name varchar(60),
  action_name varchar(20),
  method varchar(6),
  path varchar(200),
  target_owner varchar(60),
  declared_expected varchar(5),
  policy_expected varchar(5),
  expected varchar(5),
  http_status integer,
  outcome varchar(5) NOT NULL CHECK (outcome IN ('PASS','FAIL','ERROR')),
  error_code varchar(40),
  detail varchar(300),
  leak_detected boolean NOT NULL DEFAULT false,
  response_bytes integer,
  duration_ms integer,
  UNIQUE (run_id, test_case_id)
);

-- ==========================================================
-- 5. NÂNG CẤP CSDL tạo bởi phiên bản 1.0.0 (an toàn khi chạy lại)
-- ==========================================================
ALTER TABLE projects     ADD COLUMN IF NOT EXISTS description varchar(300);
ALTER TABLE test_cases   ADD COLUMN IF NOT EXISTS record_id bigint REFERENCES records(id) ON DELETE SET NULL;
ALTER TABLE test_cases   ADD COLUMN IF NOT EXISTS request_body text;
ALTER TABLE test_cases   ADD COLUMN IF NOT EXISTS expected_status integer;
ALTER TABLE test_cases   ADD COLUMN IF NOT EXISTS must_contain varchar(200);
ALTER TABLE test_cases   ADD COLUMN IF NOT EXISTS updated_by varchar(60);
ALTER TABLE test_cases   ADD COLUMN IF NOT EXISTS updated_at timestamptz DEFAULT now();
ALTER TABLE runs         ADD COLUMN IF NOT EXISTS started_by varchar(60);
ALTER TABLE runs         ADD COLUMN IF NOT EXISTS target_base_url varchar(200);
ALTER TABLE runs         ADD COLUMN IF NOT EXISTS config_snapshot jsonb;
ALTER TABLE run_results  ADD COLUMN IF NOT EXISTS case_code varchar(30);
ALTER TABLE run_results  ADD COLUMN IF NOT EXISTS account_label varchar(40);
ALTER TABLE run_results  ADD COLUMN IF NOT EXISTS role_name varchar(40);
ALTER TABLE run_results  ADD COLUMN IF NOT EXISTS owner_key varchar(60);
ALTER TABLE run_results  ADD COLUMN IF NOT EXISTS resource_name varchar(60);
ALTER TABLE run_results  ADD COLUMN IF NOT EXISTS action_name varchar(20);
ALTER TABLE run_results  ADD COLUMN IF NOT EXISTS method varchar(6);
ALTER TABLE run_results  ADD COLUMN IF NOT EXISTS path varchar(200);
ALTER TABLE run_results  ADD COLUMN IF NOT EXISTS target_owner varchar(60);
ALTER TABLE run_results  ADD COLUMN IF NOT EXISTS declared_expected varchar(5);
ALTER TABLE run_results  ADD COLUMN IF NOT EXISTS policy_expected varchar(5);
ALTER TABLE run_results  ADD COLUMN IF NOT EXISTS expected varchar(5);
ALTER TABLE run_results  ADD COLUMN IF NOT EXISTS detail varchar(300);
ALTER TABLE run_results  ADD COLUMN IF NOT EXISTS leak_detected boolean NOT NULL DEFAULT false;
ALTER TABLE run_results  ADD COLUMN IF NOT EXISTS response_bytes integer;

-- Xóa ca thử KHÔNG được xóa lịch sử chạy: đổi khóa ngoại sang SET NULL
ALTER TABLE run_results ALTER COLUMN test_case_id DROP NOT NULL;
ALTER TABLE run_results DROP CONSTRAINT IF EXISTS run_results_test_case_id_fkey;
ALTER TABLE run_results ADD CONSTRAINT run_results_test_case_id_fkey
  FOREIGN KEY (test_case_id) REFERENCES test_cases(id) ON DELETE SET NULL;

-- Điền snapshot cho kết quả cũ (chỉ những dòng còn trống)
UPDATE run_results rr
   SET case_code = tc.code,
       account_label = acc.label,
       role_name = ro.name,
       owner_key = acc.owner_key,
       resource_name = res.name,
       action_name = act.name,
       method = tc.method,
       path = tc.path,
       target_owner = tc.target_owner,
       declared_expected = tc.expected,
       expected = tc.expected
  FROM test_cases tc
  JOIN test_accounts acc ON acc.id = tc.account_id
  LEFT JOIN roles ro ON ro.id = acc.role_id
  JOIN resources res ON res.id = tc.resource_id
  JOIN actions act ON act.id = tc.action_id
 WHERE rr.test_case_id = tc.id AND rr.case_code IS NULL;

CREATE UNIQUE INDEX IF NOT EXISTS run_results_run_code_uq ON run_results (run_id, case_code);
CREATE INDEX IF NOT EXISTS runs_project_idx ON runs (project_id, id DESC);
CREATE INDEX IF NOT EXISTS project_members_user_idx ON project_members (username);

-- ==========================================================
-- 6. Schema của Ứng dụng mẫu (sample_app) trong cùng CSDL
-- ==========================================================
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
