#!/bin/bash

# 🔧 QuizWhiz AI - Environment Configuration Script
# This script helps configure the .env file for production

set -e

# Colors
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
RED='\033[0;31m'
NC='\033[0m'

echo -e "${BLUE}🔧 QuizWhiz AI - Environment Configuration${NC}"
echo -e "${BLUE}==========================================${NC}"
echo ""

# Check if .env file exists
if [ ! -f .env ]; then
    if [ -f .env.example ]; then
        cp .env.example .env
        echo -e "${GREEN}✅ Created .env file from .env.example${NC}"
    else
        echo -e "${RED}❌ No .env.example file found${NC}"
        exit 1
    fi
fi

echo -e "${YELLOW}Please provide the following information:${NC}"
echo ""

# Get domain name
read -p "Enter your domain name (e.g., examgenerator.ai): " DOMAIN
if [ -z "$DOMAIN" ]; then
    DOMAIN="localhost"
fi

# Get database information
echo -e "${BLUE}Database Configuration:${NC}"
read -p "Database name [quizwhiz_ai]: " DB_NAME
DB_NAME=${DB_NAME:-quizwhiz_ai}

read -p "Database username [quizwhiz_user]: " DB_USER
DB_USER=${DB_USER:-quizwhiz_user}

read -s -p "Database password: " DB_PASSWORD
echo ""

if [ -z "$DB_PASSWORD" ]; then
    echo -e "${RED}❌ Database password is required${NC}"
    exit 1
fi

# Get email configuration
echo -e "${BLUE}Email Configuration:${NC}"
read -p "SMTP Host [smtp.gmail.com]: " MAIL_HOST
MAIL_HOST=${MAIL_HOST:-smtp.gmail.com}

read -p "SMTP Port [587]: " MAIL_PORT
MAIL_PORT=${MAIL_PORT:-587}

read -p "Email username: " MAIL_USERNAME
read -s -p "Email password/app password: " MAIL_PASSWORD
echo ""

read -p "From email address [noreply@$DOMAIN]: " MAIL_FROM_ADDRESS
MAIL_FROM_ADDRESS=${MAIL_FROM_ADDRESS:-noreply@$DOMAIN}

# Get OpenAI API key
echo -e "${BLUE}AI Configuration:${NC}"
read -s -p "OpenAI API Key: " OPENAI_API_KEY
echo ""

# Get payment gateway information (optional)
echo -e "${BLUE}Payment Gateways (Optional):${NC}"
read -p "Razorpay Key ID (leave empty to skip): " RAZORPAY_KEY
read -s -p "Razorpay Key Secret: " RAZORPAY_SECRET
echo ""

read -p "Stripe Publishable Key (leave empty to skip): " STRIPE_KEY
read -s -p "Stripe Secret Key: " STRIPE_SECRET
echo ""

read -p "PayPal Client ID (leave empty to skip): " PAYPAL_CLIENT_ID
read -s -p "PayPal Client Secret: " PAYPAL_CLIENT_SECRET
echo ""

# Generate random APP_KEY
APP_KEY=$(openssl rand -base64 32)

echo -e "${YELLOW}Updating .env file...${NC}"

# Update .env file
sed -i "s/APP_NAME=.*/APP_NAME=\"QuizWhiz AI\"/" .env
sed -i "s/APP_ENV=.*/APP_ENV=production/" .env
sed -i "s/APP_KEY=.*/APP_KEY=base64:$APP_KEY/" .env
sed -i "s/APP_DEBUG=.*/APP_DEBUG=false/" .env
sed -i "s|APP_URL=.*|APP_URL=https://$DOMAIN|" .env

# Database configuration
sed -i "s/DB_CONNECTION=.*/DB_CONNECTION=mysql/" .env
sed -i "s/DB_HOST=.*/DB_HOST=localhost/" .env
sed -i "s/DB_PORT=.*/DB_PORT=3306/" .env
sed -i "s/DB_DATABASE=.*/DB_DATABASE=$DB_NAME/" .env
sed -i "s/DB_USERNAME=.*/DB_USERNAME=$DB_USER/" .env
sed -i "s/DB_PASSWORD=.*/DB_PASSWORD=$DB_PASSWORD/" .env

# Mail configuration
sed -i "s/MAIL_MAILER=.*/MAIL_MAILER=smtp/" .env
sed -i "s/MAIL_HOST=.*/MAIL_HOST=$MAIL_HOST/" .env
sed -i "s/MAIL_PORT=.*/MAIL_PORT=$MAIL_PORT/" .env
sed -i "s/MAIL_USERNAME=.*/MAIL_USERNAME=$MAIL_USERNAME/" .env
sed -i "s/MAIL_PASSWORD=.*/MAIL_PASSWORD=$MAIL_PASSWORD/" .env
sed -i "s/MAIL_ENCRYPTION=.*/MAIL_ENCRYPTION=tls/" .env
sed -i "s/MAIL_FROM_ADDRESS=.*/MAIL_FROM_ADDRESS=$MAIL_FROM_ADDRESS/" .env
sed -i "s/MAIL_FROM_NAME=.*/MAIL_FROM_NAME=\"QuizWhiz AI\"/" .env

# OpenAI configuration
if [ ! -z "$OPENAI_API_KEY" ]; then
    sed -i "s/OPENAI_API_KEY=.*/OPENAI_API_KEY=$OPENAI_API_KEY/" .env
fi

# Payment gateways
if [ ! -z "$RAZORPAY_KEY" ]; then
    sed -i "s/RAZORPAY_KEY=.*/RAZORPAY_KEY=$RAZORPAY_KEY/" .env
    sed -i "s/RAZORPAY_SECRET=.*/RAZORPAY_SECRET=$RAZORPAY_SECRET/" .env
fi

if [ ! -z "$STRIPE_KEY" ]; then
    sed -i "s/STRIPE_KEY=.*/STRIPE_KEY=$STRIPE_KEY/" .env
    sed -i "s/STRIPE_SECRET=.*/STRIPE_SECRET=$STRIPE_SECRET/" .env
fi

if [ ! -z "$PAYPAL_CLIENT_ID" ]; then
    sed -i "s/PAYPAL_CLIENT_ID=.*/PAYPAL_CLIENT_ID=$PAYPAL_CLIENT_ID/" .env
    sed -i "s/PAYPAL_CLIENT_SECRET=.*/PAYPAL_CLIENT_SECRET=$PAYPAL_CLIENT_SECRET/" .env
    sed -i "s/PAYPAL_MODE=.*/PAYPAL_MODE=live/" .env
fi

# Security settings
sed -i "s/SANCTUM_STATEFUL_DOMAINS=.*/SANCTUM_STATEFUL_DOMAINS=$DOMAIN,www.$DOMAIN/" .env
sed -i "s/SESSION_DOMAIN=.*/SESSION_DOMAIN=.$DOMAIN/" .env

# Cache settings
sed -i "s/CACHE_DRIVER=.*/CACHE_DRIVER=database/" .env
sed -i "s/SESSION_DRIVER=.*/SESSION_DRIVER=database/" .env
sed -i "s/QUEUE_CONNECTION=.*/QUEUE_CONNECTION=database/" .env

# File storage
sed -i "s/FILESYSTEM_DISK=.*/FILESYSTEM_DISK=public/" .env

echo -e "${GREEN}✅ Environment configuration completed!${NC}"
echo ""
echo -e "${BLUE}📋 Configuration Summary:${NC}"
echo -e "   • Domain: https://$DOMAIN"
echo -e "   • Database: $DB_NAME"
echo -e "   • Database User: $DB_USER"
echo -e "   • Email: $MAIL_FROM_ADDRESS"
echo -e "   • OpenAI API: $(if [ ! -z "$OPENAI_API_KEY" ]; then echo 'Configured'; else echo 'Not configured'; fi)"
echo -e "   • Payment Gateways: $(if [ ! -z "$RAZORPAY_KEY" ] || [ ! -z "$STRIPE_KEY" ] || [ ! -z "$PAYPAL_CLIENT_ID" ]; then echo 'Configured'; else echo 'Not configured'; fi)"
echo ""
echo -e "${YELLOW}⚠️  Next Steps:${NC}"
echo -e "   1. Review the .env file: nano .env"
echo -e "   2. Run database migrations: php artisan migrate"
echo -e "   3. Create storage link: php artisan storage:link"
echo -e "   4. Cache configurations: php artisan config:cache"
echo -e "   5. Test your application"
echo ""
echo -e "${GREEN}🎉 Environment setup completed!${NC}"
