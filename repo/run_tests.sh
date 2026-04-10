#!/bin/bash
#
# ResearchHub - Test Runner
# Runs Unit, Integration, and Frontend test suites.
# Detects whether PHP is local or needs Docker, then runs accordingly.
#
set -uo pipefail

RED='\033[0;31m'
GREEN='\033[0;32m'
BOLD='\033[1m'
NC='\033[0m'

pass() { echo -e "  ${GREEN}PASS${NC} $1"; }
fail() { echo -e "  ${RED}FAIL${NC} $1"; }

# Detect runtime: local PHP or Docker
if command -v php >/dev/null 2>&1 && [ -f "backend/artisan" ]; then
    RUN="php backend/artisan"
else
    if ! docker compose ps app --format '{{.State}}' 2>/dev/null | grep -q "running"; then
        echo -e "${RED}No local PHP and Docker app container is not running.${NC}"
        echo "  Start Docker first:  docker compose up -d"
        exit 1
    fi
    RUN="docker compose exec -T -w /var/www/html app php artisan"
fi

echo ""
echo -e "${BOLD}================================================${NC}"
echo -e "${BOLD}  ResearchHub - Test Runner${NC}"
echo -e "${BOLD}================================================${NC}"

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
