# Shield Security - Optimized Local CI/CD Pipeline
# Mirrors the optimized GitHub Actions workflow for fast local testing

param(
    [Parameter(Position=0)]
    [ValidateSet('all', 'build', 'test', 'quality')]
    [string]$Stage = 'all',
    
    [switch]$FastMode,  # Minimal testing like feature branches
    [switch]$FullMatrix, # Full PHP/WP matrix testing
    [switch]$KeepPackage # Don't clean up package after testing
)

$ErrorActionPreference = "Stop"
$StartTime = Get-Date

Write-Host "================================================" -ForegroundColor Cyan
Write-Host "Shield Security - Optimized Pipeline (Local)" -ForegroundColor Cyan
Write-Host "================================================" -ForegroundColor Cyan
Write-Host ""

# Configuration
$ProjectRoot = Split-Path -Parent $PSScriptRoot
$PackageDir = Join-Path $ProjectRoot "..\shield-package-optimized"
$LogFile = Join-Path $ProjectRoot "pipeline-optimized.log"
$PhpPath = "C:\Users\$env:USERNAME\.config\herd\bin\php83\php.exe"
$ComposerPhar = "C:\Users\$env:USERNAME\.config\herd\bin\composer.phar"

# Ensure we're in project root
Set-Location $ProjectRoot

# Initialize log
"[$(Get-Date -Format 'yyyy-MM-dd HH:mm:ss')] Pipeline started - Stage: $Stage" | Out-File $LogFile
if ($FastMode) { Add-Content $LogFile "Fast mode enabled (minimal testing)" }
if ($FullMatrix) { Add-Content $LogFile "Full matrix testing requested" }

# Helper functions
function Write-StageHeader {
    param($Name)
    Write-Host "`n╔══════════════════════════════════════════════╗" -ForegroundColor Blue
    Write-Host "║ $Name".PadRight(45) + "║" -ForegroundColor Blue
    Write-Host "╚══════════════════════════════════════════════╝" -ForegroundColor Blue
    Add-Content $LogFile "`n=== STAGE: $Name ==="
}

function Write-Step {
    param($Name)
    Write-Host "`n→ $Name" -ForegroundColor Yellow
    Add-Content $LogFile "[$(Get-Date -Format 'HH:mm:ss')] $Name"
}

function Write-Success {
    param($Message, $Duration)
    if ($Duration) {
        Write-Host "✅ $Message (${Duration}s)" -ForegroundColor Green
        Add-Content $LogFile "SUCCESS: $Message (${Duration}s)"
    } else {
        Write-Host "✅ $Message" -ForegroundColor Green
        Add-Content $LogFile "SUCCESS: $Message"
    }
}

function Write-Error {
    param($Message)
    Write-Host "❌ $Message" -ForegroundColor Red
    Add-Content $LogFile "ERROR: $Message"
}

function Measure-Step {
    param($Name, $ScriptBlock)
    
    Write-Step $Name
    $stepStart = Get-Date
    
    try {
        $output = & $ScriptBlock 2>&1
        $duration = [Math]::Round(((Get-Date) - $stepStart).TotalSeconds, 1)
        Write-Success $Name $duration
        
        if ($output -and $output.Count -gt 0) {
            $output | ForEach-Object { Add-Content $LogFile "  $_" }
        }
        
        return $true
    }
    catch {
        Write-Error "$Name failed: $_"
        Add-Content $LogFile "ERROR Details: $_"
        throw
    }
}

# STAGE 1: BUILD & PACKAGE
if ($Stage -eq 'all' -or $Stage -eq 'build') {
    Write-StageHeader "STAGE 1: BUILD & PACKAGE"
    
    # Clean previous package
    if (Test-Path $PackageDir) {
        Write-Step "Cleaning previous package"
        Remove-Item -Path $PackageDir -Recurse -Force
        Write-Success "Previous package cleaned"
    }
    
    # Install dependencies (with caching simulation)
    Measure-Step "Installing all dependencies" {
        # Root dependencies
        & $PhpPath $ComposerPhar install --no-interaction --prefer-dist --optimize-autoloader --quiet
        
        # Plugin library dependencies
        Push-Location "src\lib"
        & $PhpPath $ComposerPhar install --no-interaction --prefer-dist --no-dev --optimize-autoloader --quiet
        Pop-Location
        
        # Node dependencies
        npm ci --prefer-offline --no-audit --silent
    }
    
    # Build assets
    Measure-Step "Building frontend assets" {
        npm run build --silent
        
        if (-not (Test-Path "assets\dist")) {
            throw "Asset build failed - dist directory not created"
        }
        
        $assetCount = (Get-ChildItem "assets\dist" -File -Recurse).Count
        Write-Host "  → Built $assetCount asset files" -ForegroundColor Gray
    }
    
    # Create plugin package
    Measure-Step "Creating plugin package" {
        # Create package directory
        New-Item -ItemType Directory -Path $PackageDir -Force | Out-Null
        
        # Copy plugin files
        $pluginFiles = @(
            "icwp-wpsf.php", "plugin_init.php", "readme.txt", "plugin.json",
            "cl.json", "plugin_autoload.php", "plugin_compatibility.php",
            "uninstall.php", "unsupported.php"
        )
        
        foreach ($file in $pluginFiles) {
            Copy-Item $file $PackageDir\
        }
        
        # Copy directories
        foreach ($dir in @("src", "assets", "flags", "languages", "templates")) {
            Copy-Item $dir $PackageDir\ -Recurse
        }
        
        # Strauss prefixing
        Push-Location "$PackageDir\src\lib"
        
        $straussUrl = "https://github.com/BrianHenryIE/strauss/releases/download/0.23.0/strauss.phar"
        Invoke-WebRequest -Uri $straussUrl -OutFile "strauss.phar" -UseBasicParsing
        & $PhpPath strauss.phar
        Remove-Item strauss.phar
        
        Pop-Location
        
        # Clean duplicate libraries
        $toRemove = @(
            "$PackageDir\src\lib\vendor\twig",
            "$PackageDir\src\lib\vendor\monolog",
            "$PackageDir\src\lib\vendor\bin",
            "$PackageDir\src\lib\vendor\php-stubs"
        )
        
        foreach ($path in $toRemove) {
            if (Test-Path $path) {
                Remove-Item $path -Recurse -Force
            }
        }
        
        # Clean autoload files
        Get-ChildItem "$PackageDir\src\lib\vendor\composer\*.php" | ForEach-Object {
            $content = Get-Content $_.FullName -Raw
            $content = $content -replace '.*\/twig\/twig\/.*\r?\n', ''
            Set-Content $_.FullName $content -NoNewline
        }
        
        # Package statistics
        $stats = @{
            Files = (Get-ChildItem $PackageDir -File -Recurse).Count
            Size = [Math]::Round((Get-ChildItem $PackageDir -Recurse | Measure-Object -Property Length -Sum).Sum / 1MB, 2)
            PHPFiles = (Get-ChildItem $PackageDir -Filter "*.php" -Recurse).Count
        }
        
        Write-Host "  → Package created: $($stats.Files) files, $($stats.Size) MB" -ForegroundColor Gray
    }
    
    # Copy test files for local testing
    Measure-Step "Preparing package for testing" {
        Copy-Item "tests" "$PackageDir\" -Recurse
        Copy-Item "composer.json" "$PackageDir\"
        Copy-Item "composer.lock" "$PackageDir\"
        Copy-Item "phpunit-unit-package.xml" "$PackageDir\" -ErrorAction SilentlyContinue
        Copy-Item "phpunit-integration-package.xml" "$PackageDir\" -ErrorAction SilentlyContinue
        Copy-Item ".phpcs.xml.dist" "$PackageDir\" -ErrorAction SilentlyContinue
    }
}

# STAGE 2: PARALLEL QUALITY CHECKS
if ($Stage -eq 'all' -or $Stage -eq 'quality') {
    Write-StageHeader "STAGE 2: CODE QUALITY"
    
    Push-Location $PackageDir
    
    try {
        # Install test dependencies
        Measure-Step "Installing test dependencies" {
            & $PhpPath $ComposerPhar install --no-interaction --quiet
        }
        
        # Run PHPCS
        if (Test-Path ".phpcs.xml.dist") {
            Measure-Step "Running WordPress Coding Standards" {
                & $PhpPath vendor\bin\phpcs --report=summary
                
                if ($LASTEXITCODE -eq 2) {
                    throw "PHPCS found errors"
                } elseif ($LASTEXITCODE -eq 1) {
                    Write-Host "  ⚠️ PHPCS found warnings (non-blocking)" -ForegroundColor Yellow
                }
            }
        } else {
            Write-Host "  ⚠️ No PHPCS configuration found" -ForegroundColor Yellow
        }
    }
    finally {
        Pop-Location
    }
}

# STAGE 3: TESTING
if ($Stage -eq 'all' -or $Stage -eq 'test') {
    Write-StageHeader "STAGE 3: AUTOMATED TESTING"
    
    # Set environment for package testing
    $env:SHIELD_PACKAGE_PATH = $PackageDir
    $env:SHIELD_TEST_PACKAGE = "true"
    
    Push-Location $PackageDir
    
    try {
        # Ensure test dependencies are installed
        if (-not (Test-Path "vendor\bin\phpunit")) {
            Measure-Step "Installing test dependencies" {
                & $PhpPath $ComposerPhar install --no-interaction --quiet
            }
        }
        
        # Unit Tests
        Measure-Step "Running unit tests" {
            $config = if (Test-Path "phpunit-unit-package.xml") { "phpunit-unit-package.xml" } else { "phpunit-unit.xml" }
            & $PhpPath vendor\bin\phpunit -c $config --testdox
            
            if ($LASTEXITCODE -ne 0) {
                throw "Unit tests failed"
            }
        }
        
        # Integration Tests (if WordPress test environment exists)
        if (Test-Path "C:\tmp\wordpress-tests-lib") {
            if (-not $FastMode -or $FullMatrix) {
                Measure-Step "Running integration tests" {
                    $config = if (Test-Path "phpunit-integration-package.xml") { "phpunit-integration-package.xml" } else { "phpunit-integration.xml" }
                    & $PhpPath vendor\bin\phpunit -c $config --testdox
                    
                    if ($LASTEXITCODE -ne 0) {
                        throw "Integration tests failed"
                    }
                }
            } else {
                Write-Host "  ⚡ Skipping integration tests in fast mode" -ForegroundColor Gray
            }
        } else {
            Write-Host "  ⚠️ WordPress test environment not found - skipping integration tests" -ForegroundColor Yellow
        }
    }
    finally {
        Pop-Location
        Remove-Item env:SHIELD_PACKAGE_PATH -ErrorAction SilentlyContinue
        Remove-Item env:SHIELD_TEST_PACKAGE -ErrorAction SilentlyContinue
    }
}

# FINAL SUMMARY
$Duration = [Math]::Round(((Get-Date) - $StartTime).TotalSeconds)
$Minutes = [Math]::Floor($Duration / 60)
$Seconds = $Duration % 60

Write-Host "`n╔══════════════════════════════════════════════╗" -ForegroundColor Green
Write-Host "║ PIPELINE COMPLETED SUCCESSFULLY! 🎉           ║" -ForegroundColor Green
Write-Host "╚══════════════════════════════════════════════╝" -ForegroundColor Green

Write-Host "`nPerformance Summary:" -ForegroundColor Cyan
Write-Host "─────────────────────────────────────────" -ForegroundColor Gray
Write-Host "Total Duration: ${Minutes}m ${Seconds}s" -ForegroundColor White
Write-Host "Package Location: $PackageDir" -ForegroundColor White
Write-Host "Log File: $LogFile" -ForegroundColor White

if ($Stage -eq 'build') {
    Write-Host "`nNext Steps:" -ForegroundColor Yellow
    Write-Host "  .\bin\integration-optimized.ps1 test    # Run tests on built package"
    Write-Host "  .\bin\integration-optimized.ps1 quality # Run quality checks"
}

if (-not $KeepPackage -and $Stage -ne 'build') {
    Write-Host "`nPackage will be cleaned up. Use -KeepPackage to preserve it." -ForegroundColor Gray
    
    # Schedule cleanup after 5 seconds
    Start-Sleep -Seconds 5
    if (Test-Path $PackageDir) {
        Remove-Item $PackageDir -Recurse -Force
        Write-Host "Package cleaned up." -ForegroundColor Gray
    }
}

# Add summary to log
Add-Content $LogFile "`n=== PIPELINE SUMMARY ==="
Add-Content $LogFile "Total Duration: ${Minutes}m ${Seconds}s"
Add-Content $LogFile "Final Status: SUCCESS"
Add-Content $LogFile "Package: $PackageDir"