<script setup lang="ts">
import ViewSwitch from './ViewSwitch.vue';

withDefaults(
  defineProps<{
    title?: string;
    count?: number;
    showSort?: boolean;
  }>(),
  { title: 'Объекты', showSort: false }
);
</script>

<template>
  <div class="objects-header flex flex-wrap items-center justify-between gap-4">
    <h1 class="objects-header__title text-xl font-semibold text-slate-100">
      {{ title }}
      <span v-if="count != null" class="font-normal text-slate-400">
        · {{ count }} {{ count === 1 ? 'объект' : 'объектов' }}
      </span>
    </h1>
    <div class="objects-header__container flex flex-wrap items-center gap-4">
      <ViewSwitch />
      <div v-if="showSort" class="objects-header__sort">
        <button
          type="button"
          class="rounded-lg border border-slate-600 bg-slate-800 px-4 py-2 text-sm text-slate-300 hover:bg-slate-700"
        >
          По цене
        </button>
      </div>
    </div>
  </div>
</template>
