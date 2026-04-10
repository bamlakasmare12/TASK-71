#!/bin/bash
#
# ResearchHub - Test Runner
# Runs Unit, Integration, and Frontend test suites.
# Always runs inside the Docker app container.
#
set -uo pipefail

RED='\033[0;31m'
GREEN='\033[0;32m'
BOLD='\033[1m'
NC='\033[0m'

pass() { echo -e "  ${GREEN}PASS${NC} $1"; }
fail() { echo -e "  ${RED}FAIL${NC} $1"; }

RUN="docker compose exec -T -w /var/www/html app php artisan"

echo ""
echo -e "${BOLD}================================================${NC}"
echo -e "${BOLD}  ResearchHub - Test Runner${NC}"
echo -e "${BOLD}================================================${NC}"

# ─── Verify app container is ready ───
echo ""
echo -e "${BOLD}Checking app container...${NC}"

if ! docker compose ps app --format '{{.State}}' 2>/dev/null | grep -q "running"; then
    fail "App container is not running."
    echo "  Start Docker first:  docker compose up -d"
    exit 1
fi

# Wait for vendor to be installed (entrypoint runs composer install)
WAIT=0
while ! docker compose exec -T -w /var/www/html app test -f vendor/autoload.php 2>/dev/null; do
    if [ $WAIT -ge 120 ]; then
        fail "Timed out waiting for Composer dependencies (120s)"
        exit 1
    fi
    if [ $WAIT -eq 0 ]; then
        echo "  Waiting for Composer dependencies to install..."
    fi
    sleep 5
    WAIT=$((WAIT + 5))
done
pass "App container ready (vendor installed)"

# ─── Unit Tests ───
echo ""
echo -e "${BOLD}[1/3] Unit Tests${NC}"
echo "────────────────────────────────────────"
$RUN migrate:fresh --force --quiet 2>/dev/null
$RUN test --testsuite=Unit --colors=always 2>&1
UNIT_EXIT=$?

# ─── Integration Tests (Feature) ───
echo ""
echo -e "${BOLD}[2/3] Integration Tests${NC}"
echo "────────────────────────────────────────"
$RUN migrate:fresh --force --quiet 2>/dev/null
$RUN test --testsuite=Feature --colors=always 2>&1
FEATURE_EXIT=$?

# ─── Frontend Tests (Livewire Components) ───
echo ""
echo -e "${BOLD}[3/3] Frontend Tests (Livewire)${NC}"
echo "────────────────────────────────────────"
$RUN migrate:fresh --force --quiet 2>/dev/null
$RUN test --testsuite=Feature --filter="LoginTest|CatalogTest|StepUpAuth|BruteForce|PasswordRotation|SingleLogout" --colors=always 2>&1
FRONTEND_EXIT=$?

# ─── Summary ───
echo ""
echo -e "${BOLD}================================================${NC}"
[ $UNIT_EXIT -eq 0 ] && pass "Unit tests" || fail "Unit tests"
[ $FEATURE_EXIT -eq 0 ] && pass "Integration tests" || fail "Integration tests"
[ $FRONTEND_EXIT -eq 0 ] && pass "Frontend tests" || fail "Frontend tests"
echo -e "${BOLD}================================================${NC}"
echo ""

TOTAL_EXIT=$((UNIT_EXIT + FEATURE_EXIT + FRONTEND_EXIT))
[ $TOTAL_EXIT -eq 0 ] && echo -e "${GREEN}All test suites passed.${NC}" || echo -e "${RED}Some suites failed.${NC}"
exit $TOTAL_EXIT
