#!/bin/sh
cd /var/www/html 
nohup php artisan serve --host=0.0.0.0 --port=8080 &
/usr/bin/supervisord -c /etc/supervisord.conf