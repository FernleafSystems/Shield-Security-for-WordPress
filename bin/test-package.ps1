# Shield Security - Package-Based Testing Script
# This script builds the plugin package and runs tests against it,
# matching the CI/CD pipeline behavior for accurate local testing

param(
    [Parameter(Position=0)]
    [ValidateSet('all', 'unit', 'integration', 'build-only')]
    [string]$TestType = 'all',
    
    [switch]$SkipBuild,
    [switch]$Coverage
)

$ErrorActionPreference = "Stop"
$StartTime = Get-Date

# Use central test directory
$ProjectName = "WP_Plugin-Shield"
$TestBase = "D:\Work\Dev\Tests\$ProjectName"
New-Item -ItemType Directory -Path "$TestBase\packages" -Force | Out-Null
New-Item -ItemType Directory -Path "$TestBase\artifacts" -Force | Out-Null
New-Item -ItemType Directory -Path "$TestBase\work" -Force | Out-Null

Write-Host "========================================" -ForegroundColor Cyan
Write-Host "Shield Security - Package-Based Testing" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""

# Configuration
$ProjectRoot = Split-Path -Parent $PSScriptRoot
$PackageDir = "$TestBase\packages\shield-package-$(Get-Date -Format 'yyyyMMdd-HHmmss')"
$PhpPath = "C:\Users\$env:USERNAME\.config\herd\bin\php83\php.exe"
$ComposerPhar = "C:\Users\$env:USERNAME\.config\herd\bin\composer.phar"

# Ensure we're in project root
Set-Location $ProjectRoot

# Function to measure execution time
function Measure-Step {
    param($Name, $ScriptBlock)
    
    $stepStart = Get-Date
    Write-Host "`n$Name..." -ForegroundColor Yellow
    
    try {
        & $ScriptBlock
        $duration = (Get-Date) - $stepStart
        Write-Host "✅ $Name completed in $($duration.TotalSeconds.ToString('0.0'))s" -ForegroundColor Green
    }
    catch {
        Write-Host "❌ $Name failed: $_" -ForegroundColor Red
        throw
    }
}

# Build the package if needed
if (-not $SkipBuild) {
    Measure-Step "Building plugin package" {
        # Clean previous package
        if (Test-Path $PackageDir) {
            Write-Host "  Cleaning previous package..." -NoNewline
            Remove-Item -Path $PackageDir -Recurse -Force
            Write-Host " done"
        }
        
        # Install dependencies
        Write-Host "  Installing root dependencies..." -NoNewline
        & $PhpPath $ComposerPhar install --no-interaction --quiet
        Write-Host " done"
        
        Write-Host "  Installing plugin dependencies..." -NoNewline
        Push-Location "src\lib"
        & $PhpPath $ComposerPhar install --no-interaction --no-dev --quiet
        Pop-Location
        Write-Host " done"
        
        # Build assets
        Write-Host "  Building frontend assets..." -NoNewline
        npm run build --silent
        Write-Host " done"
        
        # Create package structure
        Write-Host "  Creating package structure..." -NoNewline
        New-Item -ItemType Directory -Path $PackageDir -Force | Out-Null
        
        # Copy files
        $files = @(
            "icwp-wpsf.php", "plugin_init.php", "readme.txt", "plugin.json",
            "cl.json", "plugin_autoload.php", "plugin_compatibility.php",
            "uninstall.php", "unsupported.php"
        )
        foreach ($file in $files) {
            Copy-Item $file $PackageDir\
        }
        
        # Copy directories
        $dirs = @("src", "assets", "flags", "languages", "templates")
        foreach ($dir in $dirs) {
            Copy-Item $dir $PackageDir\ -Recurse
        }
        Write-Host " done"
        
        # Run Strauss for prefixing
        Write-Host "  Running dependency prefixing..." -NoNewline
        Push-Location "$PackageDir\src\lib"
        
        # Download Strauss
        Invoke-WebRequest -Uri "https://github.com/BrianHenryIE/strauss/releases/download/0.23.0/strauss.phar" -OutFile "strauss.phar" -UseBasicParsing
        & $PhpPath strauss.phar
        Remove-Item strauss.phar
        
        Pop-Location
        Write-Host " done"
        
        # Clean up duplicate libraries
        Write-Host "  Cleaning duplicate libraries..." -NoNewline
        $duplicates = @(
            "$PackageDir\src\lib\vendor\twig",
            "$PackageDir\src\lib\vendor\monolog",
            "$PackageDir\src\lib\vendor\bin",
            "$PackageDir\src\lib\vendor_prefixed\autoload-files.php"
        )
        foreach ($dup in $duplicates) {
            if (Test-Path $dup) {
                Remove-Item $dup -Recurse -Force -ErrorAction SilentlyContinue
            }
        }
        
        # Clean autoload files
        $autoloadFiles = Get-ChildItem "$PackageDir\src\lib\vendor\composer\*.php"
        foreach ($file in $autoloadFiles) {
            $content = Get-Content $file -Raw
            $content = $content -replace '.*\/twig\/twig\/.*\r?\n', ''
            Set-Content $file $content -NoNewline
        }
        Write-Host " done"
        
        # Copy test files to package for local testing
        Write-Host "  Copying test files..." -NoNewline
        Copy-Item "tests" "$PackageDir\" -Recurse
        Copy-Item "phpunit-unit-package.xml" "$PackageDir\"
        Copy-Item "phpunit-integration-package.xml" "$PackageDir\"
        
        # Copy composer.json for test dependencies
        Copy-Item "composer.json" "$PackageDir\"
        Copy-Item "composer.lock" "$PackageDir\"
        Write-Host " done"
        
        # Verify package
        Write-Host "`n  Package statistics:"
        $fileCount = (Get-ChildItem $PackageDir -Recurse -File).Count
        $size = (Get-ChildItem $PackageDir -Recurse | Measure-Object -Property Length -Sum).Sum / 1MB
        Write-Host "  - Files: $fileCount"
        Write-Host "  - Size: $($size.ToString('0.0')) MB"
        
        if (-not (Test-Path "$PackageDir\src\lib\vendor_prefixed\autoload.php")) {
            throw "Package build failed - prefixed autoloader not found"
        }
    }
}
elseif (-not (Test-Path $PackageDir)) {
    Write-Host "❌ No package found. Run without -SkipBuild to create one." -ForegroundColor Red
    exit 1
}

# Set environment variable for package testing
$env:SHIELD_PACKAGE_PATH = $PackageDir
$env:SHIELD_TEST_PACKAGE = "true"

# Change to package directory for testing
Push-Location $PackageDir

try {
    # Install test dependencies in package
    if ($TestType -ne 'build-only') {
        Measure-Step "Installing test dependencies" {
            & $PhpPath $ComposerPhar install --no-interaction --quiet
        }
    }
    
    # Run tests based on type
    switch ($TestType) {
        'build-only' {
            Write-Host "`n✅ Package built successfully!" -ForegroundColor Green
            Write-Host "Package location: $PackageDir" -ForegroundColor Cyan
        }
        
        'unit' {
            Measure-Step "Running unit tests" {
                if ($Coverage) {
                    & $PhpPath vendor\bin\phpunit -c phpunit-unit-package.xml --coverage-html coverage\unit
                }
                else {
                    & $PhpPath vendor\bin\phpunit -c phpunit-unit-package.xml
                }
                
                if ($LASTEXITCODE -ne 0) {
                    throw "Unit tests failed"
                }
            }
        }
        
        'integration' {
            Measure-Step "Running integration tests" {
                # Check for WordPress test environment
                if (-not (Test-Path "C:\tmp\wordpress-tests-lib")) {
                    Write-Host "⚠️  WordPress test environment not found" -ForegroundColor Yellow
                    Write-Host "Run: bash bin/install-wp-tests.sh wordpress_test root '' localhost latest"
                    return
                }
                
                if ($Coverage) {
                    & $PhpPath vendor\bin\phpunit -c phpunit-integration-package.xml --coverage-html coverage\integration
                }
                else {
                    & $PhpPath vendor\bin\phpunit -c phpunit-integration-package.xml
                }
                
                if ($LASTEXITCODE -ne 0) {
                    throw "Integration tests failed"
                }
            }
        }
        
        'all' {
            # Run unit tests
            Measure-Step "Running unit tests" {
                & $PhpPath vendor\bin\phpunit -c phpunit-unit-package.xml
                if ($LASTEXITCODE -ne 0) {
                    throw "Unit tests failed"
                }
            }
            
            # Run integration tests
            Measure-Step "Running integration tests" {
                if (Test-Path "C:\tmp\wordpress-tests-lib") {
                    & $PhpPath vendor\bin\phpunit -c phpunit-integration-package.xml
                    if ($LASTEXITCODE -ne 0) {
                        throw "Integration tests failed"
                    }
                }
                else {
                    Write-Host "⚠️  Skipping integration tests (WordPress test environment not found)" -ForegroundColor Yellow
                }
            }
        }
    }
}
finally {
    Pop-Location
    Remove-Item env:SHIELD_PACKAGE_PATH -ErrorAction SilentlyContinue
    Remove-Item env:SHIELD_TEST_PACKAGE -ErrorAction SilentlyContinue
    
    # Cleanup - all artifacts are in central test directory
    Write-Host "`n✅ Test artifacts stored in: $TestBase" -ForegroundColor Green
}

# Final summary
$Duration = (Get-Date) - $StartTime
Write-Host "`n========================================" -ForegroundColor Cyan
Write-Host "Testing completed in $($Duration.TotalMinutes.ToString('0.0')) minutes" -ForegroundColor Green
Write-Host "========================================" -ForegroundColor Cyan

Write-Host "`nPackage location: $PackageDir" -ForegroundColor Gray
Write-Host "Test artifacts: $TestBase" -ForegroundColor Gray