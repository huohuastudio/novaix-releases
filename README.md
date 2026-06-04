# Novaix

> **⚠️ 当前为预览版本，功能尚未完善，可能存在不稳定的情况。如果你在体验过程中遇到任何问题或有功能建议，欢迎提交 [Issue](https://github.com/huohuastudio/novaix-releases/issues)。**

Novaix 是一套商业化 IDC 管理系统，面向中小型 VPS 服务商，提供节点管理、实例生命周期管理、计费订单、工单系统等一站式解决方案。

系统采用 Go + React 单体架构，编译为单个二进制文件，开箱即用。

## 功能特性

- **节点管理** — 多节点接入与监控，支持资源统计与告警
- **实例管理** — 容器/虚拟机全生命周期管理，支持创建、启停、重装、快照等操作
- **IP 池管理** — 灵活的 IP 分配与回收机制
- **镜像与 ISO** — 镜像分组管理，支持开机脚本（cloud-init）、运行模式（容器/虚拟机）、多节点分发、客户端隐藏，以及自定义 ISO 挂载
- **套餐管理** — 自定义计费套餐，支持按月/季/年等多种周期，可配置 CPU 限制
- **流量包** — 支持叠加和重置两种模式的流量包，管理端配置、用户端购买，可按套餐限制可购范围
- **计费与订单** — 完整的订单流程与支付记录
- **支付集成** — 支持易支付（兼容彩虹易支付/ZPAY 等通用规范），可扩展更多支付渠道
- **优惠券** — 灵活的促销优惠体系
- **用户系统** — 支持邮箱和手机号注册登录，手机号找回密码
- **实名认证（KYC）** — 支持阿里云、腾讯云等身份核验渠道，插件化可扩展
- **代理系统** — 代理商分组管理，支持首单/后续差异化返佣比例与分销折扣矩阵
- **短信服务** — 支持阿里云、腾讯云、通用 HTTP 三种短信渠道，用于验证码发送等场景
- **邮件服务** — 支持 SMTP、Mailgun、Resend 三种邮件发送渠道，插件化配置
- **多渠道通知** — 告警与事件通知支持 Telegram、钉钉、企业微信、Webhook，多渠道并发推送
- **对象存储** — S3 兼容存储（AWS S3、阿里云 OSS、腾讯云 COS、MinIO 等），用于镜像/ISO 远程归档与掉盘恢复
- **任务管理** — 任务大屏展示统计卡片、多状态筛选、WebSocket 实时日志流与自动刷新
- **工单系统** — 用户与管理员沟通渠道
- **公告管理** — 面向用户的通知与公告发布
- **系统在线更新** — 一键在线升级，支持数据库迁移失败自动回滚（SQLite）与 crash recovery
- **系统设置** — 站点信息、维护模式等，各服务渠道采用统一的插件化 Provider 动态配置

## 下载安装

前往 [Releases](https://github.com/huohuastudio/novaix-releases/releases) 页面，根据你的服务器架构下载对应的安装包：

| 文件名 | 架构 | 说明 |
|--------|------|------|
| `novaix-linux-amd64` | x86_64 | 大多数云服务器 |
| `novaix-linux-arm64` | ARM64 | ARM 架构服务器 |

### 快速开始

```bash
# 1. 下载（以 amd64 为例）
wget https://github.com/huohuastudio/novaix-releases/releases/latest/download/novaix-linux-amd64
chmod +x novaix-linux-amd64

# 2. 运行
./novaix-linux-amd64
```

首次启动时，程序会在当前目录自动生成默认配置文件，使用 SQLite 数据库，无需额外依赖。

### 反向代理（Nginx）

```nginx
server {
    listen 80;
    server_name your-domain.com;

    location / {
        proxy_pass http://127.0.0.1:8080;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;

        # WebSocket 支持（终端、VNC 等）
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "upgrade";
    }
}
```

## 第三方集成

[`integrations/`](./integrations/) 目录提供了与第三方财务/分销系统对接所需的文档和模块：

| 内容 | 路径 | 说明 |
|------|------|------|
| Provisioning API 参考 | [`integrations/provisioning-api.md`](./integrations/provisioning-api.md) | 完整接口说明、认证方式、幂等性、错误码 |
| Webhook 回调与验签 | [`integrations/webhook.md`](./integrations/webhook.md) | 异步通知格式与 HMAC-SHA256 验签 |
| Webhook 接收端示例 | [`integrations/webhook-examples/`](./integrations/webhook-examples/) | PHP / Python / Node 三种语言的参考实现 |
| 智简魔方模块 | [`integrations/mofang/`](./integrations/mofang/) | IDCsmart 魔方对接模块 |
| WHMCS 模块 | [`integrations/whmcs/`](./integrations/whmcs/) | WHMCS Provisioning 模块 |

如需对接其他系统，请参阅 [Provisioning API 参考](./integrations/provisioning-api.md) 和已有模块的实现。

## 联系我们

- 官网：[huohuastudio.com](https://huohuastudio.com)
- 邮箱：[support@huohuastudio.com](support@huohuastudio.com)

## License

Copyright &copy; [Spark Studio](https://huohuastudio.com). All rights reserved.
