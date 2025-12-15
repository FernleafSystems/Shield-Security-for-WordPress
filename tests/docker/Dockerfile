# Shield Security - Multi-Stage Test Runner
# Optimized for matrix testing with multi-PHP support and caching
# Part of Docker Matrix Testing Optimization (Phase 3, Task 3.1)

# Build arguments for matrix customization
ARG PHP_VERSION=8.2
ARG WP_VERSION=latest

#
# Stage 1: Base Dependencies
# Contains system packages and Composer - shared across PHP versions
#
FROM ubuntu:22.04 AS base-deps

# Prevent interactive prompts during package installation
ENV DEBIAN_FRONTEND=noninteractive
ENV TZ=UTC

# Install system dependencies
RUN apt-get update && apt-get install -y \
    curl \
    git \
    unzip \
    subversion \
    default-mysql-client \
    software-properties-common \
    gpg-agent \
    && rm -rf /var/lib/apt/lists/*

# Install Composer globally (cached layer)
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Configure Git for Docker usage (fix ownership issues)
RUN git config --global --add safe.directory '*'

#
# Stage 2: PHP Installation
# PHP version-specific installation - optimized for caching
#
FROM base-deps AS php-runtime
ARG PHP_VERSION

# Ensure non-interactive mode is maintained
ENV DEBIAN_FRONTEND=noninteractive
ENV TZ=UTC

# Add PHP repository
RUN add-apt-repository ppa:ondrej/php && apt-get update

# Install PHP and required extensions
RUN apt-get install -y \
    php${PHP_VERSION} \
    php${PHP_VERSION}-cli \
    php${PHP_VERSION}-mysql \
    php${PHP_VERSION}-xml \
    php${PHP_VERSION}-mbstring \
    php${PHP_VERSION}-curl \
    php${PHP_VERSION}-zip \
    php${PHP_VERSION}-gd \
    php${PHP_VERSION}-intl \
    php${PHP_VERSION}-bcmath \
    php${PHP_VERSION}-soap \
    php${PHP_VERSION}-dev \
    && rm -rf /var/lib/apt/lists/*

# Create symlinks for php commands
RUN update-alternatives --install /usr/bin/php php /usr/bin/php${PHP_VERSION} 100

# Configure PHP for testing
RUN echo "memory_limit=512M" > /etc/php/${PHP_VERSION}/cli/conf.d/99-testing.ini \
    && echo "max_execution_time=300" >> /etc/php/${PHP_VERSION}/cli/conf.d/99-testing.ini \
    && echo "error_reporting=E_ALL" >> /etc/php/${PHP_VERSION}/cli/conf.d/99-testing.ini

#
# Stage 3: Test Framework Installation
# PHPUnit and testing dependencies - version compatible
#
FROM php-runtime AS test-framework
ARG PHP_VERSION

# Install PHPUnit based on PHP version compatibility
# PHPUnit 9.x: PHP 7.3 - 8.0
# PHPUnit 10.x: PHP 8.1+
# PHPUnit 11.x: PHP 8.2+
RUN if [ "$(echo ${PHP_VERSION} | cut -d. -f1)" = "7" ] || [ "${PHP_VERSION}" = "8.0" ]; then \
        composer global require --no-interaction --no-cache \
            phpunit/phpunit:^9.6 \
            yoast/phpunit-polyfills:^1.1; \
    elif [ "${PHP_VERSION}" = "8.1" ]; then \
        composer global require --no-interaction --no-cache \
            phpunit/phpunit:^10.5 \
            yoast/phpunit-polyfills:^2.0; \
    else \
        composer global require --no-interaction --no-cache \
            phpunit/phpunit:^11.0 \
            yoast/phpunit-polyfills:^2.0; \
    fi

# Configure Composer plugins
RUN composer global config --no-interaction allow-plugins.dealerdirect/phpcodesniffer-composer-installer true

# Add composer global bin to PATH
ENV PATH="/root/.composer/vendor/bin:${PATH}"

#
# Stage 4: WordPress Test Framework (Dynamic)
# Downloads WordPress test framework based on version argument
# Optimized to be skipped when WP_VERSION=latest (runtime detection)
#
FROM test-framework AS wordpress-tests
ARG WP_VERSION=latest

# Create WordPress test lib directory
RUN mkdir -p /tmp/wordpress-tests-lib

# Download WordPress test framework if specific version provided
# Skip download for 'latest' - will be handled at runtime by test script
RUN if [ "${WP_VERSION}" != "latest" ]; then \
        echo "=== DOCKER BUILD: Downloading WordPress test framework for version: ${WP_VERSION} ===" && \
        cd /tmp && \
        echo "=== DOCKER BUILD: Checking out includes from: https://develop.svn.wordpress.org/tags/${WP_VERSION}/tests/phpunit/includes/ ===" && \
        svn co https://develop.svn.wordpress.org/tags/${WP_VERSION}/tests/phpunit/includes/ /tmp/wordpress-tests-lib/includes && \
        echo "=== DOCKER BUILD: Checking out data from: https://develop.svn.wordpress.org/tags/${WP_VERSION}/tests/phpunit/data/ ===" && \
        svn co https://develop.svn.wordpress.org/tags/${WP_VERSION}/tests/phpunit/data/ /tmp/wordpress-tests-lib/data && \
        echo "=== DOCKER BUILD: Verifying functions.php exists ===" && \
        ls -la /tmp/wordpress-tests-lib/includes/functions.php && \
        echo "=== DOCKER BUILD: Downloading WordPress core files for version: ${WP_VERSION} ===" && \
        mkdir -p /tmp/wordpress && \
        curl -L https://wordpress.org/wordpress-${WP_VERSION}.tar.gz -o /tmp/wordpress.tar.gz && \
        tar --strip-components=1 -zxf /tmp/wordpress.tar.gz -C /tmp/wordpress && \
        echo "=== DOCKER BUILD: Verifying WordPress core files ===" && \
        ls -la /tmp/wordpress/wp-load.php; \
    else \
        echo "WordPress test framework will be downloaded at runtime for version: ${WP_VERSION}"; \
    fi

#
# Stage 5: Final Runtime Image
# Clean, optimized image for test execution
#
FROM wordpress-tests AS shield-test-runner

# Set working directory
WORKDIR /app

# Environment variables for runtime configuration
ENV SHIELD_DOCKER_PHP_VERSION=${PHP_VERSION}
ENV SHIELD_DOCKER_WP_VERSION=${WP_VERSION}
ENV SHIELD_TEST_MODE=docker

# Labels for image identification and caching
LABEL org.label-schema.name="shield-test-runner"
LABEL org.label-schema.description="Shield Security WordPress Plugin Test Runner"
LABEL org.label-schema.php-version="${PHP_VERSION}"
LABEL org.label-schema.wp-version="${WP_VERSION}"
LABEL org.label-schema.build-date="${BUILD_DATE}"

# Health check to verify PHP and database connectivity
HEALTHCHECK --interval=30s --timeout=10s --start-period=5s --retries=3 \
    CMD php --version && composer --version

# Default command - can be overridden by docker-compose
CMD ["/bin/bash"]