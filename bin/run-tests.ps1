# Shield Security - Unified Test Runner
# Supports both native and Docker testing following WooCommerce pattern
# Usage: 
#   .\run-tests.ps1 [unit|integration|all]                    # Native testing
#   .\run-tests.ps1 [unit|integration|all] -Docker            # Docker testing
#   .\run-tests.ps1 [unit|integration|all] -Docker -Package   # Docker package testing

param(
    [Parameter(Position=0)]
    [ValidateSet('unit', 'integration', 'all', '')]
    [string]$TestType = 'all',
    
    [switch]$Docker,
    [switch]$Package,
    [string]$PhpVersion = "8.2",
    [string]$WpVersion = "6.4"
)

$ErrorActionPreference = "Stop"

Write-Host "========================================" -ForegroundColor Cyan
Write-Host "Shield Security - Test Runner" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan

if ($Docker) {
    Write-Host "Mode: Docker (PHP $PhpVersion, WordPress $WpVersion)" -ForegroundColor Blue
    if ($Package) {
        Write-Host "Package Testing: Enabled" -ForegroundColor Yellow
    }
} else {
    Write-Host "Mode: Native" -ForegroundColor Blue
}
Write-Host ""

# Docker testing path
if ($Docker) {
    # Handle package testing
    if ($Package) {
        Write-Host "Building plugin package..." -ForegroundColor Yellow
        $PackageDir = "/tmp/shield-test-package-$(Get-Date -Format 'yyyyMMddHHmmss')"
        
        & ./bin/build-package.sh $PackageDir
        if ($LASTEXITCODE -ne 0) {
            Write-Host "❌ Package build failed" -ForegroundColor Red
            exit 1
        }
        
        # Create .env for package testing
        $EnvContent = @"
PHP_VERSION=$PhpVersion
WP_VERSION=$WpVersion
PLUGIN_SOURCE=$PackageDir
SHIELD_PACKAGE_PATH=/var/www/html/wp-content/plugins/wp-simple-firewall
"@
        $EnvContent | Out-File -FilePath "tests/docker/.env" -Encoding UTF8
        Write-Host "✅ Package built and configured" -ForegroundColor Green
    } else {
        # Create .env for source testing
        $EnvContent = @"
PHP_VERSION=$PhpVersion
WP_VERSION=$WpVersion
"@
        $EnvContent | Out-File -FilePath "tests/docker/.env" -Encoding UTF8
    }
    
    Write-Host "Starting Docker containers..." -ForegroundColor Yellow
    & docker-compose -f tests/docker/docker-compose.yml up -d --build
    
    if ($LASTEXITCODE -ne 0) {
        Write-Host "❌ Failed to start Docker containers" -ForegroundColor Red
        exit 1
    }
    
    Start-Sleep -Seconds 10
    Write-Host "✅ Docker containers ready" -ForegroundColor Green
    
    try {
        # Execute Docker tests using existing composer commands
        switch ($TestType) {
            'unit' {
                Write-Host "Running unit tests in Docker..." -ForegroundColor Yellow
                & docker-compose -f tests/docker/docker-compose.yml exec -T test-runner bash -c 'cd /var/www/html/wp-content/plugins/wp-simple-firewall && composer test:unit'
            }
            'integration' {
                Write-Host "Running integration tests in Docker..." -ForegroundColor Yellow
                & docker-compose -f tests/docker/docker-compose.yml exec -T test-runner bash -c 'cd /var/www/html/wp-content/plugins/wp-simple-firewall && composer test:integration'
            }
            default {
                Write-Host "Running all tests in Docker..." -ForegroundColor Yellow
                & docker-compose -f tests/docker/docker-compose.yml exec -T test-runner bash -c 'cd /var/www/html/wp-content/plugins/wp-simple-firewall && composer test'
            }
        }
        
        $exitCode = $LASTEXITCODE
    }
    finally {
        Write-Host "Cleaning up Docker containers..." -ForegroundColor Yellow
        & docker-compose -f tests/docker/docker-compose.yml down -v --remove-orphans
        
        if (Test-Path "tests/docker/.env") {
            Remove-Item "tests/docker/.env" -Force
        }
        
        if ($Package -and $PackageDir -and (Test-Path $PackageDir)) {
            Remove-Item $PackageDir -Recurse -Force
        }
    }
} else {
    # Native testing path (existing code)
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
}

Write-Host ""
if ($exitCode -eq 0) {
    Write-Host "✅ Tests completed successfully!" -ForegroundColor Green
} else {
    Write-Host "❌ Tests failed!" -ForegroundColor Red
}

Write-Host "========================================" -ForegroundColor Cyan
exit $exitCode