FROM phpswoole/swoole:php8.1

ENV MONGO_INITDB_ROOT_USERNAME=mongo
ENV MONGO_INITDB_ROOT_PASSWORD=mongo
ENV MONGO_INITDB_DATABASE=dev

RUN apt-get update && \
    apt-get install -y -q \
    apt-utils \
    zsh \
    nano \
    vim \
    make \
    locales \
    g++ \
    gcc \
    git \
    curl \
    debianutils \
    binutils \
    sed \
    curl \
    wget \
    gnupg \
    gnupg2 \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*

RUN wget -qO - https://www.mongodb.org/static/pgp/server-5.0.asc | apt-key add -
RUN echo "deb http://repo.mongodb.org/apt/debian buster/mongodb-org/5.0 main" | tee /etc/apt/sources.list.d/mongodb-org-5.0.list
RUN apt-get update && apt-get install -y mongodb-mongosh

RUN cp /usr/local/etc/php/php.ini-development /usr/local/etc/php/php.ini

RUN pecl channel-update pecl.php.net && pecl install mongodb && pecl config-set php_ini /usr/local/etc/php/php.ini

RUN echo "extension=mongodb.so" >> /usr/local/etc/php/php.ini

RUN sh -c "$(curl -fsSL https://raw.github.com/ohmyzsh/ohmyzsh/master/tools/install.sh)"

ADD ./app /app

WORKDIR /app

ENV TERM xterm-256color

CMD ["/bin/zsh"]