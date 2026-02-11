<script setup lang="ts">
import { computed } from 'vue';
import PageLayout from '../components/ui/PageLayout.vue';
import PageLayoutFilter from '../components/ui/PageLayoutFilter.vue';
import ObjectsHeader from '../components/ui/ObjectsHeader.vue';
import FiltersSearch from '../components/ui/FiltersSearch.vue';
import { useObjectsStore } from '../stores/objects';

const store = useObjectsStore();
const search = computed({
  get: () => store.searchQuery,
  set: (v: string) => store.setSearch(v),
});
</script>

<template>
  <PageLayout>
    <div id="searchpage" class="page-layout__content" data-root="map">
      <PageLayoutFilter>
        <div class="apartments-filter apartments-filter_search">
          <FiltersSearch v-model="search" />
        </div>
      </PageLayoutFilter>

      <div class="objects-wrapper objects-wrapper_map">
        <div class="container mx-auto max-w-7xl px-4 py-4">
          <ObjectsHeader title="На карте" />
          <div class="mt-4 flex gap-4">
            <aside class="map-sidebar w-64 shrink-0 space-y-2">
              <RouterLink
                to="/objects/list"
                class="map-list-btn block rounded-lg border border-slate-600 bg-slate-800 px-4 py-2 text-sm text-slate-300 hover:bg-slate-700"
              >
                К списку
              </RouterLink>
            </aside>
            <div
              class="map-container min-h-[400px] flex-1 overflow-hidden rounded-xl border border-slate-700 bg-slate-800/50"
              data-map
            >
              <div class="flex h-full w-full items-center justify-center text-slate-500">
                Карта (интеграция с картами — заглушка)
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </PageLayout>
</template>
