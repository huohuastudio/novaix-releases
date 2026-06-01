# NovaIx WHMCS 服务器模块

将 NovaIx 作为 WHMCS 的 Provisioning 模块对接，实现 VPS 实例的自动开通、暂停、删除和管理。

## 安装

1. 把 `novaix/` 目录复制到 WHMCS 安装目录的 `modules/servers/novaix/`：

   ```
   /path/to/whmcs/modules/servers/novaix/
   └── novaix.php
   ```

2. 在 WHMCS 后台进入 **Setup → Products/Services → Servers → Add New Server**：
   - **Hostname**：NovaIx 服务器域名（如 `novaix.example.com`）
   - **Server Type**：选择 `NovaIx VPS`
   - **Username**：留空（NovaIx 用 API Key 认证）
   - **Password / Access Hash**：填入 NovaIx 管理员创建的 API 密钥（`nv_` 开头）
   - **Secure**：建议勾选（HTTPS）
   - **Port**：NovaIx 服务端口（一般是 443 或 8080）
   - 保存后点击 **Test Connection** 验证

3. 进入 **Setup → Products/Services → Products** 创建产品：
   - **Module Settings → Module Name**：`NovaIx VPS`
   - 关联前面创建的服务器
   - 填写 **NovaIx 套餐 ID**、**默认镜像 ID**（从 NovaIx 后台获取）
   - **指定节点 ID** 留空表示自动选择

## NovaIx 端的配置

按 [Provisioning API 文档 → 集成方与认证](../provisioning-api.md#1-集成方与认证) 操作：

1. 先创建一个 **Integration**（如"WHMCS 主站"），配置 webhook 回调地址，保存 `callback_secret`
2. 再创建一把关联到该 Integration 的 **API 密钥**（带 `provision` 权限）

把 `plain_key` 填入上面 WHMCS 服务器配置的 **Access Hash**。API Key 可以随时轮换而不影响已有实例。

## 支持的功能

| 功能 | 状态 |
|------|------|
| 自动开通（同步等待实例就绪） | ✅ |
| 暂停/解除暂停 | ✅ |
| 删除 | ✅ |
| 重置密码 | ✅ |
| 开机/关机/重启（客户端按钮） | ✅ |
| 重装系统 | ⚠️ 通过 NovaIx 自营面板，WHMCS 端暂未提供入口 |
| VNC 控制台 | ❌ 暂未支持，请引导用户使用 NovaIx 自营面板 |

## 同步开通的注意事项

模块的 `CreateAccount` 会同步轮询 `GET /instances/{id}/status` 直到实例就绪（详见 `_novaix_wait_for_running`），最长 `NOVAIX_CREATE_TIMEOUT` 秒（默认 180s）。

> ⚠️ **PHP-FPM Worker 占用风险**：WHMCS 的 CreateAccount 由 cron 同步触发，180s 等待会独占一个 PHP-FPM worker。同时开通多个订单时可能阻塞 worker 池。生产环境如果开通量大，建议未来切换到 Webhook 异步模式（监听 [task.completed 回调](../webhook.md) 后用 WHMCS 内部 API 回填服务状态）。

## 与 NovaIx 的对应关系

| WHMCS 概念 | NovaIx 概念 |
|------------|-------------|
| Service ID (`serviceid`) | `external_id`（幂等键） |
| Client Email | NovaIx 用户邮箱（自动创建/复用用户） |
| Service Hostname | NovaIx 实例 hostname |
| Server Access Hash | NovaIx API Key（`nv_` 开头） |

## 故障排查

| 现象 | 排查方向 |
|------|----------|
| Test Connection 失败 | 检查 hostname、port、API Key；NovaIx 端日志看是否有 401/403 |
| 开通卡 180 秒后超时 | NovaIx 后台 → 任务列表查看创建任务的实际状态和日志 |
| 重复开通报"external_id 已被占用" | 上次任务还在进行中（同一 `serviceid` 触发的幂等）。检查 NovaIx 任务状态 |
| 删除后再开通报冲突 | 等几秒让 NovaIx 异步删除任务完成（删除完成时会自动清空 `external_id`） |

## 参考

- [NovaIx Provisioning API 文档](../provisioning-api.md)
- [WHMCS 官方 Provisioning Module 文档](https://developers.whmcs.com/provisioning-modules/)
