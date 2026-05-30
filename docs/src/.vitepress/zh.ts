import { DefaultTheme, defineConfig } from 'vitepress'

export const zh = defineConfig({
  lang: 'zh-Hans',
  title: 'Novaix',
  description: '一站式 IDC 管理系统',
  themeConfig: {
    nav: nav(),

    sidebar: {
      '/guide/': { base: '/guide/', items: sidebarGuide() },
    },

    footer: {
      copyright: `All rights reserved © 2025-${new Date().getFullYear()} <a href="https://huohuastudio.com">Spark Studio</a>`
    },

    docFooter: {
      prev: '上一页',
      next: '下一页'
    },

    outline: {
      level: 'deep',
      label: '页面导航'
    },

    lastUpdated: {
      text: '最后更新于',
      formatOptions: {
        dateStyle: 'short',
        timeStyle: 'medium'
      }
    },

    langMenuLabel: '多语言',
    returnToTopLabel: '回到顶部',
    sidebarMenuLabel: '菜单',
    darkModeSwitchLabel: '主题',
    lightModeSwitchTitle: '切换到浅色模式',
    darkModeSwitchTitle: '切换到深色模式'
  }
})

function nav(): DefaultTheme.NavItem[] {
  return [
    {
      text: '指南',
      link: '/guide/introduce',
      activeMatch: '/guide/'
    },
  ]
}

function sidebarGuide(): DefaultTheme.SidebarItem[] {
  return [
    {
      text: '简介',
      collapsed: false,
      items: [
        { text: '介绍', link: 'introduce' },
        { text: '快速开始', link: 'getting-started' },
        { text: '环境要求', link: 'requirement' },
      ]
    },
    {
      text: '部署',
      collapsed: false,
      items: [
        { text: '安装', link: 'install' },
        { text: '升级', link: 'upgrade' },
        { text: '备份与恢复', link: 'backup' },
      ]
    },
    {
      text: '基础设施',
      collapsed: false,
      items: [
        { text: '节点管理', link: 'node' },
        { text: '镜像管理', link: 'image' },
        { text: 'IP 池管理', link: 'ip-pool' },
        { text: '套餐管理', link: 'plan' },
      ]
    },
    {
      text: '业务运营',
      collapsed: false,
      items: [
        { text: '实例管理', link: 'instance' },
        { text: '用户管理', link: 'user' },
        { text: '订单与计费', link: 'order' },
        { text: '支付配置', link: 'payment' },
        { text: '优惠券', link: 'coupon' },
        { text: '代理系统', link: 'agent' },
      ]
    },
    {
      text: '系统功能',
      collapsed: false,
      items: [
        { text: '工单系统', link: 'ticket' },
        { text: '公告管理', link: 'announcement' },
        { text: '监控与告警', link: 'monitoring' },
        { text: '邮件配置', link: 'mail' },
        { text: '系统设置', link: 'setting' },
        { text: '常见问题', link: 'faq' },
      ]
    },
    {
      text: '参考',
      collapsed: false,
      items: [
        { text: '配置参考', link: 'config' },
      ]
    },
  ]
}

export const search: DefaultTheme.LocalSearchOptions['locales'] = {
  root: {
    translations: {
      button: {
        buttonText: '搜索文档',
        buttonAriaLabel: '搜索文档'
      },
      modal: {
        noResultsText: '无法找到相关结果',
        resetButtonTitle: '清除查询条件',
        footer: {
          selectText: '选择',
          navigateText: '切换'
        }
      }
    }
  }
}
