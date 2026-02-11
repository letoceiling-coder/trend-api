<script setup lang="ts">
defineOptions({ name: 'PageLayout' });
</script>

<template>
  <div class="page-layout w-full">
    <div class="page-layout__content mx-auto max-w-7xl px-4 py-6">
      <slot />
    </div>
  </div>
</template>
