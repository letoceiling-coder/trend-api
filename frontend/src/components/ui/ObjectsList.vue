<script setup lang="ts">
defineOptions({ name: 'ObjectsList' });
</script>

<template>
  <div class="objects-list grid gap-4 sm:grid-cols-1">
    <slot />
  </div>
</template>
