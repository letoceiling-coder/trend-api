<script setup lang="ts">
import { ref, onMounted, computed, watch } from 'vue';
import { RouterLink } from 'vue-router';
import PageLayout from '../components/ui/PageLayout.vue';
import PageLayoutFilter from '../components/ui/PageLayoutFilter.vue';
import ObjectsHeader from '../components/ui/ObjectsHeader.vue';
import PlanCard from '../components/ui/PlanCard.vue';
import FiltersSearch from '../components/ui/FiltersSearch.vue';
import { getApartments, type ApartmentItem } from '../api/ta';
import { useObjectsStore } from '../stores/objects';

const store = useObjectsStore();
const search = computed({
  get: () => store.searchQuery,
  set: (v: string) => store.setSearch(v),
});

const plans = ref<ApartmentItem[]>([]);
const meta = ref<{ pagination?: { total?: number } }>({});
const loading = ref(true);
const offset = ref(0);
const count = 50;

async function load() {
  loading.value = true;
  try {
    const res = await getApartments({ count, offset: offset.value });
    plans.value = Array.isArray(res.data) ? res.data : [];
    meta.value = res.meta ?? {};
  } finally {
    loading.value = false;
  }
}

onMounted(load);
watch([offset], load);

const total = computed(() => meta.value.pagination?.total ?? plans.value.length);

function cardFromPlan(a: ApartmentItem) {
  return {
    id: a.apartment_id,
    title: a.title ?? undefined,
    imageUrl: undefined,
    area: a.area_total != null ? String(a.area_total) : undefined,
    rooms: a.rooms != null ? String(a.rooms) : undefined,
    price: a.price != null ? `${Number(a.price).toLocaleString('ru-RU')} ₽` : undefined,
  };
}
</script>

<template>
  <PageLayout>
    <div id="searchpage" class="page-layout__content" data-root="plans">
      <PageLayoutFilter>
        <div class="apartments-filter apartments-filter_search">
          <FiltersSearch v-model="search" />
          <button
            type="button"
            class="rounded-lg border border-slate-600 bg-slate-800 px-4 py-2 text-sm text-slate-300 hover:bg-slate-700"
            @click="load"
          >
            Применить
          </button>
        </div>
      </PageLayoutFilter>

      <div class="objects-wrapper objects-wrapper_plans">
        <div class="container mx-auto max-w-7xl px-4 py-4">
          <ObjectsHeader title="Планировки" :count="total" />
          <div v-if="loading" class="py-12 text-center text-slate-400">
            Загрузка...
          </div>
          <div
            v-else
            class="grid gap-4 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4"
          >
            <RouterLink
              v-for="p in plans"
              :key="p.apartment_id"
              :to="`/flat/${p.apartment_id}`"
              class="plancard-item"
            >
              <PlanCard v-bind="cardFromPlan(p)" />
            </RouterLink>
          </div>
        </div>
      </div>
    </div>
  </PageLayout>
</template>
