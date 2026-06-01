# NovaIx 第三方集成

NovaIx 提供 **Provisioning API**，让第三方财务系统/分销面板能自动开通和管理 VPS 实例。

## 文档

- [Provisioning API 参考](./provisioning-api.md) — 完整接口说明、认证、幂等性、错误码
- [Webhook 回调与验签](./webhook.md) — 异步通知的格式与 HMAC-SHA256 验签
- [Webhook 接收端示例](./webhook-examples/) — PHP / Python / Node 三种语言的参考实现

## 已有集成模块

| 系统 | 路径 | 状态 |
|------|------|------|
| 智简魔方（IDCsmart） | [./mofang/](./mofang/) | 已实现，待真实环境验证 |
| WHMCS | [./whmcs/](./whmcs/) | 已实现，待真实环境验证 |

## 自己接入

如果你要对接的系统不在上面列表中，请阅读 [Provisioning API 参考](./provisioning-api.md)，并参考已有模块的实现。

所有集成只需要：
1. 一个长期有效的 API 密钥（由 NovaIx 管理员创建，带 `provision` 权限）
2. 一个可被 NovaIx 访问的 HTTPS 回调地址
3. 用 `callback_secret` 验证 webhook 签名
