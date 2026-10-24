<?php

declare(strict_types=1);

require_once __DIR__ . '/helper.php';
require_once __DIR__ . '/../src/Policy.php';
require_once __DIR__ . '/../src/TargetAllowlist.php';
require_once __DIR__ . '/../src/HttpClient.php';
require_once __DIR__ . '/../src/Redactor.php';
require_once __DIR__ . '/../src/Logger.php';

echo "=== KIỂM THỬ ĐƠN VỊ (UNIT TESTS) ===\n";

// 1. Kiểm thử Policy::decide (Yêu cầu kỹ thuật 1: Mặc định DENY, cả role & data owner)
same(Policy::decide(null, 'profile', 'read', 'own', false), 'DENY', 'policy-anon-deny');
same(Policy::decide('member', 'notes', 'update', null, false), 'DENY', 'policy-undeclared-deny');
same(Policy::decide('member', 'notes', 'read', 'own', true), 'ALLOW', 'policy-own-match-allow');
same(Policy::decide('member', 'notes', 'read', 'own', false), 'DENY', 'policy-own-mismatch-deny');
same(Policy::decide('admin', 'notes', 'read', 'any', false), 'ALLOW', 'policy-any-scope-allow');
same(Policy::decide('admin', 'notes', 'update', null, false), 'DENY', 'policy-admin-no-update-deny');
same(Policy::decide('auditor', 'users', 'read', null, false), 'DENY', 'policy-auditor-no-users-deny');

// Tra cứu từ ma trận permissions
$mockPermissions = [
    ['role_name' => 'admin', 'resource_name' => 'notes', 'action_name' => 'read', 'scope' => 'any'],
    ['role_name' => 'member', 'resource_name' => 'notes', 'action_name' => 'read', 'scope' => 'own'],
    ['role_name' => 'member', 'resource_name' => 'notes', 'action_name' => 'update', 'scope' => 'own'],
];

same(Policy::evaluateFromPermissions('admin', 'notes', 'read', false, $mockPermissions), 'ALLOW', 'policy-matrix-admin-read-any');
same(Policy::evaluateFromPermissions('member', 'notes', 'read', true, $mockPermissions), 'ALLOW', 'policy-matrix-member-read-own');
same(Policy::evaluateFromPermissions('member', 'notes', 'read', false, $mockPermissions), 'DENY', 'policy-matrix-member-read-other');
same(Policy::evaluateFromPermissions('admin', 'notes', 'update', false, $mockPermissions), 'DENY', 'policy-matrix-admin-update-undeclared');
same(Policy::evaluateFromPermissions(null, 'notes', 'read', false, $mockPermissions), 'DENY', 'policy-matrix-anon-deny');

// 2. Kiểm thử TargetAllowlist (Yêu cầu an toàn mục 5)
$allowlist = new TargetAllowlist(['http'], ['127.0.0.1', 'localhost'], [8205, 8295]);
same($allowlist->isAllowed('http://127.0.0.1:8205/api/notes'), true, 'allowlist-local-8205-ok');
same($allowlist->isAllowed('http://localhost:8295/api/notes'), true, 'allowlist-localhost-8295-ok');
same($allowlist->isAllowed('http://127.0.0.1:80/api/notes'), false, 'allowlist-unapproved-port-blocked');
same($allowlist->isAllowed('https://127.0.0.1:8205/api/notes'), false, 'allowlist-unapproved-scheme-blocked');
same($allowlist->isAllowed('http://google.com:8205/api'), false, 'allowlist-external-domain-blocked');
same($allowlist->isAllowed('http://user:pass@127.0.0.1:8205/api'), false, 'allowlist-credentials-in-url-blocked');

HttpClient::setAllowlist($allowlist);
same(HttpClient::isHostAllowed('http://127.0.0.1:8205/api'), true, 'httpclient-isHostAllowed-ok');
same(HttpClient::isHostAllowed('http://external.com:8205/api'), false, 'httpclient-isHostAllowed-reject');

// 3. Kiểm thử HttpClient::classify (Phân loại kết quả - Phép thử 3)
// Lỗi mạng, timeout, hoặc host cấm -> luôn là ERROR, không bao giờ coi là từ chối đúng
$errNet = HttpClient::classify('DENY', null, '', null, null, null, 'CONNECT_FAILED');
same($errNet['outcome'], 'ERROR', 'classify-network-err-never-pass');

$errTimeout = HttpClient::classify('DENY', null, '', null, null, null, 'TIMEOUT');
same($errTimeout['outcome'], 'ERROR', 'classify-timeout-err-never-pass');

$err500 = HttpClient::classify('ALLOW', 500, '{"error":"Internal Error"}', null, null, null, null);
same($err500['outcome'], 'ERROR', 'classify-500-is-error');

// Kỳ vọng ALLOW
$allow200 = HttpClient::classify('ALLOW', 200, '{"data":1}', null, null, 200, null);
same($allow200['outcome'], 'PASS', 'classify-allow-200-pass');

$allow403 = HttpClient::classify('ALLOW', 403, '{"error":"FORBIDDEN"}', null, null, null, null);
same($allow403['outcome'], 'FAIL', 'classify-allow-403-fail');

// Kỳ vọng DENY
$deny403 = HttpClient::classify('DENY', 403, '{"error":"FORBIDDEN"}', null, null, null, null);
same($deny403['outcome'], 'PASS', 'classify-deny-403-pass');

$deny401 = HttpClient::classify('DENY', 401, '{"error":"UNAUTHORIZED"}', null, null, null, null);
same($deny401['outcome'], 'PASS', 'classify-deny-401-pass');

$deny200 = HttpClient::classify('DENY', 200, '{"note":{"id":3}}', null, null, null, null);
same($deny200['outcome'], 'FAIL', 'classify-deny-200-fail');

// Kiểm tra rò rỉ nội dung (must_not_contain)
$leakFail = HttpClient::classify('DENY', 403, '{"msg":"Bi mat cua Bob 3"}', null, 'Bi mat cua Bob 3', null, null);
same($leakFail['outcome'], 'FAIL', 'classify-must-not-contain-leaked');
same($leakFail['leak_detected'], true, 'classify-leak-detected-true');

$cleanPass = HttpClient::classify('DENY', 403, '{"msg":"Từ chối truy cập"}', null, 'Bi mat cua Bob 3', null, null);
same($cleanPass['outcome'], 'PASS', 'classify-must-not-contain-clean');

// Kiểm tra chuỗi bắt buộc (must_contain)
$containPass = HttpClient::classify('ALLOW', 200, '{"username":"alice"}', 'username', null, null, null);
same($containPass['outcome'], 'PASS', 'classify-must-contain-found');

$containFail = HttpClient::classify('ALLOW', 200, '{"status":"ok"}', 'username', null, null, null);
same($containFail['outcome'], 'FAIL', 'classify-must-contain-missing');

// 4. Kiểm thử Redactor (Khử thông tin nhạy cảm - Phép thử 5)
Redactor::clearRegisteredSecrets();
Redactor::registerSecret('my_super_secret_dynamic_token_xyz');

$rawLog = "Connecting with my_super_secret_dynamic_token_xyz and Bearer test_token_alice_123";
$cleanLog = Redactor::redactText($rawLog);
same(str_contains($cleanLog, 'my_super_secret_dynamic_token_xyz'), false, 'redactor-dynamic-secret-hidden');
same(str_contains($cleanLog, 'test_token_alice_123'), false, 'redactor-bearer-token-hidden');

$secretJson = '{"username":"admin","password":"secret_password_123","token":"tok_123"}';
$redactedJson = Redactor::redactText($secretJson);
same(str_contains($redactedJson, 'secret_password_123'), false, 'redactor-no-password-leak');
same(str_contains($redactedJson, 'tok_123'), false, 'redactor-no-json-token-leak');

$arrayWithToken = ['user' => 'alice', 'token' => 'abc123secret', 'public_info' => 'ok'];
$cleanArr = Redactor::redactArray($arrayWithToken);
same($cleanArr['token'], '[REDACTED]', 'redactor-array-token-redacted');
same($cleanArr['public_info'], 'ok', 'redactor-array-public-intact');

finish();
