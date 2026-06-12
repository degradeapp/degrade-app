#!/bin/bash

# Frontend Integration Testing Script for Degradê
BASE_URL="http://127.0.0.1:8000"
PASSED=0
FAILED=0

echo "=== Degradê Frontend Integration Tests ==="
echo "Testing: $BASE_URL"
echo ""

# Test function
test_route() {
    local route=$1
    local expected_status=$2
    local description=$3

    response=$(curl -s -w "\n%{http_code}" "$BASE_URL$route" 2>/dev/null)
    status=$(echo "$response" | tail -n1)
    body=$(echo "$response" | head -n-1)

    if [ "$status" -eq "$expected_status" ]; then
        echo "✓ $description (HTTP $status)"
        PASSED=$((PASSED + 1))
        return 0
    else
        echo "✗ $description (Expected HTTP $expected_status, got $status)"
        FAILED=$((FAILED + 1))
        return 1
    fi
}

echo "--- Auth Pages (No Auth Required) ---"
test_route "/login" "200" "Login page"
test_route "/register" "200" "Register page"

echo ""
echo "--- Protected Pages (Redirects Without Auth) ---"
test_route "/appointments" "302" "Appointments (requires auth)"
test_route "/customers" "302" "Customers (requires auth)"
test_route "/barbers" "302" "Barbers (requires auth)"
test_route "/services" "302" "Services (requires auth)"
test_route "/commissions" "302" "Commission (requires auth)"
test_route "/settings" "302" "Settings (requires auth)"
test_route "/whatsapp" "302" "WhatsApp (requires auth)"

echo ""
echo "--- API Endpoints ---"
test_route "/api/user" "401" "Get user (requires auth token)"
test_route "/health" "200" "Health check endpoint"

echo ""
echo "--- Error Pages ---"
test_route "/404" "200" "404 error page"
test_route "/403" "200" "403 error page"

echo ""
echo "=== Test Summary ==="
echo "Passed: $PASSED"
echo "Failed: $FAILED"
echo "Total:  $((PASSED + FAILED))"

if [ $FAILED -eq 0 ]; then
    echo "✓ All tests passed!"
    exit 0
else
    echo "✗ Some tests failed"
    exit 1
fi
