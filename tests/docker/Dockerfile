# Shield Security - Test Runner
# Build arguments for customization
ARG PHP_VERSION=8.2
ARG WP_VERSION=6.4

FROM wordpress:${WP_VERSION}-php${PHP_VERSION}

# Install test dependencies
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    subversion \
    default-mysql-client \
    && rm -rf /var/lib/apt/lists/*

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Install PHPUnit and test framework
RUN composer global require --dev \
    phpunit/phpunit:^9.6 \
    yoast/phpunit-polyfills:^1.1 \
    && composer global config --no-interaction allow-plugins.dealerdirect/phpcodesniffer-composer-installer true

# Re-declare ARG after FROM to use in RUN commands
ARG WP_VERSION=6.4

# Set up WordPress test framework
RUN cd /tmp && \
    svn co --quiet https://develop.svn.wordpress.org/tags/${WP_VERSION}/tests/phpunit/includes/ /tmp/wordpress-tests-lib/includes && \
    svn co --quiet https://develop.svn.wordpress.org/tags/${WP_VERSION}/tests/phpunit/data/ /tmp/wordpress-tests-lib/data

# Configure PHP
RUN echo "memory_limit=512M" > /usr/local/etc/php/conf.d/testing.ini

# Add composer global bin to PATH
ENV PATH="/root/.composer/vendor/bin:${PATH}"

WORKDIR /var/www/html