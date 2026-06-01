<?php
/**
 * NovaIx WHMCS Provisioning Module
 *
 * 通过 NovaIx Provisioning API 实现自动开通、暂停、删除等全生命周期管理。
 *
 * 安装：将本目录复制到 WHMCS 的 modules/servers/novaix/ 下
 * 文档：https://developers.whmcs.com/provisioning-modules/
 */

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

const NOVAIX_OK              = 0;
const NOVAIX_CREATE_TIMEOUT  = 180; // 创建轮询最长等待秒数
const NOVAIX_ACTION_TIMEOUT  = 60;  // 暂停/恢复/删除/重装等动作轮询最长秒数
const NOVAIX_POLL_INTERVAL   = 3;   // 轮询间隔秒数

/**
 * 模块元信息
 */
function novaix_MetaData()
{
    return [
        'DisplayName'                => 'NovaIx VPS',
        'APIVersion'                 => '1.1',
        'RequiresServer'             => true,
        'DefaultNonSSLPort'          => '8080',
        'DefaultSSLPort'             => '443',
        'ServiceSingleSignOnLabel'   => '登录 NovaIx 控制台',
        'AdminSingleSignOnLabel'     => '管理 NovaIx 实例',
    ];
}

/**
 * 产品自定义字段（管理员在 WHMCS 配置产品时填写）
 */
function novaix_ConfigOptions()
{
    return [
        'plan_id' => [
            'FriendlyName' => 'NovaIx 套餐 ID',
            'Type'         => 'text',
            'Size'         => '10',
            'Description'  => '从 NovaIx 后台获取的套餐 ID（决定 CPU/内存/磁盘）',
        ],
        'image_id' => [
            'FriendlyName' => '默认镜像 ID',
            'Type'         => 'text',
            'Size'         => '10',
            'Description'  => 'NovaIx 中的操作系统镜像 ID',
        ],
        'node_id' => [
            'FriendlyName' => '指定节点 ID（可选）',
            'Type'         => 'text',
            'Size'         => '10',
            'Description'  => '留空则由 NovaIx 自动选择可用节点',
            'Default'      => '',
        ],
    ];
}

/**
 * 测试与 NovaIx 服务器的连通性
 */
function novaix_TestConnection(array $params)
{
    try {
        $result = _novaix_request($params, 'POST', '/test');
        if ($result && ($result['code'] ?? -1) === NOVAIX_OK) {
            return ['success' => true];
        }
        return [
            'error'   => $result['message'] ?? '连通性测试失败',
            'success' => false,
        ];
    } catch (Exception $e) {
        return [
            'error'   => $e->getMessage(),
            'success' => false,
        ];
    }
}

/**
 * 开通：提交创建请求并同步等待实例就绪
 *
 * WHMCS 约定：成功返回 "success"，失败返回错误字符串
 */
function novaix_CreateAccount(array $params)
{
    try {
        // 优先按名访问（更稳健），兼容编号访问
        $planID  = (int) ($params['configoptions']['plan_id']  ?? $params['configoption1'] ?? 0);
        $imageID = (int) ($params['configoptions']['image_id'] ?? $params['configoption2'] ?? 0);
        $nodeID  = (int) ($params['configoptions']['node_id']  ?? $params['configoption3'] ?? 0);

        if ($planID <= 0 || $imageID <= 0) {
            return '产品配置缺少 plan_id 或 image_id';
        }

        $data = [
            'plan_id'     => $planID,
            'image_id'    => $imageID,
            'hostname'    => $params['domain'] ?? '',
            'password'    => $params['password'] ?? '',
            'external_id' => (string) $params['serviceid'],
            'user_email'  => $params['clientsdetails']['email'] ?? '',
        ];
        if ($nodeID > 0) {
            $data['node_id'] = $nodeID;
        }

        $result = _novaix_request($params, 'POST', '/instances', $data);
        if (!$result || ($result['code'] ?? -1) !== NOVAIX_OK) {
            return $result['message'] ?? '开通请求失败';
        }

        $instanceID = (int) ($result['data']['instance_id'] ?? 0);
        if ($instanceID <= 0) {
            return '开通响应缺少 instance_id';
        }

        return _novaix_wait_for_running($params, $instanceID);
    } catch (Exception $e) {
        logModuleCall('novaix', __FUNCTION__, $params, $e->getMessage(), $e->getTraceAsString());
        return $e->getMessage();
    }
}

// 关键计费动作（暂停/解除/删除）必须等待任务真正完成，否则计费侧已标记
// 状态变更但 NovaIx 端任务可能失败，导致两侧不一致。
// 启停/重启等"用户操作"则保持 fire-and-forget。

function novaix_SuspendAccount(array $params)
{
    return _novaix_action_and_wait($params, 'suspend', [
        'reason' => $params['suspendreason'] ?? 'overdue',
    ]);
}

function novaix_UnsuspendAccount(array $params)
{
    return _novaix_action_and_wait($params, 'unsuspend');
}

function novaix_TerminateAccount(array $params)
{
    return _novaix_action_and_wait($params, 'terminate');
}

function novaix_ChangePassword(array $params)
{
    try {
        $serviceID = $params['serviceid'];
        $result = _novaix_request($params, 'POST',
            "/instances/{$serviceID}/reset-password?by=external_id", [
                'password' => $params['password'] ?? '',
            ]);
        return _novaix_wait_for_task_from_result($params, $result);
    } catch (Exception $e) {
        return $e->getMessage();
    }
}

// 自定义按钮 key 必须是英文：WHMCS 会把它放进 URL 参数（customAction=Boot），
// 中文 key 在多数模板里不会被 urlencode，会出问题。
// 客户端显示中文标签的话，应当用 WHMCS 语言文件（lang/chinese.php）翻译。

function novaix_ClientAreaCustomButtonArray()
{
    return [
        'Boot'     => 'startInstance',
        'Shutdown' => 'stopInstance',
        'Reboot'   => 'rebootInstance',
    ];
}

function novaix_ClientAreaAllowedFunctions()
{
    return [
        'Boot'     => 'startInstance',
        'Shutdown' => 'stopInstance',
        'Reboot'   => 'rebootInstance',
    ];
}

function novaix_startInstance(array $params)
{
    return _novaix_simple_action($params, 'start');
}

function novaix_stopInstance(array $params)
{
    return _novaix_simple_action($params, 'stop');
}

function novaix_rebootInstance(array $params)
{
    return _novaix_simple_action($params, 'reboot');
}

// ========== 内部辅助函数 ==========

function _novaix_simple_action(array $params, string $action, array $data = [])
{
    try {
        $serviceID = $params['serviceid'];
        $result = _novaix_request($params, 'POST',
            "/instances/{$serviceID}/{$action}?by=external_id", $data);
        return _novaix_string_result($result);
    } catch (Exception $e) {
        logModuleCall('novaix', $action, $params, $e->getMessage(), $e->getTraceAsString());
        return $e->getMessage();
    }
}

function _novaix_action_and_wait(array $params, string $action, array $data = [])
{
    try {
        $serviceID = $params['serviceid'];
        $result = _novaix_request($params, 'POST',
            "/instances/{$serviceID}/{$action}?by=external_id", $data);
        return _novaix_wait_for_task_from_result($params, $result);
    } catch (Exception $e) {
        logModuleCall('novaix', $action, $params, $e->getMessage(), $e->getTraceAsString());
        return $e->getMessage();
    }
}

function _novaix_wait_for_task_from_result(array $params, $result)
{
    if (!is_array($result) || ($result['code'] ?? -1) !== NOVAIX_OK) {
        return is_array($result) ? ($result['message'] ?? '请求失败') : '请求失败';
    }
    $taskID = (int) ($result['data']['task_id'] ?? 0);
    if ($taskID <= 0) {
        // 响应不带 task_id（如同步操作），视为已完成
        return 'success';
    }
    return _novaix_wait_for_task($params, $taskID, NOVAIX_ACTION_TIMEOUT);
}

function _novaix_string_result($result)
{
    if (is_array($result) && ($result['code'] ?? -1) === NOVAIX_OK) {
        return 'success';
    }
    return is_array($result) ? ($result['message'] ?? 'operation failed') : 'operation failed';
}

function _novaix_wait_for_task(array $params, int $taskID, int $timeout)
{
    $start = time();
    $consecutiveErrors = 0;

    while (time() - $start < $timeout) {
        sleep(NOVAIX_POLL_INTERVAL);

        try {
            $resp = _novaix_request($params, 'GET', "/tasks/{$taskID}");
            $consecutiveErrors = 0;
        } catch (Exception $e) {
            if (++$consecutiveErrors >= 5) {
                return '查询任务状态多次失败: ' . $e->getMessage();
            }
            continue;
        }

        if (!is_array($resp) || ($resp['code'] ?? -1) !== NOVAIX_OK) {
            continue;
        }
        $s = $resp['data']['status'] ?? '';
        if ($s === 'completed') {
            return 'success';
        }
        if ($s === 'failed') {
            return $resp['data']['result'] ?? '任务失败';
        }
    }

    return '等待任务完成超时（' . $timeout . 's）';
}

function _novaix_wait_for_running(array $params, int $instanceID)
{
    $start = time();
    $consecutiveErrors = 0;

    while (time() - $start < NOVAIX_CREATE_TIMEOUT) {
        sleep(NOVAIX_POLL_INTERVAL);

        try {
            $status = _novaix_request($params, 'GET', "/instances/{$instanceID}/status");
            $consecutiveErrors = 0;
        } catch (Exception $e) {
            // 单次网络抖动不应让整个 CreateAccount 失败；连续 N 次后放弃
            if (++$consecutiveErrors >= 5) {
                return '查询实例状态多次失败: ' . $e->getMessage();
            }
            continue;
        }

        if (!is_array($status) || ($status['code'] ?? -1) !== NOVAIX_OK) {
            continue;
        }

        $s = $status['data']['status'] ?? '';
        if ($s === 'running' || $s === 'stopped') {
            return 'success';
        }
        if ($s === 'error') {
            return '实例创建失败，请到 NovaIx 后台查看任务日志';
        }
    }

    return '等待实例创建超时（' . NOVAIX_CREATE_TIMEOUT . 's）';
}

function _novaix_request(array $params, string $method, string $path, array $data = [])
{
    // 服务端连接信息从 WHMCS 服务器配置中读取
    $secure  = !empty($params['serversecure']);
    $scheme  = $secure ? 'https' : 'http';
    $host    = $params['serverhostname'] ?? '';
    $port    = $params['serverport'] ?? '';
    $apiKey  = $params['serveraccesshash'] ?? '';

    if (empty($host) || empty($apiKey)) {
        throw new Exception('NovaIx 服务器未配置（缺少 hostname 或 accesshash）');
    }

    $portPart = (!empty($port) && $port !== '80' && $port !== '443') ? ':' . $port : '';
    $url = "{$scheme}://{$host}{$portPart}/api/v1/provision{$path}";

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json',
            'Accept: application/json',
        ],
    ]);

    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        if (!empty($data)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
    } elseif ($method !== 'GET') {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    }

    $response  = curl_exec($ch);
    $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        throw new Exception('NovaIx 请求失败: ' . $curlError);
    }

    $decoded = json_decode($response, true);
    if (!is_array($decoded)) {
        throw new Exception("NovaIx 响应解析失败 (HTTP {$httpCode})");
    }
    return $decoded;
}
