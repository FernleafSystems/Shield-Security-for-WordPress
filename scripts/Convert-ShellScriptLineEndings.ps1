<#
.SYNOPSIS
    Converts CRLF line endings to LF in shell scripts for Linux compatibility.

.DESCRIPTION
    This script finds all shell script files (.sh) in the repository and converts any 
    CRLF line endings to LF line endings to ensure compatibility with Linux CI/CD 
    environments. Also updates .gitattributes to maintain proper line endings.

.PARAMETER Path
    The root path to search for shell scripts. Defaults to current directory.

.EXAMPLE
    .\Convert-ShellScriptLineEndings.ps1
    Converts line endings for all shell scripts in current directory and subdirectories.

.NOTES
    This script is critical for CI/CD success as bash scripts with CRLF fail on Linux.
    
    Version: 1.0
    Author: Shield Security Team
    Created: 2025-08-01
#>

[CmdletBinding()]
param(
    [Parameter(Mandatory = $false)]
    [string]$Path = (Get-Location).Path
)

Write-Host "Converting shell script line endings from CRLF to LF..." -ForegroundColor Green
Write-Host "Searching in path: $Path" -ForegroundColor Cyan

$shellScripts = Get-ChildItem -Path $Path -Filter "*.sh" -Recurse -File
$modifiedCount = 0

if (-not $shellScripts) {
    Write-Host "No shell script files found." -ForegroundColor Yellow
    return
}

Write-Host "Found $($shellScripts.Count) shell script file(s)" -ForegroundColor Cyan

foreach ($file in $shellScripts) {
    $relativePath = [System.IO.Path]::GetRelativePath($Path, $file.FullName)
    Write-Host "Processing: $relativePath"
    
    try {
        $content = Get-Content $file.FullName -Raw -Encoding UTF8
        if ($content) {
            $newContent = $content -replace "`r`n", "`n"
            
            # Only write if content changed
            if ($content -ne $newContent) {
                [System.IO.File]::WriteAllText($file.FullName, $newContent, [System.Text.Encoding]::UTF8)
                Write-Host "  ✓ Converted: $relativePath" -ForegroundColor Green
                $modifiedCount++
            } else {
                Write-Host "  Already has LF endings" -ForegroundColor Gray
            }
        }
    }
    catch {
        Write-Host "  ✗ Error: $($_.Exception.Message)" -ForegroundColor Red
    }
}

# Update .gitattributes for git repositories
if (Test-Path (Join-Path $Path ".git")) {
    $gitAttributesPath = Join-Path $Path ".gitattributes"
    $shellScriptRule = "*.sh text eol=lf"
    
    $needsUpdate = $true
    if (Test-Path $gitAttributesPath) {
        $existing = Get-Content $gitAttributesPath -Raw
        if ($existing -match "\.sh.*eol=lf") {
            $needsUpdate = $false
        }
    }
    
    if ($needsUpdate) {
        if (Test-Path $gitAttributesPath) {
            Add-Content $gitAttributesPath -Value $shellScriptRule -Encoding UTF8
        } else {
            Set-Content $gitAttributesPath -Value $shellScriptRule -Encoding UTF8
        }
        Write-Host "✓ Updated .gitattributes to ensure proper git handling" -ForegroundColor Green
    } else {
        Write-Host "✓ .gitattributes already configured correctly" -ForegroundColor Gray
    }
}

Write-Host ""
Write-Host "=== CONVERSION SUMMARY ===" -ForegroundColor Cyan
Write-Host "Total shell scripts found: $($shellScripts.Count)" -ForegroundColor White
Write-Host "Files converted: $modifiedCount" -ForegroundColor Green

if ($modifiedCount -gt 0) {
    Write-Host ""
    Write-Host "✓ All shell scripts now have Unix LF line endings" -ForegroundColor Green
    Write-Host "✓ Ready for Linux CI/CD environments!" -ForegroundColor Green
} else {
    Write-Host ""
    Write-Host "✓ All shell scripts already had correct LF line endings" -ForegroundColor Green
}