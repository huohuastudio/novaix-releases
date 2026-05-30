# 快速开始 {#getting-started}

Novaix 是一款商业软件，您需要先获取许可证密钥才能使用全部功能。

## 获取许可证 {#license}

您可以在 [Spark Studio 官网](https://huohuastudio.com) 购买 Novaix 许可证。购买后您将获得一个许可证密钥和验证服务地址，需要在 `config.yaml` 中配置后启动程序：

```yaml
license:
  key: "您的许可证密钥"
  service_api: "许可证验证服务地址"
```

::: warning 注意
许可证密钥只能通过配置文件设置，无法在管理后台中修改。修改后需要重启程序才能生效。系统启动后会定期验证许可证状态，许可证无效时除少量公开接口外其他接口将不可用。
:::

## 部署流程概览 {#overview}

部署 Novaix 只需几个简单步骤：

1. 确保服务器满足[环境要求](./requirement)
2. 下载二进制文件并[安装](./install)
3. 编辑配置文件，设置生产环境必要参数
4. 配置反向代理（Nginx 或 Caddy）以启用 HTTPS
5. 访问管理面板，完成初始化配置

整个过程通常只需要几分钟。
