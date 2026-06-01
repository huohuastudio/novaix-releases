<?php
/**
 * NovaIx - 智简魔方（IDCsmart）服务器模块
 *
 * 通过 Provisioning API 对接 NovaIx IDC 管理系统，
 * 实现 VPS 实例的自动开通、暂停、恢复、删除等全生命周期管理。
 */

function NovaIx_MetaData()
{
    return [
        'display_name' => 'NovaIx VPS',
        'version'      => '1.0.0',
        'author'       => 'NovaIx',
    ];
}

function NovaIx_ConfigOptions()
{
    return [
        [
            'type'        => 'text',
            'name'        => '套餐 ID',
            'description' => '在 NovaIx 后台创建的套餐 ID',
            'key'         => 'plan_id',
        ],
        [
            'type'        => 'text',
            'name'        => '镜像 ID',
            'description' => '默认操作系统镜像 ID',
            'key'         => 'image_id',
        ],
        [
            'type'        => 'text',
            'name'        => '节点 ID（可选）',
            'description' => '留空则自动选择可用节点',
            'key'         => 'node_id',
            'default'     => '',
        ],
    ];
}

function NovaIx_TestLink($params)
{
    $result = _novaix_request($params, 'POST', '/test');
    if ($result && ($result['code'] ?? -1) === 0) {
        return ['status' => 'success', 'msg' => '连接成功 (v' . ($result['data']['version'] ?? '?') . ')'];
    }
    return ['status' => 'error', 'msg' => $result['message'] ?? '连接失败'];
}

function NovaIx_CreateAccount($params)
{
    $data = [
        'plan_id'     => (int) ($params['configoptions']['plan_id'] ?? 0),
        'image_id'    => (int) ($params['configoptions']['image_id'] ?? 0),
        'hostname'    => $params['domain'] ?? '',
        'password'    => $params['password'] ?? '',
        'external_id' => (string) $params['hostid'],
        'user_email'  => $params['clientsdetails']['email'] ?? ($params['email'] ?? ''),
    ];

    $nodeId = $params['configoptions']['node_id'] ?? '';
    if ($nodeId !== '') {
        $data['node_id'] = (int) $nodeId;
    }

    // 提交创建请求（NovaIx 异步创建实例）
    $result = _novaix_request($params, 'POST', '/instances', $data);
    if (!$result || ($result['code'] ?? -1) !== NOVAIX_OK) {
        return ['status' => 'error', 'msg' => $result['message'] ?? '开通请求失败'];
    }

    $instanceId = (int) ($result['data']['instance_id'] ?? 0);
    if ($instanceId <= 0) {
        return ['status' => 'error', 'msg' => '开通响应缺少 instance_id'];
    }

    // 轮询等待最终状态，最多 NOVAIX_CREATE_TIMEOUT 秒
    return _novaix_wait_for_running($params, $instanceId);
}

function _novaix_wait_for_running($params, $instanceId)
{
    $start = time();
    while (time() - $start < NOVAIX_CREATE_TIMEOUT) {
        sleep(NOVAIX_POLL_INTERVAL);

        $status = _novaix_request($params, 'GET', "/instances/{$instanceId}/status");
        if (!$status || ($status['code'] ?? -1) !== NOVAIX_OK) {
            continue; // 偶发网络/查询失败，继续等待
        }

        $s = $status['data']['status'] ?? '';
        if ($s === 'running' || $s === 'stopped') {
            return ['status' => 'success', 'msg' => '开通成功'];
        }
        if ($s === 'error') {
            return ['status' => 'error', 'msg' => '实例创建失败，请检查 NovaIx 后台任务日志'];
        }
        // creating/其他过渡状态：继续轮询
    }

    return ['status' => 'error', 'msg' => '等待实例创建超时（' . NOVAIX_CREATE_TIMEOUT . 's），请稍后到 NovaIx 后台确认状态'];
}

// 关键计费动作（暂停/解除/删除）等待任务真正完成，避免和魔方两侧状态不一致

function NovaIx_SuspendAccount($params)
{
    $reason = $params['suspend_type'] ?? 'overdue';
    return _novaix_action_and_wait($params, 'suspend', ['reason' => $reason]);
}

function NovaIx_UnsuspendAccount($params)
{
    return _novaix_action_and_wait($params, 'unsuspend');
}

function NovaIx_TerminateAccount($params)
{
    return _novaix_action_and_wait($params, 'terminate');
}

function NovaIx_On($params)
{
    return _novaix_simple_action($params, 'start');
}

function NovaIx_Off($params)
{
    return _novaix_simple_action($params, 'stop');
}

function NovaIx_Reboot($params)
{
    return _novaix_simple_action($params, 'reboot');
}

function NovaIx_Reinstall($params)
{
    return _novaix_action_and_wait($params, 'reinstall', [
        'image_id' => (int) ($params['configoptions']['image_id'] ?? 0),
        'password' => $params['password'] ?? '',
    ]);
}

function NovaIx_CrackPassword($params, $newPassword)
{
    return _novaix_action_and_wait($params, 'reset-password', [
        'password' => $newPassword,
    ]);
}

function NovaIx_AllowFunction()
{
    return [
        'client' => ['On', 'Off', 'Reboot', 'Reinstall', 'CrackPassword'],
        'admin'  => [],
    ];
}

function NovaIx_ClientArea($params)
{
    return [];
}

function NovaIx_ClientAreaOutput($params, $key)
{
    return '';
}

function NovaIx_hostList($params)
{
    return [];
}

function NovaIx_clientProductConfigOption($params)
{
    return [];
}

function NovaIx_cartCalculatePrice($params)
{
    return [];
}

// ========== 内部辅助函数 ==========

const NOVAIX_OK = 0;
const NOVAIX_CREATE_TIMEOUT = 180; // 创建轮询最长等待秒数
const NOVAIX_ACTION_TIMEOUT = 60;  // 暂停/恢复/删除/重装等动作轮询最长秒数
const NOVAIX_POLL_INTERVAL  = 3;   // 轮询间隔秒数

function _novaix_simple_action($params, $action, $data = [])
{
    $hostId = $params['hostid'];
    $result = _novaix_request($params, 'POST', "/instances/{$hostId}/{$action}?by=external_id", $data);
    return _novaix_format($result);
}

function _novaix_action_and_wait($params, $action, $data = [])
{
    $hostId = $params['hostid'];
    $result = _novaix_request($params, 'POST', "/instances/{$hostId}/{$action}?by=external_id", $data);
    if (!$result || ($result['code'] ?? -1) !== NOVAIX_OK) {
        return ['status' => 'error', 'msg' => $result['message'] ?? '请求失败'];
    }
    $taskId = (int) ($result['data']['task_id'] ?? 0);
    if ($taskId <= 0) {
        return ['status' => 'success', 'msg' => '操作成功'];
    }
    return _novaix_wait_for_task($params, $taskId);
}

function _novaix_wait_for_task($params, $taskId)
{
    $start = time();
    $errors = 0;
    while (time() - $start < NOVAIX_ACTION_TIMEOUT) {
        sleep(NOVAIX_POLL_INTERVAL);

        $resp = _novaix_request($params, 'GET', "/tasks/{$taskId}");
        if (!$resp || ($resp['code'] ?? -1) !== NOVAIX_OK) {
            if (++$errors >= 5) {
                return ['status' => 'error', 'msg' => '查询任务状态多次失败'];
            }
            continue;
        }
        $errors = 0;
        $s = $resp['data']['status'] ?? '';
        if ($s === 'completed') {
            return ['status' => 'success', 'msg' => '操作成功'];
        }
        if ($s === 'failed') {
            return ['status' => 'error', 'msg' => $resp['data']['result'] ?? '任务失败'];
        }
    }
    return ['status' => 'error', 'msg' => '等待任务完成超时（' . NOVAIX_ACTION_TIMEOUT . 's）'];
}

function _novaix_request($params, $method, $path, $data = [])
{
    $apiUrl = rtrim($params['server_ip'] ?? '', '/');
    $apiKey = $params['accesshash'] ?? '';

    $url = $apiUrl . '/api/v1/provision' . $path;

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
    }

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        return ['code' => -1, 'message' => '网络请求失败: ' . $curlError];
    }

    $decoded = json_decode($response, true);
    if (!is_array($decoded)) {
        return ['code' => -1, 'message' => "响应解析失败 (HTTP {$httpCode})"];
    }
    return $decoded;
}

function _novaix_format($result, $successMsg = '操作成功')
{
    if ($result && ($result['code'] ?? -1) === NOVAIX_OK) {
        return ['status' => 'success', 'msg' => $successMsg];
    }
    return ['status' => 'error', 'msg' => $result['message'] ?? '操作失败'];
}
