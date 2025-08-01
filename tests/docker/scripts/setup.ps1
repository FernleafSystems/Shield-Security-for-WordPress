# Docker Setup Script for Shield Security Testing (Windows)

Write-Host "Setting up Docker testing environment for Shield Security..." -ForegroundColor Green

# Check if Docker is installed
try {
    docker --version | Out-Null
} catch {
    Write-Host "Error: Docker is not installed. Please install Docker Desktop for Windows first." -ForegroundColor Red
    exit 1
}

# Check if Docker Compose is installed
try {
    docker-compose --version | Out-Null
} catch {
    try {
        docker compose version | Out-Null
    } catch {
        Write-Host "Error: Docker Compose is not installed. Please install Docker Compose first." -ForegroundColor Red
        exit 1
    }
}

# Navigate to docker directory
$scriptPath = Split-Path -Parent $MyInvocation.MyCommand.Path
Set-Location "$scriptPath\.."

# Copy .env.example to .env if it doesn't exist
if (-not (Test-Path ".env")) {
    Copy-Item ".env.example" ".env"
    Write-Host "Created .env file from .env.example" -ForegroundColor Yellow
}

# Build Docker images
Write-Host "Building Docker images..." -ForegroundColor Yellow
docker-compose build

# Pull MySQL image
Write-Host "Pulling MySQL image..." -ForegroundColor Yellow
docker-compose pull mysql

Write-Host "Setup complete! You can now run tests with:" -ForegroundColor Green
Write-Host "  .\scripts\run-tests.ps1" -ForegroundColor Cyan