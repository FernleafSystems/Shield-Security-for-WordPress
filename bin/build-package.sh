#!/bin/bash
# Shield Plugin Package Builder
# Creates a production package with Strauss prefixing and cleanup

set -e

# Configuration
PACKAGE_DIR="${1:-}"
WORKSPACE_DIR="${2:-$(pwd)}"

if [ -z "$PACKAGE_DIR" ]; then
    echo "Usage: $0 <package_directory> [workspace_directory]"
    echo "Example: $0 /tmp/shield-package $(pwd)"
    exit 1
fi

echo "=== Shield Package Builder ==="
echo "Package Directory: $PACKAGE_DIR"
echo "Workspace Directory: $WORKSPACE_DIR"

# Create package directory
mkdir -p "$PACKAGE_DIR"

# Copy plugin files (matching WordPress.org structure)
echo "Copying plugin files..."
for file in icwp-wpsf.php plugin_init.php readme.txt plugin.json cl.json \
            plugin_autoload.php plugin_compatibility.php uninstall.php unsupported.php; do
  if [ -f "$file" ]; then
    cp "$file" "$PACKAGE_DIR/"
    echo "Copied: $file"
  fi
done

# Copy directories
echo "Copying directories..."
for dir in src assets flags languages templates; do
  if [ -d "$dir" ]; then
    cp -R "$dir" "$PACKAGE_DIR/"
    echo "Copied directory: $dir"
  fi
done

echo "Package structure created successfully"

# Install composer dependencies in the package (production only)
echo "Installing composer dependencies in package..."
cd "$PACKAGE_DIR/src/lib"
composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader

# Download and run Strauss v0.19.4 for package prefixing
echo "Downloading Strauss v0.19.4..."
curl -L https://github.com/BrianHenryIE/strauss/releases/download/0.19.4/strauss.phar -o strauss.phar
[ -f strauss.phar ] && echo "✓ strauss.phar downloaded" || { echo "✗ strauss.phar download FAILED"; exit 1; }

echo "Current directory: $(pwd)"
echo "Checking for composer.json: $([ -f composer.json ] && echo 'YES' || echo 'NO')"

echo "Running Strauss prefixing..."
php strauss.phar || { echo "Strauss failed with exit code $?"; exit 1; }

# Check if vendor_prefixed was created
echo "=== After Strauss ==="
[ -d vendor_prefixed ] && echo "✓ vendor_prefixed directory created" || echo "✗ vendor_prefixed NOT created"

# Clean up duplicates and dev files
echo "Removing duplicate libraries from main vendor..."
rm -rf vendor/twig/ vendor/monolog/ vendor/bin/

# Remove development files from vendor_prefixed
echo "Removing development-only files..."
rm -f vendor_prefixed/autoload-files.php strauss.phar

# Clean autoload files - remove twig references
echo "Cleaning autoload files..."
cd vendor/composer

# Debug: Show files before cleaning
echo "=== Autoload files before cleaning ==="
ls -la *.php

# Clean each autoload file
for file in autoload_files.php autoload_static.php autoload_psr4.php; do
  if [ -f "$file" ]; then
    echo "Cleaning $file..."
    # Count twig references before
    BEFORE=$(grep -c '/twig/twig/' "$file" || true)
    echo "  - Found $BEFORE twig references"
    
    # Remove lines containing /twig/twig/
    grep -v '/twig/twig/' "$file" > "$file.tmp" && mv "$file.tmp" "$file"
    
    # Count after
    AFTER=$(grep -c '/twig/twig/' "$file" || true)
    echo "  - After cleaning: $AFTER twig references"
  fi
done

# Return to workspace
cd "$WORKSPACE_DIR"

echo "=== Package Verification ==="
[ -f "$PACKAGE_DIR/icwp-wpsf.php" ] && echo "✓ Main plugin file exists" || echo "✗ Main plugin file MISSING"
[ -f "$PACKAGE_DIR/src/lib/vendor/autoload.php" ] && echo "✓ src/lib/vendor/autoload.php exists" || echo "✗ src/lib/vendor/autoload.php MISSING"
[ -d "$PACKAGE_DIR/src/lib/vendor_prefixed" ] && echo "✓ src/lib/vendor_prefixed directory exists" || echo "✗ src/lib/vendor_prefixed MISSING"
[ -d "$PACKAGE_DIR/assets/dist" ] && echo "✓ assets/dist directory exists" || echo "✗ assets/dist MISSING"
[ -f "$PACKAGE_DIR/src/lib/vendor_prefixed/autoload-classmap.php" ] && echo "✓ autoload-classmap.php exists" || echo "✗ autoload-classmap.php MISSING"

echo "✅ Package built successfully: $PACKAGE_DIR"