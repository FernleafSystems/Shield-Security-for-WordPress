# Shield Security WordPress Plugin - Smoke Tests Runner
# Fast validation of critical plugin functionality and configuration
# Uses central test directory for all artifacts
#
# Usage:
#   .\run-smoke-tests.ps1                       # Run all smoke tests
#   .\run-smoke-tests.ps1 -TestFilter json     # Run only tests matching 'json'
#   .\run-smoke-tests.ps1 -Verbose             # Run with verbose output
#   .\run-smoke-tests.ps1 -FailFast            # Stop on first failure

param(
    [string]$TestFilter = "",
    [switch]$Verbose,
    [switch]$FailFast
)

$ErrorActionPreference = "Stop"

# Test configuration
$ProjectName = "WP_Plugin-Shield"
$TestBase = "D:\Work\Dev\Tests\$ProjectName"
$ArtifactsDir = "$TestBase\artifacts"
$LogFile = "$ArtifactsDir\smoke-tests-$(Get-Date -Format 'yyyyMMdd-HHmmss').log"
$TimeoutSeconds = 60  # Smoke tests should be fast

# Detect current username for PHP paths
$CurrentUser = $env:USERNAME
$PhpPath = "C:\Users\$CurrentUser\.config\herd\bin\php83\php.exe"
$ComposerPhar = "C:\Users\$CurrentUser\.config\herd\bin\composer.phar"

# Test results tracking
$TestResults = @{
    Total = 0
    Passed = 0
    Failed = 0
    StartTime = Get-Date
}

# Ensure test directories exist
New-Item -ItemType Directory -Path "$TestBase\artifacts" -Force | Out-Null

# Write to both console and log file
function Write-Log {
    param(
        [string]$Message,
        [string]$Level = "INFO",
        [ConsoleColor]$Color = "White"
    )
    
    $Timestamp = Get-Date -Format "yyyy-MM-dd HH:mm:ss"
    $LogMessage = "[$Timestamp] [$Level] $Message"
    
    # Write to log file
    $LogMessage | Out-File -FilePath $LogFile -Append -Encoding UTF8
    
    # Write to console with color
    Write-Host $Message -ForegroundColor $Color
}

# Header
Write-Log "============================================" -Color Cyan
Write-Log "Shield Security - Smoke Tests Runner" -Color Cyan
Write-Log "============================================" -Color Cyan
Write-Log ""

# System information
Write-Log "Test Configuration:" -Color Yellow
Write-Log "  Project: $ProjectName"
Write-Log "  User: $CurrentUser"
Write-Log "  PHP: $PhpPath"
Write-Log "  Log: $(Split-Path -Leaf $LogFile)"
Write-Log "  Timeout: $TimeoutSeconds seconds"
Write-Log ""

# Verify PHP executable
if (-not (Test-Path $PhpPath)) {
    Write-Log "PHP executable not found at: $PhpPath" -Level "ERROR" -Color Red
    Write-Log "Please ensure Laravel Herd is installed for user: $CurrentUser" -Level "ERROR" -Color Red
    exit 1
}

# Test PHP
try {
    $phpVersion = & $PhpPath --version 2>&1 | Select-Object -First 1
    Write-Log "PHP Version: $phpVersion" -Color Green
} catch {
    Write-Log "Failed to execute PHP: $_" -Level "ERROR" -Color Red
    exit 1
}

# Change to project directory
$ProjectDir = Split-Path -Parent $PSScriptRoot
Set-Location $ProjectDir
Write-Log "Working Directory: $ProjectDir"
Write-Log ""

# Function to run a specific test file
function Run-TestFile {
    param(
        [string]$TestFile,
        [string]$TestName
    )
    
    Write-Log "----------------------------------------" -Color DarkGray
    Write-Log "Running: $TestName" -Color Yellow
    Write-Log "File: $TestFile" -Color DarkGray
    
    $TestResults.Total++
    $testStart = Get-Date
    
    try {
        # Create command to run specific test file
        $testCmd = {
            & $using:PhpPath vendor\bin\phpunit `
                --no-interaction `
                --colors=never `
                --no-coverage `
                -c phpunit-unit.xml `
                $using:TestFile 2>&1
        }
        
        # Run test with timeout
        $job = Start-Job -ScriptBlock $testCmd
        $completed = Wait-Job -Job $job -Timeout $TimeoutSeconds
        
        if ($completed) {
            $output = Receive-Job -Job $job
            $exitCode = if ($job.State -eq "Completed" -and $output -notmatch "FAILURES|ERRORS") { 0 } else { 1 }
            Remove-Job -Job $job
            
            $duration = [Math]::Round((Get-Date).Subtract($testStart).TotalSeconds, 2)
            
            if ($exitCode -eq 0) {
                $TestResults.Passed++
                Write-Log "✅ PASSED in $duration seconds" -Color Green
                
                # Extract test count from output
                if ($output -match "OK \((\d+) test[s]?, (\d+) assertion[s]?\)") {
                    Write-Log "   Tests: $($Matches[1]), Assertions: $($Matches[2])" -Color DarkGreen
                }
                
                # Show verbose output if requested
                if ($Verbose) {
                    Write-Log "   Verbose Output:" -Color DarkGray
                    $output -split "`n" | ForEach-Object {
                        if ($_ -match "^\s*$") { return }
                        Write-Log "   $_" -Color DarkGray
                    }
                }
            } else {
                $TestResults.Failed++
                Write-Log "❌ FAILED in $duration seconds" -Level "ERROR" -Color Red
                
                # Log failure details
                Write-Log "Test Output:" -Level "ERROR" -Color Red
                $output -split "`n" | ForEach-Object {
                    if ($_ -match "FAILURES|ERRORS|Failed|Error") {
                        Write-Log "   $_" -Level "ERROR" -Color Red
                    }
                }
            }
            
            # Save full output to log
            "`n--- $TestName Output ---" | Out-File -FilePath $LogFile -Append -Encoding UTF8
            $output | Out-File -FilePath $LogFile -Append -Encoding UTF8
            "--- End $TestName Output ---`n" | Out-File -FilePath $LogFile -Append -Encoding UTF8
            
        } else {
            Stop-Job -Job $job
            Remove-Job -Job $job
            $TestResults.Failed++
            Write-Log "⏱️ TIMEOUT after $TimeoutSeconds seconds" -Level "ERROR" -Color Red
        }
        
    } catch {
        $TestResults.Failed++
        Write-Log "💥 EXCEPTION: $_" -Level "ERROR" -Color Red
    }
}

# Run smoke tests
Write-Log "============================================" -Color Cyan
Write-Log "Executing Smoke Tests" -Color Cyan
Write-Log "============================================" -Color Cyan
Write-Log ""

# Define smoke tests to run
$smokeTests = @(
    @{
        File = "tests\Unit\PluginJsonSchemaTest.php"
        Name = "Plugin Configuration Schema Validation"
    },
    @{
        File = "tests\Unit\CorePluginSmokeTest.php"
        Name = "Core Plugin Functionality Check"
    }
)

# Apply filter if specified
if ($TestFilter) {
    Write-Log "Applying test filter: '$TestFilter'" -Color Yellow
    $smokeTests = $smokeTests | Where-Object { 
        $_.File -like "*$TestFilter*" -or $_.Name -like "*$TestFilter*" 
    }
    
    if ($smokeTests.Count -eq 0) {
        Write-Log "No tests match filter: '$TestFilter'" -Level "WARNING" -Color Yellow
        Write-Log "Available tests:" -Color Yellow
        $allTests = @(
            @{
                File = "tests\Unit\PluginJsonSchemaTest.php"
                Name = "Plugin Configuration Schema Validation"
            },
            @{
                File = "tests\Unit\CorePluginSmokeTest.php"
                Name = "Core Plugin Functionality Check"
            }
        )
        $allTests | ForEach-Object {
            Write-Log "  - $($_.Name) ($($_.File))" -Color DarkGray
        }
        exit 0
    }
}

# Run each smoke test
foreach ($test in $smokeTests) {
    Run-TestFile -TestFile $test.File -TestName $test.Name
    
    # Stop on first failure if FailFast is enabled
    if ($FailFast -and $TestResults.Failed -gt 0) {
        Write-Log ""
        Write-Log "FailFast mode: Stopping after first failure" -Level "ERROR" -Color Red
        break
    }
}

# Calculate total duration
$totalDuration = [Math]::Round((Get-Date).Subtract($TestResults.StartTime).TotalSeconds, 2)

# Summary Report
Write-Log ""
Write-Log "============================================" -Color Cyan
Write-Log "Smoke Test Summary" -Color Cyan
Write-Log "============================================" -Color Cyan
Write-Log ""
Write-Log "Total Tests Run: $($TestResults.Total)" -Color Yellow
Write-Log "✅ Passed: $($TestResults.Passed)" -Color Green
Write-Log "❌ Failed: $($TestResults.Failed)" -Color $(if ($TestResults.Failed -gt 0) { "Red" } else { "Green" })
Write-Log ""
Write-Log "Total Duration: $totalDuration seconds" -Color Yellow
Write-Log "Average per test: $([Math]::Round($totalDuration / $TestResults.Total, 2)) seconds" -Color Yellow
Write-Log ""

# Generate summary report file
$summaryFile = "$ArtifactsDir\smoke-tests-summary-$(Get-Date -Format 'yyyyMMdd-HHmmss').json"
$summary = @{
    TestRun = @{
        Type = "Smoke Tests"
        Project = $ProjectName
        StartTime = $TestResults.StartTime.ToString("yyyy-MM-dd HH:mm:ss")
        EndTime = (Get-Date).ToString("yyyy-MM-dd HH:mm:ss")
        Duration = $totalDuration
        User = $CurrentUser
        PHP = $phpVersion
    }
    Results = @{
        Total = $TestResults.Total
        Passed = $TestResults.Passed
        Failed = $TestResults.Failed
        PassRate = if ($TestResults.Total -gt 0) { [Math]::Round(($TestResults.Passed / $TestResults.Total) * 100, 2) } else { 0 }
    }
    Tests = $smokeTests | ForEach-Object {
        @{
            Name = $_.Name
            File = $_.File
        }
    }
}

$summary | ConvertTo-Json -Depth 10 | Out-File -FilePath $summaryFile -Encoding UTF8
Write-Log "Summary report saved: $(Split-Path -Leaf $summaryFile)" -Color Gray

# Final status
if ($TestResults.Failed -eq 0) {
    Write-Log "============================================" -Color Green
    Write-Log "🎉 ALL SMOKE TESTS PASSED! 🎉" -Color Green
    Write-Log "============================================" -Color Green
    Write-Log ""
    Write-Log "Shield Security plugin core functionality verified!" -Color Green
    $exitCode = 0
} else {
    Write-Log "============================================" -Color Red
    Write-Log "⚠️  SMOKE TESTS FAILED  ⚠️" -Color Red
    Write-Log "============================================" -Color Red
    Write-Log ""
    Write-Log "Critical issues detected. Check the log for details:" -Color Red
    Write-Log "  $LogFile" -Color Yellow
    $exitCode = 1
}

Write-Log ""
Write-Log "Log files location: $ArtifactsDir" -Color Gray
Write-Log ""

# Return to original directory
Set-Location $PSScriptRoot

# Exit with appropriate code for CI/CD
exit $exitCode