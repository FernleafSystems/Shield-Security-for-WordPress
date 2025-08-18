#!/bin/bash
# Test script to validate parallel execution logic without running actual Docker tests

set -e

echo "🧪 Testing Parallel Execution Logic"
echo "===================================="

# Mock WordPress versions
LATEST_VERSION="6.8.2"
PREVIOUS_VERSION="6.7.1"
PARALLEL_TESTING="true"
DEBUG_MODE="true"

# Create test output directory
mkdir -p /tmp

echo "📋 Testing process management and output capture..."

# Mock parallel execution test
test_parallel_execution() {
    echo "🚀 Starting mock parallel execution..."
    
    # Record start time
    PARALLEL_START_TIME=$(date +%s)
    echo "⏱️  Mock execution started at: $(date)"
    
    # Initialize tracking
    declare -A EXIT_CODES
    declare -A PROCESS_PIDS
    
    # Mock Latest process
    (
        echo "[LATEST] Mock test for WordPress $LATEST_VERSION starting at $(date)" > /tmp/shield-test-latest.log
        sleep 5  # Simulate test execution
        echo "[LATEST] Mock Unit tests: 71 tests completed" >> /tmp/shield-test-latest.log
        echo "[LATEST] Mock Integration tests: 33 tests completed" >> /tmp/shield-test-latest.log
        echo "[LATEST] Mock tests completed successfully at $(date)" >> /tmp/shield-test-latest.log
        echo 0 > /tmp/shield-test-latest.exit
    ) &
    LATEST_PID=$!
    PROCESS_PIDS["latest"]=$LATEST_PID
    echo "📱 Mock Latest stream started (PID: $LATEST_PID)"
    
    # Mock Previous process
    (
        echo "[PREVIOUS] Mock test for WordPress $PREVIOUS_VERSION starting at $(date)" > /tmp/shield-test-previous.log
        sleep 4  # Simulate slightly faster execution
        echo "[PREVIOUS] Mock Unit tests: 71 tests completed" >> /tmp/shield-test-previous.log
        echo "[PREVIOUS] Mock Integration tests: 33 tests completed" >> /tmp/shield-test-previous.log
        echo "[PREVIOUS] Mock tests completed successfully at $(date)" >> /tmp/shield-test-previous.log
        echo 0 > /tmp/shield-test-previous.exit
    ) &
    PREVIOUS_PID=$!
    PROCESS_PIDS["previous"]=$PREVIOUS_PID
    echo "📱 Mock Previous stream started (PID: $PREVIOUS_PID)"
    
    # Monitor processes
    echo "🔍 Monitoring parallel execution..."
    for i in {1..5}; do
        sleep 1
        LATEST_RUNNING=$(ps -p $LATEST_PID > /dev/null 2>&1 && echo "Running" || echo "Stopped")
        PREVIOUS_RUNNING=$(ps -p $PREVIOUS_PID > /dev/null 2>&1 && echo "Running" || echo "Stopped")
        echo "   Check $i: Latest PID $LATEST_PID ($LATEST_RUNNING), Previous PID $PREVIOUS_PID ($PREVIOUS_RUNNING)"
    done
    
    # Wait for processes
    echo "⏳ Waiting for mock processes to complete..."
    wait $LATEST_PID
    EXIT_CODES["latest"]=$(cat /tmp/shield-test-latest.exit 2>/dev/null || echo "1")
    
    wait $PREVIOUS_PID
    EXIT_CODES["previous"]=$(cat /tmp/shield-test-previous.exit 2>/dev/null || echo "1")
    
    # Calculate timing
    PARALLEL_END_TIME=$(date +%s)
    PARALLEL_DURATION=$((PARALLEL_END_TIME - PARALLEL_START_TIME))
    echo "⏱️  Mock execution completed in ${PARALLEL_DURATION} seconds"
    
    # Verify results
    echo "🔍 Verification Results:"
    echo "   Latest exit code: ${EXIT_CODES[latest]}"
    echo "   Previous exit code: ${EXIT_CODES[previous]}"
    echo "   Latest log lines: $(wc -l < /tmp/shield-test-latest.log 2>/dev/null || echo 0)"
    echo "   Previous log lines: $(wc -l < /tmp/shield-test-previous.log 2>/dev/null || echo 0)"
    
    # Test exit code aggregation
    if [ "${EXIT_CODES[latest]}" != "0" ] || [ "${EXIT_CODES[previous]}" != "0" ]; then
        echo "❌ Mock test would have failed"
        return 1
    else
        echo "✅ Mock test passed"
        return 0
    fi
}

# Run the test
if test_parallel_execution; then
    echo ""
    echo "✅ Parallel execution logic test PASSED"
    echo "📁 Mock log files created:"
    [ -f /tmp/shield-test-latest.log ] && echo "   - /tmp/shield-test-latest.log ($(wc -l < /tmp/shield-test-latest.log) lines)"
    [ -f /tmp/shield-test-previous.log ] && echo "   - /tmp/shield-test-previous.log ($(wc -l < /tmp/shield-test-previous.log) lines)"
    
    echo ""
    echo "📋 Sample output from Latest stream:"
    [ -f /tmp/shield-test-latest.log ] && head -3 /tmp/shield-test-latest.log
    
    echo ""
    echo "📋 Sample output from Previous stream:"
    [ -f /tmp/shield-test-previous.log ] && head -3 /tmp/shield-test-previous.log
    
    exit 0
else
    echo ""
    echo "❌ Parallel execution logic test FAILED"
    exit 1
fi