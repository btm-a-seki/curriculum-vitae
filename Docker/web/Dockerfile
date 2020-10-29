FROM php:apache

# composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

SHELL ["/bin/bash", "-l", "-c"]

# PHPライブラリのインストール
RUN apt-get update
RUN apt-get -y install vim git libicu-dev libpq-dev libonig-dev libzip-dev libxslt-dev libpng-dev zip unzip
RUN docker-php-ext-install intl pdo pdo_pgsql pdo_mysql mysqli pgsql zip xsl gd

# Mecabのインストール
RUN apt-get -y install mecab libmecab-dev mecab-ipadic
RUN apt-get -y install mecab-ipadic-utf8

# MecabのNeoLogdのインストール、及び辞書の書き換え
RUN cd /var/tmp
RUN git clone https://github.com/neologd/mecab-ipadic-neologd.git
RUN mkdir -p /usr/lib/x86_64-linux-gnu/mecab/dic
RUN mecab-ipadic-neologd/bin/install-mecab-ipadic-neologd -y

# apacheのrewriteを有効にする
RUN cd /etc/apache2/mods-enabled \
    && ln -s ../mods-available/rewrite.load
RUN a2enmod rewrite
