# Unit Tests Only - Fast, no database required
$ErrorActionPreference = "Stop"

Write-Host "========================================" -ForegroundColor Cyan
Write-Host "Shield Security - Unit Tests" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan

# Get PHP path
$PhpPath = "C:\Users\$env:USERNAME\.config\herd\bin\php83\php.exe"

# Check if PHP exists
if (-not (Test-Path $PhpPath)) {
    Write-Host "❌ PHP not found at: $PhpPath" -ForegroundColor Red
    exit 1
}

# Run unit tests
Write-Host "`nRunning unit tests..." -ForegroundColor Yellow
& $PhpPath vendor\bin\phpunit -c phpunit-unit.xml

if ($LASTEXITCODE -eq 0) {
    Write-Host "`n✅ Unit tests passed!" -ForegroundColor Green
} else {
    Write-Host "`n❌ Unit tests failed!" -ForegroundColor Red
    exit $LASTEXITCODE
}