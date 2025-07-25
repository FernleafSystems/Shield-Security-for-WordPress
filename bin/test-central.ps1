# Shield Security - Central Test Management Script
# This script manages all testing through the central test directory
# ensuring no test artifacts are created in the repository

param(
    [Parameter(Position=0)]
    [ValidateSet('full', 'unit', 'integration', 'package', 'clean', 'status')]
    [string]$TestMode = 'full',
    
    [switch]$KeepArtifacts,
    [switch]$Verbose
)

$ErrorActionPreference = "Stop"
$StartTime = Get-Date

# Central test directory configuration
$ProjectName = "WP_Plugin-Shield"
$TestBase = "D:\Work\Dev\Tests\$ProjectName"
$ProjectRoot = Split-Path -Parent $PSScriptRoot

Write-Host "========================================" -ForegroundColor Cyan
Write-Host "Shield Security - Central Test Manager" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""
Write-Host "Test Base Directory: $TestBase" -ForegroundColor Gray
Write-Host "Project Root: $ProjectRoot" -ForegroundColor Gray
Write-Host ""

# Ensure test directory structure exists
function Initialize-TestDirectory {
    Write-Host "Initializing test directory structure..." -ForegroundColor Yellow
    
    $directories = @("scripts", "artifacts", "packages", "work")
    foreach ($dir in $directories) {
        $path = Join-Path $TestBase $dir
        if (!(Test-Path $path)) {
            New-Item -ItemType Directory -Path $path -Force | Out-Null
            Write-Host "✅ Created: $dir\" -ForegroundColor Green
        }
        else {
            Write-Host "✓ Exists: $dir\" -ForegroundColor DarkGray
        }
    }
    Write-Host ""
}

# Clean test artifacts
function Clean-TestArtifacts {
    param([switch]$Full)
    
    Write-Host "Cleaning test artifacts..." -ForegroundColor Yellow
    
    if ($Full) {
        # Clean everything
        $dirs = @("work", "packages", "artifacts")
        foreach ($dir in $dirs) {
            $path = Join-Path $TestBase $dir
            if (Test-Path $path) {
                Get-ChildItem $path | Remove-Item -Recurse -Force
                Write-Host "✅ Cleaned: $dir\" -ForegroundColor Green
            }
        }
    }
    else {
        # Clean only work directory and old artifacts
        $workPath = Join-Path $TestBase "work"
        if (Test-Path $workPath) {
            Get-ChildItem $workPath | Remove-Item -Recurse -Force
            Write-Host "✅ Cleaned: work\" -ForegroundColor Green
        }
        
        # Clean artifacts older than 7 days
        $artifactsPath = Join-Path $TestBase "artifacts"
        if (Test-Path $artifactsPath) {
            $oldFiles = Get-ChildItem $artifactsPath | Where-Object { $_.LastWriteTime -lt (Get-Date).AddDays(-7) }
            if ($oldFiles) {
                $oldFiles | Remove-Item -Force
                Write-Host "✅ Cleaned: $($oldFiles.Count) old artifacts" -ForegroundColor Green
            }
        }
    }
    Write-Host ""
}

# Show test directory status
function Show-TestStatus {
    Write-Host "Test Directory Status:" -ForegroundColor Yellow
    Write-Host ""
    
    $dirs = @("scripts", "artifacts", "packages", "work")
    foreach ($dir in $dirs) {
        $path = Join-Path $TestBase $dir
        if (Test-Path $path) {
            $items = Get-ChildItem $path
            $count = $items.Count
            $size = ($items | Measure-Object -Property Length -Sum).Sum
            $sizeMB = [Math]::Round($size / 1MB, 2)
            Write-Host "${dir}:" -NoNewline
            Write-Host " $count items, $sizeMB MB" -ForegroundColor Gray
        }
        else {
            Write-Host "${dir}:" -NoNewline
            Write-Host " (not created)" -ForegroundColor DarkGray
        }
    }
    
    # Show recent artifacts
    $artifactsPath = Join-Path $TestBase "artifacts"
    if (Test-Path $artifactsPath) {
        $recentFiles = Get-ChildItem $artifactsPath | Sort-Object LastWriteTime -Descending | Select-Object -First 5
        if ($recentFiles) {
            Write-Host "`nRecent artifacts:" -ForegroundColor Yellow
            foreach ($file in $recentFiles) {
                $sizeKB = [Math]::Round($file.Length / 1024, 1)
                Write-Host "  - $($file.Name) (${sizeKB} KB)" -ForegroundColor Gray
            }
        }
    }
    Write-Host ""
}

# Main execution
Initialize-TestDirectory

switch ($TestMode) {
    'clean' {
        Clean-TestArtifacts -Full
        Write-Host "Test directory cleaned" -ForegroundColor Green
    }
    
    'status' {
        Show-TestStatus
    }
    
    'full' {
        Write-Host "Running full integration tests..." -ForegroundColor Yellow
        Clean-TestArtifacts
        
        # Run integration-full.ps1
        $scriptPath = Join-Path $ProjectRoot "bin\integration-full.ps1"
        if (Test-Path $scriptPath) {
            Set-Location $ProjectRoot
            & $scriptPath
        }
        else {
            Write-Host "❌ integration-full.ps1 not found!" -ForegroundColor Red
            exit 1
        }
    }
    
    'package' {
        Write-Host "Running package-based tests..." -ForegroundColor Yellow
        Clean-TestArtifacts
        
        # Run test-package.ps1
        $scriptPath = Join-Path $ProjectRoot "bin\test-package.ps1"
        if (Test-Path $scriptPath) {
            Set-Location $ProjectRoot
            & $scriptPath all
        }
        else {
            Write-Host "❌ test-package.ps1 not found!" -ForegroundColor Red
            exit 1
        }
    }
    
    'unit' {
        Write-Host "Running unit tests only..." -ForegroundColor Yellow
        Clean-TestArtifacts
        
        # Run test-package.ps1 with unit parameter
        $scriptPath = Join-Path $ProjectRoot "bin\test-package.ps1"
        if (Test-Path $scriptPath) {
            Set-Location $ProjectRoot
            & $scriptPath unit
        }
        else {
            Write-Host "❌ test-package.ps1 not found!" -ForegroundColor Red
            exit 1
        }
    }
    
    'integration' {
        Write-Host "Running integration tests only..." -ForegroundColor Yellow
        Clean-TestArtifacts
        
        # Run test-package.ps1 with integration parameter
        $scriptPath = Join-Path $ProjectRoot "bin\test-package.ps1"
        if (Test-Path $scriptPath) {
            Set-Location $ProjectRoot
            & $scriptPath integration
        }
        else {
            Write-Host "❌ test-package.ps1 not found!" -ForegroundColor Red
            exit 1
        }
    }
}

# Final summary
$Duration = (Get-Date) - $StartTime
Write-Host "`n========================================" -ForegroundColor Cyan
Write-Host "Test management completed in $($Duration.TotalSeconds) seconds" -ForegroundColor Green
Write-Host "========================================" -ForegroundColor Cyan

if (!$KeepArtifacts -and $TestMode -ne 'clean' -and $TestMode -ne 'status') {
    Write-Host "`nArtifacts location: $TestBase" -ForegroundColor Gray
    Write-Host "Use -KeepArtifacts to preserve test data" -ForegroundColor Gray
}

# Show status at the end unless we just showed it
if ($TestMode -ne 'status') {
    Write-Host ""
    Show-TestStatus
}