# 配置参考 {#config}

Novaix 通过 `config.yaml` 文件进行配置，所有配置项均可通过 `NOVAIX_` 前缀的环境变量覆盖。例如 `server.port` 对应 `NOVAIX_SERVER_PORT`。

环境变量的优先级高于配置文件。

## 完整配置项 {#reference}

| 配置项 | 默认值 | 说明 |
|--------|--------|------|
| `server.host` | `0.0.0.0` | 监听地址 |
| `server.port` | `8080` | 监听端口 |
| `server.mode` | `debug` | 运行模式，`debug` / `release`（生产必须为 `release`） |
| `server.external_url` | - | 对外访问地址，如 `https://panel.example.com`（生产必填） |
| `server.allowed_origins` | `["*"]` | CORS 允许的源（生产环境不可使用 `*`） |
| `server.trusted_proxies` | `[]` | 反向代理 IP，如 `["127.0.0.1"]` |
| `database.driver` | `sqlite` | 数据库类型，`sqlite` / `mysql` |
| `database.dsn` | `novaix.db` | SQLite 文件路径 或 MySQL DSN |
| `database.max_open_conns` | `25` | MySQL 最大打开连接数 |
| `database.max_idle_conns` | `10` | MySQL 最大空闲连接数 |
| `database.conn_max_lifetime` | `3600` | MySQL 连接最大存活时间（秒） |
| `jwt.secret` | - | JWT 签名密钥（生产必填，≥16 字符） |
| `jwt.expire` | `24h` | Token 过期时间 |
| `security.encryption_key` | - | AES-256 加密密钥，用于加密敏感数据（生产必填） |
| `log.level` | `info` | 日志级别，`debug` / `info` / `warn` / `error` |
| `log.format` | `text` | 日志格式，`text`（彩色终端）/ `json`（日志采集） |
| `task.workers` | `5` | 异步任务并发数 |
| `collector.interval` | `60` | 监控数据采集间隔（秒） |
| `collector.retention` | `720` | 监控数据保留时间（小时，默认 30 天） |
| `collector.timeout` | `10` | 单次采集超时（秒） |
| `storage.image_dir` | `data/images` | 镜像和 ISO 文件存储目录 |
| `incus.insecure_skip_verify` | `false` | 是否跳过节点 TLS 证书验证 |
| `admin.initial_password` | - | 初始管理员密码（空时自动生成，仅首次启动生效） |
| `license.key` | - | 许可证密钥 |
| `license.service_api` | - | 许可证验证服务地址 |
| `demo.enabled` | `false` | 演示模式（定期重置数据） |
| `demo.reset_interval` | `1h` | 演示模式数据重置间隔 |

## MySQL DSN 格式 {#mysql-dsn}

如果您使用 MySQL 数据库，`database.dsn` 的格式为：

```
user:password@tcp(host:port)/dbname?charset=utf8mb4&parseTime=True
```

示例：

```yaml
database:
  driver: mysql
  dsn: novaix:your-password@tcp(127.0.0.1:3306)/novaix?charset=utf8mb4&parseTime=True
  max_open_conns: 25
  max_idle_conns: 10
  conn_max_lifetime: 3600
```

## 生产环境清单 {#production-checklist}

> [!IMPORTANT]
> 部署到生产环境前，请确保完成以下配置：
> 1. 设置 `server.mode: release`
> 2. 设置 `jwt.secret` 为强随机字符串（≥16 字符）
> 3. 设置 `security.encryption_key` 为独立的加密密钥
> 4. 设置 `server.external_url` 为实际域名
> 5. 设置 `server.allowed_origins` 为具体域名（不使用 `*`）
> 6. 确保 `demo.enabled: false`
> 7. 使用 Nginx 或 Caddy 配置反向代理和 TLS
> 8. 配置 SMTP 邮件（通过管理后台系统设置）
> 9. 配置支付渠道（通过管理后台系统设置）
