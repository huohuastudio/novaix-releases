# Webhook 回调

NovaIx 在异步任务完成或失败时，会向**集成方（Integration）配置的 `callback_url`** 发送 POST 请求。

> **关于配置位置**：`callback_url` 和 `callback_secret` 属于 Integration（不是 API Key）。API Key 只是访问凭证，可以轮换；集成方身份和回调配置保持稳定。详见 [Provisioning API → 集成方与认证](./provisioning-api.md#1-集成方与认证)。

## 触发时机

| 任务类型 | 何时触发 |
|----------|----------|
| 创建实例 | Incus 实例创建成功 / 失败 |
| 删除实例 | 实例完成删除 / 删除失败 |
| 启停/重启 | 操作完成 / 失败 |
| 重装系统 | 重装完成 / 失败 |
| 重置密码 | 操作完成 / 失败 |
| 暂停/解除暂停 | 操作完成 / 失败 |

只有**通过 Provisioning API 创建的实例**（带 `external_id` 和 `integration_id`）才会触发 webhook。管理员在后台直接创建的实例不会通知。

## 请求格式

```http
POST https://your-billing.example.com/callback HTTP/1.1
Content-Type: application/json
X-Novaix-Signature: 7f3c5a2e9b1d...
```

**Body**:

```json
{
  "event": "task.completed",
  "task_id": 100,
  "task_type": "create_instance",
  "external_id": "your_service_id_123",
  "status": "completed",
  "result": "",
  "data": {
    "ip_address": "103.25.60.15",
    "ipv6_address": "2001:db8::1",
    "hostname": "web-01"
  },
  "timestamp": 1748707200
}
```

| 字段 | 类型 | 说明 |
|------|------|------|
| `event` | string | `task.completed` 或 `task.failed` |
| `task_id` | int | NovaIx 内部任务 ID |
| `task_type` | string | 任务类型，如 `create_instance`、`delete_instance`、`start_instance` 等 |
| `external_id` | string | 集成方传入的服务 ID |
| `status` | string | `completed` 或 `failed` |
| `result` | string | 失败时的错误描述（成功时为空） |
| `data` | object | **仅创建成功时**附带，包含 IP、hostname 等 |
| `timestamp` | int | Unix 时间戳（秒） |

## 验签

NovaIx 在 `X-Novaix-Signature` 头中发送 **HMAC-SHA256(body, callback_secret)** 的 hex 编码。

**接收端必须验证签名**，否则攻击者可以伪造任务完成通知导致服务被错误标记为已开通。

### 验证步骤

1. 取出 `X-Novaix-Signature` 头的值
2. 用 **Integration 关联的 `callback_secret`**（创建 Integration 或轮换时一次性返回）对**原始请求体**做 HMAC-SHA256
3. 用恒定时间比较函数（不要直接 `==`）对比两个签名是否相等

### ⚠️ 安全注意

- **必须用原始 body**：不要解析后再签名，序列化结果可能不一致
- **必须用恒定时间比较**：避免时序攻击（PHP `hash_equals`、Python `hmac.compare_digest`、Node `crypto.timingSafeEqual`）
- **HTTPS-only**：`callback_url` 必须用 HTTPS，否则签名密钥在传输中可能泄露
- **幂等处理**：NovaIx 会重试 3 次（间隔 5s/10s/15s），接收端要对同一 `task_id` 做幂等处理

## 重试策略与可靠性

> ⚠️ **Webhook 是 best-effort 投递，不是可靠交付**。集成方**必须**用主动轮询作为兜底。

**NovaIx 端的局限**：

| 失败场景 | 现状 |
|----------|------|
| 接收端瞬时故障 | 最多重试 3 次，间隔 5s/10s/15s |
| 重试耗尽 | 不再发送，事件**永久丢失** |
| 任务完成时 NovaIx 进程崩溃 | webhook 未发送，**永久丢失** |
| 任务完成瞬间并发量爆增 | 调度并发达 32 上限时**当场丢弃**，不进入重试队列 |
| API Key 被删除后任务才完成 | webhook 不会发送（找不到回调地址） |

**集成方必须做的**：

1. **关键状态确认走任务轮询**：`suspend/unsuspend/terminate/reinstall/reset-password` 调用后用 `GET /provision/tasks/{id}` 等待最终状态，不要依赖 webhook
2. **创建实例**：用 `GET /provision/instances/{id}/status` 轮询 `running` / `error` 作为终态判定
3. **定期对账**：每天用 `GET /provision/instances` 拉取所有实例状态，与自己计费侧对比

NovaIx 后续版本可能引入**持久化重试队列**让 webhook 升级为可靠交付，届时会通过文档更新通知。在那之前，请把 webhook 视为"通知方便"，而不是"状态来源"。

## 接收端实现示例

- [PHP（含独立 PHP 与 Laravel 示例）](./webhook-examples/php/)
- [Python（Flask）](./webhook-examples/python/)
- [Node.js（Express）](./webhook-examples/node/)

## 故障排查

| 现象 | 可能原因 |
|------|----------|
| 收不到任何 webhook | API 密钥没配 `callback_url`；或网络隔离访问不通 |
| 签名验证总失败 | 用了 JSON 解析后的字符串而不是原始 body；密钥用错（每个 API 密钥的 `callback_secret` 不同） |
| 收到的 `external_id` 为空 | 不应该出现——失败时 NovaIx 同步快照 external_id 后才清空。如果出现请检查 NovaIx 版本 |
| 创建成功但 `data` 中 `ip_address` 为空 | 节点没配置 IP 池，或 IP 池已耗尽。检查 NovaIx 后台节点配置 |
