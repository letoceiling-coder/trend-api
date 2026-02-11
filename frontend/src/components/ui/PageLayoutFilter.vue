<script setup lang="ts">
defineOptions({ name: 'PageLayoutFilter' });
</script>

<template>
  <div class="page-layout__filter">
    <div class="container">
      <div class="apartments-filter apartments-filter_search">
        <div class="apartments-filter__main">
          <slot />
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.page-layout__filter {
  @apply border-b border-slate-800 bg-slate-900/50;
}
.container {
  @apply mx-auto max-w-7xl px-4 py-4;
}
.apartments-filter_search {
  @apply flex flex-wrap items-center gap-3;
}
</style>
