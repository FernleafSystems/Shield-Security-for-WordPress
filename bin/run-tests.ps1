# Shield Security - Simplified Test Runner
# Delegates all test execution to composer following WooCommerce pattern
# Usage: .\run-tests.ps1 [unit|integration|all]

param(
    [Parameter(Position=0)]
    [ValidateSet('unit', 'integration', 'all', '')]
    [string]$TestType = 'all'
)

$ErrorActionPreference = "Stop"

Write-Host "========================================" -ForegroundColor Cyan
Write-Host "Shield Security - Test Runner" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""

# Set up PHP and Composer paths for Windows/Laravel Herd
$PhpPath = "C:\Users\paulg\.config\herd\bin\php83\php.exe"
$ComposerPhar = "C:\Users\paulg\.config\herd\bin\composer.phar"

# Validate PHP exists
if (-not (Test-Path $PhpPath)) {
    Write-Host "❌ PHP not found at: $PhpPath" -ForegroundColor Red
    Write-Host "Please check your Laravel Herd installation" -ForegroundColor Yellow
    exit 1
}

# Validate composer.phar exists
if (-not (Test-Path $ComposerPhar)) {
    Write-Host "❌ Composer not found at: $ComposerPhar" -ForegroundColor Red
    Write-Host "Please check your Laravel Herd installation" -ForegroundColor Yellow
    exit 1
}

# Validate vendor directory exists
if (-not (Test-Path "vendor")) {
    Write-Host "❌ Vendor directory not found" -ForegroundColor Red
    Write-Host "Please run: & `"$PhpPath`" `"$ComposerPhar`" install --no-interaction" -ForegroundColor Yellow
    exit 1
}

# Execute the appropriate test command
switch ($TestType) {
    'unit' {
        Write-Host "Running unit tests only..." -ForegroundColor Yellow
        & $PhpPath $ComposerPhar test:unit --no-interaction
    }
    'integration' {
        Write-Host "Running integration tests only..." -ForegroundColor Yellow
        & $PhpPath $ComposerPhar test:integration --no-interaction
    }
    default {
        Write-Host "Running all tests..." -ForegroundColor Yellow
        & $PhpPath $ComposerPhar test --no-interaction
    }
}

$exitCode = $LASTEXITCODE

Write-Host ""
if ($exitCode -eq 0) {
    Write-Host "✅ Tests completed successfully!" -ForegroundColor Green
} else {
    Write-Host "❌ Tests failed!" -ForegroundColor Red
}

Write-Host "========================================" -ForegroundColor Cyan
exit $exitCode