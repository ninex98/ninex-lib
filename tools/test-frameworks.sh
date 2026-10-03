#!/usr/bin/env bash
set -euo pipefail
taskRoot=$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)
cd "$taskRoot"
taskPhp=("${NINEX_PHP_BINARY:-php}")
if [[ "${NINEX_PHP_NO_INI:-0}" == 1 ]]; then taskPhp+=(-n); fi
taskLaravelVendor="${NINEX_VENDOR_DIR:-$taskRoot/tests/environments/laravel/vendor}"
taskThinkVendor="${NINEX_THINK_VENDOR_DIR:-$taskRoot/tests/environments/thinkphp/vendor}"
"${taskPhp[@]}" "$taskLaravelVendor/bin/phpunit" -c "$taskRoot/phpunit.laravel.xml"
"${taskPhp[@]}" "$taskThinkVendor/bin/phpunit" -c "$taskRoot/phpunit.thinkphp.xml"
