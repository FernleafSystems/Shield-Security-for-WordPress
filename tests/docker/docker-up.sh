#!/bin/bash
# MINIMAL CONVENIENCE WRAPPER - Use Composer for actual testing
# This script only starts Docker containers. For testing, use:
# composer test:unit or composer test:integration

cd "$(dirname "$0")"
docker-compose up -d