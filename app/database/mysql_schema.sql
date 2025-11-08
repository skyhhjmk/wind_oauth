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
    `redirect_dynamic_enabled` TINYINT(1) NOT NULL DEFAULT 0,
    `redirect_whitelist` JSON NULL,
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

-- OAuth第三方提供商表
CREATE TABLE IF NOT EXISTS `oauth_providers` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(255) NOT NULL COMMENT '提供商名称',
    `slug` VARCHAR(100) NOT NULL UNIQUE COMMENT '提供商标识（URL友好）',
    `client_id` VARCHAR(255) NOT NULL COMMENT '客户端ID',
    `client_secret` VARCHAR(255) NOT NULL COMMENT '客户端密钥',
    `authorize_url` VARCHAR(500) NOT NULL COMMENT '授权端点URL',
    `token_url` VARCHAR(500) NOT NULL COMMENT '令牌端点URL',
    `userinfo_url` VARCHAR(500) NOT NULL COMMENT '用户信息端点URL',
    `scope` VARCHAR(500) NULL COMMENT '请求的权限范围',
    `icon_class` VARCHAR(100) NULL COMMENT '图标CSS类',
    `button_color` VARCHAR(50) DEFAULT '#4F46E5' COMMENT '按钮颜色',
    `status` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '状态：1启用，0禁用',
    `sort_order` INT NOT NULL DEFAULT 0 COMMENT '排序',
    `created_at` TIMESTAMP NULL,
    `updated_at` TIMESTAMP NULL,
    INDEX `idx_slug` (`slug`),
    INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='第三方OAuth提供商配置表';

-- OAuth第三方账号绑定表
CREATE TABLE IF NOT EXISTS `oauth_user_bindings` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` BIGINT UNSIGNED NOT NULL COMMENT '本地用户ID',
    `provider_id` BIGINT UNSIGNED NOT NULL COMMENT 'OAuth提供商ID',
    `provider_user_id` VARCHAR(255) NOT NULL COMMENT '第三方用户ID',
    `provider_username` VARCHAR(255) NULL COMMENT '第三方用户名',
    `provider_email` VARCHAR(255) NULL COMMENT '第三方邮箱',
    `provider_avatar` VARCHAR(500) NULL COMMENT '第三方头像',
    `access_token` TEXT NULL COMMENT '访问令牌',
    `refresh_token` TEXT NULL COMMENT '刷新令牌',
    `token_expires_at` TIMESTAMP NULL COMMENT '令牌过期时间',
    `created_at` TIMESTAMP NULL,
    `updated_at` TIMESTAMP NULL,
    INDEX `idx_user_id` (`user_id`),
    INDEX `idx_provider_id` (`provider_id`),
    INDEX `idx_provider_user_id` (`provider_user_id`),
    UNIQUE KEY `unique_binding` (`provider_id`, `provider_user_id`),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`provider_id`) REFERENCES `oauth_providers`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='用户OAuth账号绑定表';

-- 插入默认权限范围
INSERT INTO `oauth_scopes` (`scope`, `description`, `is_default`) VALUES
('basic', '基本信息访问', 1),
('profile', '用户详细信息', 0),
('email', '用户邮箱', 0);

-- 插入默认管理员用户 (密码: admin123)
INSERT INTO `users` (`username`, `email`, `password`, `name`, `is_admin`, `status`, `created_at`, `updated_at`) VALUES
('admin', 'admin@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Administrator', 1, 1, NOW(), NOW());

-- 插入示例OAuth提供商（GitHub）
INSERT INTO `oauth_providers` (`name`, `slug`, `client_id`, `client_secret`, `authorize_url`, `token_url`, `userinfo_url`, `scope`, `icon_class`, `button_color`, `status`, `sort_order`) VALUES
('GitHub', 'github', 'YOUR_GITHUB_CLIENT_ID', 'YOUR_GITHUB_CLIENT_SECRET', 'https://github.com/login/oauth/authorize', 'https://github.com/login/oauth/access_token', 'https://api.github.com/user', 'user:email', 'fa fa-github', '#24292e', 0, 1);

-- 插入示例OAuth提供商（Google）
INSERT INTO `oauth_providers` (`name`, `slug`, `client_id`, `client_secret`, `authorize_url`, `token_url`, `userinfo_url`, `scope`, `icon_class`, `button_color`, `status`, `sort_order`) VALUES
('Google', 'google', 'YOUR_GOOGLE_CLIENT_ID', 'YOUR_GOOGLE_CLIENT_SECRET', 'https://accounts.google.com/o/oauth2/v2/auth', 'https://oauth2.googleapis.com/token', 'https://www.googleapis.com/oauth2/v2/userinfo', 'openid email profile', 'fa fa-google', '#DB4437', 0, 2);
