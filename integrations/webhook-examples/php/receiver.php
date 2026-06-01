<?php
/**
 * NovaIx Webhook 接收端 - PHP 原生示例
 *
 * 部署方式：
 *   1. 将此文件放到 web 服务器可访问的位置（如 /var/www/html/novaix-callback.php）
 *   2. 设置环境变量 NOVAIX_CALLBACK_SECRET 为创建 API 密钥时返回的 callback_secret
 *   3. 在 NovaIx 创建 API 密钥时填入此文件的 HTTPS URL
 *
 * 关键点：
 *   - 必须用原始请求体计算 HMAC，不能用 json_decode 后的结果
 *   - 必须用 hash_equals 做恒定时间比较，避免时序攻击
 *   - 必须用 HTTPS（生产环境）
 */

const SIGNATURE_HEADER = 'HTTP_X_NOVAIX_SIGNATURE';
const MAX_BODY_SIZE    = 65536; // 64 KB，挡掉大 payload 攻击

function readSecret(): string {
    $secret = getenv('NOVAIX_CALLBACK_SECRET') ?: '';
    if ($secret === '') {
        http_response_code(500);
        echo json_encode(['error' => '服务端未配置 NOVAIX_CALLBACK_SECRET']);
        exit;
    }
    return $secret;
}

function verifySignature(string $rawBody, string $signature, string $secret): bool {
    if (empty($signature)) {
        return false;
    }
    $expected = hash_hmac('sha256', $rawBody, $secret);
    return hash_equals($expected, $signature);
}

// 1. 读取原始请求体（限制大小）
$rawBody = file_get_contents('php://input', false, null, 0, MAX_BODY_SIZE + 1);
if (strlen($rawBody) > MAX_BODY_SIZE) {
    http_response_code(413);
    echo json_encode(['error' => 'payload 过大']);
    exit;
}

// 2. 验证签名
$signature = $_SERVER[SIGNATURE_HEADER] ?? '';
$secret = readSecret();

if (!verifySignature($rawBody, $signature, $secret)) {
    http_response_code(401);
    echo json_encode(['error' => '签名验证失败']);
    exit;
}

// 3. 解析 payload
$payload = json_decode($rawBody, true);
if (!is_array($payload)) {
    http_response_code(400);
    echo json_encode(['error' => '无效的 JSON']);
    exit;
}

// 4. 业务处理
$event      = $payload['event']       ?? '';
$taskID     = $payload['task_id']     ?? 0;
$taskType   = $payload['task_type']   ?? '';
$externalID = $payload['external_id'] ?? '';
$status     = $payload['status']      ?? '';

// 幂等处理：检查 task_id 是否已处理过（伪代码）
// if (alreadyProcessed($taskID)) {
//     http_response_code(200);
//     echo 'ok';
//     exit;
// }

switch ($taskType) {
    case 'create_instance':
        if ($status === 'completed') {
            $ipAddress = $payload['data']['ip_address'] ?? '';
            $hostname  = $payload['data']['hostname']   ?? '';
            error_log("[NovaIx] 实例开通成功 external_id={$externalID} ip={$ipAddress}");
            // markServiceActive($externalID, $ipAddress, $hostname);
        } else {
            $errorMsg = $payload['result'] ?? '未知错误';
            error_log("[NovaIx] 实例开通失败 external_id={$externalID} reason={$errorMsg}");
            // markServiceFailed($externalID, $errorMsg);
        }
        break;

    case 'delete_instance':
        error_log("[NovaIx] 实例删除完成 external_id={$externalID}");
        // markServiceTerminated($externalID);
        break;

    case 'start_instance':
    case 'stop_instance':
    case 'restart_instance':
    case 'freeze_instance':
    case 'unfreeze_instance':
        error_log("[NovaIx] 操作完成 task_type={$taskType} status={$status} external_id={$externalID}");
        break;

    default:
        error_log("[NovaIx] 未处理的任务类型 task_type={$taskType}");
}

// 5. markProcessed($taskID);

// 6. 返回 2xx 表示成功接收（NovaIx 不会重试）
http_response_code(200);
echo 'ok';
