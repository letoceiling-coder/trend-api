<script setup lang="ts">
import { ref, onMounted, computed, watch } from 'vue';
import { useRoute, RouterLink } from 'vue-router';
import PageLayout from '../components/ui/PageLayout.vue';
import { getApartments, type ApartmentItem } from '../api/ta';

const route = useRoute();
const blockId = computed(() => String(route.params.blockId ?? ''));

const apartments = ref<ApartmentItem[]>([]);
const loading = ref(true);

async function load() {
  loading.value = true;
  try {
    const res = await getApartments({
      count: 100,
      offset: 0,
      ...(blockId.value ? { block_id: blockId.value } : {}),
    });
    apartments.value = Array.isArray(res.data) ? res.data : [];
  } finally {
    loading.value = false;
  }
}

onMounted(load);
watch(blockId, load);
</script>

<template>
  <PageLayout>
    <div class="checkerboard page-layout__content" data-root="checkerboard">
      <div class="checkerboard-filter mb-4 flex flex-wrap gap-2">
        <input
          type="text"
          placeholder="Фильтр..."
          class="rounded-lg border border-slate-600 bg-slate-800 px-4 py-2 text-slate-100"
        />
      </div>
      <div
        class="checkerboard-container__sections-container grid gap-4 sm:grid-cols-2 lg:grid-cols-3"
      >
        <div
          v-if="loading"
          class="col-span-full py-12 text-center text-slate-400"
        >
          Загрузка...
        </div>
        <RouterLink
          v-for="a in apartments"
          :key="a.apartment_id"
          :to="`/flat/${a.apartment_id}`"
          class="checkerboard-cell rounded-xl border border-slate-700 bg-slate-800/50 p-4 transition hover:border-slate-600"
        >
          <div class="font-medium text-slate-200">
            {{ a.title ?? a.apartment_id }}
          </div>
          <div class="mt-1 text-sm text-slate-400">
            {{ a.rooms != null ? `${a.rooms} комн.` : '' }}
            {{ a.area_total != null ? ` · ${a.area_total} м²` : '' }}
            {{ a.price != null ? ` · ${Number(a.price).toLocaleString('ru-RU')} ₽` : '' }}
          </div>
        </RouterLink>
      </div>
    </div>
  </PageLayout>
</template>
