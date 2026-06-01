# NovaIx 智简魔方模块

智简魔方（IDCsmart）服务器模块，通过 Provisioning API 对接 NovaIx。

> ⚠️ **当前版本未在真实魔方环境完整测试，请先在测试环境验证再投入生产。**

## 安装

1. 将 `novaix/` 目录复制到魔方安装目录的 `modules/servers/`：

   ```
   /path/to/idcsmart/modules/servers/novaix/
   └── novaix.php
   ```

2. 在魔方后台 → 产品管理 → 通用接口 → 添加接口，选择 `NovaIx VPS`
3. 填入 NovaIx 的接口地址和 API 密钥，点击"测试连接"

## 配置

### NovaIx 侧

按 [Provisioning API 文档 → 集成方与认证](../provisioning-api.md#1-集成方与认证) 操作：

1. 先创建一个 **Integration**（如"魔方主站"），配置 webhook 回调地址，保存 `callback_secret`
2. 再创建一把关联到该 Integration 的 **API 密钥**（带 `provision` 权限），保存 `plain_key`

API Key 可以随时轮换、过期、删除，业务连续性由 Integration 保证。

### 魔方侧

1. 通用接口配置：
   - **接口地址**：NovaIx 服务器域名（如 `novaix.example.com`）
   - **API 密钥**：上一步创建的 `nv_` 开头的密钥（填入"接口服务器密码"或"Access Hash"字段）
   - **端口**：NovaIx 服务端口（如 `8080`，使用 HTTPS 反代则为 `443`）
   - **SSL**：如使用 HTTPS 则勾选
2. 创建产品，关联该接口，填写：
   - **套餐 ID**：NovaIx 后台的套餐 ID
   - **镜像 ID**：默认操作系统镜像 ID
   - **节点 ID**：可选，留空自动选择

## 支持的功能

| 功能 | 状态 | 说明 |
|------|------|------|
| 自动开通 | ✅ | 套餐驱动 + 自动分配 IP + 同步等待实例就绪 |
| 暂停/解除暂停 | ✅ | |
| 删除 | ✅ | |
| 开机/关机/重启 | ✅ | |
| 重装系统 | ✅ | |
| 重置密码 | ✅ | |
| 幂等创建 | ✅ | 通过 `external_id`（魔方 `hostid`）保证 |
| VNC 控制台 | ❌ | 暂未支持，建议引导用户使用 NovaIx 自营面板 |

## 同步开通的等待策略

`CreateAccount` 同步轮询 `/instances/{id}/status` 直到实例就绪，最长 `NOVAIX_CREATE_TIMEOUT` 秒（默认 180s）。可在 `novaix.php` 顶部调整常量。

## Webhook 回调

任务完成时 NovaIx 会向 Integration 配置的回调地址 POST 通知，请参见 [Webhook 文档](../webhook.md) 了解 payload 格式和验签方法，[Webhook 接收端示例](../webhook-examples/) 提供 PHP/Python/Node 三种语言的参考实现。
