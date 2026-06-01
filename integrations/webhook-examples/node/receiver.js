/**
 * NovaIx Webhook 接收端 - Express 示例
 *
 * 部署方式：
 *   npm install express
 *   NOVAIX_CALLBACK_SECRET=<创建 API 密钥时返回的 callback_secret> node receiver.js
 *
 * 关键点：
 *   - express.raw() 中间件确保拿到原始请求体（不能用 express.json()，否则无法计算原始 HMAC）
 *   - crypto.timingSafeEqual 做恒定时间比较，避免时序攻击
 *   - 必须用 HTTPS（生产环境，建议放在 nginx/Caddy 反向代理后）
 */

const crypto = require('node:crypto');
const express = require('express');

const app = express();
const SECRET = process.env.NOVAIX_CALLBACK_SECRET || '';
const PORT = process.env.PORT || 8000;

function verifySignature(rawBody, signature, secret) {
  if (!signature || !secret) return false;
  // Buffer.from(s, 'hex') 对非偶数长度的字符串会静默截断，先做格式校验
  if (!/^[0-9a-f]{64}$/i.test(signature)) return false;
  const expected = crypto.createHmac('sha256', secret).update(rawBody).digest('hex');
  return crypto.timingSafeEqual(Buffer.from(expected, 'hex'), Buffer.from(signature, 'hex'));
}

// 用 raw 中间件拿到原始 body（Buffer），后续才能用它计算 HMAC
app.post('/callback', express.raw({ type: 'application/json', limit: '64kb' }), (req, res) => {
  if (!SECRET) {
    console.error('服务端未配置 NOVAIX_CALLBACK_SECRET');
    return res.status(500).send('config missing');
  }

  // 1. 验证签名
  const signature = req.header('X-Novaix-Signature') || '';
  if (!verifySignature(req.body, signature, SECRET)) {
    console.warn('签名验证失败');
    return res.status(401).send('invalid signature');
  }

  // 2. 解析 payload（验签后才解析）
  let payload;
  try {
    payload = JSON.parse(req.body.toString('utf8'));
  } catch (e) {
    return res.status(400).send('invalid json');
  }

  const { event, task_id, task_type, external_id, status } = payload;

  // 3. 幂等处理（伪代码）
  // if (alreadyProcessed(task_id)) {
  //   return res.status(200).send('ok');
  // }

  switch (task_type) {
    case 'create_instance':
      if (status === 'completed') {
        const ip = payload.data?.ip_address || '';
        const hostname = payload.data?.hostname || '';
        console.log(`实例开通成功 external_id=${external_id} ip=${ip}`);
        // markServiceActive(external_id, ip, hostname);
      } else {
        const err = payload.result || '未知错误';
        console.warn(`实例开通失败 external_id=${external_id} reason=${err}`);
        // markServiceFailed(external_id, err);
      }
      break;

    case 'delete_instance':
      console.log(`实例删除完成 external_id=${external_id}`);
      // markServiceTerminated(external_id);
      break;

    case 'start_instance':
    case 'stop_instance':
    case 'restart_instance':
    case 'freeze_instance':
    case 'unfreeze_instance':
      console.log(`操作完成 task_type=${task_type} status=${status} external_id=${external_id}`);
      break;

    default:
      console.log(`未处理的任务类型 task_type=${task_type}`);
  }

  // 4. markProcessed(task_id);

  // 5. 返回 2xx 表示成功接收（NovaIx 不会重试）
  res.status(200).send('ok');
});

app.listen(PORT, () => {
  console.log(`NovaIx callback receiver listening on :${PORT}`);
});
