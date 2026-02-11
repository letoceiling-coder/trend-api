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
        class="field text-field__element w-full rounded-lg border border-slate-600 bg-slate-800 px-4 py-2.5 text-slate-100 placeholder-slate-500 focus:border-slate-500 focus:outline-none focus:ring-1 focus:ring-slate-500"
      />
    </div>
  </div>
</template>
