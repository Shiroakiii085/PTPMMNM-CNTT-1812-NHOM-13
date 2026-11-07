FROM php:8.2-cli-alpine

# Cài đặt extension PostgreSQL cho PHP
RUN apk add --no-cache postgresql-dev \
    && docker-php-ext-install pgsql pdo_pgsql

# Thiết lập thư mục làm việc
WORKDIR /app

# Sao chép toàn bộ mã nguồn vào container
COPY . /app

# Mở cổng (Render, Railway hoặc Koyeb sẽ tự động truyền biến PORT)
ENV PORT=8205
EXPOSE 8205

# Khởi chạy ứng dụng MediRecord EMR
CMD ["sh", "-c", "php -S 0.0.0.0:${PORT:-8205} -t Ma_nguon/sample-app/public"]
