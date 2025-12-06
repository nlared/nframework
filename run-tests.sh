#!/bin/bash
#
# Test Runner Script for nframework
# This script runs the PHPUnit test suite
#

# Set script directory
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
PROJECT_ROOT="$SCRIPT_DIR"

# Colors for output
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo "========================================="
echo "  nframework Test Suite"
echo "========================================="
echo ""

# Check if PHPUnit is installed
if [ ! -f "$PROJECT_ROOT/includes/vendor/bin/phpunit" ]; then
    echo -e "${YELLOW}PHPUnit is not installed. Installing dependencies...${NC}"
    cd "$PROJECT_ROOT/includes"
    composer install
    if [ $? -ne 0 ]; then
        echo -e "${RED}Failed to install dependencies. Please run 'composer install' manually in the includes/ directory.${NC}"
        exit 1
    fi
    cd "$PROJECT_ROOT"
fi

# Run tests based on arguments
if [ "$1" == "unit" ]; then
    echo "Running Unit Tests..."
    "$PROJECT_ROOT/includes/vendor/bin/phpunit" --testsuite Unit
elif [ "$1" == "feature" ]; then
    echo "Running Feature Tests..."
    "$PROJECT_ROOT/includes/vendor/bin/phpunit" --testsuite Feature
elif [ "$1" == "coverage" ]; then
    echo "Running Tests with Coverage Report..."
    "$PROJECT_ROOT/includes/vendor/bin/phpunit" --coverage-html coverage
    echo ""
    echo -e "${GREEN}Coverage report generated in: coverage/index.html${NC}"
else
    echo "Running All Tests..."
    "$PROJECT_ROOT/includes/vendor/bin/phpunit"
fi

# Check exit code
if [ $? -eq 0 ]; then
    echo ""
    echo -e "${GREEN}✓ All tests passed!${NC}"
else
    echo ""
    echo -e "${RED}✗ Some tests failed.${NC}"
    exit 1
fi
