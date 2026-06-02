# Stage 1 : compilation des extensions PHP
FROM php:8.2-apache AS builder
RUN apt-get update && apt-get install -y --no-install-recommends \
        libpng-dev \
        libjpeg-dev \
    && docker-php-ext-install pdo pdo_mysql \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Stage 2 : image finale propre
FROM php:8.2-apache AS production

LABEL maintainer="salim-nejdi" \
      org.opencontainers.image.source="https://github.com/salim-nejdi/pictionary-devops" \
      org.opencontainers.image.description="Application web Pictionary"

# ARG = valeur fournie au moment du build (par la CI/CD via --build-arg).
# ENV = rend cette valeur disponible comme variable d'environnement DANS le conteneur,
#       lisible par PHP via getenv('APP_VERSION').
ARG VERSION=dev
ENV APP_VERSION=${VERSION}

COPY --from=builder /usr/local/lib/php/extensions/ /usr/local/lib/php/extensions/
COPY --from=builder /usr/local/etc/php/conf.d/     /usr/local/etc/php/conf.d/
COPY . /var/www/html/

RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

EXPOSE 80
CMD ["apache2-foreground"]
