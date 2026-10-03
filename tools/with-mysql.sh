#!/usr/bin/env bash
# Start a disposable socket-only MySQL instance, run a command, then shut it down.
set -euo pipefail
if [[ $# -lt 3 || "$2" != '--' ]]; then
  echo 'Usage: tools/with-mysql.sh /path/to/mysql/bin -- command [args...]' >&2
  exit 2
fi
taskMysqlBin="$1"
shift 2
for taskBinary in mysqld mysqladmin; do
  [[ -x "$taskMysqlBin/$taskBinary" ]] || { echo "Missing executable: $taskMysqlBin/$taskBinary" >&2; exit 2; }
done
taskMysqlDir=$(mktemp -d /tmp/ninex-mysql.XXXXXX)
taskMysqlPid=''
cleanup() {
  if [[ -n "$taskMysqlPid" ]]; then
    "$taskMysqlBin/mysqladmin" --no-defaults --protocol=socket --socket="$taskMysqlDir/mysql.sock" --user=root shutdown >/dev/null 2>&1 || kill "$taskMysqlPid" 2>/dev/null || true
    wait "$taskMysqlPid" 2>/dev/null || true
  fi
  rm -rf -- "$taskMysqlDir"
}
trap cleanup EXIT
trap 'exit 130' INT
trap 'exit 143' TERM
"$taskMysqlBin/mysqld" --no-defaults --initialize-insecure --datadir="$taskMysqlDir/data" --log-error="$taskMysqlDir/initialize.log" || { cat "$taskMysqlDir/initialize.log" >&2; exit 1; }
"$taskMysqlBin/mysqld" --no-defaults --datadir="$taskMysqlDir/data" --socket="$taskMysqlDir/mysql.sock" --pid-file="$taskMysqlDir/mysql.pid" --skip-networking --mysqlx=0 --log-error="$taskMysqlDir/server.log" &
taskMysqlPid=$!
taskReady=0
for ((taskAttempt=0; taskAttempt<120; taskAttempt++)); do
  if "$taskMysqlBin/mysqladmin" --no-defaults --protocol=socket --socket="$taskMysqlDir/mysql.sock" --user=root ping >/dev/null 2>&1; then taskReady=1; break; fi
  if ! kill -0 "$taskMysqlPid" 2>/dev/null; then break; fi
  sleep 0.25
done
if [[ "$taskReady" != 1 ]]; then cat "$taskMysqlDir/server.log" >&2; exit 1; fi
export NINEX_TEST_DB_DRIVER=mysql
export NINEX_TEST_MYSQL_SOCKET="$taskMysqlDir/mysql.sock"
export NINEX_TEST_MYSQL_USER=root
export NINEX_TEST_MYSQL_PASSWORD=''
"$taskMysqlBin/mysqld" --no-defaults --version
"$@"
