<div align="center">
  <a href="https://novaix.cc">
    <img src="https://raw.githubusercontent.com/huohuastudio/novaix-releases/main/logo.png" width="80" height="80" alt="Novaix Logo" />
  </a>
  <h1>Novaix</h1>
  <p>面向中小型 VPS 服务商的一站式 IDC 管理系统</p>

  <p>
    <a href="https://github.com/huohuastudio/novaix-releases/releases/latest"><img src="https://img.shields.io/github/v/release/huohuastudio/novaix-releases?style=for-the-badge&color=2563EB&label=%E6%9C%80%E6%96%B0%E7%89%88%E6%9C%AC" alt="Latest Release" /></a>
    &nbsp;
    <a href="https://github.com/huohuastudio/novaix-releases/releases"><img src="https://img.shields.io/github/downloads/huohuastudio/novaix-releases/total?style=for-the-badge&color=60A5FA&label=%E4%B8%8B%E8%BD%BD%E9%87%8F" alt="Downloads" /></a>
    &nbsp;
    <a href="https://github.com/huohuastudio/novaix-releases/issues"><img src="https://img.shields.io/github/issues/huohuastudio/novaix-releases?style=for-the-badge&color=1E3A8A&label=Issues" alt="Issues" /></a>
  </p>

  <p>
    <a href="https://novaix.cc">官网</a> · <a href="https://novaix.huohuastudio.com">演示</a> · <a href="https://docs.huohuastudio.com/novaix">文档</a> · <a href="https://github.com/huohuastudio/novaix-releases/releases">下载</a> · <a href="https://github.com/huohuastudio/novaix-releases/issues">反馈</a> · <a href="https://qm.qq.com/q/twNnYXDBmw">QQ 群</a>
  </p>
</div>

<br />

<p align="center">
  <img src="https://raw.githubusercontent.com/huohuastudio/novaix-releases/main/screenshots/dashboard.webp" alt="Novaix Dashboard" width="800" />
</p>

<br />

> **⚠️ 当前为预览版本，功能尚未完善，可能存在不稳定的情况。如果你在体验过程中遇到任何问题或有功能建议，欢迎提交 [Issue](https://github.com/huohuastudio/novaix-releases/issues)。**

## 亮点特性

- **单文件部署** — Go + React 编译为单个二进制文件，下载即用，无需安装运行时或外部依赖
- **零配置启动** — 内置 SQLite，首次运行自动生成配置，30 秒完成部署
- **容器与虚拟机** — 同时管理 Linux 容器和完整虚拟机（含 Windows），统一操作界面
- **完整计费体系** — 套餐管理、按时/按月计费、订单流程、在线支付、优惠券、流量包、库存管控
- **多节点集群** — 节点分组与集群管理，支持实例在线热迁移与 HA 自动疏散
- **主题与插件** — 前端主题可替换，功能通过插件扩展，支付/短信/邮件/认证均为插件化配置
- **在线更新** — 一键升级，自动数据库迁移，SQLite 迁移失败自动回滚
- **开放集成** — 提供 Provisioning API 和 Webhook 回调，可对接任意第三方系统

## 快速开始

```bash
# 下载并解压（以 amd64 为例）
wget https://github.com/huohuastudio/novaix-releases/releases/latest/download/novaix_linux_amd64.tar.gz
tar -xzf novaix_linux_amd64.tar.gz

# 运行
./novaix_linux_amd64/novaix
```

首次启动时，程序会在当前目录自动生成默认配置文件，使用 SQLite 数据库，无需额外依赖。

| 文件名 | 架构 | 说明 |
|--------|------|------|
| `novaix_linux_amd64.tar.gz` | x86_64 | 大多数云服务器 |
| `novaix_linux_arm64.tar.gz` | ARM64 | ARM 架构服务器 |

前往 [Releases](https://github.com/huohuastudio/novaix-releases/releases) 下载最新版本。详细部署指南请参阅[文档](https://docs.huohuastudio.com/novaix)。

## 免费版与授权版

部署即用，无需注册。免费版包含核心功能，足够运营一个小型 VPS 业务。

| 限制项 | 免费版 | 授权版 |
|--------|--------|--------|
| 节点数 | ≤ 2 | 不限 |
| HA 高可用（自动疏散） | — | ✓ |
| 告警通知 | — | ✓ |
| 私有网络（VPC） | — | ✓ |
| 代理商系统 | — | ✓ |
| 共享 IP / NAT | — | ✓ |
| 插件系统 | — | ✓ |

前往 [Spark Studio 官网](https://huohuastudio.com) 获取激活码。

## 技术亮点

| | |
|---|---|
| **语言与框架** | Go (Gin) + React (TypeScript) + TailwindCSS |
| **数据库** | SQLite（默认）/ MySQL / PostgreSQL |
| **部署方式** | 单二进制文件，支持 x86_64 和 ARM64 |
| **前端架构** | SPA 嵌入二进制，支持主题热替换 |
| **扩展机制** | 插件化 Provider（支付、短信、邮件、对象存储、实名认证） |
| **API** | RESTful API，Swagger 文档，Provisioning API + Webhook |

<details>
<summary><strong>功能全览</strong></summary>

<br />

**资源管理**
- 多节点接入与监控，资源统计与告警，一键连通性测试
- 节点分组与集群，实例在线热迁移，HA 自动疏散与维护模式
- 容器/虚拟机全生命周期管理（创建、启停、重装、快照、批量操作）
- 基于 OVN 的 VPC 私有网络，子网划分与安全组
- IP 池管理，灵活的 IP 分配与回收
- 镜像分组管理，cloud-init 开机脚本，多节点分发，自定义 ISO（含双 ISO）
- 支持 Linux 和 Windows 虚拟机，Windows 支持代理自动密码重置和网络配置

**计费与销售**
- 套餐管理，支持按月/季/年/小时等多种计费周期，CPU 限制，试建验证
- 流量包（叠加/重置两种模式），按套餐限制可购范围
- 完整订单流程，自动扣费与余额不足暂停，资源预检
- 库存管理，资源不足自动拦截下单
- 支付集成（易支付/彩虹易支付/ZPAY），可扩展更多渠道
- 优惠券与促销
- 发票管理（申请、审核、录入、在线查看下载）
- 代理商分组，差异化返佣与分销折扣矩阵

**用户与安全**
- 邮箱/手机号注册登录，多邮箱管理，TOTP 两步验证
- 实名认证（二要素 / 人脸识别），内置阿里云、腾讯云渠道
- 后台路径自定义，增强安全性

**系统能力**
- 短信服务（阿里云 / 腾讯云 / 通用 HTTP）
- 邮件服务（SMTP / Mailgun / Resend）
- 多渠道通知（Telegram / 钉钉 / 企业微信 / Webhook）
- S3 兼容对象存储（镜像归档与掉盘恢复）
- 任务大屏，WebSocket 实时日志流
- 工单系统
- CMS 内容管理（公告、文章、帮助中心、FAQ 等 14 个模块，均提供公开 API）
- CLI 管理工具（密码重置、插件管理、主题切换、系统信息等）
- 系统在线更新，数据库迁移失败自动回滚

</details>

<details>
<summary><strong>主题市场</strong></summary>

<br />

Novaix 支持通过主题系统自定义前端界面。前端源码开源在 [novaix-ui](https://github.com/huohuastudio/novaix-ui) 仓库，你可以基于它开发自己的主题。

#### 提交主题到市场

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

**审核标准：** 主题能正常安装和使用、`theme.json` 字段完整且格式正确、不包含恶意代码或外部跟踪脚本、zip 大小不超过 50MB。

</details>

<details>
<summary><strong>第三方集成（已停止维护）</strong></summary>

<br />

> **⚠️ WHMCS 模块和魔方模块已停止维护**，不会继续更新，可能与新版本存在兼容性问题。Provisioning API 和 Webhook 接口仍保持稳定。推荐直接使用 Novaix 内置的用户前台和计费功能。

[`integrations/`](./integrations/) 目录提供了与第三方系统对接所需的文档和模块：

| 内容 | 路径 | 说明 |
|------|------|------|
| Provisioning API 参考 | [`integrations/provisioning-api.md`](./integrations/provisioning-api.md) | 完整接口说明、认证方式、幂等性、错误码 |
| Webhook 回调与验签 | [`integrations/webhook.md`](./integrations/webhook.md) | 异步通知格式与 HMAC-SHA256 验签 |
| Webhook 接收端示例 | [`integrations/webhook-examples/`](./integrations/webhook-examples/) | PHP / Python / Node 三种语言的参考实现 |
| 魔方 V10 上游供应商模块 | [`integrations/mofang-reserver/`](./integrations/mofang-reserver/) | ⚠️ 已停止维护 |
| 魔方 V10 服务器模块 | [`integrations/mofang/`](./integrations/mofang/) | ⚠️ 已停止维护 |
| 魔方 2.x 模块 | [`integrations/mofang-legacy/`](./integrations/mofang-legacy/) | ⚠️ 已停止维护 |
| WHMCS 模块 | [`integrations/whmcs/`](./integrations/whmcs/) | ⚠️ 已停止维护 |

</details>

## 联系我们

- 官网：[novaix.cc](https://novaix.cc)
- 演示：[novaix.huohuastudio.com](https://novaix.huohuastudio.com)
- 文档：[docs.huohuastudio.com/novaix](https://docs.huohuastudio.com/novaix)
- 邮箱：[support@huohuastudio.com](mailto:support@huohuastudio.com)
- QQ 群：[点击加入](https://qm.qq.com/q/twNnYXDBmw)

## License

Copyright &copy; [Spark Studio](https://novaix.cc). All rights reserved.
