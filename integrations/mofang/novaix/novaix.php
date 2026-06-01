<?php
/**
 * NovaIx - 智简魔方（IDCsmart）服务器模块
 *
 * 通过 Provisioning API 对接 NovaIx IDC 管理系统，
 * 实现 VPS 实例的自动开通、暂停、恢复、删除等全生命周期管理。
 */

function novaix_MetaData()
{
    return [
        'DisplayName' => 'NovaIx VPS',
        'APIVersion'  => '1.1',
        'HelpDoc'     => 'https://docs.huohuastudio.com/novaix/integrations/mofang',
    ];
}

function novaix_ConfigOptions()
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

function novaix_TestLink($params)
{
    $result = _novaix_request($params, 'POST', '/test');
    if ($result && ($result['code'] ?? -1) === 0) {
        return ['status' => 'success', 'msg' => '连接成功 (v' . ($result['data']['version'] ?? '?') . ')'];
    }
    return ['status' => 'error', 'msg' => $result['message'] ?? '连接失败'];
}

function novaix_CreateAccount($params)
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

    $result = _novaix_request($params, 'POST', '/instances', $data);
    if (!$result || ($result['code'] ?? -1) !== NOVAIX_OK) {
        return ['status' => 'error', 'msg' => $result['message'] ?? '开通请求失败'];
    }

    $instanceId = (int) ($result['data']['instance_id'] ?? 0);
    if ($instanceId <= 0) {
        return ['status' => 'error', 'msg' => '开通响应缺少 instance_id'];
    }

    return _novaix_wait_for_running($params, $instanceId);
}

function _novaix_wait_for_running($params, $instanceId)
{
    $start = time();
    while (time() - $start < NOVAIX_CREATE_TIMEOUT) {
        sleep(NOVAIX_POLL_INTERVAL);

        $status = _novaix_request($params, 'GET', "/instances/{$instanceId}/status");
        if (!$status || ($status['code'] ?? -1) !== NOVAIX_OK) {
            continue;
        }

        $s = $status['data']['status'] ?? '';
        if ($s === 'running' || $s === 'stopped') {
            return ['status' => 'success', 'msg' => '开通成功'];
        }
        if ($s === 'error') {
            return ['status' => 'error', 'msg' => '实例创建失败，请检查 NovaIx 后台任务日志'];
        }
    }

    return ['status' => 'error', 'msg' => '等待实例创建超时（' . NOVAIX_CREATE_TIMEOUT . 's），请稍后到 NovaIx 后台确认状态'];
}

function novaix_SuspendAccount($params)
{
    $reason = $params['suspend_type'] ?? 'overdue';
    return _novaix_action_and_wait($params, 'suspend', ['reason' => $reason]);
}

function novaix_UnsuspendAccount($params)
{
    return _novaix_action_and_wait($params, 'unsuspend');
}

function novaix_TerminateAccount($params)
{
    return _novaix_action_and_wait($params, 'terminate');
}

function novaix_On($params)
{
    return _novaix_simple_action($params, 'start');
}

function novaix_Off($params)
{
    return _novaix_simple_action($params, 'stop');
}

function novaix_Reboot($params)
{
    return _novaix_simple_action($params, 'reboot');
}

function novaix_Reinstall($params)
{
    return _novaix_action_and_wait($params, 'reinstall', [
        'image_id' => (int) ($params['configoptions']['image_id'] ?? 0),
        'password' => $params['password'] ?? '',
    ]);
}

function novaix_CrackPassword($params)
{
    return _novaix_action_and_wait($params, 'reset-password', [
        'password' => $params['password'] ?? '',
    ]);
}

function novaix_AllowFunction()
{
    return [
        'client' => ['On', 'Off', 'Reboot', 'Reinstall', 'CrackPassword'],
        'admin'  => [],
    ];
}

function novaix_ClientArea($params)
{
    return [];
}

function novaix_ClientAreaOutput($params, $key)
{
    return '';
}

// ========== 内部辅助函数 ==========

const NOVAIX_OK = 0;
const NOVAIX_CREATE_TIMEOUT = 180;
const NOVAIX_ACTION_TIMEOUT = 60;
const NOVAIX_POLL_INTERVAL  = 3;

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
    $scheme = !empty($params['secure']) ? 'https' : 'http';
    $host = $params['server_host'] ?? ($params['server_ip'] ?? '');
    $port = $params['port'] ?? '';

    $baseUrl = $scheme . '://' . $host;
    if ($port !== '' && $port !== '443' && $port !== '80') {
        $baseUrl .= ':' . $port;
    }

    $url = $baseUrl . '/api/v1/provision' . $path;
    $apiKey = $params['server_password'] ?? ($params['accesshash'] ?? '');

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
