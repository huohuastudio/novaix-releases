# 升级 {#upgrade}

Novaix 的升级非常简单，只需要下载新版本的二进制文件替换旧的，然后重启服务即可。数据库迁移会在启动时自动执行，无需手动操作。

## 升级步骤 {#steps}

```bash
# 1. 下载新版本
wget https://github.com/huohuastudio/novaix-releases/releases/latest/download/novaix-linux-amd64 -O /usr/local/bin/novaix
chmod +x /usr/local/bin/novaix

# 2. 重启服务
supervisorctl restart novaix
# 或
systemctl restart novaix
```

::: warning 注意
升级前建议先[备份](./backup)数据库，以便出现问题时可以回滚。虽然 Novaix 的升级过程设计为向前兼容，但备份永远是好习惯。
:::

## 查看版本 {#version}

您可以通过以下方式查看当前运行的版本：

- 管理面板的「更新日志」页面
- 接口 `GET /api/v1/ping` 的返回信息

## 注意事项 {#notes}

- 升级是**不可逆**的，数据库迁移会在启动时自动执行，回退到旧版本可能导致不兼容
- 如果跨多个版本升级，建议查看 [更新日志](https://github.com/huohuastudio/novaix-releases/releases) 了解每个版本的变更内容
- 升级过程中服务会短暂中断（通常只需几秒），如果您对可用性要求较高，建议在低峰期操作
