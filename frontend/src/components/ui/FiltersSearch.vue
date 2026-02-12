<script setup lang="ts">
defineOptions({ name: 'FiltersSearch' });

const model = defineModel<string>({ default: '' });
</script>

<template>
  <div class="filters-search__wrapper">
    <div class="filters-search max-w-md">
      <input
        v-model="model"
        type="text"
        placeholder="Метро, район, локация, ЖК, улица..."
        class="field text-field__element w-full rounded-lg border border-ta-border bg-white px-4 py-2.5 text-ta-text placeholder-ta-text-muted focus:border-ta-text focus:outline-none focus:ring-1 focus:ring-ta-border"
      />
    </div>
  </div>
</template>
