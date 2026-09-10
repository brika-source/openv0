#!/usr/bin/env sh
#
# Full test run: fresh database, PHP built-in server, both suites.
#
#   sh tests/run.sh            # uses port 8111
#   PORT=9000 sh tests/run.sh
#
# Exits non-zero if any assertion fails.

set -e

ROOT=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)
PORT=${PORT:-8111}
HOST=127.0.0.1
LOG="$ROOT/storage/logs/test-server.log"

cd "$ROOT"

printf '\033[1mResetting the database…\033[0m\n'
php bin/console.php reset > /dev/null
: > storage/logs/php-error.log

printf '\033[1mStarting the server on %s:%s…\033[0m\n' "$HOST" "$PORT"
php -S "$HOST:$PORT" -t public > "$LOG" 2>&1 &
SERVER_PID=$!

# Stop the server however this script exits.
trap 'kill "$SERVER_PID" 2>/dev/null || true' EXIT INT TERM

# Wait for it to accept connections.
i=0
while [ "$i" -lt 30 ]; do
    if curl -sS -o /dev/null "http://$HOST:$PORT/" 2>/dev/null; then
        break
    fi
    i=$((i + 1))
    sleep 1
done

STATUS=0

printf '\n\033[1m=== Unit suite (tests/unit.php) ===\033[0m\n'
php tests/unit.php || STATUS=1

printf '\n\033[1m=== Security suite (tests/security.php) ===\033[0m\n'
php tests/security.php "http://$HOST:$PORT" || STATUS=1

# The security suite deliberately fills the database with hostile strings and
# takes a decision on a queued case; start the workflow suite from clean data.
printf '\n\033[1mResetting the database between suites…\033[0m\n'
php bin/console.php reset > /dev/null
: > storage/logs/php-error.log

printf '\n\033[1m=== Workflow suite (tests/http.php) ===\033[0m\n'
php tests/http.php "http://$HOST:$PORT" || STATUS=1

printf '\n\033[1m=== Page sweep (tests/sweep.php) ===\033[0m\n'
php tests/sweep.php "http://$HOST:$PORT" || STATUS=1

printf '\n\033[1m=== PHP error log ===\033[0m\n'
if [ -s storage/logs/php-error.log ]; then
    printf '\033[31mstorage/logs/php-error.log is not empty:\033[0m\n'
    cat storage/logs/php-error.log
    STATUS=1
else
    printf '\033[32mEmpty — no warnings, notices or deprecations.\033[0m\n'
fi

exit "$STATUS"
