#!/bin/bash

# V8Js Installation Script for macOS
# This script installs the V8Js PHP extension

set -e

echo "🚀 Installing V8Js PHP Extension..."

# Check if Homebrew is installed
if ! command -v brew &> /dev/null; then
    echo "❌ Homebrew is not installed. Please install it first:"
    echo "   /bin/bash -c \"\$(curl -fsSL https://raw.githubusercontent.com/Homebrew/install/HEAD/install.sh)\""
    exit 1
fi

# Check PHP version
PHP_VERSION=$(php -r "echo PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION;")
echo "📦 Detected PHP version: $PHP_VERSION"

# Install V8 library
echo "📥 Installing V8 library..."
brew install v8

# Install PECL if not available
if ! command -v pecl &> /dev/null; then
    echo "📥 Installing PECL..."
    brew install php
fi

# Install V8Js extension
echo "📥 Installing V8Js extension..."
pecl install v8js

# Find PHP ini file
PHP_INI=$(php --ini | grep "Loaded Configuration File" | awk '{print $4}')

if [ -z "$PHP_INI" ] || [ "$PHP_INI" = "(none)" ]; then
    # Try to find php.ini in common locations
    if [ -f "/opt/homebrew/etc/php/$PHP_VERSION/php.ini" ]; then
        PHP_INI="/opt/homebrew/etc/php/$PHP_VERSION/php.ini"
    elif [ -f "/usr/local/etc/php/$PHP_VERSION/php.ini" ]; then
        PHP_INI="/usr/local/etc/php/$PHP_VERSION/php.ini"
    else
        echo "⚠️  Could not find php.ini file. Please add this line manually:"
        echo "   extension=v8js.so"
        exit 0
    fi
fi

# Add extension to php.ini if not already present
if ! grep -q "extension=v8js.so" "$PHP_INI"; then
    echo "📝 Adding V8Js extension to php.ini..."
    echo "extension=v8js.so" >> "$PHP_INI"
fi

# Verify installation
echo ""
echo "✅ Verifying installation..."
if php -m | grep -q v8js; then
    echo "✅ V8Js extension installed successfully!"
    php -r "echo 'V8Js version: ' . V8Js::V8_VERSION . PHP_EOL;"
else
    echo "❌ V8Js extension installation failed."
    echo "   Please check the error messages above."
    exit 1
fi

echo ""
echo "🎉 Installation complete!"
echo ""
echo "You can now use the CodeNode in your workflows."
