CREATE DATABASE IF NOT EXISTS trend_api CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS 'trend_api_user'@'localhost' IDENTIFIED BY 'Tr3ndAp1_S3cur3_2026';
GRANT ALL PRIVILEGES ON trend_api.* TO 'trend_api_user'@'localhost';
FLUSH PRIVILEGES;
