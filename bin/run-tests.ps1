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

# Validate composer exists
if (-not (Get-Command "composer" -ErrorAction SilentlyContinue)) {
    Write-Host "❌ Composer not found in PATH" -ForegroundColor Red
    Write-Host "Please install Composer: https://getcomposer.org/" -ForegroundColor Yellow
    exit 1
}

# Validate vendor directory exists
if (-not (Test-Path "vendor")) {
    Write-Host "❌ Vendor directory not found" -ForegroundColor Red
    Write-Host "Please run: composer install" -ForegroundColor Yellow
    exit 1
}

# Execute the appropriate test command
switch ($TestType) {
    'unit' {
        Write-Host "Running unit tests only..." -ForegroundColor Yellow
        & composer test:unit
    }
    'integration' {
        Write-Host "Running integration tests only..." -ForegroundColor Yellow
        & composer test:integration
    }
    default {
        Write-Host "Running all tests..." -ForegroundColor Yellow
        & composer test
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