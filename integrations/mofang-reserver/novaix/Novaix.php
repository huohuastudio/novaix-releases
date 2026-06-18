<?php
namespace reserver\novaix;

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
     * 前台产品内页输出
     */
    public function clientArea()
    {
        $theme = $this->resolveTheme('clientarea', 'clientarea_theme', 'product_detail.html');
        return ['template' => $theme];
    }

    /**
     * 前台产品列表
     */
    public function hostList($param)
    {
        $theme = $this->resolveTheme('clientarea', 'clientarea_theme', 'product_list.html');
        return ['template' => $theme];
    }

    /**
     * 前台商品购买页面输出
     */
    public function clientProductConfigOption($param)
    {
        $theme = $this->resolveTheme('cart', 'cart_theme', 'goods.html');
        return ['template' => $theme];
    }

    private function resolveTheme($dir, $configKey, $file)
    {
        $isMobile = function_exists('use_mobile') && use_mobile();
        $type = $isMobile ? 'mobile' : 'pc';
        $suffix = $isMobile ? '_mobile' : '';
        $theme = function_exists('configuration') ? configuration($configKey . $suffix) : 'default';

        // 依次尝试：指定主题 → 默认主题 → pc 默认主题（移动端回退）
        $candidates = [
            [$type, $theme],
            [$type, 'default'],
            ['pc', 'default'],
        ];

        foreach ($candidates as [$t, $th]) {
            if (file_exists(__DIR__ . "/template/{$dir}/{$t}/{$th}/{$file}")) {
                return "template/{$dir}/{$t}/{$th}/{$file}";
            }
        }

        return "template/{$dir}/pc/default/{$file}";
    }
}
