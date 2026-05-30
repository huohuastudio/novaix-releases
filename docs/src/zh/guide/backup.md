# 备份与恢复 {#backup}

定期备份是保障数据安全的基本操作。Novaix 的数据备份主要包括数据库文件和配置文件。

## SQLite 备份（默认） {#sqlite}

SQLite 数据库是一个单独的文件，直接复制即可完成备份：

```bash
# 手动备份
cp /opt/novaix/novaix.db /backup/novaix-$(date +%Y%m%d).db
```

设置定期自动备份（编辑 `crontab -e` 添加）：

```bash
# 每天凌晨 3 点自动备份
0 3 * * * cp /opt/novaix/novaix.db /backup/novaix-$(date +\%Y\%m\%d).db
```

## MySQL 备份 {#mysql}

```bash
# 手动备份
mysqldump -u root -p novaix > /backup/novaix-$(date +%Y%m%d).sql
```

设置定期自动备份：

```bash
# 每天凌晨 3 点自动备份
0 3 * * * mysqldump -u novaix_user -pYOUR_PASSWORD novaix > /backup/novaix-$(date +\%Y\%m\%d).sql
```

## 恢复 {#restore}

### 恢复 SQLite {#restore-sqlite}

```bash
# 停止服务
supervisorctl stop novaix

# 恢复数据库文件
cp /backup/novaix-20260530.db /opt/novaix/novaix.db

# 启动服务
supervisorctl start novaix
```

### 恢复 MySQL {#restore-mysql}

```bash
# 停止服务
supervisorctl stop novaix

# 恢复数据库
mysql -u root -p novaix < /backup/novaix-20260530.sql

# 启动服务
supervisorctl start novaix
```

## 配置文件备份 {#config-backup}

除了数据库，您还应该备份以下文件：

- `config.yaml`：系统配置文件
- `data/images/`：上传的镜像和 ISO 文件（如有）

::: tip 备份建议
- 建议将备份文件存储到异地（如对象存储），避免服务器故障时备份也丢失
- 备份前建议停止服务以确保数据一致性，特别是 SQLite 数据库
- 定期检查备份文件是否完整可用
:::

## 哪些数据不在备份范围内 {#not-included}

::: warning 请注意
Novaix 的备份只覆盖了管理面板的数据（数据库、配置、镜像文件）。以下数据**不在备份范围内**，需要您单独处理：

- **节点上运行的实例数据**：实例的磁盘、快照等数据存储在节点服务器上，不在 Novaix 管理面板的服务器上。如果节点服务器损坏，需要依赖节点自身的备份机制。
- **支付渠道的交易记录**：Novaix 数据库中只记录了订单和支付状态，详细的交易流水以支付渠道后台（支付宝、微信等）为准。

建议您同时为每个节点服务器制定独立的备份策略。
:::
