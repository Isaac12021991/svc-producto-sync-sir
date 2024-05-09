FROM ubuntu:20.04

ENV TZ=America/Guayaquil
RUN ln -snf /usr/share/zoneinfo/$TZ /etc/localtime && echo $TZ > /etc/timezone

# set main params
ARG BUILD_ARGUMENT_DEBUG_ENABLED=false
ENV DEBUG_ENABLED=$BUILD_ARGUMENT_DEBUG_ENABLED
ARG BUILD_ARGUMENT_ENV=prod
ENV ENV=$BUILD_ARGUMENT_ENV
ENV APP_HOME /var/www/html
ARG UID=1000
ARG GID=1000
ENV USERNAME=www-data
ENV ACCEPT_EULA=Y


RUN apt-get update
RUN apt-get install vim -y
RUN apt-get install -y software-properties-common curl git

RUN apt-get install -y \
    apt-transport-https \
    ca-certificates \
    gnupg-agent

#install crontab
RUN apt-get update && apt-get -y install cron

#install supervisor
RUN apt-get -y install supervisor

#php 8.2
RUN add-apt-repository ppa:ondrej/php -y
RUN apt-get update
RUN apt-get install php8.2 php8.2-dev php8.2-xml -y --allow-unauthenticated

#Install ODBC
RUN curl https://packages.microsoft.com/keys/microsoft.asc | apt-key add -
RUN curl https://packages.microsoft.com/config/ubuntu/20.04/prod.list > /etc/apt/sources.list.d/mssql-release.list
RUN apt-get update
RUN ACCEPT_EULA=Y apt-get install -y msodbcsql17
RUN ACCEPT_EULA=Y apt-get install -y mssql-tools
RUN apt-get install -y unixodbc-dev

#Install PHP Drivers for SQL Server
RUN pecl install sqlsrv
RUN pecl install pdo_sqlsrv
RUN printf "; priority=20\nextension=sqlsrv.so\n" > /etc/php/8.2/mods-available/sqlsrv.ini
RUN printf "; priority=30\nextension=pdo_sqlsrv.so\n" > /etc/php/8.2/mods-available/pdo_sqlsrv.ini
RUN phpenmod -v 8.2 sqlsrv pdo_sqlsrv

#Install PHP Driver Mysql
RUN apt-get install -y php8.2-mysql php8.2-gd php8.2-curl php8.2-zip php8.2-redis

#Se instala redis-server y redis-tools
# RUN apt-get install -y redis-server redis-tools 

#Se expone el puerto 6379
#EXPOSE 80

#Install NodeJS
RUN curl -sL https://deb.nodesource.com/setup_12.x | bash - 
RUN apt-get install -y nodejs

# install locales
RUN apt-get install -y locales && echo "en_US.UTF-8 UTF-8" > /etc/locale.gen && locale-gen

#Clean Apt cache (using "du -shc" to see the folders length)
RUN apt-get clean
RUN rm -rf /var/lib/apt/lists/*

#disable default site and delete all default files inside APP_HOME
RUN a2dissite 000-default.conf
RUN rm -r $APP_HOME

# create document root, fix permissions for www-data user and change owner to www-data
RUN mkdir -p $APP_HOME/public && \
    mkdir -p /home/$USERNAME && chown $USERNAME:$USERNAME /home/$USERNAME \
    && usermod -u $UID $USERNAME -d /home/$USERNAME \
    && groupmod -g $GID $USERNAME \
    && chown -R ${USERNAME}:${USERNAME} $APP_HOME

# put apache and php config for Laravel, enable sites
COPY ./docker/general/laravel.conf /etc/apache2/sites-available/laravel.conf
COPY ./docker/general/laravel-ssl.conf /etc/apache2/sites-available/laravel-ssl.conf
RUN a2ensite laravel.conf && a2ensite laravel-ssl
COPY ./docker/$BUILD_ARGUMENT_ENV/php.ini /usr/local/etc/php/php.ini

# enable apache modules
RUN a2enmod rewrite
RUN a2enmod ssl

# install composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer
RUN chmod +x /usr/bin/composer
ENV COMPOSER_ALLOW_SUPERUSER 1

# generate certificates
# TODO: change it and make additional logic for production environment
RUN openssl req -x509 -nodes -days 365 -newkey rsa:2048 -keyout /etc/ssl/private/ssl-cert-snakeoil.key -out /etc/ssl/certs/ssl-cert-snakeoil.pem -subj "/C=AT/ST=Vienna/L=Vienna/O=Security/OU=Development/CN=example.com"

# set working directory
WORKDIR $APP_HOME

USER ${USERNAME}

# copy source files and config file
COPY --chown=${USERNAME}:${USERNAME} . $APP_HOME/
COPY --chown=${USERNAME}:${USERNAME} .env.$ENV $APP_HOME/.env

# install all PHP dependencies
RUN if [ "$BUILD_ARGUMENT_ENV" = "dev" ] || [ "$BUILD_ARGUMENT_ENV" = "test" ]; then COMPOSER_MEMORY_LIMIT=-1 composer install --optimize-autoloader --no-interaction --no-progress; \
    else COMPOSER_MEMORY_LIMIT=-1 composer install --optimize-autoloader --no-interaction --no-progress --no-dev; \
    fi

USER root
# add root to www group
RUN chmod -R ug+w /var/www/html

WORKDIR /var/www/html/

#node
#RUN npm install
#RUN npm run dev

COPY ./docker/start.sh /usr/sbin/start.sh
COPY ./docker/cron-schedule.sh /usr/sbin/cron-schedule.sh
COPY ./docker/supervisor/supervisor.conf /etc/supervisord.conf

RUN chmod +x /usr/sbin/start.sh
RUN chmod +x /usr/sbin/cron-schedule.sh

# sobre-escribir Crontab para ejecutar schedule:run
# RUN echo "* * * * *       root    /usr/sbin/cron-schedule.sh >> /var/log/cron-schedule.log 2>&1" >> /etc/crontab
RUN echo "* * * * *       root    cd /var/www/html  && php artisan schedule:run >> /var/log/cron-schedule.log 2>&1" >> /etc/crontab


ENTRYPOINT ["sh","/usr/sbin/start.sh"]

CMD ["/usr/sbin/apache2ctl", "-D", "FOREGROUND"]