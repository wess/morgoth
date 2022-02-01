FROM phpswoole/swoole:php8.1-alpine

ENV MONGO_INITDB_ROOT_USERNAME=mongo
ENV MONGO_INITDB_ROOT_PASSWORD=mongo
ENV MONGO_INITDB_DATABASE=dev

RUN apk add --no-cache \
    zsh \
    bash \
    sed \
    git \
    g++ \
    gcc \
    make \
    sed \
    autoconf \
    gnupg \
    openssl \
    openrc \
    openssl-dev 

RUN echo 'http://dl-cdn.alpinelinux.org/alpine/v3.9/main' >> /etc/apk/repositories
RUN echo 'http://dl-cdn.alpinelinux.org/alpine/v3.9/community' >> /etc/apk/repositories

RUN apk update
RUN apk upgrade
RUN apk add -v --no-cache mongodb

VOLUME ["/data/db"]

COPY mongo_run.sh /root
RUN sh /root/mongo_run.sh

RUN cp /usr/local/etc/php/php.ini-development /usr/local/etc/php/php.ini

RUN pecl channel-update pecl.php.net && pecl install mongodb && pecl config-set php_ini /usr/local/etc/php/php.ini

RUN echo "extension=mongodb.so" >> /usr/local/etc/php/php.ini

RUN sh -c "$(curl -fsSL https://raw.github.com/ohmyzsh/ohmyzsh/master/tools/install.sh)"

ADD ./app /app

WORKDIR /app

ENV TERM xterm-256color

CMD ["/bin/zsh"]