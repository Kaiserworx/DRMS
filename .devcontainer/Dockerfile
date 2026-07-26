FROM mcr.microsoft.com/devcontainers/php:3-8.4-bookworm

USER root

RUN apt-get update \
    && export DEBIAN_FRONTEND=noninteractive \
    && apt-get install -y --no-install-recommends \
        default-mysql-client \
        libicu-dev \
        libonig-dev \
        libxml2-dev \
        libzip-dev \
    && docker-php-ext-install -j"$(nproc)" \
        bcmath \
        dom \
        intl \
        mbstring \
        pcntl \
        pdo_mysql \
        xmlreader \
        zip \
    && rm -rf /var/lib/apt/lists/*

USER vscode
