# NovaIx 智简魔方财务系统（2.x）模块

智简魔方财务系统 2.x 版本的服务器模块，通过 Provisioning API 对接 NovaIx。

> ⚠️ 本模块适用于**魔方财务 2.x**（`public/plugins/servers/` 目录结构）。
> 如果你使用的是**智简魔方 V10**（ZJMF-CBAP），请使用 [`mofang/`](../mofang/) 目录下的 `idcsmart_common` 子模块。

> ⚠️ **当前版本未在真实魔方环境完整测试，请先在测试环境验证再投入生产。**

## 环境要求

- 智简魔方财务系统 2.x（基于 ThinkPHP 5）
- PHP 7.4+，启用 curl 和 json 扩展

## 安装

将 `novaix/` 目录复制到魔方的服务器模块目录：

```
{魔方安装目录}/public/plugins/servers/novaix/
└── novaix.php
```

## 配置

### NovaIx 侧

按 [Provisioning API 文档 → 集成方与认证](../provisioning-api.md#1-集成方与认证) 操作：

1. 先创建一个 **Integration**（如"魔方主站"），配置 webhook 回调地址，保存 `callback_secret`
2. 再创建一把关联到该 Integration 的 **API 密钥**（带 `provision` 权限），保存 `nv_` 开头的密钥

### 魔方侧

1. **添加服务器**：后台 → 设置 → 商品设置 → 通用接口 → 创建接口
   - **服务器模块**：选择 `NovaIx VPS`
   - **IP 地址**：NovaIx 服务器域名或 IP（如 `novaix.example.com`）
   - **密码**：填入 `nv_` 开头的 API 密钥
   - **端口**：NovaIx 服务端口（如 `8080`，使用 HTTPS 反代则为 `443`）
   - **SSL**：如使用 HTTPS 则勾选
   - 点击"测试连接"验证

2. **创建商品**：关联到上面的服务器，在模块配置中填写：
   - **套餐 ID**：NovaIx 后台的套餐 ID
   - **镜像 ID**：默认操作系统镜像 ID
   - **节点 ID**：可选，留空自动选择

## 支持的功能

| 功能 | 状态 | 说明 |
|------|------|------|
| 自动开通 | ✅ | 套餐驱动 + 自动分配 IP + 同步等待实例就绪 |
| 暂停/解除暂停 | ✅ | |
| 删除 | ✅ | |
| 开机/关机/重启 | ✅ | 含硬关机、硬重启 |
| 重装系统 | ✅ | |
| 重置密码 | ✅ | |
| 状态同步 | ✅ | 自动同步 IP 地址和实例状态 |
| 幂等创建 | ✅ | 通过 `external_id`（魔方 `hostid`）保证 |
| 续费/升降级 | ✅ | 由魔方侧管理，NovaIx 无需额外操作 |
| VNC 控制台 | ❌ | 暂未支持，建议引导用户使用 NovaIx 面板 |
| 流量图表 | ❌ | 暂未支持 |

## 如何判断我的版本？

| 特征 | 魔方财务 2.x | 智简魔方 V10 |
|------|-------------|-------------|
| 服务器模块路径 | `public/plugins/servers/` | `public/plugins/server/idcsmart_common/module/` |
| 后台界面 | 传统布局 | Vue 2 前后端分离 |
| 内置功能 | 需购买专业版 | 开源 + 应用商店 |
| 框架 | ThinkPHP 5 | ThinkPHP 6 |

## 与 V10 模块的区别

本模块与 V10 版本的核心区别在于数据回写方式：

- **V10 模块**：通过 `IdcsmartCommonServerHostLinkModel` 和 `HostIpModel` 回写 IP 和状态
- **本模块**：通过 ThinkPHP 5 的 `\think\Db` 直接更新 `host` 表

两个模块调用的 NovaIx Provisioning API 完全相同，功能无差异。

## 同步开通的等待策略

`CreateAccount` 同步轮询 `/instances/{id}/status` 直到实例就绪，最长 180 秒。可在 `novaix.php` 顶部调整 `NOVAIX_CREATE_TIMEOUT` 常量。

## Webhook 回调

任务完成时 NovaIx 会向 Integration 配置的回调地址 POST 通知，请参见 [Webhook 文档](../webhook.md) 了解 payload 格式和验签方法。
