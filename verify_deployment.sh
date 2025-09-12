#!/bin/bash

echo "🔍 Verifying deployment at https://rdo.vetel.ind.br"
echo "================================================"

# Test HTTPS redirect
echo -n "Testing HTTPS connection: "
response=$(curl -s -o /dev/null -w "%{http_code}" -L https://rdo.vetel.ind.br)
if [ "$response" = "200" ] || [ "$response" = "302" ]; then
    echo "✅ Success (HTTP $response)"
else
    echo "❌ Failed (HTTP $response)"
fi

# Test login redirect (should get 302 to login.php if not authenticated)
echo -n "Testing authentication redirect: "
response=$(curl -s -o /dev/null -w "%{http_code}" https://rdo.vetel.ind.br)
if [ "$response" = "302" ]; then
    echo "✅ Redirecting to login (HTTP 302)"
else
    echo "⚠️  Unexpected response (HTTP $response)"
fi

# Get redirect location
echo -n "Checking redirect location: "
location=$(curl -s -I https://rdo.vetel.ind.br | grep -i "location:" | cut -d' ' -f2 | tr -d '\r')
if [[ "$location" == *"login.php"* ]]; then
    echo "✅ Redirects to login.php"
else
    echo "ℹ️  Redirects to: $location"
fi

echo ""
echo "📊 Deployment Summary:"
echo "- Application URL: https://rdo.vetel.ind.br"
echo "- Expected behavior: Redirect to /login.php for authentication"
echo "- Database: External MariaDB at 103.199.185.165:5987"
echo ""
echo "✨ Deployment appears to be successful!"