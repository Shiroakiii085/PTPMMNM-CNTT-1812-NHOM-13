#!/usr/bin/env bash
# Wrapper thuận tiện tại thư mục gốc lab5 gọi vào Ma_nguon/lab.sh
root="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"
exec bash "$root/Ma_nguon/lab.sh" "$@"
