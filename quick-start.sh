#!/bin/bash

# LMS Platform Quick Start Script
# This script helps you quickly set up and run the LMS platform

echo "========================================="
echo "LMS Platform Quick Start"
echo "========================================="

# Check if we're in the right directory
if [ ! -f "artisan" ]; then
    echo "Error: Please run this script from the LMS platform root directory"
    exit 1
fi

# Step 1: Check PHP version
echo -e "\n1. Checking PHP version..."
php --version

# Step 2: Generate app key if needed
if ! grep -q "^APP_KEY=base64:" .env 2>/dev/null; then
    echo -e "\n2. Generating application key..."
    php artisan key:generate
else
    echo -e "\n2. Application key already set"
fi

# Step 3: Install PHP dependencies
echo -e "\n3. Installing PHP dependencies..."
if [ -f "../composer.phar" ]; then
    php ../composer.phar install --ignore-platform-reqs --no-interaction
else
    echo "Composer not found. Trying global composer..."
    composer install --ignore-platform-reqs --no-interaction
fi

# Step 4: Install Node dependencies
echo -e "\n4. Installing Node dependencies..."
npm install

# Step 5: Database setup
echo -e "\n5. Setting up database..."
echo "Choose database option:"
echo "1) SQLite (Quick testing)"
echo "2) MySQL (Production ready)"
read -p "Enter choice (1 or 2): " DB_CHOICE

if [ "$DB_CHOICE" = "1" ]; then
    # SQLite setup
    sed -i 's/DB_CONNECTION=.*/DB_CONNECTION=sqlite/' .env
    touch database/database.sqlite
    echo "SQLite database created"
else
    # MySQL setup
    echo "Make sure MySQL is running and you have created the database."
    read -p "Enter MySQL database name (default: lms_platform): " DB_NAME
    DB_NAME=${DB_NAME:-lms_platform}
    
    read -p "Enter MySQL username (default: root): " DB_USER
    DB_USER=${DB_USER:-root}
    
    read -s -p "Enter MySQL password: " DB_PASS
    echo
    
    # Update .env file
    sed -i "s/DB_CONNECTION=.*/DB_CONNECTION=mysql/" .env
    sed -i "s/DB_DATABASE=.*/DB_DATABASE=$DB_NAME/" .env
    sed -i "s/DB_USERNAME=.*/DB_USERNAME=$DB_USER/" .env
    sed -i "s/DB_PASSWORD=.*/DB_PASSWORD=$DB_PASS/" .env
fi

# Step 6: Run migrations
echo -e "\n6. Running database migrations..."
php artisan migrate --force

# Step 7: Seed database
echo -e "\n7. Seeding database with test data..."
php artisan db:seed --force

# Step 8: Build frontend assets
echo -e "\n8. Building frontend assets..."
npm run build

# Step 9: Clear caches
echo -e "\n9. Clearing application caches..."
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear

echo -e "\n========================================="
echo "Setup Complete!"
echo "========================================="
echo ""
echo "Test Accounts:"
echo "- admin@lms.com / password123"
echo "- teacher@lms.com / password123"
echo "- student@lms.com / password123"
echo ""
echo "To start the development server:"
echo "1. Run: php artisan serve"
echo "2. In another terminal: npm run dev"
echo "3. Visit: http://localhost:8000"
echo ""
echo "To test the API:"
echo "Run: ./test-api.sh"
echo "========================================="