#!/bin/bash
# MySQL Container Health Check Monitoring Test Script
# Tests MySQL startup timing and validates health check approach
# 
# Purpose: Understand actual MySQL startup times and validate monitoring approaches
# before implementing in the main Docker test script
#
# What this script does:
# 1. Starts a MySQL test container in background
# 2. Monitors startup with Docker health checks
# 3. Tests direct mysqladmin ping approach
# 4. Measures and compares actual startup times
# 5. Tests rapid connection attempts
# 6. Cleans up automatically after testing

set -e

echo "=== MySQL Container Health Check Test ==="
echo "Testing MySQL container startup timing and monitoring..."

# Configuration
TEST_CONTAINER="test-mysql-health-check"
MAX_ATTEMPTS=60  # Maximum seconds to wait for MySQL to be ready
MYSQL_IMAGE="mysql:8.0"

# Function to cleanup test container
# Ensures we don't leave test containers running
cleanup() {
    echo "Cleaning up test container..."
    docker stop $TEST_CONTAINER 2>/dev/null || true
    docker rm $TEST_CONTAINER 2>/dev/null || true
}

# Ensure cleanup on exit (including on script failure or interruption)
trap cleanup EXIT

# Remove any existing test container from previous runs
cleanup

# Record start time for measuring startup duration
START_TIME=$(date +%s)
echo "Starting MySQL test container at $(date +%H:%M:%S)..."

# Start MySQL container in background with health check configuration
# Using Docker's built-in health check feature for monitoring
docker run -d \
    --name $TEST_CONTAINER \
    -e MYSQL_ROOT_PASSWORD=testpass \
    -e MYSQL_DATABASE=test_db \
    -e MYSQL_ALLOW_EMPTY_PASSWORD=yes \
    --health-cmd="mysqladmin ping -h localhost --silent" \
    --health-interval=1s \
    --health-timeout=5s \
    --health-retries=60 \
    $MYSQL_IMAGE \
    --default-authentication-plugin=mysql_native_password

echo "Container started, monitoring health status..."

# Monitor container health using Docker's health check status
# This approach uses Docker's built-in health monitoring
ATTEMPTS=0
while [ $ATTEMPTS -lt $MAX_ATTEMPTS ]; do
    # Check container health status from Docker
    HEALTH_STATUS=$(docker inspect --format='{{.State.Health.Status}}' $TEST_CONTAINER 2>/dev/null || echo "unknown")
    
    if [ "$HEALTH_STATUS" = "healthy" ]; then
        END_TIME=$(date +%s)
        DURATION=$((END_TIME - START_TIME))
        echo "✅ MySQL container healthy after ${DURATION} seconds"
        break
    fi
    
    ATTEMPTS=$((ATTEMPTS + 1))
    
    # Progress indicator every 5 seconds to show script is still running
    if [ $((ATTEMPTS % 5)) -eq 0 ]; then
        echo "  Waiting for MySQL... attempt $ATTEMPTS/$MAX_ATTEMPTS (status: $HEALTH_STATUS)"
    fi
    
    sleep 1
done

# Test alternative approach: direct mysqladmin ping
# This tests executing mysqladmin directly inside the container
echo ""
echo "Testing direct mysqladmin ping approach..."
START_TIME_2=$(date +%s)
ATTEMPTS_2=0

while [ $ATTEMPTS_2 -lt $MAX_ATTEMPTS ]; do
    # Try to ping MySQL directly using mysqladmin
    if docker exec $TEST_CONTAINER mysqladmin ping -h localhost --silent 2>/dev/null; then
        END_TIME_2=$(date +%s)
        DURATION_2=$((END_TIME_2 - START_TIME_2))
        echo "✅ Direct mysqladmin ping successful after ${DURATION_2} seconds"
        break
    fi
    
    ATTEMPTS_2=$((ATTEMPTS_2 + 1))
    
    # Progress indicator for direct ping attempts
    if [ $((ATTEMPTS_2 % 5)) -eq 0 ]; then
        echo "  Direct ping attempt $ATTEMPTS_2/$MAX_ATTEMPTS"
    fi
    
    sleep 1
done

# Display comparative results
echo ""
echo "=== Test Results ==="
echo "Docker health check method: ${DURATION:-FAILED} seconds"
echo "Direct mysqladmin method: ${DURATION_2:-FAILED} seconds"
echo "Container logs (last 10 lines):"
docker logs --tail 10 $TEST_CONTAINER

# Test multiple rapid connections to ensure MySQL is truly ready
# This validates that MySQL can handle actual queries, not just pings
echo ""
echo "Testing rapid connection attempts..."
for i in {1..5}; do
    if docker exec $TEST_CONTAINER mysql -uroot -ptestpass -e "SELECT 1" test_db >/dev/null 2>&1; then
        echo "  Connection $i: ✅ Success"
    else
        echo "  Connection $i: ❌ Failed"
    fi
done

echo ""
echo "Test completed successfully!"