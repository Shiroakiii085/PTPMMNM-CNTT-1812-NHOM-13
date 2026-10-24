#!/usr/bin/env bash
# Đề tài 4: Công cụ kiểm thử tự động quyền truy cập Web (AccessTester)
# Phát triển từ nhánh lab5 của FIT-DNU/Open-Source-Software-Development
set -u
root="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)" || exit 2
cd "$root" || exit 2
lesson="5"
port="8105"
sample_port="8205"

usage() {
  printf 'Cách dùng: bash lab.sh check | run | sample | cli | test | setup | release | db-config | sql\n'
  printf '  check:     Kiểm tra môi trường PHP, Git, PostgreSQL và kết nối CSDL\n'
  printf '  setup:     Nạp cấu trúc CSDL và dữ liệu mẫu 24 ca thử nghiệm\n'
  printf '  run:       Khởi chạy Web UI của công cụ tại http://127.0.0.1:%s\n' "$port"
  printf '  sample:    Khởi chạy ứng dụng mẫu tại http://127.0.0.1:%s\n' "$sample_port"
  printf '             (Tùy chọn: BUG_IDOR=1 bash lab.sh sample để kích hoạt lỗi cố ý)\n'
  printf '  cli:       Gọi công cụ dòng lệnh (ví dụ: bash lab.sh cli --project=1 --label=test)\n'
  printf '  test:      Chạy toàn bộ kiểm thử tự động (Unit -> Integration -> E2E)\n'
  printf '  release:   Kiểm tra tính sẵn sàng và đóng gói bản phát hành (.tar.gz)\n'
  printf '  db-config: Thiết lập thông tin kết nối CSDL accesstest_lab\n'
  printf '  sql:       Mở giao diện dòng lệnh psql kết nối tới CSDL\n'
}

need_php() {
  command -v php >/dev/null 2>&1 || { echo 'THIEU_PHP: Cần cài đặt php-cli'; return 2; }
  php -r 'exit(PHP_VERSION_ID >= 80100 ? 0 : 1);' || { echo 'CAN_PHP_8_1_TRO_LEN'; return 2; }
}

check_env() {
  need_php || return 2
  command -v git >/dev/null 2>&1 || { echo 'THIEU_GIT'; return 2; }
  command -v psql >/dev/null 2>&1 || { echo 'THIEU_PSQL: Cần cài đặt postgresql-client'; return 2; }
  php -r 'exit(extension_loaded("pgsql") ? 0 : 1);' || { echo 'THIEU_PGSQL: Cần cài phần mở rộng php-pgsql'; return 2; }
  php tools/db-check.php || return 2
  printf 'MOI_TRUONG=SAN_SANG | Đề tài 4 (Lab %s)\n' "$lesson"
}

setup_db() {
  check_env || exit 2
  echo "Đang nạp cấu trúc và dữ liệu mẫu vào CSDL accesstest_lab..."
  php -r '
    require_once __DIR__ . "/src/Database.php";
    $c = db();
    $schema = file_get_contents(__DIR__ . "/database/schema.sql");
    $seed = file_get_contents(__DIR__ . "/database/seed.sql");
    if (pg_query($c, $schema) === false) { fwrite(STDERR, "Lỗi schema: " . pg_last_error($c) . "\n"); exit(2); }
    if (pg_query($c, $seed) === false) { fwrite(STDERR, "Lỗi seed: " . pg_last_error($c) . "\n"); exit(2); }
    echo "CSDL_DA_NAP_THANH_CONG: Schema và 24 ca thử đã sẵn sàng.\n";
  '
}

package_release() {
  check_env || exit 2
  echo "Chạy kiểm thử trước khi đóng gói..."
  [ -f env.local.sh ] && source env.local.sh
  bash lab.sh test || { echo "Đóng gói thất bại: Kiểm thử chưa đạt!"; exit 1; }
  
  rel_dir="$root/../build"
  mkdir -p "$rel_dir"
  rel_name="accesstest-release-$(date +%Y%m%d).tar.gz"
  echo "Đang tạo bản phát hành: $rel_dir/$rel_name"
  tar -czf "$rel_dir/$rel_name" \
    --exclude='.local' \
    --exclude='*.log' \
    --exclude='env.local.sh' \
    --exclude='.git' \
    -C "$root/.." Ma_nguon README.md LICENSE
  echo "BAN_PHAT_HANH_SAN_SANG: $rel_dir/$rel_name"
}

case "${1:-help}" in
  check) check_env ;;
  setup) setup_db ;;
  run)
    check_env || exit 2
    echo "THU_MUC=$root"
    echo "DIA_CHI=http://127.0.0.1:$port"
    echo 'Khởi chạy Web UI công cụ kiểm thử. Nhấn Ctrl+C để dừng.'
    mkdir -p .local/sessions || exit 2
    [ -f env.local.sh ] && source env.local.sh
    php -d session.name="ACCESSTEST" -d session.save_path="$root/.local/sessions" \
      -S "127.0.0.1:$port" -t public ;;
  sample)
    need_php || exit 2
    echo "THU_MUC=$root/sample-app"
    echo "DIA_CHI=http://127.0.0.1:$sample_port"
    idor_status="${BUG_IDOR:-0}"
    mkdir -p .local
    printf '%s' "$idor_status" > .local/bug_idor.flag
    if [ "$idor_status" = "1" ]; then
      echo "CHẾ ĐỘ: BẬT LỖI CỐ Ý IDOR (BUG_IDOR=1) - Ca R02 sẽ FAIL"
    else
      echo "CHẾ ĐỘ: BẢN ĐÃ SỬA LỖI (BUG_IDOR=0) - 24/24 ca sẽ PASS"
    fi
    echo 'Khởi chạy ứng dụng mẫu (đối tượng kiểm thử). Nhấn Ctrl+C để dừng.'
    BUG_IDOR="$idor_status" php -S "127.0.0.1:$sample_port" -t sample-app/public ;;
  cli)
    need_php || exit 2
    [ -f env.local.sh ] && source env.local.sh
    shift
    php bin/run.php "$@" ;;
  test)
    check_env || exit 2
    echo "BẮT ĐẦU CHẠY TOÀN BỘ KIỂM THỬ..."
    php tests/run.php && php tests/integration.php && php tests/e2e.php ;;
  release) package_release ;;
  db-config)
    need_php || exit 2
    php tools/db-config.php ;;
  sql)
    need_php || exit 2
    php tools/sql.php ;;
  help|-h|--help) usage ;;
  *) usage; exit 2 ;;
esac
