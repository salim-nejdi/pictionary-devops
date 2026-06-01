# =============================================================================
# STAGE 1 — "builder"
# =============================================================================
FROM php:8.2-apache AS builder
RUN apt-get update && apt-get install -y --no-install-recommends \
        libpng-dev \
        libjpeg-dev \
    && docker-php-ext-install pdo pdo_mysql \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# =============================================================================
# STAGE 2 — "production"
# =============================================================================
FROM php:8.2-apache AS production
LABEL maintainer="salim-nejdi" \
      org.opencontainers.image.source="https://github.com/salim-nejdi/pictionary-devops" \
      org.opencontainers.image.description="Application web Pictionary" \
      org.opencontainers.image.version="1.0.0"
COPY --from=builder /usr/local/lib/php/extensions/ /usr/local/lib/php/extensions/
COPY --from=builder /usr/local/etc/php/conf.d/     /usr/local/etc/php/conf.d/
COPY . /var/www/html/
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html
EXPOSE 80
CMD ["apache2-foreground"]
