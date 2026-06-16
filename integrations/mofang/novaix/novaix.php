<?php
/**
 * NovaIx - 智简魔方 V10（IDCsmart ZJMF-CBAP）服务器子模块
 *
 * 安装路径：{idcsmart}/public/plugins/server/idcsmart_common/module/novaix/
 */

const NOVAIX_OK             = 0;
const NOVAIX_CREATE_TIMEOUT = 180;
const NOVAIX_ACTION_TIMEOUT = 60;
const NOVAIX_HEAVY_TIMEOUT  = 180;
const NOVAIX_POLL_INTERVAL  = 3;

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
    if (_novaix_is_ok($result)) {
        return [
            'status' => 200,
            'data'   => [
                'server_status' => 1,
                'msg'           => '连接成功 (v' . ($result['data']['version'] ?? '?') . ')',
            ],
        ];
    }
    return [
        'status' => 200,
        'data'   => [
            'server_status' => 0,
            'msg'           => $result['message'] ?? '连接失败',
        ],
    ];
}

function novaix_CreateAccount($params)
{
    $password = $params['password'] ?? '';
    if (empty($password)) {
        $password = rand_str(12);
    }

    $data = [
        'plan_id'     => (int) _novaix_config($params, 'plan_id'),
        'image_id'    => (int) _novaix_config($params, 'image_id'),
        'hostname'    => $params['domain'] ?? '',
        'password'    => $password,
        'external_id' => (string) $params['hostid'],
        'user_email'  => $params['user_info']['email'] ?? ($params['email'] ?? ''),
    ];

    $nodeId = _novaix_config($params, 'node_id');
    if ($nodeId !== '' && $nodeId !== '0') {
        $data['node_id'] = (int) $nodeId;
    }

    $result = _novaix_request($params, 'POST', '/instances', $data);
    if (!_novaix_is_ok($result)) {
        return ['status' => 'error', 'msg' => $result['message'] ?? '开通请求失败'];
    }

    $instanceId = (int) ($result['data']['instance_id'] ?? 0);
    if ($instanceId <= 0) {
        return ['status' => 'error', 'msg' => '开通响应缺少 instance_id'];
    }

    $waitResult = _novaix_wait_for_running($params, $instanceId);
    if ($waitResult['status'] !== 'success') {
        return $waitResult;
    }

    $update = [
        'username' => 'root',
        'password' => function_exists('password_encrypt') ? password_encrypt($password) : $password,
    ];

    $detail = _novaix_request($params, 'GET', "/instances/{$instanceId}");
    if (_novaix_is_ok($detail)) {
        $instanceData = $detail['data'] ?? [];
        $mainIp = $instanceData['ip_address'] ?? '';
        $ipv6   = $instanceData['ipv6_address'] ?? '';

        if ($mainIp !== '') {
            $update['dedicatedip'] = $mainIp;
        }
        if ($ipv6 !== '') {
            $update['assignedips'] = $ipv6;
        }
    }

    _novaix_update_host_link($params['hostid'], $update);

    if (!empty($update['dedicatedip'])) {
        _novaix_update_host_ip($params['hostid'], $update['dedicatedip'], $update['assignedips'] ?? '');
    }

    return $waitResult;
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
    return _novaix_action_and_wait($params, 'terminate', [], NOVAIX_HEAVY_TIMEOUT);
}

function novaix_Renew($params)
{
    return ['status' => 'success', 'msg' => '续费成功'];
}

function novaix_ChangePackage($params)
{
    return ['status' => 'success', 'msg' => '升降级成功'];
}

function novaix_On($params)
{
    return _novaix_action_and_wait($params, 'start');
}

function novaix_Off($params)
{
    return _novaix_action_and_wait($params, 'stop');
}

// NovaIx API 不区分软/硬操作，映射到相同端点
function novaix_HardOff($params)
{
    return _novaix_action_and_wait($params, 'stop');
}

function novaix_Reboot($params)
{
    return _novaix_action_and_wait($params, 'reboot');
}

// NovaIx API 不区分软/硬操作，映射到相同端点
function novaix_HardReboot($params)
{
    return _novaix_action_and_wait($params, 'reboot');
}

function novaix_Reinstall($params)
{
    $imageId = (int) _novaix_config($params, 'image_id');
    if (!empty($params['reinstall_os'])) {
        $imageId = (int) $params['reinstall_os'];
    }

    $password = $params['password'] ?? '';
    if (empty($password)) {
        $password = function_exists('rand_str') ? rand_str(12) : substr(str_shuffle('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 12);
    }

    $result = _novaix_action_and_wait($params, 'reinstall', [
        'image_id' => $imageId,
        'password' => $password,
    ], NOVAIX_HEAVY_TIMEOUT);

    if ($result['status'] === 'success') {
        $encrypted = function_exists('password_encrypt') ? password_encrypt($password) : $password;
        _novaix_update_host_link($params['hostid'], ['password' => $encrypted]);
    }

    return $result;
}

function novaix_CrackPassword($params, $newPass = '')
{
    $password = $newPass !== '' ? $newPass : ($params['password'] ?? '');
    return _novaix_action_and_wait($params, 'reset-password', [
        'password' => $password,
    ]);
}

function novaix_Status($params)
{
    $hostId = $params['hostid'];
    $result = _novaix_request($params, 'GET', "/instances/{$hostId}/status?by=external_id");
    if (!_novaix_is_ok($result)) {
        return ['status' => 'error', 'msg' => $result['message'] ?? '查询状态失败'];
    }

    $map = [
        'running'  => ['status' => 'on',      'des' => '运行中'],
        'stopped'  => ['status' => 'off',      'des' => '已关机'],
        'frozen'   => ['status' => 'suspend',  'des' => '已暂停'],
        'creating' => ['status' => 'process',  'des' => '创建中'],
        'error'    => ['status' => 'off',      'des' => '异常'],
        'deleting' => ['status' => 'process',  'des' => '删除中'],
    ];
    $s = $result['data']['status'] ?? 'unknown';
    $mapped = $map[$s] ?? ['status' => 'unknown', 'des' => '未知'];

    return ['status' => 'success', 'data' => $mapped];
}

function novaix_Sync($params)
{
    $hostId = $params['hostid'];
    $result = _novaix_request($params, 'GET', "/instances/{$hostId}?by=external_id");
    if (!_novaix_is_ok($result)) {
        return ['status' => 'error', 'msg' => $result['message'] ?? '同步失败'];
    }

    $data      = $result['data'] ?? [];
    $linkUpdate = [];

    if (!empty($data['ip_address'])) {
        $linkUpdate['dedicatedip'] = $data['ip_address'];
    }
    if (!empty($data['ipv6_address'])) {
        $linkUpdate['assignedips'] = $data['ipv6_address'];
    }

    if (!empty($linkUpdate)) {
        _novaix_update_host_link($hostId, $linkUpdate);
        _novaix_update_host_ip(
            $hostId,
            $linkUpdate['dedicatedip'] ?? '',
            $linkUpdate['assignedips'] ?? ''
        );
    }

    $statusMap = [
        'running' => 'Active',
        'stopped' => 'Active',
        'frozen'  => 'Suspended',
        'error'   => 'Active',
    ];
    $s = $data['status'] ?? '';
    if (isset($statusMap[$s])) {
        _novaix_update_host_status($hostId, $statusMap[$s]);
    }

    return ['status' => 'success', 'data' => $linkUpdate];
}

function novaix_AllowFunction()
{
    return [
        'client' => ['On', 'Off', 'Reboot', 'HardOff', 'HardReboot', 'Reinstall', 'CrackPassword'],
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

function novaix_ClientButton($params)
{
    return [];
}

function novaix_AdminArea($params)
{
    return '';
}

function novaix_Chart($params)
{
    return [];
}

function _novaix_is_ok($result)
{
    return $result && ($result['code'] ?? -1) === NOVAIX_OK;
}

function _novaix_config($params, $key)
{
    if (isset($params['configoptions'][$key])) {
        return $params['configoptions'][$key];
    }
    $mapping = ['plan_id' => 1, 'image_id' => 2, 'node_id' => 3];
    $idx = $mapping[$key] ?? null;
    if ($idx !== null && isset($params['config_option' . $idx])) {
        return $params['config_option' . $idx];
    }
    return '';
}

function _novaix_update_host_link($hostId, $data)
{
    try {
        $model = new \server\idcsmart_common\model\IdcsmartCommonServerHostLinkModel();
        $model->where('host_id', $hostId)->update($data);
    } catch (\Throwable $e) {
        _novaix_log('写回 server_host_link 失败: host_id=' . $hostId . ' error=' . $e->getMessage());
    }
}

function _novaix_update_host_ip($hostId, $dedicateIp, $assignIp = '')
{
    try {
        $model = new \app\common\model\HostIpModel();
        $model->hostIpSave([
            'host_id'     => $hostId,
            'dedicate_ip' => $dedicateIp,
            'assign_ip'   => $assignIp,
            'write_log'   => true,
        ]);
    } catch (\Throwable $e) {
        _novaix_log('写回 host_ip 失败: host_id=' . $hostId . ' error=' . $e->getMessage());
    }
}

function _novaix_update_host_status($hostId, $status)
{
    try {
        $model = new \app\common\model\HostModel();
        $model->where('id', $hostId)->update(['status' => $status]);
    } catch (\Throwable $e) {
        _novaix_log('写回 host status 失败: host_id=' . $hostId . ' error=' . $e->getMessage());
    }
}

function _novaix_log($message)
{
    if (function_exists('active_log')) {
        active_log($message, 'module', 'novaix');
    }
}

function _novaix_action_and_wait($params, $action, $data = [], $timeout = NOVAIX_ACTION_TIMEOUT)
{
    $hostId = $params['hostid'];
    $result = _novaix_request($params, 'POST', "/instances/{$hostId}/{$action}?by=external_id", $data);
    if (!_novaix_is_ok($result)) {
        return ['status' => 'error', 'msg' => $result['message'] ?? '请求失败'];
    }
    $taskId = (int) ($result['data']['task_id'] ?? 0);
    if ($taskId <= 0) {
        return ['status' => 'success', 'msg' => '操作成功'];
    }
    return _novaix_wait_for_task($params, $taskId, $timeout);
}

function _novaix_wait_for_running($params, $instanceId)
{
    $start = time();
    while (time() - $start < NOVAIX_CREATE_TIMEOUT) {
        sleep(NOVAIX_POLL_INTERVAL);

        $status = _novaix_request($params, 'GET', "/instances/{$instanceId}/status");
        if (!_novaix_is_ok($status)) {
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

    return ['status' => 'error', 'msg' => '等待实例创建超时（' . NOVAIX_CREATE_TIMEOUT . 's），请稍后确认状态'];
}

function _novaix_wait_for_task($params, $taskId, $timeout = NOVAIX_ACTION_TIMEOUT)
{
    $start  = time();
    $errors = 0;
    while (time() - $start < $timeout) {
        sleep(NOVAIX_POLL_INTERVAL);

        $resp = _novaix_request($params, 'GET', "/tasks/{$taskId}");
        if (!_novaix_is_ok($resp)) {
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
    return ['status' => 'error', 'msg' => '等待任务完成超时（' . $timeout . 's）'];
}

function _novaix_request($params, $method, $path, $data = [])
{
    $scheme = !empty($params['secure']) ? 'https' : 'http';
    $host   = !empty($params['server_host']) ? $params['server_host'] : ($params['server_ip'] ?? '');
    $port   = !empty($params['port']) ? $params['port'] : ($params['server_port'] ?? '');

    $baseUrl = $scheme . '://' . $host;
    if ($port !== '' && $port !== '443' && $port !== '80') {
        $baseUrl .= ':' . $port;
    }

    $url    = $baseUrl . '/api/v1/provision' . $path;
    $apiKey = !empty($params['server_password']) ? $params['server_password'] : ($params['accesshash'] ?? '');

    $headers = [
        'Authorization: Bearer ' . $apiKey,
        'Accept: application/json',
    ];
    if ($method === 'POST') {
        $headers[] = 'Content-Type: application/json';
    }

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_HTTPHEADER     => $headers,
    ]);

    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        if (!empty($data)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
    }

    $response  = curl_exec($ch);
    $curlError = curl_error($ch);
    $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
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
