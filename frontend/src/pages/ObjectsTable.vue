<script setup lang="ts">
import { ref, onMounted, computed, watch } from 'vue';
import { RouterLink } from 'vue-router';
import PageLayout from '../components/ui/PageLayout.vue';
import PageLayoutFilter from '../components/ui/PageLayoutFilter.vue';
import ObjectsHeader from '../components/ui/ObjectsHeader.vue';
import FiltersSearch from '../components/ui/FiltersSearch.vue';
import Pagination from '../components/ui/Pagination.vue';
import { getApartments, type ApartmentItem } from '../api/ta';
import { useObjectsStore } from '../stores/objects';

const store = useObjectsStore();
const search = computed({
  get: () => store.searchQuery,
  set: (v: string) => store.setSearch(v),
});

const apartments = ref<ApartmentItem[]>([]);
const meta = ref<{ pagination?: { total?: number; count?: number; offset?: number } }>({});
const loading = ref(true);
const offset = ref(0);
const count = 20;

async function load() {
  loading.value = true;
  try {
    const res = await getApartments({
      count,
      offset: offset.value,
    });
    apartments.value = Array.isArray(res.data) ? res.data : [];
    meta.value = res.meta ?? {};
  } finally {
    loading.value = false;
  }
}

onMounted(load);
watch([offset], load);

const total = computed(() => meta.value.pagination?.total ?? 0);
const totalPages = computed(() => Math.ceil(total.value / count) || 1);
const currentPage = computed({
  get: () => Math.floor(offset.value / count) + 1,
  set: (p: number) => {
    offset.value = (p - 1) * count;
  },
});
</script>

<template>
  <PageLayout>
    <div id="searchpage" class="page-layout__content" data-root="table">
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

      <div class="objects-wrapper objects-wrapper_table">
        <div class="container mx-auto max-w-7xl px-4 py-4">
          <ObjectsHeader
            title="Квартиры"
            :count="total"
            :show-sort="true"
          />
          <div v-if="loading" class="py-12 text-center text-slate-400">
            Загрузка...
          </div>
          <template v-else>
            <div class="overflow-x-auto rounded-lg border border-slate-700">
              <table class="table w-full border-collapse text-left text-sm">
                <thead>
                  <tr class="border-b border-slate-700 bg-slate-800/80">
                    <th class="table-row px-4 py-3 font-medium text-slate-300">ID</th>
                    <th class="table-row px-4 py-3 font-medium text-slate-300">Название</th>
                    <th class="table-row px-4 py-3 font-medium text-slate-300">Площадь</th>
                    <th class="table-row px-4 py-3 font-medium text-slate-300">Цена</th>
                    <th class="table-row px-4 py-3 font-medium text-slate-300">Комнат</th>
                  </tr>
                </thead>
                <tbody>
                  <tr
                    v-for="a in apartments"
                    :key="a.apartment_id"
                    class="table-row border-b border-slate-700/80 hover:bg-slate-800/50"
                  >
                    <td class="px-4 py-3 text-slate-200">
                      <RouterLink
                        :to="`/flat/${a.apartment_id}`"
                        class="text-sky-400 hover:underline"
                      >
                        {{ a.apartment_id }}
                      </RouterLink>
                    </td>
                    <td class="px-4 py-3 text-slate-200">
                      <RouterLink
                        :to="`/flat/${a.apartment_id}`"
                        class="text-sky-400 hover:underline"
                      >
                        {{ a.title ?? '—' }}
                      </RouterLink>
                    </td>
                    <td class="px-4 py-3 text-slate-200">
                      {{ a.area_total != null ? `${a.area_total} м²` : '—' }}
                    </td>
                    <td class="px-4 py-3 text-slate-200">
                      {{ a.price != null ? `${Number(a.price).toLocaleString('ru-RU')} ₽` : '—' }}
                    </td>
                    <td class="px-4 py-3 text-slate-200">{{ a.rooms ?? '—' }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
            <Pagination
              :page="currentPage"
              :total-pages="totalPages"
              :total="total"
              @update:page="(p: number) => { currentPage = p }"
            />
          </template>
        </div>
      </div>
    </div>
  </PageLayout>
</template>
