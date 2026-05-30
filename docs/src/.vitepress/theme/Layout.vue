<script setup>
import DefaultTheme from 'vitepress/theme'

const { Layout } = DefaultTheme
</script>

<template>
  <Layout>
    <template #doc-before>
      <div class="dev-banner">
        ⚠️ Novaix 目前处于早期开发阶段，功能尚未稳定，可能存在严重的 Bug。请勿用于生产环境。
      </div>
    </template>
  </Layout>
</template>

<style>
.dev-banner {
  background-color: #fef3c7;
  border: 1px solid #f59e0b;
  border-radius: 8px;
  color: #92400e;
  text-align: center;
  padding: 10px 16px;
  font-size: 14px;
  font-weight: 500;
  margin-bottom: 24px;
}

.dark .dev-banner {
  background-color: #422006;
  border-color: #b45309;
  color: #fcd34d;
}
</style>
