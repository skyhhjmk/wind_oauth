-- Wind OAuth PostgreSQL Schema

-- 用户表
CREATE TABLE IF NOT EXISTS users (
    id BIGSERIAL PRIMARY KEY,
    username VARCHAR(255) NOT NULL UNIQUE,
    email VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    name VARCHAR(255),
    is_admin SMALLINT NOT NULL DEFAULT 0,
    status SMALLINT NOT NULL DEFAULT 1,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_users_username ON users(username);
CREATE INDEX IF NOT EXISTS idx_users_email ON users(email);

-- OAuth客户端表
CREATE TABLE IF NOT EXISTS oauth_clients (
    id BIGSERIAL PRIMARY KEY,
    user_id BIGINT NOT NULL,
    name VARCHAR(255) NOT NULL,
    client_id VARCHAR(100) NOT NULL UNIQUE,
    client_secret VARCHAR(100) NOT NULL,
    redirect_uri TEXT NOT NULL,
    redirect_dynamic_enabled SMALLINT NOT NULL DEFAULT 0,
    redirect_whitelist JSONB,
    grant_types JSONB,
    scope JSONB,
    status SMALLINT NOT NULL DEFAULT 1,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_oauth_clients_client_id ON oauth_clients(client_id);
CREATE INDEX IF NOT EXISTS idx_oauth_clients_user_id ON oauth_clients(user_id);

-- OAuth授权码表
CREATE TABLE IF NOT EXISTS oauth_authorization_codes (
    id BIGSERIAL PRIMARY KEY,
    authorization_code VARCHAR(255) NOT NULL UNIQUE,
    client_id VARCHAR(100) NOT NULL,
    user_id BIGINT NOT NULL,
    redirect_uri TEXT NOT NULL,
    scope JSONB,
    expires_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_oauth_authorization_codes_code ON oauth_authorization_codes(authorization_code);
CREATE INDEX IF NOT EXISTS idx_oauth_authorization_codes_client_id ON oauth_authorization_codes(client_id);
CREATE INDEX IF NOT EXISTS idx_oauth_authorization_codes_user_id ON oauth_authorization_codes(user_id);

-- OAuth令牌表
CREATE TABLE IF NOT EXISTS oauth_tokens (
    id BIGSERIAL PRIMARY KEY,
    access_token VARCHAR(255) NOT NULL UNIQUE,
    refresh_token VARCHAR(255) UNIQUE,
    client_id VARCHAR(100) NOT NULL,
    user_id BIGINT NOT NULL,
    scope JSONB,
    expires_at TIMESTAMP NOT NULL,
    refresh_expires_at TIMESTAMP,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_oauth_tokens_access_token ON oauth_tokens(access_token);
CREATE INDEX IF NOT EXISTS idx_oauth_tokens_refresh_token ON oauth_tokens(refresh_token);
CREATE INDEX IF NOT EXISTS idx_oauth_tokens_client_id ON oauth_tokens(client_id);
CREATE INDEX IF NOT EXISTS idx_oauth_tokens_user_id ON oauth_tokens(user_id);

-- OAuth权限范围表
CREATE TABLE IF NOT EXISTS oauth_scopes (
    id BIGSERIAL PRIMARY KEY,
    scope VARCHAR(255) NOT NULL UNIQUE,
    description TEXT,
    is_default SMALLINT NOT NULL DEFAULT 0,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_oauth_scopes_scope ON oauth_scopes(scope);

-- OAuth第三方提供商表
CREATE TABLE IF NOT EXISTS oauth_providers (
    id BIGSERIAL PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    slug VARCHAR(100) NOT NULL UNIQUE,
    client_id VARCHAR(255) NOT NULL,
    client_secret VARCHAR(255) NOT NULL,
    authorize_url VARCHAR(500) NOT NULL,
    token_url VARCHAR(500) NOT NULL,
    userinfo_url VARCHAR(500) NOT NULL,
    scope VARCHAR(500),
    icon_class VARCHAR(100),
    button_color VARCHAR(50) DEFAULT '#4F46E5',
    status SMALLINT NOT NULL DEFAULT 1,
    sort_order INTEGER NOT NULL DEFAULT 0,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_oauth_providers_slug ON oauth_providers(slug);
CREATE INDEX IF NOT EXISTS idx_oauth_providers_status ON oauth_providers(status);

-- OAuth第三方账号绑定表
CREATE TABLE IF NOT EXISTS oauth_user_bindings (
    id BIGSERIAL PRIMARY KEY,
    user_id BIGINT NOT NULL,
    provider_id BIGINT NOT NULL,
    provider_user_id VARCHAR(255) NOT NULL,
    provider_username VARCHAR(255),
    provider_email VARCHAR(255),
    provider_avatar VARCHAR(500),
    access_token TEXT,
    refresh_token TEXT,
    token_expires_at TIMESTAMP,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (provider_id) REFERENCES oauth_providers(id) ON DELETE CASCADE,
    UNIQUE (provider_id, provider_user_id)
);

CREATE INDEX IF NOT EXISTS idx_oauth_user_bindings_user_id ON oauth_user_bindings(user_id);
CREATE INDEX IF NOT EXISTS idx_oauth_user_bindings_provider_id ON oauth_user_bindings(provider_id);
CREATE INDEX IF NOT EXISTS idx_oauth_user_bindings_provider_user_id ON oauth_user_bindings(provider_user_id);

-- 插入默认权限范围
INSERT INTO oauth_scopes (scope, description, is_default, created_at, updated_at) VALUES
('basic', '基本信息访问', 1, NOW(), NOW()),
('profile', '用户详细信息', 0, NOW(), NOW()),
('email', '用户邮箱', 0, NOW(), NOW())
ON CONFLICT (scope) DO NOTHING;

-- 插入默认管理员用户 (密码: admin123)
INSERT INTO users (username, email, password, name, is_admin, status, created_at, updated_at) VALUES
('admin', 'admin@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Administrator', 1, 1, NOW(), NOW())
ON CONFLICT (username) DO NOTHING;

-- 插入示例OAuth提供商（GitHub）
INSERT INTO oauth_providers (name, slug, client_id, client_secret, authorize_url, token_url, userinfo_url, scope, icon_class, button_color, status, sort_order) VALUES
('GitHub', 'github', 'YOUR_GITHUB_CLIENT_ID', 'YOUR_GITHUB_CLIENT_SECRET', 'https://github.com/login/oauth/authorize', 'https://github.com/login/oauth/access_token', 'https://api.github.com/user', 'user:email', 'fa fa-github', '#24292e', 0, 1)
ON CONFLICT (slug) DO NOTHING;

-- 插入示例OAuth提供商（Google）
INSERT INTO oauth_providers (name, slug, client_id, client_secret, authorize_url, token_url, userinfo_url, scope, icon_class, button_color, status, sort_order) VALUES
('Google', 'google', 'YOUR_GOOGLE_CLIENT_ID', 'YOUR_GOOGLE_CLIENT_SECRET', 'https://accounts.google.com/o/oauth2/v2/auth', 'https://oauth2.googleapis.com/token', 'https://www.googleapis.com/oauth2/v2/userinfo', 'openid email profile', 'fa fa-google', '#DB4437', 0, 2)
ON CONFLICT (slug) DO NOTHING;
