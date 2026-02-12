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
      <div class="group__elements flex rounded-lg border border-ta-border bg-white p-0.5 shadow-sm">
        <RouterLink
          v-for="v in views"
          :key="v.type"
          :to="v.path"
          class="objects-header__view-button btn px-3 py-2 text-sm font-medium transition-colors rounded-md"
          :class="
            isActive(v.path)
              ? 'bg-ta-text text-white'
              : 'text-ta-text-muted hover:text-ta-text hover:bg-ta-border/30'
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
