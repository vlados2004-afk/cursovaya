# Dockerfile для PHP/HTML проекта
FROM php:8.2-apache

# Метаданные
LABEL maintainer="ваш-email@example.com"
LABEL version="1.0"

# Включаем необходимые PHP модули
RUN docker-php-ext-install pdo pdo_mysql mysqli

# Копируем все файлы проекта в папку Apache
COPY . /var/www/html/

# Устанавливаем правильные права
RUN chown -R www-data:www-data /var/www/html && \
    chmod -R 755 /var/www/html

# Включаем модуль rewrite для чистых URL
RUN a2enmod rewrite

# Открываем порт 80
EXPOSE 80

# Команда запуска Apache
CMD ["apache2-foreground"]