# NovaIx 第三方集成

NovaIx 提供 **Provisioning API**，让第三方财务系统/分销面板能自动开通和管理 VPS 实例。

## 文档

- [Provisioning API 参考](./provisioning-api.md) — 完整接口说明、认证、幂等性、错误码
- [Webhook 回调与验签](./webhook.md) — 异步通知的格式与 HMAC-SHA256 验签
- [Webhook 接收端示例](./webhook-examples/) — PHP / Python / Node 三种语言的参考实现

## 已有集成模块

### 智简魔方 V10（ZJMF-CBAP）

| 模块 | 路径 | 模式 | 说明 |
|------|------|------|------|
| 上游供应商模块（推荐） | [./mofang-reserver/](./mofang-reserver/) | reserver（代理转售） | 自动同步商品、加价转售、前台管理。安装到 `public/plugins/reserver/novaix/` |
| 服务器模块 | [./mofang/](./mofang/) | server（自营） | 手动填写套餐 ID/镜像 ID 的传统模式。安装到 `public/plugins/server/idcsmart_common/module/novaix/` |

两个模块面向不同场景，互不依赖：
- **reserver（代理转售）**：适合从 Novaix 批发商品加价转卖，商品从上游自动同步，无需手动配置
- **server（自营）**：适合自营站点直接管理 Novaix 节点，需要在每个商品中手动填写套餐和镜像 ID

### 其他系统

| 系统 | 路径 | 状态 |
|------|------|------|
| 智简魔方财务 2.x | [./mofang-legacy/](./mofang-legacy/) | 已实现，待真实环境验证 |
| WHMCS | [./whmcs/](./whmcs/) | 已实现，待真实环境验证 |

## 自己接入

如果你要对接的系统不在上面列表中，请阅读 [Provisioning API 参考](./provisioning-api.md)，并参考已有模块的实现。

所有集成只需要：
1. 一个长期有效的 API 密钥（由 NovaIx 管理员创建，带 `provision` 权限）
2. 一个可被 NovaIx 访问的 HTTPS 回调地址
3. 用 `callback_secret` 验证 webhook 签名
