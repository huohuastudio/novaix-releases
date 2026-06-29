# Novaix

> **⚠️ 当前为预览版本，功能尚未完善，可能存在不稳定的情况。如果你在体验过程中遇到任何问题或有功能建议，欢迎提交 [Issue](https://github.com/huohuastudio/novaix-releases/issues)。**

Novaix 是一套商业化 IDC 管理系统，面向中小型 VPS 服务商，提供节点管理、实例生命周期管理、计费订单、工单系统等一站式解决方案。

系统采用 Go + React 单体架构，编译为单个二进制文件，开箱即用。

## 功能特性

- **节点管理** — 多节点接入与监控，支持资源统计与告警，一键测试 SSH/服务端连通性
- **节点组与集群** — 节点分组管理，同组节点组成集群，支持实例在线热迁移
- **HA 高可用** — 节点故障时自动疏散实例到同组健康节点，支持维护模式手动触发疏散
- **实例管理** — 容器/虚拟机全生命周期管理，支持创建、启停、重装、快照等操作
- **VPC 私有网络** — 基于 OVN 的 L2 隔离网络，支持子网划分、实例挂载与安全组规则
- **IP 池管理** — 灵活的 IP 分配与回收机制
- **镜像与 ISO** — 镜像分组管理，支持开机脚本（cloud-init）、运行模式（容器/虚拟机）、多节点分发、客户端隐藏，以及自定义 ISO 挂载
- **套餐管理** — 自定义计费套餐，支持按月/季/年等多种周期及按小时计费，可配置 CPU 限制，支持试建验证创建链路
- **流量包** — 支持叠加和重置两种模式的流量包，管理端配置、用户端购买，可按套餐限制可购范围
- **计费与订单** — 完整的订单流程与支付记录，支持按小时自动扣费与余额不足暂停，下单/支付时自动资源预检
- **库存管理** — 实时展示节点资源可用性和套餐库存状态，资源不足或售罄时自动拦截下单
- **支付集成** — 支持易支付（兼容彩虹易支付/ZPAY 等通用规范），可扩展更多支付渠道
- **优惠券** — 灵活的促销优惠体系
- **用户系统** — 支持邮箱和手机号注册登录，多邮箱管理、手机号绑定，敏感操作支持 TOTP 两步验证
- **实名认证（KYC）** — 支持二要素（姓名+身份证号）和人脸识别（H5 活体检测）两种认证模式，内置阿里云、腾讯云渠道，插件化可扩展
- **代理系统** — 代理商分组管理，支持首单/后续差异化返佣比例与分销折扣矩阵
- **短信服务** — 支持阿里云、腾讯云、通用 HTTP 三种短信渠道，用于验证码发送等场景
- **邮件服务** — 支持 SMTP、Mailgun、Resend 三种邮件发送渠道，插件化配置
- **多渠道通知** — 告警与事件通知支持 Telegram、钉钉、企业微信、Webhook，多渠道并发推送
- **对象存储** — S3 兼容存储（AWS S3、阿里云 OSS、腾讯云 COS、MinIO 等），用于镜像/ISO 远程归档与掉盘恢复
- **任务管理** — 任务大屏展示统计卡片、多状态筛选、WebSocket 实时日志流与自动刷新
- **工单系统** — 用户与管理员沟通渠道
- **CMS 内容管理** — 公告、文章、单页面、帮助中心、FAQ、导航菜单、轮播图、合作伙伴、客户评价、数据中心、友情链接、更新日志、团队成员、品牌素材等 14 个模块，全部提供公开 API 供第三方主题动态渲染；默认主题内置完整的门户端展示页面（文章、帮助中心、FAQ、更新日志、数据中心等）
- **CLI 管理工具** — 内置终端管理命令，支持密码重置、插件启停、主题切换与重置、设置管理、系统信息查看等，适用于忘记密码、插件异常或主题白屏等紧急运维场景
- **系统在线更新** — 一键在线升级，支持数据库迁移失败自动回滚（SQLite）与 crash recovery（MySQL / PostgreSQL）
- **后台路径自定义** — 管理后台路径可配置（如 `/manage`、`/control`），增强安全性
- **系统设置** — 站点信息、维护模式等，各服务渠道采用统一的插件化 Provider 动态配置

## 下载安装

前往 [Releases](https://github.com/huohuastudio/novaix-releases/releases) 页面，根据你的服务器架构下载对应的安装包：

| 文件名 | 架构 | 说明 |
|--------|------|------|
| `novaix_linux_amd64.tar.gz` | x86_64 | 大多数云服务器 |
| `novaix_linux_arm64.tar.gz` | ARM64 | ARM 架构服务器 |

### 快速开始

```bash
# 1. 下载并解压（以 amd64 为例）
wget https://github.com/huohuastudio/novaix-releases/releases/latest/download/novaix_linux_amd64.tar.gz
tar -xzf novaix_linux_amd64.tar.gz

# 2. 运行
./novaix_linux_amd64/novaix
```

首次启动时，程序会在当前目录自动生成默认配置文件，使用 SQLite 数据库，无需额外依赖。也可配置为 MySQL 或 PostgreSQL。

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

## 主题市场

Novaix 支持通过主题系统自定义前端界面。前端源码开源在 [novaix-ui](https://github.com/huohuastudio/novaix-ui) 仓库，你可以基于它开发自己的主题。

### 提交主题到市场

如果你开发了一个主题并希望上架到 Novaix 主题市场，请通过 PR 提交：

1. 将你的主题 zip 包放到 `themes/` 目录下（如 `themes/my-theme.zip`）
2. 在 `themes/index.json` 的 `themes` 数组中添加一条记录
3. 提交 PR

**主题 zip 包要求：**

```
my-theme.zip
├── theme.json           # 必需
├── screenshot.png       # 建议提供（1280×800，<500KB）
└── ui/                  # 必需，pnpm build 的完整产物
    ├── index.html
    └── assets/
```

**index.json 条目格式：**

```json
{
  "id": "my-theme",
  "name": "主题名称",
  "version": "1.0.0",
  "description": "简短描述",
  "author": {"name": "作者名", "url": "https://github.com/your-name"},
  "requires": "~0.2.5",
  "download_url": "https://raw.githubusercontent.com/huohuastudio/novaix-releases/main/themes/my-theme.zip"
}
```

> `download_url` 中的文件名必须与你放到 `themes/` 目录下的 zip 文件名一致。`requires` 字段建议使用 `~x.y.z` 约束，表示兼容该 patch 版本范围。

**审核标准：**

- 主题能正常安装和使用
- `theme.json` 字段完整且格式正确
- 不包含恶意代码或外部跟踪脚本
- zip 大小不超过 50MB

## 第三方集成

> **推荐直接使用 Novaix** — Novaix 自身已提供完整的用户前台、套餐管理、订单计费、支付集成、工单系统等功能，大多数场景下无需额外对接第三方财务系统。仅当你已有现成的财务系统且不希望迁移时，才建议使用下方的集成模块。

[`integrations/`](./integrations/) 目录提供了与第三方财务/分销系统对接所需的文档和模块：

| 内容 | 路径 | 说明 |
|------|------|------|
| Provisioning API 参考 | [`integrations/provisioning-api.md`](./integrations/provisioning-api.md) | 完整接口说明、认证方式、幂等性、错误码 |
| Webhook 回调与验签 | [`integrations/webhook.md`](./integrations/webhook.md) | 异步通知格式与 HMAC-SHA256 验签 |
| Webhook 接收端示例 | [`integrations/webhook-examples/`](./integrations/webhook-examples/) | PHP / Python / Node 三种语言的参考实现 |
| 魔方 V10 上游供应商模块 | [`integrations/mofang-reserver/`](./integrations/mofang-reserver/) | reserver 模式，自动同步商品、加价转售（实验性） |
| 魔方 V10 服务器模块 | [`integrations/mofang/`](./integrations/mofang/) | server 模式，手动配置的传统对接方式 |
| 魔方 2.x 模块 | [`integrations/mofang-legacy/`](./integrations/mofang-legacy/) | 适用于魔方财务 2.x 版本 |
| WHMCS 模块 | [`integrations/whmcs/`](./integrations/whmcs/) | WHMCS Provisioning 模块 |

如需对接其他系统，请参阅 [Provisioning API 参考](./integrations/provisioning-api.md) 和已有模块的实现。

## 联系我们

- 官网：[novaix.cc](https://novaix.cc)
- 文档：[docs.huohuastudio.com/novaix](https://docs.huohuastudio.com/novaix)
- 邮箱：[support@huohuastudio.com](mailto:support@huohuastudio.com)

## License

Copyright &copy; [Spark Studio](https://novaix.cc). All rights reserved.
