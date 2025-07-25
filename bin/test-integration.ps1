# Integration Tests - Requires WordPress test environment
$ErrorActionPreference = "Stop"

Write-Host "========================================" -ForegroundColor Cyan
Write-Host "Shield Security - Integration Tests" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan

# Check for WordPress test environment
if (-not (Test-Path "C:\tmp\wordpress-tests-lib")) {
    Write-Host "❌ WordPress test environment not found!" -ForegroundColor Red
    Write-Host "Please set up WordPress test environment first." -ForegroundColor Yellow
    Write-Host "You can use bin\install-wp-tests.ps1 to set it up." -ForegroundColor Yellow
    exit 1
}

# Get PHP path
$PhpPath = "C:\Users\$env:USERNAME\.config\herd\bin\php83\php.exe"

# Check if PHP exists
if (-not (Test-Path $PhpPath)) {
    Write-Host "❌ PHP not found at: $PhpPath" -ForegroundColor Red
    exit 1
}

# Run integration tests
Write-Host "`nRunning integration tests..." -ForegroundColor Yellow
& $PhpPath vendor\bin\phpunit -c phpunit-integration.xml

if ($LASTEXITCODE -eq 0) {
    Write-Host "`n✅ Integration tests passed!" -ForegroundColor Green
} else {
    Write-Host "`n❌ Integration tests failed!" -ForegroundColor Red
    exit $LASTEXITCODE
}