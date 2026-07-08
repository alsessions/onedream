#!/usr/bin/env bash

set -euo pipefail

cd "$(dirname "$0")"

echo "Pulling latest code..."
git pull --ff-only

echo "Installing Composer dependencies..."
composer install --no-dev --prefer-dist --optimize-autoloader

echo "Running Craft migrations..."
php craft migrate/all --interactive=0

echo "Applying Craft project config..."
php craft project-config/apply --interactive=0

echo "Clearing Craft caches..."
php craft clear-caches/all

echo "Deploy complete."
