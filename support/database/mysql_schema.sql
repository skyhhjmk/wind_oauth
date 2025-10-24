-- Wind OAuth MySQL Schema

-- 用户表
CREATE TABLE IF NOT EXISTS `users` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(255) NOT NULL UNIQUE,
    `email` VARCHAR(255) NOT NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL,
    `name` VARCHAR(255) NULL,
    `is_admin` TINYINT(1) NOT NULL DEFAULT 0,
    `status` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP NULL,
    `updated_at` TIMESTAMP NULL,
    INDEX `idx_username` (`username`),
    INDEX `idx_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- OAuth客户端表
CREATE TABLE IF NOT EXISTS `oauth_clients` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `name` VARCHAR(255) NOT NULL,
    `client_id` VARCHAR(100) NOT NULL UNIQUE,
    `client_secret` VARCHAR(100) NOT NULL,
    `redirect_uri` TEXT NOT NULL,
    `grant_types` JSON NULL,
    `scope` JSON NULL,
    `status` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP NULL,
    `updated_at` TIMESTAMP NULL,
    INDEX `idx_client_id` (`client_id`),
    INDEX `idx_user_id` (`user_id`),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- OAuth授权码表
CREATE TABLE IF NOT EXISTS `oauth_authorization_codes` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `authorization_code` VARCHAR(255) NOT NULL UNIQUE,
    `client_id` VARCHAR(100) NOT NULL,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `redirect_uri` TEXT NOT NULL,
    `scope` JSON NULL,
    `expires_at` TIMESTAMP NOT NULL,
    `created_at` TIMESTAMP NULL,
    `updated_at` TIMESTAMP NULL,
    INDEX `idx_authorization_code` (`authorization_code`),
    INDEX `idx_client_id` (`client_id`),
    INDEX `idx_user_id` (`user_id`),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- OAuth令牌表
CREATE TABLE IF NOT EXISTS `oauth_tokens` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `access_token` VARCHAR(255) NOT NULL UNIQUE,
    `refresh_token` VARCHAR(255) NULL UNIQUE,
    `client_id` VARCHAR(100) NOT NULL,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `scope` JSON NULL,
    `expires_at` TIMESTAMP NOT NULL,
    `refresh_expires_at` TIMESTAMP NULL,
    `created_at` TIMESTAMP NULL,
    `updated_at` TIMESTAMP NULL,
    INDEX `idx_access_token` (`access_token`),
    INDEX `idx_refresh_token` (`refresh_token`),
    INDEX `idx_client_id` (`client_id`),
    INDEX `idx_user_id` (`user_id`),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- OAuth权限范围表
CREATE TABLE IF NOT EXISTS `oauth_scopes` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `scope` VARCHAR(255) NOT NULL UNIQUE,
    `description` TEXT NULL,
    `is_default` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NULL,
    `updated_at` TIMESTAMP NULL,
    INDEX `idx_scope` (`scope`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 插入默认权限范围
INSERT INTO `oauth_scopes` (`scope`, `description`, `is_default`) VALUES
('basic', '基本信息访问', 1),
('profile', '用户详细信息', 0),
('email', '用户邮箱', 0);

-- 插入默认管理员用户 (密码: admin123)
INSERT INTO `users` (`username`, `email`, `password`, `name`, `is_admin`, `status`, `created_at`, `updated_at`) VALUES
('admin', 'admin@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Administrator', 1, 1, NOW(), NOW());
