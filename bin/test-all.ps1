# Complete Test Suite - Runs all tests
$ErrorActionPreference = "Stop"

Write-Host "========================================" -ForegroundColor Cyan
Write-Host "Shield Security - Complete Test Suite" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan

$scriptPath = Split-Path -Parent $MyInvocation.MyCommand.Path

# Run unit tests first
Write-Host "`n1. Running Unit Tests..." -ForegroundColor Yellow
Write-Host "------------------------" -ForegroundColor Gray

& "$scriptPath\test-unit.ps1"
$unitResult = $LASTEXITCODE

if ($unitResult -ne 0) {
    Write-Host "`n❌ Unit tests failed. Stopping test suite." -ForegroundColor Red
    exit $unitResult
}

# Run integration tests
Write-Host "`n2. Running Integration Tests..." -ForegroundColor Yellow
Write-Host "------------------------------" -ForegroundColor Gray

& "$scriptPath\test-integration.ps1"
$integrationResult = $LASTEXITCODE

if ($integrationResult -ne 0) {
    Write-Host "`n❌ Integration tests failed." -ForegroundColor Red
    exit $integrationResult
}

# All tests passed
Write-Host "`n========================================" -ForegroundColor Green
Write-Host "✅ All tests passed successfully!" -ForegroundColor Green
Write-Host "========================================" -ForegroundColor Green