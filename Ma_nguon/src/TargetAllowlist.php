<?php

declare(strict_types=1);

/**
 * Danh sách đích được phép kiểm thử (yêu cầu an toàn mục 5 của đề tài).
 * Mọi URL phải khớp đồng thời scheme, host và cổng được khai báo trong config/targets.json
 * (hoặc tệp chỉ định bởi biến môi trường ACCESSTEST_TARGETS_FILE).
 */
final class TargetAllowlist
{
    /**
     * @param list<string> $schemes
     * @param list<string> $hosts
     * @param list<int> $ports
     */
    public function __construct(
        private array $schemes,
        private array $hosts,
        private array $ports
    ) {
        $this->schemes = array_values(array_map('strtolower', $schemes));
        $this->hosts = array_values(array_map(fn($h) => strtolower(trim((string)$h, '[]')), $hosts));
        $this->ports = array_values(array_map('intval', $ports));
    }

    public static function defaultPath(): string
    {
        $env = getenv('ACCESSTEST_TARGETS_FILE');
        return ($env !== false && $env !== '') ? $env : __DIR__ . '/../config/targets.json';
    }

    public static function fromConfig(?string $path = null): self
    {
        $path ??= self::defaultPath();
        if (!is_file($path)) {
            // Không có cấu hình => chỉ cho phép cổng ứng dụng mẫu trên loopback
            return new self(['http'], ['127.0.0.1', 'localhost', '::1'], [8205]);
        }
        $data = json_decode((string)file_get_contents($path), true);
        if (!is_array($data)) {
            throw new RuntimeException('TARGETS_CONFIG_INVALID');
        }
        $hosts = $data['allowed_hosts'] ?? [];
        $ports = $data['allowed_ports'] ?? [];
        $schemes = $data['allowed_schemes'] ?? ['http'];
        if (!is_array($hosts) || !is_array($ports) || !is_array($schemes) || $hosts === [] || $ports === []) {
            throw new RuntimeException('TARGETS_CONFIG_INVALID');
        }
        return new self($schemes, $hosts, $ports);
    }

    /**
     * Trả về null nếu URL hợp lệ, ngược lại trả về mã lỗi.
     */
    public function check(string $url): ?string
    {
        $parts = parse_url($url);
        if ($parts === false || !isset($parts['scheme'], $parts['host'])) {
            return 'URL_INVALID';
        }
        if (isset($parts['user']) || isset($parts['pass'])) {
            return 'CREDENTIALS_IN_URL';
        }
        $scheme = strtolower($parts['scheme']);
        if (!in_array($scheme, $this->schemes, true)) {
            return 'SCHEME_NOT_ALLOWED';
        }
        $host = strtolower(trim($parts['host'], '[]'));
        if (!in_array($host, $this->hosts, true)) {
            return 'HOST_NOT_ALLOWED';
        }
        $port = (int)($parts['port'] ?? ($scheme === 'https' ? 443 : 80));
        if (!in_array($port, $this->ports, true)) {
            return 'PORT_NOT_ALLOWED';
        }
        return null;
    }

    public function isAllowed(string $url): bool
    {
        return $this->check($url) === null;
    }

    /** @return array{schemes: list<string>, hosts: list<string>, ports: list<int>} */
    public function toArray(): array
    {
        return ['schemes' => $this->schemes, 'hosts' => $this->hosts, 'ports' => $this->ports];
    }

    public function describe(): string
    {
        return implode(', ', $this->hosts) . ' | cổng ' . implode(', ', $this->ports);
    }
}
