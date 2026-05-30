# 安装 Novaix {#install}

Novaix 编译为单个二进制文件，部署非常简单。您只需要下载二进制文件、编辑配置文件，然后启动即可。

## 下载 {#download}

从 [GitHub Releases](https://github.com/huohuastudio/novaix-releases/releases) 下载最新版本的二进制文件：

```bash
# 下载（以 linux-amd64 为例）
wget https://github.com/huohuastudio/novaix-releases/releases/latest/download/novaix-linux-amd64

# 添加执行权限并移动到系统路径
chmod +x novaix-linux-amd64
mv novaix-linux-amd64 /usr/local/bin/novaix
```

## 准备目录与配置 {#prepare}

```bash
# 创建工作目录
mkdir -p /opt/novaix
cd /opt/novaix

# 首次运行，程序会自动生成默认配置文件
novaix --config config.yaml
# 看到启动日志后按 Ctrl+C 停止，然后编辑配置
```

::: warning 注意
Novaix 的工作目录非常重要，数据库文件（SQLite 模式下）、镜像文件、日志等都会存储在这个目录下。请不要在 `/tmp` 等临时目录中运行 Novaix，也不要随意移动工作目录，否则可能导致数据丢失。
:::

编辑 `config.yaml`，完成以下生产环境必要配置：

```yaml
server:
  mode: release                           # 生产环境必须设为 release
  port: 8080
  external_url: https://panel.example.com # 您的实际域名
  allowed_origins:
    - https://panel.example.com           # CORS 白名单
  trusted_proxies:
    - 127.0.0.1                           # 反向代理 IP

jwt:
  secret: "这里填一个至少16字符的随机字符串"   # 生产环境必须修改

security:
  encryption_key: "这里填另一个随机字符串"     # 用于加密敏感数据

log:
  level: info
  format: json                             # 生产环境推荐 json，便于日志采集
```

::: tip
您可以使用 `openssl rand -hex 32` 来生成随机密钥。`jwt.secret` 和 `security.encryption_key` 建议使用不同的值。
:::

::: warning 注意
`server.mode` 必须设为 `release`，否则系统将以调试模式运行，存在安全风险。`server.external_url` 用于生成邮件链接和回调地址等，必须设置为您的实际域名。
:::

::: danger 关于 encryption_key
`security.encryption_key` 一旦设置并使用后，**切勿修改或丢失**。该密钥用于加密数据库中存储的所有敏感信息（如节点的 SSH 密钥、SMTP 密码等）。如果更换密钥，所有已加密的数据将无法解密，您需要重新配置所有节点证书和敏感信息。
:::

首次启动后，程序会在终端输出初始管理员密码，请务必记录下来。如果您希望指定初始密码，可以在 `config.yaml` 中添加：

```yaml
admin:
  initial_password: "your-password"  # 仅首次启动时生效
```

## 使用 Supervisor 守护进程 {#supervisor}

在生产环境中，您需要使用进程管理工具来保证 Novaix 持续运行。我们推荐使用 Supervisor 或 systemd。

### 安装 Supervisor {#install-supervisor}

::: code-group

```bash [Debian / Ubuntu]
apt install -y supervisor
```

```bash [CentOS / RHEL]
yum install -y supervisor
systemctl enable supervisord
systemctl start supervisord
```

:::

### 配置 Supervisor {#configure-supervisor}

创建配置文件 `/etc/supervisor/conf.d/novaix.conf`：

```ini
[program:novaix]
command=/usr/local/bin/novaix --config /opt/novaix/config.yaml
directory=/opt/novaix
autostart=true
autorestart=true
startsecs=5
startretries=3
user=root
redirect_stderr=true
stdout_logfile=/var/log/novaix/app.log
stdout_logfile_maxbytes=50MB
stdout_logfile_backups=10
```

启动服务：

```bash
# 创建日志目录
mkdir -p /var/log/novaix

# 加载配置并启动
supervisorctl reread
supervisorctl update
supervisorctl start novaix
```

常用管理命令：

```bash
supervisorctl status novaix      # 查看状态
supervisorctl restart novaix     # 重启
supervisorctl stop novaix        # 停止
supervisorctl tail -f novaix     # 实时查看日志
```

## 使用 systemd 守护进程（可选） {#systemd}

如果您更偏好 systemd，创建 `/etc/systemd/system/novaix.service`：

```ini
[Unit]
Description=Novaix IDC Management System
After=network.target

[Service]
Type=simple
User=root
WorkingDirectory=/opt/novaix
ExecStart=/usr/local/bin/novaix --config /opt/novaix/config.yaml
Restart=always
RestartSec=5
LimitNOFILE=65536

[Install]
WantedBy=multi-user.target
```

```bash
systemctl daemon-reload
systemctl enable novaix
systemctl start novaix

# 管理命令
systemctl status novaix          # 查看状态
systemctl restart novaix         # 重启
journalctl -u novaix -f          # 实时查看日志
```

## 配置 Nginx 反向代理 {#nginx}

Novaix 本身仅监听 HTTP，生产环境必须使用反向代理处理 HTTPS。

安装 Nginx 和 Certbot：

```bash
apt install -y nginx certbot python3-certbot-nginx
```

创建 `/etc/nginx/sites-available/novaix`：

```nginx
server {
    listen 80;
    server_name panel.example.com;

    location / {
        proxy_pass http://127.0.0.1:8080;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;

        # WebSocket 支持（终端、控制台、任务日志等功能需要）
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "upgrade";
        proxy_read_timeout 86400;
    }
}
```

启用站点并申请 SSL 证书：

```bash
ln -s /etc/nginx/sites-available/novaix /etc/nginx/sites-enabled/
nginx -t && systemctl reload nginx

# 自动申请 Let's Encrypt 证书
certbot --nginx -d panel.example.com
```

::: warning 注意
WebSocket 支持是必须的，Novaix 的实例终端、控制台、任务日志等功能都依赖 WebSocket 连接。请确保您的 Nginx 配置中包含了 `Upgrade` 和 `Connection` 头的转发。
:::

## 配置 Caddy 反向代理（可选） {#caddy}

如果您更偏好 Caddy，它可以自动处理 HTTPS 证书的申请和续期，配置更为简单。

创建 `/etc/caddy/Caddyfile`：

```
panel.example.com {
    reverse_proxy 127.0.0.1:8080
}
```

```bash
systemctl restart caddy
```

Caddy 会自动申请和续期 SSL 证书，无需额外配置。Caddy 会自动处理 WebSocket 转发，无需额外配置。

## 使用 MySQL 数据库（可选） {#mysql}

如果您的业务规模较大或有高并发需求，可以将数据库从默认的 SQLite 切换到 MySQL。

安装 MySQL：

::: code-group

```bash [Debian / Ubuntu]
apt install -y mysql-server
```

```bash [CentOS / RHEL]
yum install -y mysql-server
systemctl enable mysqld
systemctl start mysqld
```

:::

创建数据库和用户：

```bash
mysql -u root -p
```

```sql
CREATE DATABASE novaix CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'novaix'@'localhost' IDENTIFIED BY 'your-password';
GRANT ALL PRIVILEGES ON novaix.* TO 'novaix'@'localhost';
FLUSH PRIVILEGES;
```

修改 `config.yaml`：

```yaml
database:
  driver: mysql
  dsn: "novaix:your-password@tcp(127.0.0.1:3306)/novaix?charset=utf8mb4&parseTime=True"
  max_open_conns: 25
  max_idle_conns: 10
  conn_max_lifetime: 3600
```

::: warning 注意
- 字符集必须使用 `utf8mb4`，否则某些特殊字符（如 emoji）可能无法正常存储
- 目前不支持从 SQLite 在线迁移到 MySQL，您需要在首次部署时就决定使用哪种数据库
:::

## 验证安装 {#verify}

安装完成后，使用浏览器访问您配置的域名（如 `https://panel.example.com`），您应该能看到登录页面。

使用初始管理员账号登录后，建议您按以下顺序进行初始化配置：

1. 在管理后台「系统设置」中配置 SMTP 邮件服务（用于工单通知、密码重置等）
2. 在管理后台「系统设置」中配置支付渠道（如需在线支付功能）
3. 添加并初始化节点服务器
4. 为节点创建 IP 池
5. 导入镜像并分发到节点
6. 创建套餐分组和套餐
7. 在管理后台「系统设置」中开启用户注册（默认关闭）
8. 发布公告，开始运营

::: warning 注意
许可证密钥需要在 `config.yaml` 中配置，不在管理后台设置。详见[快速开始](./getting-started#license)。
:::

::: tip
以上步骤将在后续章节中逐一详细介绍。
:::
