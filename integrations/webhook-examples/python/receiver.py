"""
NovaIx Webhook 接收端 - Flask 示例

部署方式：
    pip install flask
    export NOVAIX_CALLBACK_SECRET=<创建 API 密钥时返回的 callback_secret>
    flask --app receiver run --host 0.0.0.0 --port 8000

生产部署建议用 gunicorn / uvicorn + nginx 反向代理。

关键点：
    - 必须用原始请求体（request.get_data()）计算 HMAC，不能用 request.get_json() 后的结果
    - 必须用 hmac.compare_digest 做恒定时间比较
    - 必须用 HTTPS（生产环境）
"""

import hashlib
import hmac
import logging
import os

from flask import Flask, abort, request

app = Flask(__name__)
app.config["MAX_CONTENT_LENGTH"] = 64 * 1024  # 64 KB，挡掉大 payload 攻击
logging.basicConfig(level=logging.INFO)
log = logging.getLogger("novaix-callback")

SECRET = os.environ.get("NOVAIX_CALLBACK_SECRET", "")


def verify_signature(raw_body: bytes, signature: str, secret: str) -> bool:
    if not signature or not secret:
        return False
    expected = hmac.new(secret.encode(), raw_body, hashlib.sha256).hexdigest()
    return hmac.compare_digest(expected, signature)


@app.post("/callback")
def callback():
    if not SECRET:
        log.error("服务端未配置 NOVAIX_CALLBACK_SECRET")
        abort(500)

    # 1. 读取原始请求体
    raw_body = request.get_data()

    # 2. 验证签名
    signature = request.headers.get("X-Novaix-Signature", "")
    if not verify_signature(raw_body, signature, SECRET):
        log.warning("签名验证失败")
        abort(401)

    # 3. 解析 payload（验签后才解析）
    payload = request.get_json(silent=True) or {}

    event = payload.get("event", "")
    task_id = payload.get("task_id", 0)
    task_type = payload.get("task_type", "")
    external_id = payload.get("external_id", "")
    status = payload.get("status", "")

    # 4. 幂等处理（伪代码）
    # if already_processed(task_id):
    #     return "ok", 200

    if task_type == "create_instance":
        if status == "completed":
            data = payload.get("data") or {}
            ip = data.get("ip_address", "")
            hostname = data.get("hostname", "")
            log.info("实例开通成功 external_id=%s ip=%s", external_id, ip)
            # mark_service_active(external_id, ip, hostname)
        else:
            err = payload.get("result", "未知错误")
            log.warning("实例开通失败 external_id=%s reason=%s", external_id, err)
            # mark_service_failed(external_id, err)

    elif task_type == "delete_instance":
        log.info("实例删除完成 external_id=%s", external_id)
        # mark_service_terminated(external_id)

    elif task_type in {
        "start_instance",
        "stop_instance",
        "restart_instance",
        "freeze_instance",
        "unfreeze_instance",
    }:
        log.info("操作完成 task_type=%s status=%s external_id=%s", task_type, status, external_id)

    else:
        log.info("未处理的任务类型 task_type=%s", task_type)

    # 5. mark_processed(task_id)

    # 6. 返回 2xx 表示成功接收（NovaIx 不会重试）
    return "ok", 200


if __name__ == "__main__":
    app.run(host="0.0.0.0", port=8000)
