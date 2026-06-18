<?php
namespace reserver\novaix\logic;

use app\common\model\UpstreamProductModel;
use app\common\model\UpstreamHostModel;
use app\common\model\HostModel;

class RouteLogic
{
    protected $timeout = 60;

    public $isUpstream = true;
    public $supplier_id;
    public $upstream_product_id;
    public $upstream_host_id;
    public $profit_type;
    public $profit_percent;
    public $renew_profit_type;
    public $renew_profit_percent;
    public $price_multiple;

    /**
     * 通过商品 ID 获取上游代理信息
     */
    public function routeByProduct($id)
    {
        $upstreamProduct = $this->findUpstreamProduct($id);

        $this->supplier_id         = $upstreamProduct['supplier_id'];
        $this->upstream_product_id = $upstreamProduct['upstream_product_id'];
        $this->applyProfitConfig($upstreamProduct);
    }

    /**
     * 通过主机 ID 获取上游代理信息
     */
    public function routeByHost($id)
    {
        $upstreamHost = UpstreamHostModel::where('host_id', $id)->find();
        $this->ensureUpstream($upstreamHost);

        $this->supplier_id      = $upstreamHost['supplier_id'];
        $this->upstream_host_id = $upstreamHost['upstream_host_id'];

        $host = HostModel::find($id);
        $upstreamProduct = $this->findUpstreamProduct($host['product_id'] ?? 0);
        $this->applyProfitConfig($upstreamProduct, true);
    }

    /**
     * 查找上游商品配置，不存在则抛异常
     */
    private function findUpstreamProduct($productId)
    {
        $upstreamProduct = UpstreamProductModel::where('product_id', $productId)->where('res_module', 'novaix')->find();
        $this->ensureUpstream($upstreamProduct);
        return $upstreamProduct;
    }

    /**
     * 校验上游记录是否存在
     */
    private function ensureUpstream($record)
    {
        if (empty($record)) {
            $this->isUpstream = false;
            throw new \Exception('not upstream');
        }
        bcscale(2);
    }

    /**
     * 应用利润配置
     * @param array $upstreamProduct 上游商品记录
     * @param bool $withDefaults 是否为字段提供默认值（routeByHost 场景）
     */
    private function applyProfitConfig($upstreamProduct, $withDefaults = false)
    {
        $default = $withDefaults ? 0 : null;
        $this->profit_type          = $upstreamProduct['profit_type'] ?? $default;
        $this->profit_percent       = $upstreamProduct['profit_percent'] ?? $default;
        $this->renew_profit_type    = $upstreamProduct['renew_profit_type'] ?? $default;
        $this->renew_profit_percent = $upstreamProduct['renew_profit_percent'] ?? $default;
        $this->price_multiple       = bcdiv(bcadd(100, $this->profit_percent), 100);
    }

    public function setTimeout($timeout)
    {
        $this->timeout = $timeout;
    }

    /**
     * 请求上游 Novaix 兼容 API
     */
    public function curl($path, $data = [], $request = 'POST')
    {
        $clientId = $this->getDownstreamClientId();
        $data['downstream_client_id'] = $clientId;
        return idcsmart_api_curl($this->supplier_id, $path, $data, $this->timeout, $request);
    }

    protected function getDownstreamClientId()
    {
        $clientId = function_exists('get_client_id') ? get_client_id() : 0;
        if (request()->is_api) {
            $param = request()->param();
            if (isset($param['downstream_client_id']) && $param['downstream_client_id'] > 0) {
                return (int)$param['downstream_client_id'];
            }
            return -1;
        }
        return (int)$clientId;
    }
}
