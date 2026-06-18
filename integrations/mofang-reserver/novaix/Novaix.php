<?php
namespace reserver\novaix;

use reserver\novaix\logic\RouteLogic;

class Novaix
{
    public function metaData()
    {
        return [
            'display_name' => 'NovaIx VPS',
            'version'      => '1.0.0',
        ];
    }

    /**
     * 前台产品内页输出 — 服务端渲染
     */
    public function clientArea($param = [])
    {
        $host = $param['host'] ?? null;
        if (empty($host)) {
            return $this->errorHtml('产品不存在');
        }

        $hostId = (int)$host['id'];

        try {
            $route = new RouteLogic();
            $route->routeByHost($hostId);

            $detail = $route->curl(
                sprintf('console/v1/host/%d', $route->upstream_host_id),
                [],
                'GET'
            );
        } catch (\Exception $e) {
            return $this->errorHtml('产品信息加载失败: ' . htmlspecialchars($e->getMessage()));
        }

        if (empty($detail['data']['host'])) {
            return $this->errorHtml('产品信息加载失败');
        }

        $h = $detail['data']['host'];
        $prefix = $this->currencyPrefix();
        $rawStatus = $h['raw_status'] ?? 'running';
        $st = $this->statusInfo($rawStatus);

        $ip = htmlspecialchars($h['dedicate_ip'] ?? '', ENT_QUOTES);
        $ipv6 = htmlspecialchars($h['assign_ip'] ?? '', ENT_QUOTES);
        $hostName = htmlspecialchars($host['name'] ?? '', ENT_QUOTES);
        $productName = htmlspecialchars($param['product']['name'] ?? '', ENT_QUOTES);

        $dueTime = ($host['due_time'] ?? 0) ? date('Y-m-d H:i', $host['due_time']) : '--';
        $activeTime = ($host['active_time'] ?? 0) ? date('Y-m-d H:i', $host['active_time']) : '--';
        $renewAmount = $host['renew_amount'] ?? '0.00';
        $billingCycleName = htmlspecialchars($host['billing_cycle_name'] ?? '', ENT_QUOTES);

        $html = $this->cssLinks();
        $html .= '<div class="template common_product_detail"><div class="main-card">';

        // 标题栏
        $html .= '<div class="main-card-title"><div class="left">';
        $html .= '<span style="cursor:pointer;font-size:18px;margin-right:8px" onclick="history.back()">&#8592;</span>';
        $html .= '<span class="title">' . $productName . '</span> ' . $this->badge($st);
        $html .= '</div></div>';

        // 财务信息
        $html .= '<div class="finance-info"><div class="box">';
        $html .= $this->infoRow('开通时间', $activeTime);
        $html .= $this->infoRow('到期时间', $dueTime);
        $html .= $this->infoRow('产品标识', $hostName);
        $html .= $this->infoRow('续费价格', htmlspecialchars($prefix, ENT_QUOTES) . $renewAmount . '/' . $billingCycleName);
        $html .= '</div></div>';

        // 基础信息
        $specs = [
            ['IP 地址', $ip],
        ];
        if ($ipv6) $specs[] = ['IPv6 地址', $ipv6];
        $specs[] = ['CPU', ($h['cpu'] ?? '-') . ' Core'];
        $specs[] = ['内存', $this->formatMemory($h['memory'] ?? 0)];
        $specs[] = ['硬盘', ($h['disk'] ?? '-') . ' GB'];
        $specs[] = ['带宽', ($h['bandwidth'] ?? '-') . ' Mbps'];
        if (!empty($h['os_type'])) $specs[] = ['系统', htmlspecialchars($h['os_type'], ENT_QUOTES)];
        $specs[] = ['运行状态', $st['text']];

        $html .= $this->sectionTitle('基础信息');
        $html .= '<div class="box" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:14px">';
        foreach ($specs as [$label, $value]) {
            $html .= $this->infoCell($label, $value);
        }
        $html .= '</div></div>';

        // 管理按钮
        $isDisabled = in_array($rawStatus, ['creating', 'error']);
        $disabledAttr = $isDisabled ? ' disabled tabindex="-1" style="opacity:0.5;pointer-events:none"' : '';
        $html .= '<div style="padding:16px 0;border-top:1px solid #f0f0f0">';
        $html .= '<h3 style="font-size:16px;font-weight:600;margin:0 0 16px;color:#333">管理</h3>';
        $html .= '<div style="display:flex;flex-wrap:wrap;gap:10px">';

        $onclickMap = [
            'vnc'        => "novaix_vnc({$hostId})",
            'crack_pass' => "novaix_resetpw({$hostId})",
        ];
        $btns = [
            ['on', '开机', 'primary'], ['off', '关机', 'warning'], ['reboot', '重启', 'info'],
            ['vnc', '控制台', 'success'], ['reinstall', '重装系统', 'danger'], ['crack_pass', '重置密码', 'default'],
        ];
        foreach ($btns as [$action, $label, $type]) {
            $onclick = $onclickMap[$action] ?? "novaix_action('{$action}',{$hostId})";
            $html .= '<button class="el-button el-button--' . $type . ' el-button--small"' . $disabledAttr . ' onclick="' . $onclick . '"><span>' . $label . '</span></button>';
        }
        $html .= '</div>';
        $html .= '<p style="font-size:12px;color:#999;margin-top:10px">操作提交后请稍等片刻，刷新页面查看最新状态。</p>';
        $html .= '</div></div></div>';

        $html .= '<script>
function novaix_action(func, hostId) {
  if (!confirm("确认执行 " + func + " 操作？")) return;
  Axios.post("/console/v1/renovaix/host/" + hostId + "/provision/" + func).then(function(r) {
    if (r.data.status === 200) { alert("操作已提交"); location.reload(); }
    else { alert(r.data.msg || "操作失败"); }
  }).catch(function() { alert("请求失败"); });
}
function novaix_vnc(hostId) {
  Axios.post("/console/v1/renovaix/host/" + hostId + "/provision/vnc").then(function(r) {
    if (r.data.status === 200 && r.data.data.console_url) { window.open(r.data.data.console_url, "_blank"); }
    else { alert(r.data.msg || "VNC 打开失败"); }
  }).catch(function() { alert("请求失败"); });
}
function novaix_resetpw(hostId) {
  var pw = prompt("请输入新密码（留空则自动生成）：");
  if (pw === null) return;
  Axios.post("/console/v1/renovaix/host/" + hostId + "/provision/crack_pass", {password: pw || undefined}).then(function(r) {
    if (r.data.status === 200) { alert("密码重置已提交"); }
    else { alert(r.data.msg || "操作失败"); }
  }).catch(function() { alert("请求失败"); });
}
</script>';

        return $html;
    }

    /**
     * 前台产品列表 — 由魔方框架渲染
     */
    public function hostList($param)
    {
        return '';
    }

    /**
     * 前台商品购买页面输出 — 服务端渲染
     */
    public function clientProductConfigOption($param)
    {
        $product = $param['product'] ?? null;
        if (empty($product)) {
            return '';
        }

        $productId = (int)$product['id'];

        try {
            $route = new RouteLogic();
            $route->routeByProduct($productId);

            $detail = $route->curl(
                sprintf('api/v1/product/%d', $route->upstream_product_id),
                [],
                'GET'
            );
        } catch (\Exception $e) {
            return $this->errorHtml('商品配置加载失败');
        }

        if (empty($detail['data']['product'])) {
            return $this->errorHtml('商品配置加载失败');
        }

        $p = $detail['data']['product'];
        $prefix = $this->currencyPrefix();

        $name = htmlspecialchars($p['name'] ?? '', ENT_QUOTES);
        $desc = htmlspecialchars($p['description'] ?? '', ENT_QUOTES);
        $prices = $p['prices'] ?? [];

        $cycleMap = ['monthly' => '月付', 'quarterly' => '季付', 'yearly' => '年付'];
        $cycleItems = [];
        $idx = 0;
        foreach (['monthly', 'quarterly', 'yearly'] as $k) {
            if (!empty($prices[$k]) && floatval($prices[$k]) > 0) {
                $active = $idx === 0 ? ' com-active' : '';
                $cycleItems[] = '<div class="item' . $active . '" data-i="' . $idx . '" data-cycle="' . $k . '" data-price="' . htmlspecialchars($prices[$k], ENT_QUOTES) . '">'
                    . '<p class="name">' . $cycleMap[$k] . '</p>'
                    . '<p class="price">' . htmlspecialchars($prefix, ENT_QUOTES) . number_format(floatval($prices[$k]), 2) . '</p>'
                    . '<i class="el-icon-check"></i></div>';
                $idx++;
            }
        }
        if (empty($cycleItems)) {
            $price = $p['price'] ?? '0.00';
            $cycleItems[] = '<div class="item com-active" data-i="0" data-cycle="monthly" data-price="' . htmlspecialchars($price, ENT_QUOTES) . '">'
                . '<p class="name">月付</p>'
                . '<p class="price">' . htmlspecialchars($prefix, ENT_QUOTES) . number_format(floatval($price), 2) . '</p>'
                . '<i class="el-icon-check"></i></div>';
        }
        $cyclesHtml = implode('', $cycleItems);

        $previewLines = '<p class="des"><span>' . $name . '</span></p>';
        $specItems = [
            ['cpu',       'CPU',  ' Core'],
            ['memory',    '内存', ''],
            ['disk',      '硬盘', ' GB'],
            ['bandwidth', '带宽', ' Mbps'],
        ];
        foreach ($specItems as [$key, $label, $unit]) {
            if (empty($p[$key])) continue;
            $value = $key === 'memory' ? $this->formatMemory($p[$key]) : (int)$p[$key] . $unit;
            $previewLines .= '<p class="des"><span class="name">' . $label . '</span><span class="value">' . $value . '</span></p>';
        }

        $firstPrice = $prices['monthly'] ?? ($p['price'] ?? '0.00');
        $totalDisplay = htmlspecialchars($prefix, ENT_QUOTES) . number_format(floatval($firstPrice), 2);

        // JS 变量安全输出
        $jsPid = json_encode((string)$productId);
        $jsPrefix = json_encode($prefix);

        $html = $this->cssLinks();
        $html .= <<<HTML
<div class="template common-config" id="novaix-goods">
  <div class="main-card">
    <div class="pro-tit">{$name}</div>
    <div class="common-box">
      <div class="l-config">
        <div class="description">{$desc}</div>
        <div class="config-item">
          <p class="config-tit">周期</p>
          <div class="cycle" id="ng-cycles">{$cyclesHtml}</div>
        </div>
      </div>
      <div class="order-right">
        <div class="right-main">
          <div class="right-title">配置预览</div>
          <div class="info">{$previewLines}</div>
          <div class="subtotal">
            <span class="name">合计：</span>
            <span id="ng-total">{$totalDisplay}</span>
          </div>
        </div>
        <div class="f-box">
          <div class="f-btn ifram-hiden">
            <button class="el-button el-button--primary buy-btn" style="width:100%" id="ng-buy"><span>立即购买</span></button>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
<script>
(function(){
  var pid = {$jsPid};
  var prefix = {$jsPrefix};
  var cyclesEl = document.getElementById('ng-cycles');
  if(cyclesEl){
    cyclesEl.onclick = function(e){
      var t = e.target.closest('[data-i]');
      if(!t) return;
      Array.from(cyclesEl.children).forEach(function(d){ d.className = 'item'; });
      t.className = 'item com-active';
      document.getElementById('ng-total').textContent = prefix + parseFloat(t.dataset.price).toFixed(2);
    };
  }
  var btn = document.getElementById('ng-buy');
  if(btn){
    btn.onclick = function(){
      var sel = cyclesEl.querySelector('.com-active');
      var params = {
        product_id: pid,
        config_options: { configoption: {}, cycle: sel ? sel.dataset.cycle : 'monthly' },
        qty: 1, customfield: {}, self_defined_field: {},
      };
      sessionStorage.setItem('product_information', JSON.stringify(params));
      location.href = '/cart/settlement.htm?id=' + pid;
    };
  }
})();
</script>
HTML;
        return $html;
    }

    private function errorHtml($message)
    {
        return '<div style="padding:20px;color:#999;">' . $message . '</div>';
    }

    private function badge($statusInfo)
    {
        return '<span style="display:inline-block;padding:2px 10px;border-radius:4px;font-size:12px;color:'
            . $statusInfo['color'] . ';background:' . $statusInfo['bg'] . '">'
            . $statusInfo['text'] . '</span>';
    }

    private function sectionTitle($title)
    {
        return '<div style="padding:16px 0"><h3 style="font-size:16px;font-weight:600;margin:0 0 16px;color:#333">' . $title . '</h3>';
    }

    private function currencyPrefix()
    {
        return function_exists('configuration') ? (configuration('currency_prefix') ?: '¥') : '¥';
    }

    private function formatMemory($mb)
    {
        $mb = (int)$mb;
        return $mb >= 1024 ? number_format($mb / 1024, 1) . ' GB' : $mb . ' MB';
    }

    private function statusInfo($rawStatus)
    {
        $map = [
            'running' => ['text' => '运行中', 'color' => '#67C23A', 'bg' => '#f0f9eb'],
            'stopped' => ['text' => '已关机', 'color' => '#909399', 'bg' => '#f4f4f5'],
            'frozen'  => ['text' => '已暂停', 'color' => '#E6A23C', 'bg' => '#fdf6ec'],
            'creating'=> ['text' => '创建中', 'color' => '#409EFF', 'bg' => '#ecf5ff'],
            'error'   => ['text' => '异常',   'color' => '#F56C6C', 'bg' => '#fef0f0'],
        ];
        return $map[$rawStatus] ?? $map['running'];
    }

    private function infoRow($label, $value)
    {
        return '<div class="item"><span class="label">' . $label . '：</span><span>' . $value . '</span></div>';
    }

    private function infoCell($label, $value)
    {
        return '<div style="display:flex;flex-direction:column;gap:4px"><span style="font-size:12px;color:#999">' . $label . '</span><span style="font-size:14px;color:#333">' . $value . '</span></div>';
    }

    private function cssLinks()
    {
        return '<link rel="stylesheet" href="/plugins/reserver/idcsmart_common/template/clientarea/pc/default/css/common_product_detail.css">'
             . '<link rel="stylesheet" href="/plugins/reserver/idcsmart_common/template/clientarea/pc/default/css/cloudDetail.css">'
             . '<link rel="stylesheet" href="/plugins/reserver/idcsmart_common/template/clientarea/pc/default/css/common_config.css">';
    }
}
