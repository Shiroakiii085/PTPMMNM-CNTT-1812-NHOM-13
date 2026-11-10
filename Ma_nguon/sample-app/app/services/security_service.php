<?php

declare(strict_types=1);

// =============================================================================
// HỆ THỐNG GIÁM SÁT AN NINH & GHI NHẬN SỰ CỐ XÂM PHẠM (SECURITY INCIDENT LOG)
// =============================================================================
function getSecurityIncidents(): array {
    $file = SAMPLE_PUBLIC_DIR . '/../../.local/security_incidents.json';
    $list = [];
    if (file_exists($file)) {
        $content = @file_get_contents($file);
        $list = json_decode((string)$content, true) ?: [];
    }
    if (isset($_SESSION['security_incidents']) && is_array($_SESSION['security_incidents'])) {
        foreach ($_SESSION['security_incidents'] as $item) {
            $list[] = $item;
        }
    }
    
    // Khởi tạo các sự cố mẫu ban đầu nếu hệ thống chưa có nhật ký
    if (empty($list)) {
        $list = [
            [
                'id' => 'INC-20261026-091522-8A1C',
                'time' => '2026-10-26 09:15:22',
                'type' => 'ABAC_PROBE_BLOCKED',
                'severity' => 'WARNING',
                'actor_username' => 'bn_an',
                'actor_name' => 'Bệnh nhân Nguyễn Văn An',
                'actor_role' => 'member',
                'target' => 'Bệnh án số 03 (BN Trần Thị Bình)',
                'description' => 'Phát hiện hành vi thăm dò trái phép: Tài khoản bn_an cố tình mở bệnh án #03 của bệnh nhân khác nhưng đã bị hệ thống phân quyền ABAC chặn đứng kịp thời.',
                'status_code' => 403,
                'cwe' => 'CWE-639 (BOLA/IDOR)'
            ],
            [
                'id' => 'INC-20261026-084011-3F9D',
                'time' => '2026-10-26 08:40:11',
                'type' => 'PRIVILEGE_ESCALATION_BLOCKED',
                'severity' => 'HIGH',
                'actor_username' => 'anon',
                'actor_name' => 'Khách vãng lai (Anonymous)',
                'actor_role' => 'anon',
                'target' => 'Module Quản trị người dùng (/api/admin/users)',
                'description' => 'Cố tình leo thang đặc quyền: Người dùng chưa xác thực cố truy cập trái phép API danh sách nhân sự của Quản trị viên.',
                'status_code' => 401,
                'cwe' => 'CWE-285 (Improper Authorization)'
            ]
        ];
        @mkdir(dirname($file), 0777, true);
        @file_put_contents($file, json_encode($list, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
    
    $unique = [];
    foreach ($list as $inc) {
        if (isset($inc['id'])) {
            $unique[$inc['id']] = $inc;
        }
    }
    uasort($unique, fn($a, $b) => strcmp($b['time'], $a['time']));
    return array_values($unique);
}

function recordSecurityIncident(string $type, string $severity, ?array $actor, string $target, string $description, array $details = []): void {
    $file = SAMPLE_PUBLIC_DIR . '/../../.local/security_incidents.json';
    @mkdir(dirname($file), 0777, true);
    
    $incidents = [];
    if (file_exists($file)) {
        $content = @file_get_contents($file);
        $incidents = json_decode((string)$content, true) ?: [];
    }
    
    $newIncident = [
        'id' => 'INC-' . date('Ymd-His') . '-' . strtoupper(substr(md5(uniqid('', true)), 0, 4)),
        'time' => date('Y-m-d H:i:s'),
        'type' => $type,
        'severity' => $severity, // CRITICAL, HIGH, WARNING, INFO
        'actor_username' => $actor ? $actor['username'] : 'anon',
        'actor_name' => $actor ? $actor['full_name'] : 'Khách vãng lai (Anonymous)',
        'actor_role' => $actor ? $actor['role'] : 'anon',
        'target' => $target,
        'description' => $description,
        'status_code' => $details['status_code'] ?? 200,
        'cwe' => $details['cwe'] ?? 'CWE-639 (BOLA/IDOR)',
        'ip' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
        'uri' => $_SERVER['REQUEST_URI'] ?? '/'
    ];
    
    array_unshift($incidents, $newIncident);
    if (count($incidents) > 100) {
        $incidents = array_slice($incidents, 0, 100);
    }
    @file_put_contents($file, json_encode($incidents, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    
    if (!isset($_SESSION['security_incidents'])) {
        $_SESSION['security_incidents'] = [];
    }
    array_unshift($_SESSION['security_incidents'], $newIncident);
}

