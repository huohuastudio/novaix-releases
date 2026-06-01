# Provisioning API 参考

NovaIx 提供 `/api/v1/provision/` 路由组，让第三方系统通过 API 自动创建/管理 VPS 实例。所有接口返回标准格式：

```json
{ "code": 0, "message": "ok", "data": { ... } }
```

`code = 0` 表示成功，非零表示业务错误（详见末尾错误码表）。

---

## 1. 集成方与认证

### 概念：集成方（Integration） vs API 密钥（API Key）

NovaIx 把"集成方身份"和"访问凭证"分开：

- **Integration**：稳定的集成方身份（如"魔方主站"、"WHMCS 灰度环境"），承载 `callback_url`、`callback_secret`、实例归属、external_id 唯一性
- **API Key**：短期访问凭证（`nv_` 开头），关联到某个 Integration

这种分离让 API Key **可以安全轮换**：删除旧 Key、创建新 Key，业务连续性不受影响——旧实例仍归属同一 Integration，新 Key 能继续操作；Webhook 回调地址不变。

### 创建 Integration（管理员）

1. 登录 NovaIx 后台
2. 系统设置 → 集成方管理 → 新建
3. 填写：
   - **名称**：如"魔方主站"
   - **回调地址**：Webhook 接收端 HTTPS URL
4. 保存后**立即记录** `callback_secret`，仅展示一次（后续可通过"轮换"重新生成）

### 创建 API 密钥

**必须由管理员创建**（普通用户无法授予 `provision` 权限）：

1. 进入 个人资料 → API 密钥 → 新建
2. **关联集成方**：选择上一步创建的 Integration
3. **权限**：勾选 `provision` (read + write)
4. 保存。**`plain_key` 只展示一次**，立即妥善保存

### 认证

所有请求必须携带 `Authorization: Bearer nv_xxx` 头：

```http
GET /api/v1/provision/test HTTP/1.1
Host: novaix.example.com
Authorization: Bearer nv_a1b2c3d4e5f6...
```

服务端通过 API Key 解出关联的 Integration ID，所有实例查询/操作都按 Integration 作用域隔离。

---

## 2. 接口列表

| 方法 | 路径 | 用途 |
|------|------|------|
| POST | `/test` | 连通性测试 |
| GET | `/plans` | 列出可用套餐 |
| GET | `/images` | 列出可用镜像 |
| POST | `/instances` | 创建实例（开通） |
| GET | `/instances` | 列出本集成方创建的实例（分页） |
| GET | `/instances/:id` | 查询实例详情 |
| GET | `/instances/:id/status` | 查询实例状态 |
| POST | `/instances/:id/start` | 开机 |
| POST | `/instances/:id/stop` | 关机 |
| POST | `/instances/:id/reboot` | 重启 |
| POST | `/instances/:id/suspend` | 暂停（冻结） |
| POST | `/instances/:id/unsuspend` | 解除暂停 |
| POST | `/instances/:id/terminate` | 删除 |
| POST | `/instances/:id/reinstall` | 重装系统 |
| POST | `/instances/:id/reset-password` | 重置密码 |
| GET | `/tasks/:id` | 查询异步任务状态（用于确认 suspend/terminate 等动作真正完成） |

所有 `/:id` 端点支持 `?by=external_id` 查询参数：传入第三方系统的服务 ID 即可，无需维护内部 ID 映射。

---

## 3. 创建实例

### POST `/api/v1/provision/instances`

**请求体**:

```json
{
  "plan_id": 1,
  "image_id": 1,
  "node_id": null,
  "hostname": "web-01",
  "password": "SecurePass123!",
  "external_id": "your_service_id_123",
  "user_email": "customer@example.com"
}
```

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| `plan_id` | int | ✅ | NovaIx 套餐 ID，决定 CPU/内存/磁盘/带宽 |
| `image_id` | int | ✅ | 操作系统镜像 ID |
| `node_id` | int | ❌ | 指定节点；不传时自动从套餐允许的节点中选择 |
| `hostname` | string | ❌ | 主机名；不传则自动生成 |
| `password` | string | ✅ | root 密码，最少 8 位 |
| `external_id` | string | ✅ | **幂等键**。同一 Integration 下唯一，重复调用返回同一实例 |
| `user_email` | string | ✅ | 关联终端用户邮箱；NovaIx 自动查找或创建该用户 |

**响应**:

```json
{
  "code": 0,
  "message": "ok",
  "data": {
    "instance_id": 42,
    "external_id": "your_service_id_123",
    "task_id": 100
  }
}
```

> ⚠️ **该接口是异步的**。返回 `task_id` 仅表示任务已提交，不代表实例已就绪。  
> 真正的"创建完成"需要通过以下两种方式之一确认：
> 1. **Webhook 回调**（推荐）：任务完成时 NovaIx 主动通知
> 2. **轮询 status 端点**：每 3-5 秒查询 `GET /instances/{id}/status`，状态变为 `running` 表示就绪

### 幂等性

同一 **Integration** + 同一 `external_id` 的重复请求会**返回已存在的实例**，不会重复创建。

- 同一 Integration 下多把 API Key 都看到同样的实例（支持 Key 轮换）
- 不同 Integration 用同一 `external_id` 不冲突（不同集成方互相隔离）
- **失败实例不会被幂等返回**：如果上次创建失败（状态变为 `error`），NovaIx 自动清空 `external_id`，重试时会真正重新创建

---

## 4. 实例操作

所有操作返回 `task_id`，表示任务已提交：

```json
{
  "code": 0,
  "message": "ok",
  "data": { "task_id": 101, "status": "pending" }
}
```

### 开机 / 关机 / 重启

```bash
POST /api/v1/provision/instances/{id}/start
POST /api/v1/provision/instances/{id}/stop
POST /api/v1/provision/instances/{id}/reboot
```

### 暂停 / 解除暂停（计费场景）

```bash
POST /api/v1/provision/instances/{id}/suspend
Content-Type: application/json

{ "reason": "overdue" }
```

`reason` 可选，仅用于日志记录。NovaIx 实际执行的是"冻结"（freeze），保留内存状态。

```bash
POST /api/v1/provision/instances/{id}/unsuspend
```

### 删除

```bash
POST /api/v1/provision/instances/{id}/terminate
```

删除后 `external_id` 会被清空，集成方可以用**同一 external_id 再次创建**新实例。

### 重装系统

```bash
POST /api/v1/provision/instances/{id}/reinstall
Content-Type: application/json

{
  "image_id": 1,
  "password": "NewPass123!"
}
```

### 重置密码

```bash
POST /api/v1/provision/instances/{id}/reset-password
Content-Type: application/json

{ "password": "NewPass123!" }
```

---

## 5. 查询

### 通过外部 ID 查询

任何 `/:id` 端点都支持 `?by=external_id`：

```bash
GET /api/v1/provision/instances/your_service_id_123?by=external_id
POST /api/v1/provision/instances/your_service_id_123/stop?by=external_id
```

### 实例详情

```bash
GET /api/v1/provision/instances/{id}
```

响应:

```json
{
  "code": 0,
  "data": {
    "id": 42,
    "external_id": "your_service_id_123",
    "status": "running",
    "hostname": "web-01",
    "ip_address": "103.25.60.15",
    "ipv6_address": "2001:db8::1",
    "cpu": 2,
    "memory": 2048,
    "disk": 40,
    "bandwidth": 100,
    "os_type": "debian",
    "node_id": 1,
    "plan_id": 5,
    "created_at": "2026-05-31 12:00:00",
    "expire_at": "2026-06-30 12:00:00"
  }
}
```

### 状态查询（轻量）

```bash
GET /api/v1/provision/instances/{id}/status
```

```json
{
  "code": 0,
  "data": {
    "id": 42,
    "external_id": "your_service_id_123",
    "status": "running",
    "ip_address": "103.25.60.15"
  }
}
```

**实例状态值**:
- `creating` — 创建中（异步任务执行中）
- `running` — 运行中
- `stopped` — 已停止
- `frozen` — 已冻结（暂停）
- `error` — 创建/操作失败
- `deleting` — 删除中

---

## 6. 资源查询

### 套餐列表

```bash
GET /api/v1/provision/plans
```

返回所有上架且有库存的套餐，供集成方在产品管理中映射。

### 镜像列表

```bash
GET /api/v1/provision/images
```

---

## 7. 任务状态查询

所有实例操作（包括 suspend/terminate/reinstall）都是异步的，返回的 `task_id` 仅表示任务已提交。集成方应轮询任务状态以确认操作真正完成：

```bash
GET /api/v1/provision/tasks/{task_id}
```

**响应**:

```json
{
  "code": 0,
  "data": {
    "id": 100,
    "type": "delete_instance",
    "status": "completed",
    "result": "",
    "instance_id": 42,
    "external_id": "your_service_id_123",
    "created_at": "2026-05-31 12:00:00",
    "finished_at": "2026-05-31 12:00:05"
  }
}
```

**状态值**：`pending` / `running` / `completed` / `failed`。失败时 `result` 字段包含错误描述。

> ⚠️ **务必用此端点确认 `suspend` / `unsuspend` / `terminate` / `reinstall` / `reset-password` 的真正结果**。仅依赖端点返回 200 不够——任务可能后续失败。

任务必须属于当前 API 密钥创建的实例，否则返回 404。

## 8. 错误码

| HTTP | code | 含义 |
|------|------|------|
| 400 | 10400 | 参数错误 |
| 401 | 10401 | 未提供令牌或令牌无效 |
| 401 | 21502 | API 密钥已过期 |
| 401 | 21503 | API 密钥无效 |
| 403 | 21504 | API 密钥权限不足 |
| 403 | 22006 | 此接口仅限 API 密钥访问（不能用 JWT） |
| 404 | 22001 | 套餐不存在或已下架 |
| 404 | 22002 | 镜像不存在 |
| 404 | 22005 | 实例不存在（或不属于当前 API Key） |
| 400 | 22003 | 无可用节点 |
| 409 | 20507 | 套餐库存不足 |
| 409 | 22004 | external_id 已被占用（同 API Key 下） |
| 409 | 20208 | 实例有正在执行的任务 |
| 422 | 10422 | 参数验证失败（`data` 中含字段级错误） |

---

## 9. 完整示例（curl）

```bash
export NV_API="https://novaix.example.com"
export NV_KEY="nv_a1b2c3d4..."

# 1. 连通性测试
curl -X POST "$NV_API/api/v1/provision/test" \
  -H "Authorization: Bearer $NV_KEY"

# 2. 查看可用套餐
curl "$NV_API/api/v1/provision/plans" \
  -H "Authorization: Bearer $NV_KEY"

# 3. 开通实例
curl -X POST "$NV_API/api/v1/provision/instances" \
  -H "Authorization: Bearer $NV_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "plan_id": 1,
    "image_id": 1,
    "password": "SecurePass123!",
    "external_id": "service_001",
    "user_email": "user@example.com"
  }'

# 4. 轮询状态直到 running
curl "$NV_API/api/v1/provision/instances/service_001/status?by=external_id" \
  -H "Authorization: Bearer $NV_KEY"

# 5. 暂停（欠费场景）
curl -X POST "$NV_API/api/v1/provision/instances/service_001/suspend?by=external_id" \
  -H "Authorization: Bearer $NV_KEY" \
  -H "Content-Type: application/json" \
  -d '{ "reason": "overdue" }'

# 6. 删除
curl -X POST "$NV_API/api/v1/provision/instances/service_001/terminate?by=external_id" \
  -H "Authorization: Bearer $NV_KEY"
```

---

## 10. 设计要点

### 为什么是异步？

实例创建涉及拉镜像、启动 VM、配置网络等多个步骤，耗时几十秒到几分钟。同步等待会让集成方的 HTTP 请求大概率超时。NovaIx 采用任务队列处理，通过 Webhook 通知结果。

### 为什么需要 external_id？

- **幂等**：网络重试不会重复创建实例
- **解耦内部 ID**：集成方用自己的服务 ID 操作，无需保存 NovaIx 内部 ID
- **作用域隔离**：不同集成方互不影响

### 为什么权限只限管理员授予？

防止普通注册用户给自己发 `provision` 权限的 API 密钥后绕过订单/支付直接开通实例。

### 资源归属

通过 Provisioning API 创建的实例：
- **属于**：`user_email` 对应的终端用户（NovaIx 自动创建用户）
- **集成方**：通过 `IntegrationID` 标记，所有操作和列表查询都按此隔离
- **审计**：`CreatedByAPIKeyID` 记录是哪把 Key 触发的创建
- **管理员**：在 NovaIx 后台能看到所有实例

### API Key 轮换流程

```
1. 管理员保持 Integration 不变
2. 创建新 API Key 关联同一个 Integration
3. 集成方更新自己的配置使用新 Key
4. 删除旧 API Key
```

整个过程中，实例操作、Webhook 回调都不会中断。

---

## 11. 后续阅读

- [Webhook 回调与验签](./webhook.md)
- [Webhook 接收端示例代码](./webhook-examples/)
