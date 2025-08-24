#!/bin/bash

# LMS Platform API Testing Script
# Run this after starting the Laravel server

BASE_URL="http://localhost:8000/api/v1"
TOKEN=""

echo "========================================="
echo "LMS Platform API Testing"
echo "========================================="

# Test 1: Health Check
echo -e "\n1. Testing Health Check..."
curl -s "$BASE_URL/../health" | python3 -m json.tool

# Test 2: Register New User
echo -e "\n2. Registering new test user..."
REGISTER_RESPONSE=$(curl -s -X POST "$BASE_URL/auth/register" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "username": "testuser'$(date +%s)'",
    "email": "test'$(date +%s)'@example.com",
    "password": "password123",
    "password_confirmation": "password123",
    "firstName": "Test",
    "lastName": "User"
  }')

echo "$REGISTER_RESPONSE" | python3 -m json.tool

# Test 3: Login
echo -e "\n3. Testing login with demo account..."
LOGIN_RESPONSE=$(curl -s -X POST "$BASE_URL/auth/login" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "login": "student@lms.com",
    "password": "password123"
  }')

echo "$LOGIN_RESPONSE" | python3 -m json.tool

# Extract token from login response
TOKEN=$(echo "$LOGIN_RESPONSE" | python3 -c "import sys, json; print(json.load(sys.stdin).get('token', ''))" 2>/dev/null)

if [ -z "$TOKEN" ]; then
    echo "Failed to get authentication token!"
    exit 1
fi

echo -e "\nToken obtained: ${TOKEN:0:20}..."

# Test 4: Get Courses (Public)
echo -e "\n4. Fetching public course list..."
curl -s "$BASE_URL/courses" | python3 -m json.tool

# Test 5: Get User Profile (Protected)
echo -e "\n5. Fetching authenticated user profile..."
curl -s "$BASE_URL/auth/me" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Accept: application/json" | python3 -m json.tool

# Test 6: Get User's Enrollments
echo -e "\n6. Fetching user enrollments..."
curl -s "$BASE_URL/my-enrollments" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Accept: application/json" | python3 -m json.tool

# Test 7: Get Dashboard
echo -e "\n7. Fetching dashboard..."
curl -s "$BASE_URL/dashboard" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Accept: application/json" | python3 -m json.tool

echo -e "\n========================================="
echo "API Testing Complete!"
echo "========================================="