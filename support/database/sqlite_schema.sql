-- Wind OAuth SQLite Schema

-- 用户表
CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username TEXT NOT NULL UNIQUE,
    email TEXT NOT NULL UNIQUE,
    password TEXT NOT NULL,
    name TEXT,
    is_admin INTEGER NOT NULL DEFAULT 0,
    status INTEGER NOT NULL DEFAULT 1,
    created_at TEXT,
    updated_at TEXT
);

CREATE INDEX IF NOT EXISTS idx_users_username ON users(username);
CREATE INDEX IF NOT EXISTS idx_users_email ON users(email);

-- OAuth客户端表
CREATE TABLE IF NOT EXISTS oauth_clients (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    name TEXT NOT NULL,
    client_id TEXT NOT NULL UNIQUE,
    client_secret TEXT NOT NULL,
    redirect_uri TEXT NOT NULL,
    grant_types TEXT,
    scope TEXT,
    status INTEGER NOT NULL DEFAULT 1,
    created_at TEXT,
    updated_at TEXT,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_oauth_clients_client_id ON oauth_clients(client_id);
CREATE INDEX IF NOT EXISTS idx_oauth_clients_user_id ON oauth_clients(user_id);

-- OAuth授权码表
CREATE TABLE IF NOT EXISTS oauth_authorization_codes (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    authorization_code TEXT NOT NULL UNIQUE,
    client_id TEXT NOT NULL,
    user_id INTEGER NOT NULL,
    redirect_uri TEXT NOT NULL,
    scope TEXT,
    expires_at TEXT NOT NULL,
    created_at TEXT,
    updated_at TEXT,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_oauth_authorization_codes_code ON oauth_authorization_codes(authorization_code);
CREATE INDEX IF NOT EXISTS idx_oauth_authorization_codes_client_id ON oauth_authorization_codes(client_id);
CREATE INDEX IF NOT EXISTS idx_oauth_authorization_codes_user_id ON oauth_authorization_codes(user_id);

-- OAuth令牌表
CREATE TABLE IF NOT EXISTS oauth_tokens (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    access_token TEXT NOT NULL UNIQUE,
    refresh_token TEXT UNIQUE,
    client_id TEXT NOT NULL,
    user_id INTEGER NOT NULL,
    scope TEXT,
    expires_at TEXT NOT NULL,
    refresh_expires_at TEXT,
    created_at TEXT,
    updated_at TEXT,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_oauth_tokens_access_token ON oauth_tokens(access_token);
CREATE INDEX IF NOT EXISTS idx_oauth_tokens_refresh_token ON oauth_tokens(refresh_token);
CREATE INDEX IF NOT EXISTS idx_oauth_tokens_client_id ON oauth_tokens(client_id);
CREATE INDEX IF NOT EXISTS idx_oauth_tokens_user_id ON oauth_tokens(user_id);

-- OAuth权限范围表
CREATE TABLE IF NOT EXISTS oauth_scopes (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    scope TEXT NOT NULL UNIQUE,
    description TEXT,
    is_default INTEGER NOT NULL DEFAULT 0,
    created_at TEXT,
    updated_at TEXT
);

CREATE INDEX IF NOT EXISTS idx_oauth_scopes_scope ON oauth_scopes(scope);

-- 插入默认权限范围
INSERT OR IGNORE INTO oauth_scopes (scope, description, is_default, created_at, updated_at) VALUES
('basic', '基本信息访问', 1, datetime('now'), datetime('now')),
('profile', '用户详细信息', 0, datetime('now'), datetime('now')),
('email', '用户邮箱', 0, datetime('now'), datetime('now'));

-- 插入默认管理员用户 (密码: admin123)
INSERT OR IGNORE INTO users (username, email, password, name, is_admin, status, created_at, updated_at) VALUES
('admin', 'admin@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Administrator', 1, 1, datetime('now'), datetime('now'));
