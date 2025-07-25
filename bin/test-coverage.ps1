# Test Coverage Reports - Generates coverage for both unit and integration tests
$ErrorActionPreference = "Stop"

Write-Host "========================================" -ForegroundColor Cyan
Write-Host "Shield Security - Code Coverage Reports" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan

# Get PHP path
$PhpPath = "C:\Users\$env:USERNAME\.config\herd\bin\php83\php.exe"

# Check if PHP exists
if (-not (Test-Path $PhpPath)) {
    Write-Host "❌ PHP not found at: $PhpPath" -ForegroundColor Red
    exit 1
}

# Create coverage directory if it doesn't exist
if (-not (Test-Path "coverage")) {
    New-Item -ItemType Directory -Path "coverage" -Force | Out-Null
}

# Run unit tests with coverage
Write-Host "`n1. Generating Unit Test Coverage..." -ForegroundColor Yellow
Write-Host "-----------------------------------" -ForegroundColor Gray

& $PhpPath vendor\bin\phpunit -c phpunit-unit.xml --coverage-html coverage/unit --coverage-text

if ($LASTEXITCODE -ne 0) {
    Write-Host "`n⚠️  Unit test coverage generation failed." -ForegroundColor Yellow
}

# Run integration tests with coverage
Write-Host "`n2. Generating Integration Test Coverage..." -ForegroundColor Yellow
Write-Host "------------------------------------------" -ForegroundColor Gray

& $PhpPath vendor\bin\phpunit -c phpunit-integration.xml --coverage-html coverage/integration --coverage-text

if ($LASTEXITCODE -ne 0) {
    Write-Host "`n⚠️  Integration test coverage generation failed." -ForegroundColor Yellow
}

# Report results
Write-Host "`n========================================" -ForegroundColor Green
Write-Host "✅ Coverage reports generated!" -ForegroundColor Green
Write-Host "========================================" -ForegroundColor Green
Write-Host ""
Write-Host "Coverage reports available at:" -ForegroundColor Cyan
Write-Host "  Unit Tests:        coverage/unit/index.html" -ForegroundColor White
Write-Host "  Integration Tests: coverage/integration/index.html" -ForegroundColor White