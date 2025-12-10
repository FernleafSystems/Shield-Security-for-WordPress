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
    [string]$WpVersion = "6.4",
    [switch]$DynamicWpVersion
)

$ErrorActionPreference = "Stop"

# Dynamic WordPress version detection
if ($Docker -and ($DynamicWpVersion -or $WpVersion -eq "latest" -or $WpVersion -eq "previous")) {
    Write-Host "Detecting WordPress versions..." -ForegroundColor Cyan
    try {
        $response = Invoke-RestMethod -Uri "https://api.wordpress.org/core/version-check/1.7/" -ErrorAction Stop
        $versions = $response.offers | Select-Object -ExpandProperty version
        
        # Get the latest version
        $detectedLatest = $versions[0]
        
        # Parse version components for previous major
        $latestMajorMinor = $detectedLatest -replace '\.\d+$', ''
        $latestMajor = $latestMajorMinor.Split('.')[0]
        $latestMinor = $latestMajorMinor.Split('.')[1]
        
        # Find the latest version of the previous major release
        $previousMajor = "$latestMajor.$([int]$latestMinor - 1)"
        $detectedPrevious = $versions | Where-Object { $_ -like "$previousMajor.*" } | Select-Object -First 1
        
        if (-not $detectedPrevious) {
            # If previous minor not found, try previous major version
            $previousMajor = "$([int]$latestMajor - 1)"
            $detectedPrevious = $versions | Where-Object { $_ -like "$previousMajor.*" } | Select-Object -First 1
        }
        
        Write-Host "✓ Detected latest WordPress: $detectedLatest" -ForegroundColor Green
        Write-Host "✓ Detected previous major: $detectedPrevious" -ForegroundColor Green
        
        # Apply version based on parameter
        if ($WpVersion -eq "latest") {
            $WpVersion = $detectedLatest
        } elseif ($WpVersion -eq "previous") {
            $WpVersion = $detectedPrevious
        } elseif ($DynamicWpVersion -and $WpVersion -eq "6.4") {
            # Only override default if DynamicWpVersion is specified
            $WpVersion = $detectedLatest
        }
    }
    catch {
        Write-Host "⚠ Could not detect WordPress versions, using default: $WpVersion" -ForegroundColor Yellow
    }
}

Write-Host "========================================" -ForegroundColor Cyan
Write-Host "Shield Security - Test Runner" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan

if ($Docker) {
    Write-Host "Mode: Docker (PHP $PhpVersion, WordPress $WpVersion)" -ForegroundColor Blue
    if ($Package) {
        Write-Host "Package Testing: Enabled" -ForegroundColor Yellow
        Write-Host "ℹ Package will be built and mounted into Docker container" -ForegroundColor Gray
    } else {
        Write-Host "Source Testing: Testing against repository source code" -ForegroundColor Yellow
    }
} else {
    Write-Host "Mode: Native" -ForegroundColor Blue
}
Write-Host ""

# Docker testing path
if ($Docker) {
    # Validate Docker is available
    try {
        & docker --version | Out-Null
        if ($LASTEXITCODE -ne 0) {
            throw "Docker command failed"
        }
    }
    catch {
        Write-Host "❌ Docker is not available or not running" -ForegroundColor Red
        Write-Host "Please ensure Docker Desktop is installed and running" -ForegroundColor Yellow
        exit 1
    }
    
    # Validate docker-compose is available
    try {
        & docker-compose --version | Out-Null
        if ($LASTEXITCODE -ne 0) {
            throw "Docker Compose command failed"
        }
    }
    catch {
        Write-Host "❌ Docker Compose is not available" -ForegroundColor Red
        Write-Host "Please ensure Docker Compose is installed" -ForegroundColor Yellow
        exit 1
    }
    # Handle package testing
    if ($Package) {
        Write-Host "Building plugin package..." -ForegroundColor Yellow
        
        # Use Windows temp directory that Docker Desktop can mount
        $PackageDir = Join-Path $env:TEMP "shield-test-package-$(Get-Date -Format 'yyyyMMddHHmmss')"
        
        # Convert Windows path to Docker Desktop compatible path
        # Docker Desktop on Windows can handle both formats:
        # 1. Native Windows path: C:\Path\To\Package 
        # 2. WSL2 path: /mnt/c/Path/To/Package
        # We'll use the native Windows path as Docker Desktop handles the conversion
        $DockerPackageDir = $PackageDir
        
        Write-Host "Package directory: $PackageDir" -ForegroundColor Gray
        Write-Host "Building package..." -ForegroundColor Gray
        
        $composerArgs = @('package-plugin', '--', "--output=$PackageDir")
        & composer @composerArgs
        if ($LASTEXITCODE -ne 0) {
            Write-Host "❌ Package build failed" -ForegroundColor Red
            exit 1
        }
        
        # Validate package was created successfully
        if (-not (Test-Path $PackageDir)) {
            Write-Host "❌ Package directory was not created: $PackageDir" -ForegroundColor Red
            exit 1
        }
        
        if (-not (Test-Path (Join-Path $PackageDir "icwp-wpsf.php"))) {
            Write-Host "❌ Main plugin file not found in package" -ForegroundColor Red
            exit 1
        }
        
        Write-Host "✅ Package built successfully: $PackageDir" -ForegroundColor Green
        
        # Create .env for package testing
        $EnvContent = @"
PHP_VERSION=$PhpVersion
WP_VERSION=$WpVersion
PLUGIN_SOURCE=$DockerPackageDir
SHIELD_PACKAGE_PATH=/package
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
    
    # Choose appropriate docker-compose files based on package mode
    if ($Package) {
        & docker-compose -f tests/docker/docker-compose.yml -f tests/docker/docker-compose.package.yml up -d --build
    } else {
        & docker-compose -f tests/docker/docker-compose.yml up -d --build
    }

    if ($LASTEXITCODE -ne 0) {
        Write-Host "❌ Failed to start Docker containers" -ForegroundColor Red
        exit 1
    }
    
    Start-Sleep -Seconds 10
    Write-Host "✅ Docker containers ready" -ForegroundColor Green
    
    try {
        # Build docker-compose command arguments
        $ComposeArgs = @('-f', 'tests/docker/docker-compose.yml')
        if ($Package) {
            $ComposeArgs += @('-f', 'tests/docker/docker-compose.package.yml')
        }
        
        # Execute Docker tests using existing composer commands
        switch ($TestType) {
            'unit' {
                Write-Host "Running unit tests in Docker..." -ForegroundColor Yellow
                & docker-compose @ComposeArgs exec -T test-runner bash -c 'cd /var/www/html/wp-content/plugins/wp-simple-firewall && composer test:unit'
            }
            'integration' {
                Write-Host "Running integration tests in Docker..." -ForegroundColor Yellow
                & docker-compose @ComposeArgs exec -T test-runner bash -c 'cd /var/www/html/wp-content/plugins/wp-simple-firewall && composer test:integration'
            }
            default {
                Write-Host "Running all tests in Docker..." -ForegroundColor Yellow
                & docker-compose @ComposeArgs exec -T test-runner bash -c 'cd /var/www/html/wp-content/plugins/wp-simple-firewall && composer test'
            }
        }
        
        $exitCode = $LASTEXITCODE
    }
    finally {
        Write-Host "Cleaning up Docker containers..." -ForegroundColor Yellow
        
        # Use the same compose args for cleanup
        $ComposeArgs = @('-f', 'tests/docker/docker-compose.yml')
        if ($Package) {
            $ComposeArgs += @('-f', 'tests/docker/docker-compose.package.yml')
        }
        
        & docker-compose @ComposeArgs down -v --remove-orphans
        
        if (Test-Path "tests/docker/.env") {
            Remove-Item "tests/docker/.env" -Force
        }
        
        if ($Package -and $PackageDir -and (Test-Path $PackageDir)) {
            Write-Host "Removing package directory: $PackageDir" -ForegroundColor Yellow
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