<?php
/**
 * 辅助函数
 */

/**
 * 初始化SQLite数据库表结构
 */
function init_sqlite_database($dbPath) {
    if (!file_exists($dbPath)) {
        // 创建数据库文件
        touch($dbPath);
    }
    
    $pdo = new PDO('sqlite:' . $dbPath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // 检查表是否已存在
    $result = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='users'");
    if ($result->fetch()) {
        return; // 表已存在
    }
    
    // 读取并执行SQL脚本
    $sqlFile = base_path() . '/support/database/sqlite_schema.sql';
    if (file_exists($sqlFile)) {
        $sql = file_get_contents($sqlFile);
        $pdo->exec($sql);
        echo "SQLite database initialized successfully!\n";
    }
}
