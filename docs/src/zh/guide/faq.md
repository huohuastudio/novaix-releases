# 常见问题 {#faq}

## 首次启动后管理员密码在哪里？ {#admin-password}

首次启动时，如果未在 `config.yaml` 中设置 `admin.initial_password`，系统会自动生成一个 16 位随机密码并输出到终端。如果使用 Supervisor 或 systemd，可以在日志中查找：

```bash
# Supervisor（密码输出到 stderr，Supervisor 默认会将其重定向到日志）
grep "密码" /var/log/novaix/app.log

# systemd
journalctl -u novaix | grep "密码"
```

::: warning 注意
自动生成的密码只会在首次启动时输出一次。如果您没有及时记录，将无法再次查看。建议在部署时通过 `config.yaml` 的 `admin.initial_password` 指定初始密码（仅首次启动生效）。
:::

## 忘记了管理员密码怎么办？ {#forgot-password}

如果您配置了 SMTP 邮件服务，可以通过登录页面的「忘记密码」功能重置密码。如果没有配置邮件服务，目前暂时没有其他方式重置密码，请务必妥善保管管理员密码。

## 环境变量和配置文件的关系？ {#env-vs-config}

所有配置项都可以通过 `NOVAIX_` 前缀的环境变量覆盖。例如 `server.port` 对应 `NOVAIX_SERVER_PORT`，`jwt.secret` 对应 `NOVAIX_JWT_SECRET`。环境变量的优先级高于配置文件。

## 可以同时使用 SQLite 和 MySQL 吗？ {#sqlite-or-mysql}

不可以，同一时间只能使用一种数据库。如果需要从 SQLite 切换到 MySQL，需要手动迁移数据。

## 节点连接失败怎么排查？ {#node-connection}

1. 确认节点服务器的运行环境已正常启动
2. 确认管理端口（默认 8443）已开放，没有被防火墙拦截
3. 确认客户端证书和密钥正确
4. 如果配置了 `incus.insecure_skip_verify: false`，确认节点的 TLS 证书有效

## WebSocket 连接失败（终端/控制台无法打开）？ {#websocket}

通常是反向代理配置问题。请确保您的 Nginx 或 Caddy 配置正确转发了 WebSocket 连接。参考[安装](./install#nginx)章节中的 Nginx 配置示例，确保包含了 `Upgrade` 和 `Connection` 头的转发。

## 支付回调没有生效？ {#payment-callback}

1. 确认回调地址（如 `https://您的域名/api/callbacks/alipay`）可以从公网访问
2. 确认使用的是 HTTPS
3. 检查支付渠道的密钥配置是否正确
4. 查看 Novaix 日志中是否有相关错误信息

## 镜像上传中断了怎么办？ {#upload-resume}

镜像上传基于 TUS 协议实现了断点续传，刷新页面后重新选择同一文件上传即可自动从中断处继续，无需重新开始。

## 如何修改监听端口？ {#change-port}

修改 `config.yaml` 中的 `server.port`，或通过环境变量 `NOVAIX_SERVER_PORT` 设置。修改后需要重启服务。同时记得同步修改反向代理中的 `proxy_pass` 地址。

## TOTP 双因素认证丢失了怎么办？ {#totp-lost}

如果您丢失了 TOTP 设备（手机损坏、应用被删除等），目前暂时没有自助恢复方式。请在绑定 TOTP 时妥善保管恢复码或将密钥备份在安全的地方。

## 用户购买后实例一直显示「创建中」？ {#instance-creating}

1. 检查「任务管理」中对应的创建任务状态和日志
2. 如果任务失败，查看日志中的错误信息。常见原因包括：
   - 节点连接中断或服务端口不可达
   - 镜像未分发到目标节点
   - 节点磁盘空间不足
   - IP 池没有空闲 IP
3. 如果任务长时间处于「运行中」无进展，可能是节点负载过高或网络中断

## 实例到期后会被直接删除吗？ {#instance-expiry}

实例到期后系统会自动停止实例，不会立即删除。具体的到期处理策略取决于您在系统设置中的配置。建议设置合理的宽限期，给用户足够的时间续费。

## 更换了服务器怎么迁移？ {#migration}

1. 在旧服务器上停止 Novaix 服务
2. 将整个工作目录（默认 `/opt/novaix`）复制到新服务器
3. 将二进制文件 `/usr/local/bin/novaix` 复制到新服务器
4. 在新服务器上启动服务
5. 更新反向代理配置和 DNS 解析

如果使用 SQLite，数据库文件在工作目录下，一起复制即可。如果使用 MySQL，还需要单独迁移数据库。

::: warning 注意
迁移后请确保 `config.yaml` 中的 `server.external_url` 和 `server.allowed_origins` 与新的域名一致。如果域名不变则无需修改。
:::

## debug 模式和 release 模式有什么区别？ {#debug-vs-release}

| 特性 | debug | release |
|------|-------|---------|
| 错误详情 | 在 API 响应中返回详细错误堆栈 | 只返回通用错误信息 |
| API 文档 | 可访问 `/docs` 查看 | 不可访问 |
| 日志级别 | 输出更详细的调试日志 | 仅输出 info 及以上级别 |
| 演示模式 | 可启用 | 不可启用 |

生产环境**必须**使用 `release` 模式。`debug` 模式仅用于开发和测试，在生产环境中使用会暴露敏感的错误信息和接口文档，存在安全风险。

## 数据库文件越来越大怎么办？ {#database-size}

如果使用 SQLite，数据库文件会随着监控数据、操作日志等的累积而增大。您可以：

1. 调低 `collector.retention`（监控数据保留时间），减少历史监控数据
2. 定期在管理面板中清理过期的操作日志
3. 使用 SQLite 的 `VACUUM` 命令回收空间：
   ```bash
   # 停止 Novaix 后执行
   sqlite3 /opt/novaix/novaix.db "VACUUM;"
   ```
