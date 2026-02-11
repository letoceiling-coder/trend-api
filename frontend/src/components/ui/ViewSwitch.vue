<script setup lang="ts">
import { RouterLink, useRoute } from 'vue-router';
import type { ViewType } from '../../stores/objects';

const route = useRoute();

const views: { type: ViewType; label: string; path: string; icon: string }[] = [
  { type: 'list', label: 'Комплексы', path: '/objects/list', icon: '⊞' },
  { type: 'table', label: 'Квартиры', path: '/objects/table', icon: '▦' },
  { type: 'plans', label: 'Планировки', path: '/objects/plans', icon: '▤' },
  { type: 'map', label: 'На карте', path: '/objects/map', icon: '📍' },
];

function isActive(path: string) {
  return route.path === path || route.path.startsWith(path + '/');
}
</script>

<template>
  <nav class="objects-header__view d-flex">
    <div class="group objects-header__view-group" role="group">
      <div class="group__elements flex rounded-lg border border-slate-700 bg-slate-800/50 p-0.5">
        <RouterLink
          v-for="v in views"
          :key="v.type"
          :to="v.path"
          class="objects-header__view-button btn px-3 py-2 text-sm font-medium transition"
          :class="
            isActive(v.path)
              ? 'bg-slate-600 text-white rounded-md'
              : 'text-slate-400 hover:text-white hover:bg-slate-700/50 rounded-md'
          "
        >
          <span class="btn__content inline-flex items-center gap-2">
            <span aria-hidden="true">{{ v.icon }}</span>
            <span>{{ v.label }}</span>
          </span>
        </RouterLink>
      </div>
    </div>
  </nav>
</template>

<style scoped>
.d-flex {
  display: flex;
}
</style>
