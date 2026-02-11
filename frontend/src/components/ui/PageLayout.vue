<script setup lang="ts">
defineOptions({ name: 'PageLayout' });
</script>

<template>
  <div class="page-layout">
    <div class="page-layout__content">
      <slot />
    </div>
  </div>
</template>

<style scoped>
.page-layout {
  @apply w-full;
}
.page-layout__content {
  @apply mx-auto max-w-7xl px-4 py-6;
}
</style>
