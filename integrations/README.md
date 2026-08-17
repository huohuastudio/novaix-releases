# NovaIx 开放接口

NovaIx 提供 **Provisioning API** 和 **Webhook** 回调，允许外部系统自动开通和管理 VPS 实例。

## 文档

- [Provisioning API 参考](./provisioning-api.md) — 完整接口说明、认证、幂等性、错误码
- [Webhook 回调与验签](./webhook.md) — 异步通知的格式与 HMAC-SHA256 验签
- [Webhook 接收端示例](./webhook-examples/) — PHP / Python / Node 三种语言的参考实现

## 自行对接

对接只需要：
1. 一个长期有效的 API 密钥（由 NovaIx 管理员创建，带 `provision` 权限）
2. （可选）如需接收 Webhook 回调，配置一个可被 NovaIx 访问的 HTTPS 回调地址，并用 `callback_secret` 验证签名
