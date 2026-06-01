# Webhook 接收端示例

三种语言的完整可运行示例，演示如何正确接收和验证 NovaIx Webhook。

| 语言 | 文件 | 依赖 | 启动 |
|------|------|------|------|
| PHP | [php/receiver.php](./php/receiver.php) | 仅 PHP 8.0+ | 放到 web 服务器上 |
| Python | [python/receiver.py](./python/receiver.py) | `flask` | `flask --app receiver run` |
| Node.js | [node/receiver.js](./node/receiver.js) | `express`，Node 18+ | `node receiver.js` |

## 三种实现的共同点

无论使用哪种语言，正确的实现都必须做这三件事：

1. **拿到原始请求体**（不是解析后的 JSON 对象）
2. **用 callback_secret 计算 HMAC-SHA256**，与 `X-Novaix-Signature` 头比较
3. **用恒定时间比较函数**（`hash_equals` / `hmac.compare_digest` / `crypto.timingSafeEqual`），不要用 `==`

## 本地测试

可以用 curl 模拟一次 NovaIx 的回调请求：

```bash
SECRET="your_callback_secret"
BODY='{"event":"task.completed","task_id":1,"task_type":"create_instance","external_id":"test_1","status":"completed","data":{"ip_address":"1.2.3.4"},"timestamp":1748707200}'

# 计算签名
SIG=$(echo -n "$BODY" | openssl dgst -sha256 -hmac "$SECRET" | awk '{print $2}')

# 发送测试请求
curl -X POST http://localhost:8000/callback \
  -H "Content-Type: application/json" \
  -H "X-Novaix-Signature: $SIG" \
  -d "$BODY"
```

## 关键安全点

- **HTTPS-only**：生产环境必须用 HTTPS，否则 `callback_secret` 在传输中可能泄露
- **不要打日志记录 secret**：日志聚合系统可能会泄露
- **幂等处理**：用 `task_id` 去重，避免重试导致重复处理
- **可观测性**：记录 `task_id`、`external_id`、`status` 便于排查
