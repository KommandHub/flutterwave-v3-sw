FROM dockware/shopware:6.7.8.0

USER root

# Install useful tools (optional)
RUN apt-get update && \
    apt-get install -y make nano build-essential php-pear php-dev && \
    echo "extension=pcov.so" > /etc/php/8.3/cli/conf.d/20-pcov.ini && \
    echo "pcov.enabled=1" >> /etc/php/8.3/cli/conf.d/20-pcov.ini && \
    echo "pcov.directory=/var/www/html/custom/plugins/KommandhubFlutterwaveV3SW/src" >> /etc/php/8.3/cli/conf.d/20-pcov.ini && \
    apt-get clean && \
    rm -rf /var/lib/apt/lists/*

# Set proper permissions
RUN chown -R www-data:www-data /var/www/html && \
    chmod -R 755 /var/www/html
