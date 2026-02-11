<script setup lang="ts">
defineOptions({ name: 'PageLayoutFilter' });
</script>

<template>
  <div class="page-layout__filter border-b border-slate-800 bg-slate-900/50">
    <div class="container mx-auto max-w-7xl px-4 py-4">
      <div class="apartments-filter apartments-filter_search flex flex-wrap items-center gap-3">
        <div class="apartments-filter__main">
          <slot />
        </div>
      </div>
    </div>
  </div>
</template>
