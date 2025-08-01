# Shield Security - Simple Test Runner
FROM wordpress:6.4-php8.2

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

# Set up WordPress test framework
RUN cd /tmp && \
    svn co --quiet https://develop.svn.wordpress.org/tags/6.4/tests/phpunit/includes/ /tmp/wordpress-tests-lib/includes && \
    svn co --quiet https://develop.svn.wordpress.org/tags/6.4/tests/phpunit/data/ /tmp/wordpress-tests-lib/data

# Configure PHP
RUN echo "memory_limit=512M" > /usr/local/etc/php/conf.d/testing.ini

# Add composer global bin to PATH
ENV PATH="/root/.composer/vendor/bin:${PATH}"

WORKDIR /var/www/html