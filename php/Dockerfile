ARG PHP_CLI_VERSION=8.1
ARG CONTAINER_PACKAGE_URL=mirrors.ustc.edu.cn

FROM mlocati/php-extension-installer AS php-extension-installer
FROM php:"${PHP_CLI_VERSION}-cli-alpine"

RUN if [ $CONTAINER_PACKAGE_URL ] ; then sed -i "s/dl-cdn.alpinelinux.org/${CONTAINER_PACKAGE_URL}/g" /etc/apk/repositories ; fi

RUN apk add --no-cache supervisor unzip shadow su-exec

COPY --from=php-extension-installer /usr/bin/install-php-extensions /usr/local/bin/
RUN chmod uga+x /usr/local/bin/install-php-extensions
RUN install-php-extensions \
    apcu\
    bcmath \
    bz2 \
    event \
    exif\
    ffi\
    gd \
    igbinary \
    imagick \
    mysqli \
    pdo_mysql \
    pdo_pgsql \
    opcache \
    pcntl \
    redis \
    memcached \
    mongodb \
    sockets \
    zip \
    @composer

RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

COPY php.ini "$PHP_INI_DIR/conf.d/99-sail.ini"

RUN rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*

ARG WWWGROUP
RUN groupadd --force -g $WWWGROUP sail && useradd -ms /bin/bash --no-user-group -g $WWWGROUP -u 1337 sail

COPY supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY start-container /usr/local/bin/start-container
RUN chmod +x /usr/local/bin/start-container

WORKDIR /www
EXPOSE 8787

ENTRYPOINT ["start-container"]