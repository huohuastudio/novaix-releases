# Novaix

> **⚠️ 当前为预览版本，功能尚未完善，可能存在不稳定的情况。如果你在使用过程中遇到任何问题或有功能建议，欢迎提交 [Issue](https://github.com/huohuastudio/novaix-releases/issues)。**

Novaix 是一套商业化 IDC 管理系统，面向中小型 VPS 服务商，提供节点管理、实例生命周期管理、计费订单、工单系统等一站式解决方案。

系统采用 Go + React 单体架构，编译为单个二进制文件，开箱即用。

## 功能特性

- **节点管理** — 多节点接入与监控，支持资源统计与告警
- **实例管理** — 容器/虚拟机全生命周期管理，支持创建、启停、重装、快照等操作
- **IP 池管理** — 灵活的 IP 分配与回收机制
- **镜像与 ISO** — 系统镜像管理，支持自定义 ISO 挂载
- **套餐管理** — 自定义计费套餐，支持按月/季/年等多种周期
- **计费与订单** — 完整的订单流程与支付记录
- **优惠券** — 灵活的促销优惠体系
- **用户系统** — 用户注册、登录、个人资料管理
- **代理系统** — 多级代理与佣金管理
- **工单系统** — 用户与管理员沟通渠道
- **公告管理** — 面向用户的通知与公告发布
- **系统设置** — 站点信息、维护模式、支付配置等

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

## 联系我们

- 官网：[huohuastudio.com](https://huohuastudio.com)

## License

Copyright &copy; [Spark Studio](https://huohuastudio.com). All rights reserved.
