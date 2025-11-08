# Wind OAuth

## Installation

依赖：

- PHP 8.3+(理论8.1+,未测试)
- PostgreSQL(或 MySQL 或 SQLite)

### Using Docker

```bash
docker run -d -p 8788:8788 --name wind_oauth ghcr.io/skyhhjmk/wind_oauth:latest
```

下载源码包后，根据操作系统不同选择不同的启动命令

### Linux

```bash
php start.php start
```

### Windows

运行 `windows.bat`

或

```bash
php windows.php
```

