<?php
namespace reserver\novaix\controller\home;

use app\common\model\HostModel;
use app\common\model\ProductModel;
use app\common\model\MenuModel;
use reserver\novaix\logic\RouteLogic;

class CloudController
{
    /**
     * 产品列表
     * @url /console/v1/renovaix/host
     * @method GET
     */
    public function hostList()
    {
        $param = request()->param();

        $result = [
            'status' => 200,
            'msg'    => lang_plugins('success_message'),
            'data'   => ['list' => [], 'count' => 0],
        ];

        $clientId = get_client_id();
        if (empty($clientId)) {
            return json($result);
        }

        $where = [];
        if (isset($param['m']) && !empty($param['m'])) {
            $menu = MenuModel::where('menu_type', 'res_module')
                ->where('module', 'novaix')
                ->where('id', $param['m'])
                ->find();
            if (!empty($menu) && !empty($menu['product_id'])) {
                $productIds = json_decode($menu['product_id'], true);
                if (!empty($productIds)) {
                    $where[] = ['h.product_id', 'IN', $productIds];
                }
            }
        }

        $param['page']    = max(1, (int)($param['page'] ?? 1));
        $param['limit']   = max(1, (int)($param['limit'] ?? config('idcsmart.limit')));
        $param['sort']    = $param['sort'] ?? config('idcsmart.sort');
        $param['orderby'] = isset($param['orderby']) && in_array($param['orderby'], ['id', 'due_time', 'status']) ? $param['orderby'] : 'id';
        $param['orderby'] = 'h.' . $param['orderby'];

        $where[] = ['h.client_id', '=', $clientId];
        $where[] = ['h.status', '<>', 'Cancelled'];

        if (function_exists('configuration') && configuration('home_show_deleted_host') != 1) {
            $where[] = ['h.status', '<>', 'Deleted'];
        }

        // 订单回收站：过滤已软删除的产品
        if (function_exists('configuration') && is_numeric(configuration('order_recycle_bin'))) {
            $where[] = ['h.is_delete', '=', 0];
        }

        // 子账户可见产品过滤：hook 返回 status=200 即表示启用了子账户限制
        $res = hook('get_client_host_id', ['client_id' => get_client_id(false)]);
        $res = array_values(array_filter($res ?? []));
        foreach ($res as $value) {
            if (isset($value['status']) && $value['status'] == 200) {
                $allowedHostIds = $value['data']['host'] ?? [];
                if (empty($allowedHostIds)) {
                    // 子账户没有任何可见产品，直接返回空
                    return json($result);
                }
                $where[] = ['h.id', 'IN', $allowedHostIds];
            }
        }

        if (isset($param['status']) && !empty($param['status'])) {
            if ($param['status'] == 'Pending') {
                $where[] = ['h.status', 'IN', ['Pending', 'Failed']];
            } elseif (in_array($param['status'], ['Unpaid', 'Active', 'Suspended', 'Deleted'])) {
                $where[] = ['h.status', '=', $param['status']];
            }
        }
        if (isset($param['keywords']) && $param['keywords'] !== '') {
            $where[] = ['h.name', 'LIKE', '%' . $param['keywords'] . '%'];
        }

        $count = HostModel::alias('h')
            ->leftJoin('product p', 'h.product_id=p.id')
            ->join('upstream_product up', 'p.id=up.product_id AND up.res_module="novaix"')
            ->where($where)
            ->count();

        $host = HostModel::alias('h')
            ->field('h.id,h.product_id,h.name,h.status,h.active_time,h.due_time,h.first_payment_amount,h.renew_amount,h.billing_cycle,h.billing_cycle_name,p.name product_name,h.client_notes')
            ->leftJoin('product p', 'h.product_id=p.id')
            ->join('upstream_product up', 'p.id=up.product_id AND up.res_module="novaix"')
            ->where($where)
            ->withAttr('status', function ($val) {
                return $val == 'Failed' ? 'Pending' : $val;
            })
            ->limit($param['limit'])
            ->page($param['page'])
            ->order($param['orderby'], $param['sort'])
            ->group('h.id')
            ->select()
            ->toArray();

        $result['data']['list']  = $host;
        $result['data']['count'] = $count;
        return json($result);
    }

    /**
     * 前台商品配置信息（购买页计价）
     * @url /console/v1/renovaix/product/:product_id/configoption
     * @method GET
     */
    public function cartConfigoption()
    {
        $param = request()->param();
        $productId = $param['product_id'];

        try {
            $RouteLogic = new RouteLogic();
            $RouteLogic->routeByProduct($productId);

            $result = $RouteLogic->curl(
                sprintf('console/v1/product/%d/config_option', $RouteLogic->upstream_product_id),
                $param,
                'POST'
            );
        } catch (\Exception $e) {
            $result = ['status' => 400, 'msg' => lang_plugins('res_novaix_act_exception')];
        }
        return json($result);
    }

    /**
     * 前台产品内页（主机详情）
     * @url /console/v1/renovaix/host/:host_id/configoption
     * @method GET
     */
    public function hostConfigoption()
    {
        $param = request()->param();
        $host = $this->assertHostAccessible($param['host_id']);
        if (empty($host)) {
            return json(['status' => 400, 'msg' => lang_plugins('res_novaix_host_not_found')]);
        }
        $product = ProductModel::find($host['product_id']);

        $result = $this->callUpstreamByHost($param['host_id'], function ($route) {
            return $route->curl(
                sprintf('console/v1/host/%d', $route->upstream_host_id),
                [],
                'GET'
            );
        });

        if ($result['status'] == 200 && isset($result['data']['host'])) {
            $upstream = $result['data']['host'];

            $result['data']['host'] = array_merge($upstream, [
                'id'                   => $host['id'],
                'order_id'             => $host['order_id'],
                'product_id'           => $host['product_id'],
                'create_time'          => $host['create_time'],
                'due_time'             => $host['due_time'],
                'billing_cycle'        => $host['billing_cycle'],
                'billing_cycle_name'   => $host['billing_cycle_name'],
                'renew_amount'         => $host['renew_amount'],
                'first_payment_amount' => $host['first_payment_amount'],
                'name'                 => $product['name'] ?? '',
                'host_name'            => $host['name'],
                'client_notes'         => $host['client_notes'],
            ]);

            $result['data']['client_button'] = $this->buildClientButtons();
        }

        return json($result);
    }

    /**
     * 执行实例操作（开机/关机/重启/重装/重置密码）
     * @url /console/v1/renovaix/host/:host_id/provision/:func
     * @method POST
     */
    public function provisionFunc()
    {
        $param = request()->param();
        $host = $this->assertHostAccessible($param['host_id']);
        if (empty($host)) {
            return json(['status' => 400, 'msg' => lang_plugins('res_novaix_host_not_found')]);
        }
        $func = $param['func'];

        // 映射魔方前台操作名到 Novaix 兼容 API 操作名
        $actionMap = [
            'on'         => 'on',
            'off'        => 'off',
            'reboot'     => 'reboot',
            'hard_off'   => 'off',
            'hard_reboot'=> 'reboot',
            'reinstall'  => 'reinstall',
            'crack_pass' => 'reset_password',
        ];

        if (!isset($actionMap[$func])) {
            return json(['status' => 400, 'msg' => lang_plugins('res_novaix_act_exception')]);
        }
        $action = $actionMap[$func];

        $data = $param;
        unset($data['host_id'], $data['func']);

        $result = $this->callUpstreamByHost($param['host_id'], function ($route) use ($action, $data) {
            return $route->curl(
                sprintf('console/v1/renovaix/%d/%s', $route->upstream_host_id, $action),
                $data,
                'POST'
            );
        });

        $logKey = $result['status'] == 200 ? 'success' : 'fail';
        $description = lang_plugins("res_novaix_log_{$func}_{$logKey}", [
            '{hostname}' => $host['name'],
        ]);
        active_log($description, 'host', $host['id']);

        return json($result);
    }

    /**
     * VNC 控制台
     * @url /console/v1/renovaix/host/:host_id/provision/vnc
     * @method POST
     */
    public function provisionVnc()
    {
        $param = request()->param();
        $host = $this->assertHostAccessible($param['host_id']);
        if (empty($host)) {
            return json(['status' => 400, 'msg' => lang_plugins('res_novaix_host_not_found')]);
        }

        $result = $this->callUpstreamByHost($param['host_id'], function ($route) {
            return $route->curl(
                sprintf('console/v1/renovaix/%d/vnc', $route->upstream_host_id),
                [],
                'POST'
            );
        });

        if ($result['status'] == 200 && isset($result['data']['url'])) {
            $this->logHostAction($host, 'vnc', true);
            return json([
                'status' => 200,
                'msg'    => lang_plugins('success_message'),
                'data'   => [
                    'url'         => $result['data']['url'],
                    'console_url' => $result['data']['console_url'] ?? '',
                    'token'       => $result['data']['token'] ?? '',
                ],
            ]);
        }

        $this->logHostAction($host, 'vnc', false);
        return json($result);
    }

    /**
     * 查询实例运行状态
     * @url /console/v1/renovaix/host/:host_id/provision/status
     * @method POST
     */
    public function provisionFuncStatus()
    {
        $param = request()->param();
        $host = $this->assertHostAccessible($param['host_id']);
        if (empty($host)) {
            return json(['status' => 400, 'msg' => lang_plugins('res_novaix_host_not_found')]);
        }

        $result = $this->callUpstreamByHost($param['host_id'], function ($route) {
            return $route->curl(
                sprintf('console/v1/host/%d', $route->upstream_host_id),
                [],
                'GET'
            );
        });

        if ($result['status'] == 200 && isset($result['data']['host'])) {
            $result['data']['status'] = $result['data']['host']['raw_status'] ?? 'Active';
        }

        return json($result);
    }

    /**
     * 校验主机归属和子账户可见性
     */
    private function assertHostAccessible($hostId)
    {
        $host = HostModel::find($hostId);
        if (empty($host) || $host['client_id'] != get_client_id() || $host['is_delete']) {
            return null;
        }

        // 子账户可见性校验：hook 返回 status=200 即表示启用了子账户限制
        $res = hook('get_client_host_id', ['client_id' => get_client_id(false)]);
        $res = array_values(array_filter($res ?? []));
        foreach ($res as $value) {
            if (isset($value['status']) && $value['status'] == 200) {
                $allowedIds = $value['data']['host'] ?? [];
                if (!in_array($host['id'], $allowedIds)) {
                    return null;
                }
            }
        }

        return $host;
    }

    /**
     * 初始化 RouteLogic 并调用上游 API，统一异常处理
     * @param int $hostId 本地主机 ID
     * @param callable $callback 接收 RouteLogic 实例，返回上游响应
     */
    private function callUpstreamByHost($hostId, callable $callback)
    {
        try {
            $route = new RouteLogic();
            $route->routeByHost($hostId);
            return $callback($route);
        } catch (\Exception $e) {
            return ['status' => 400, 'msg' => lang_plugins('res_novaix_act_exception')];
        }
    }

    /**
     * 记录主机操作日志
     */
    private function logHostAction($host, $action, $success)
    {
        $logKey = $success ? 'success' : 'fail';
        $description = lang_plugins("res_novaix_log_{$action}_{$logKey}", [
            '{hostname}' => $host['name'],
        ]);
        active_log($description, 'host', $host['id']);
    }

    /**
     * 构建前台操作按钮列表
     */
    private function buildClientButtons()
    {
        return [
            'console' => [
                ['func' => 'on',          'name' => lang_plugins('res_novaix_btn_on'),          'type' => 'default'],
                ['func' => 'off',         'name' => lang_plugins('res_novaix_btn_off'),         'type' => 'default'],
                ['func' => 'reboot',      'name' => lang_plugins('res_novaix_btn_reboot'),      'type' => 'default'],
                ['func' => 'vnc',         'name' => lang_plugins('res_novaix_btn_vnc'),         'type' => 'vnc'],
            ],
            'control' => [
                ['func' => 'reinstall',   'name' => lang_plugins('res_novaix_btn_reinstall'),   'type' => 'default'],
                ['func' => 'crack_pass',  'name' => lang_plugins('res_novaix_btn_crack_pass'),  'type' => 'password'],
            ],
        ];
    }
}
