<script setup lang="ts">
import { ref, onMounted, computed, watch } from 'vue';
import PageLayout from '../components/ui/PageLayout.vue';
import PageLayoutFilter from '../components/ui/PageLayoutFilter.vue';
import ObjectsHeader from '../components/ui/ObjectsHeader.vue';
import ObjectsListGrid from '../components/ui/ObjectsList.vue';
import HouseCard from '../components/ui/HouseCard.vue';
import FiltersSearch from '../components/ui/FiltersSearch.vue';
import Pagination from '../components/ui/Pagination.vue';
import { getBlocks, type BlockItem } from '../api/ta';
import { useObjectsStore } from '../stores/objects';

const store = useObjectsStore();
const search = computed({
  get: () => store.searchQuery,
  set: (v: string) => store.setSearch(v),
});

const blocks = ref<BlockItem[]>([]);
const meta = ref<{ pagination?: { total?: number; count?: number; offset?: number } }>({});
const loading = ref(true);
const offset = ref(0);
const count = 20;

async function load() {
  loading.value = true;
  try {
    const res = await getBlocks({
      count,
      offset: offset.value,
      ...(search.value ? {} : {}),
    });
    blocks.value = Array.isArray(res.data) ? res.data : [];
    meta.value = res.meta ?? {};
  } finally {
    loading.value = false;
  }
}

onMounted(load);
watch([offset, search], load);

const total = computed(() => meta.value.pagination?.total ?? 0);
const totalPages = computed(() => Math.ceil(total.value / count) || 1);
const currentPage = computed({
  get: () => Math.floor(offset.value / count) + 1,
  set: (p: number) => {
    offset.value = (p - 1) * count;
  },
});

function cardFromBlock(b: BlockItem) {
  return {
    id: b.block_id ?? b.id ?? '',
    title: b.title ?? 'Без названия',
    deadline: b.deadline ? String(b.deadline) : undefined,
    address: b.location ? String(b.location) : undefined,
    imageUrl: b.image_url ?? undefined,
    price:
      b.min_price != null
        ? `${Number(b.min_price).toLocaleString('ru-RU')} ₽`
        : undefined,
    rooms: undefined,
  };
}
</script>

<template>
  <PageLayout>
    <!-- #searchpage .page-layout__content (base-list-complex.selectors.json) -->
    <div id="searchpage" class="page-layout__content">
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

      <div class="objects-wrapper objects-wrapper_list">
        <div class="container mx-auto max-w-7xl px-4 py-4">
          <ObjectsHeader
            title="Комплексы"
            :count="total"
            :show-sort="true"
          />
          <div v-if="loading" class="py-12 text-center text-ta-text-muted">
            Загрузка...
          </div>
          <template v-else>
            <ObjectsListGrid>
              <HouseCard
                v-for="b in blocks"
                :key="b.block_id"
                v-bind="cardFromBlock(b)"
              />
            </ObjectsListGrid>
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
