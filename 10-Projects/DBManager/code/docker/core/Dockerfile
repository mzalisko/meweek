FROM php:8.4-cli-alpine
RUN docker-php-ext-install pdo_mysql bcmath
WORKDIR /app
EXPOSE 8000
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
