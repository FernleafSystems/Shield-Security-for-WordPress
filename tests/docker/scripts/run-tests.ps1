# Docker Test Runner for Shield Security (Windows)

param(
    [string]$TestType = "all",
    [string]$TestFile = "",
    [switch]$Unit,
    [switch]$Integration,
    [switch]$Package
)

# Handle switch parameters
if ($Unit) { $TestType = "unit" }
if ($Integration) { $TestType = "integration" }
if ($Package) { $TestType = "package" }

# Navigate to docker directory
$scriptPath = Split-Path -Parent $MyInvocation.MyCommand.Path
Set-Location "$scriptPath\.."

# Ensure containers are running
Write-Host "Starting Docker containers..." -ForegroundColor Yellow
docker-compose up -d

# Wait for MySQL to be ready
Write-Host "Waiting for database..." -ForegroundColor Yellow
$maxRetries = 30
$retryCount = 0
while ($retryCount -lt $maxRetries) {
    try {
        docker-compose exec -T mysql mysqladmin ping -h localhost 2>$null
        break
    } catch {
        Start-Sleep -Seconds 1
        $retryCount++
    }
}

if ($retryCount -eq $maxRetries) {
    Write-Host "Error: Database failed to start" -ForegroundColor Red
    exit 1
}

# Run tests based on type
switch ($TestType) {
    "unit" {
        Write-Host "Running unit tests..." -ForegroundColor Green
        docker-compose exec -T test-runner phpunit -c /var/www/html/wp-content/plugins/wp-simple-firewall/phpunit-unit.xml $TestFile
    }
    "integration" {
        Write-Host "Running integration tests..." -ForegroundColor Green
        docker-compose exec -T test-runner phpunit -c /var/www/html/wp-content/plugins/wp-simple-firewall/phpunit-integration.xml $TestFile
    }
    "package" {
        Write-Host "Building and testing package..." -ForegroundColor Green
        # TODO: Implement package testing
        Write-Host "Package testing not yet implemented" -ForegroundColor Yellow
    }
    "all" {
        Write-Host "Running all tests..." -ForegroundColor Green
        docker-compose exec -T test-runner phpunit -c /var/www/html/wp-content/plugins/wp-simple-firewall/phpunit.xml $TestFile
    }
}