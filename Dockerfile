FROM ubuntu:22.04
ENV DEBIAN_FRONTEND=noninteractive
ENV COMPOSER_ALLOW_SUPERUSER=1

RUN apt-get update \
  && apt-get -y install apt-utils \
  && apt-get -y upgrade \
  && apt-get -y install wget curl nano zip unzip git openssl sqlite3 build-essential software-properties-common cron supervisor gnupg tzdata

RUN wget -O PaloAlto_SSLInspection_ForwardTrust.crt https://tetrapi.pt/gp/PaloAlto_SSLInspection_ForwardTrust.crt
RUN cp PaloAlto_SSLInspection_ForwardTrust.crt /usr/local/share/ca-certificates/
RUN update-ca-certificates

RUN echo "UTC" >> /etc/timezone \
  && dpkg-reconfigure -f noninteractive tzdata

RUN add-apt-repository ppa:ondrej/php \
    && apt-get update \
    && apt-get -y install xmlsec1 libxmlsec1-openssl nginx php8.4-fpm php8.4-cli php8.4-curl php8.4-mbstring \
        php8.4-mysql php8.4-gd php8.4-bcmath php8.4-readline \
        php8.4-zip php8.4-imap php8.4-xml php8.4-intl php8.4-soap \
        php8.4-memcache php8.4-memcached php8.4-yaml php8.4-dom php8.4-ldap php8.4-gnupg supervisor ca-certificates curl gnupg && mkdir -p /var/log/supervisor \
        && rm -rf /var/lib/apt/lists/*

RUN curl -fsSL https://deb.nodesource.com/gpgkey/nodesource-repo.gpg.key | gpg --dearmor -o /etc/apt/keyrings/nodesource.gpg
RUN echo "deb [signed-by=/etc/apt/keyrings/nodesource.gpg] https://deb.nodesource.com/node_20.x nodistro main" | tee /etc/apt/sources.list.d/nodesource.list
RUN apt-get update -y && apt-get install nodejs yarn -y
RUN php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');" \
    && php composer-setup.php --install-dir=/usr/bin --filename=composer

RUN ln -fs /usr/share/zoneinfo/UTC /etc/localtime
RUN composer self-update --2
#RUN rm -rf /var/lib/apt/lists/*
RUN mkdir -p /var/www/.gnupg && chown -R www-data:www-data /var/www/.gnupg
WORKDIR /var/www/.gnupg
RUN chmod 700 /var/www/.gnupg
WORKDIR /var/www/openroaming
COPY . /var/www/openroaming/
COPY ./.env.sample /var/www/openroaming/.env
RUN composer install
RUN npm config set cafile /usr/local/share/ca-certificates/PaloAlto_SSLInspection_ForwardTrust.crt
RUN npm config set strict-ssl false
RUN echo 'cafile "/usr/local/share/ca-certificates/PaloAlto_SSLInspection_ForwardTrust.crt"' >> ~/.yarnrc
RUN echo 'strict-ssl false' >> ~/.yarnrc
RUN echo 'httpsCaFilePath: "/usr/local/share/ca-certificates/PaloAlto_SSLInspection_ForwardTrust.crt"' >> ~/.yarnrc.yml
RUN echo 'enableStrictSsl: false' >> ~/.yarnrc.yml
RUN npm i && npm run build
RUN rm -rf /var/www/openroaming/.env

COPY service-config/supervisor/supervisord.conf /etc/supervisor/conf.d/
COPY service-config/nginx/nginx.conf /etc/nginx/nginx.conf
COPY service-config/nginx/mime.types /etc/nginx/mime.types
COPY service-config/nginx/fastcgi_params /etc/nginx/fastcgi_params
COPY service-config/nginx/sites /etc/nginx/conf.d
COPY --chown=www-data:www-data . /var/www/openroaming
RUN mkdir /run/php
CMD ["/usr/bin/supervisord"]

