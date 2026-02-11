import { defineStore } from 'pinia';
import { ref, computed } from 'vue';

export type ViewType = 'list' | 'table' | 'map' | 'plans';

export const useObjectsStore = defineStore('objects', () => {
  const viewType = ref<ViewType>('list');
  const searchQuery = ref('');
  const filters = ref<Record<string, unknown>>({});

  const viewRouteMap: Record<ViewType, string> = {
    list: '/objects/list',
    table: '/objects/table',
    map: '/objects/map',
    plans: '/objects/plans',
  };

  const currentViewPath = computed(() => viewRouteMap[viewType.value]);

  function setViewType(v: ViewType) {
    viewType.value = v;
  }

  function setSearch(q: string) {
    searchQuery.value = q;
  }

  function setFilters(f: Record<string, unknown>) {
    filters.value = f;
  }

  return {
    viewType,
    searchQuery,
    filters,
    currentViewPath,
    setViewType,
    setSearch,
    setFilters,
  };
});
