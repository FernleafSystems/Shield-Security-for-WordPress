#!/bin/bash
# Docker Setup Script for Shield Security Testing

set -e

echo "Setting up Docker testing environment for Shield Security..."

# Check if Docker is installed
if ! command -v docker &> /dev/null; then
    echo "Error: Docker is not installed. Please install Docker first."
    exit 1
fi

# Check if Docker Compose is installed
if ! command -v docker-compose &> /dev/null && ! docker compose version &> /dev/null; then
    echo "Error: Docker Compose is not installed. Please install Docker Compose first."
    exit 1
fi

# Navigate to docker directory
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
cd "$SCRIPT_DIR/.."

# Copy .env.example to .env if it doesn't exist
if [ ! -f .env ]; then
    cp .env.example .env
    echo "Created .env file from .env.example"
fi

# Build Docker images
echo "Building Docker images..."
docker-compose build

# Pull MySQL image
echo "Pulling MySQL image..."
docker-compose pull mysql

echo "Setup complete! You can now run tests with:"
echo "  ./scripts/run-tests.sh"