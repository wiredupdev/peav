FROM php:8.4-cli-bookworm

# Install system dependencies and tools for development and testing
RUN apt-get update && apt-get install -y --no-install-recommends \
    git \
    unzip \
    zip \
    libzip-dev \
    libpq-dev \
    libsqlite3-dev \
    libicu-dev \
    curl \
    ca-certificates \
    sudo \
    && rm -rf /var/lib/apt/lists/*

# Install official PHP extension installer helper for clean and reliable compilation
ADD https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/

RUN chmod +x /usr/local/bin/install-php-extensions && \
    install-php-extensions \
    pdo_sqlite \
    pdo_mysql \
    pdo_pgsql \
    intl \
    zip \
    pcov \
    opcache

# Copy latest Composer binary
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

# Configure non-root development user for secure workspace isolation
ARG USERNAME=developer
ARG USER_UID=1000
ARG USER_GID=$USER_UID

RUN groupadd --gid $USER_GID $USERNAME \
    && useradd --uid $USER_UID --gid $USER_GID -m -s /bin/bash $USERNAME \
    && echo "$USERNAME ALL=(ALL) NOPASSWD:ALL" > /etc/sudoers.d/$USERNAME \
    && chmod 0440 /etc/sudoers.d/$USERNAME

USER $USERNAME
WORKDIR /workspace
