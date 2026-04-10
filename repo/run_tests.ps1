#
# ResearchHub - Test Runner
# Runs Unit, Integration, and Frontend test suites.
# Detects whether PHP is local or needs Docker, then runs accordingly.
#
$ErrorActionPreference = "Continue"

function Pass($msg) { Write-Host "  PASS " -ForegroundColor Green -NoNewline; Write-Host $msg }
function Fail($msg) { Write-Host "  FAIL " -ForegroundColor Red -NoNewline; Write-Host $msg }

# Detect runtime
if ((Get-Command php -ErrorAction SilentlyContinue) -and (Test-Path "backend/artisan")) {
    $run = "php backend/artisan"
} else {
    $state = docker compose ps app --format '{{.State}}' 2>$null
    if ($state -ne "running") {
        Write-Host "No local PHP and Docker app container is not running." -ForegroundColor Red
        Write-Host "  Start Docker first:  docker compose up -d"
        exit 1
    }
    $run = "docker compose exec -T -w /var/www/html app php artisan"
}

Write-Host "`n================================================"
Write-Host "  ResearchHub - Test Runner"
Write-Host "================================================"

# Unit Tests
Write-Host "`n[1/3] Unit Tests"
Write-Host ("─" * 45)
Invoke-Expression "$run migrate:fresh --force --quiet" 2>$null
Invoke-Expression "$run test --testsuite=Unit" 2>&1 | ForEach-Object { Write-Host $_ }
$unitExit = $LASTEXITCODE

# Integration Tests
Write-Host "`n[2/3] Integration Tests"
Write-Host ("─" * 45)
Invoke-Expression "$run migrate:fresh --force --quiet" 2>$null
Invoke-Expression "$run test --testsuite=Feature" 2>&1 | ForEach-Object { Write-Host $_ }
$featureExit = $LASTEXITCODE

# Frontend Tests (Livewire)
Write-Host "`n[3/3] Frontend Tests (Livewire)"
Write-Host ("─" * 45)
Invoke-Expression "$run migrate:fresh --force --quiet" 2>$null
Invoke-Expression "$run test --testsuite=Feature --filter='LoginTest|CatalogTest|StepUpAuth|BruteForce|PasswordRotation|SingleLogout'" 2>&1 | ForEach-Object { Write-Host $_ }
$frontendExit = $LASTEXITCODE

# Summary
Write-Host "`n================================================"
if ($unitExit -eq 0) { Pass "Unit tests" } else { Fail "Unit tests" }
if ($featureExit -eq 0) { Pass "Integration tests" } else { Fail "Integration tests" }
if ($frontendExit -eq 0) { Pass "Frontend tests" } else { Fail "Frontend tests" }
Write-Host "================================================`n"

$total = $unitExit + $featureExit + $frontendExit
if ($total -eq 0) { Write-Host "All test suites passed." -ForegroundColor Green } else { Write-Host "Some suites failed." -ForegroundColor Red }
exit $total
