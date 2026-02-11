<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue';
import { useRoute, RouterLink } from 'vue-router';
import PageLayout from '../components/ui/PageLayout.vue';
import ObjectGallery from '../components/ui/ObjectGallery.vue';
import Tabs from '../components/ui/Tabs.vue';
import {
  getBlockDetail,
  type BlockItemWithDetail,
  type BlockDetailPayload,
} from '../api/ta';

const route = useRoute();
const blockId = computed(() => String(route.params.blockId ?? ''));

const block = ref<BlockItemWithDetail | null>(null);
const loading = ref(true);
const error = ref<{ status?: number; message?: string } | null>(null);
const refreshing = ref(false);
const activeTab = ref('about');

const tabs = [
  { id: 'about', label: 'О комплексе' },
  { id: 'advantages', label: 'Преимущества' },
  { id: 'nearby', label: 'Рядом' },
  { id: 'bank', label: 'Банк' },
  { id: 'geo', label: 'На карте' },
  { id: 'prices', label: 'Цены' },
  { id: 'flats', label: 'Квартиры' },
  { id: 'docs', label: 'Документы' },
];

async function load() {
  if (!blockId.value) return;
  loading.value = true;
  error.value = null;
  try {
    const res = await getBlockDetail(blockId.value);
    block.value = (res.data as BlockItemWithDetail) ?? null;
  } catch (e: unknown) {
    const ax = e as { response?: { status?: number }; message?: string };
    error.value = {
      status: ax.response?.status,
      message: ax.message ?? 'Ошибка загрузки',
    };
    block.value = null;
  } finally {
    loading.value = false;
  }
}

async function refresh() {
  if (!blockId.value) return;
  refreshing.value = true;
  error.value = null;
  try {
    const res = await getBlockDetail(blockId.value);
    block.value = (res.data as BlockItemWithDetail) ?? null;
  } catch (e: unknown) {
    const ax = e as { response?: { status?: number }; message?: string };
    error.value = {
      status: ax.response?.status,
      message: ax.message ?? 'Ошибка загрузки',
    };
  } finally {
    refreshing.value = false;
  }
}

onMounted(load);
watch(blockId, load);

const detail = computed(() => block.value?.detail ?? null);
const hasDetail = computed(() => !!detail.value);

const images = computed(() => {
  const b = block.value;
  if (!b) return [];
  const d = detail.value as BlockDetailPayload & { gallery?: string[] };
  const fromUnified = d?.unified_payload as { images?: string[] } | undefined;
  const list = fromUnified?.images ?? (b as Record<string, unknown>).gallery;
  if (Array.isArray(list)) return list.map(String);
  if (typeof list === 'string') return [list];
  return [];
});

function renderPayload(value: unknown): string {
  if (value == null) return '—';
  if (typeof value === 'string') return value;
  if (Array.isArray(value)) return value.map((v) => renderPayload(v)).join(', ');
  if (typeof value === 'object') return JSON.stringify(value, null, 2);
  return String(value);
}

const aboutContent = computed(() => {
  const d = detail.value?.unified_payload as Record<string, unknown> | undefined;
  if (!d) return null;
  const desc = d.description ?? d.text ?? d.about;
  return desc != null ? renderPayload(desc) : null;
});

const advantagesContent = computed(() => {
  const d = detail.value?.advantages_payload;
  return d != null ? renderPayload(d) : null;
});

const nearbyContent = computed(() => {
  const d = detail.value?.nearby_places_payload;
  return d != null ? renderPayload(d) : null;
});

const bankContent = computed(() => {
  const d = detail.value?.bank_payload;
  return d != null ? renderPayload(d) : null;
});

const geoContent = computed(() => {
  const d = detail.value?.geo_buildings_payload;
  return d != null ? renderPayload(d) : null;
});

const pricesContent = computed(() => {
  const d = detail.value?.apartments_min_price_payload;
  return d != null ? renderPayload(d) : null;
});
</script>

<template>
  <PageLayout>
    <div id="objectpage" class="page-layout__content" data-root="object-detail">
      <div class="mb-4">
        <RouterLink
          to="/objects/list"
          class="text-sm text-slate-400 hover:text-white"
        >
          ← К списку объектов
        </RouterLink>
      </div>

      <div v-if="loading" class="py-12 text-center text-slate-400">
        Загрузка...
      </div>

      <div
        v-else-if="error && (error.status === 404 || !block)"
        class="py-12 text-center text-slate-400"
      >
        <p>Объект не найден.</p>
        <p v-if="error.message" class="mt-2 text-sm text-red-400">
          {{ error.message }}
        </p>
      </div>

      <div v-else-if="error" class="py-12 text-center text-slate-400">
        <p>Ошибка загрузки.</p>
        <p class="mt-2 text-sm text-red-400">{{ error.message }}</p>
        <button
          type="button"
          class="mt-4 rounded-lg border border-slate-600 bg-slate-800 px-4 py-2 text-sm text-slate-300 hover:bg-slate-700"
          @click="load"
        >
          Повторить
        </button>
      </div>

      <template v-else-if="block">
        <header class="object-detail-header mb-4">
          <h1 class="text-2xl font-semibold text-slate-100">
            {{ block.title ?? 'Объект' }}
          </h1>
        </header>

        <div v-if="!hasDetail" class="mb-6 rounded-xl border border-amber-800 bg-amber-950/30 p-4 text-amber-200">
          <p class="mb-2">Данные обновляются.</p>
          <button
            type="button"
            class="rounded-lg border border-amber-600 bg-amber-900/50 px-4 py-2 text-sm hover:bg-amber-900/70 disabled:opacity-50"
            :disabled="refreshing"
            @click="refresh"
          >
            {{ refreshing ? 'Обновление…' : 'Обновить' }}
          </button>
        </div>

        <ObjectGallery
          class="object-gallery mb-6"
          :images="images"
          :alt="String(block.title ?? '')"
        />

        <Tabs v-model="activeTab" :tabs="tabs" class="object-detail-tabs" />

        <div class="mt-4 text-slate-300">
          <div v-if="activeTab === 'about'" class="prose prose-invert max-w-none">
            <p v-if="aboutContent">{{ aboutContent }}</p>
            <p v-else-if="hasDetail">Описание отсутствует.</p>
            <p v-else class="text-slate-500">Загрузите детали по кнопке «Обновить».</p>
          </div>
          <div v-else-if="activeTab === 'advantages'" class="prose prose-invert max-w-none">
            <pre v-if="advantagesContent" class="whitespace-pre-wrap text-sm">{{ advantagesContent }}</pre>
            <p v-else class="text-slate-500">Нет данных.</p>
          </div>
          <div v-else-if="activeTab === 'nearby'" class="prose prose-invert max-w-none">
            <pre v-if="nearbyContent" class="whitespace-pre-wrap text-sm">{{ nearbyContent }}</pre>
            <p v-else class="text-slate-500">Нет данных.</p>
          </div>
          <div v-else-if="activeTab === 'bank'" class="prose prose-invert max-w-none">
            <pre v-if="bankContent" class="whitespace-pre-wrap text-sm">{{ bankContent }}</pre>
            <p v-else class="text-slate-500">Нет данных.</p>
          </div>
          <div v-else-if="activeTab === 'geo'" class="prose prose-invert max-w-none">
            <pre v-if="geoContent" class="whitespace-pre-wrap text-sm">{{ geoContent }}</pre>
            <p v-else class="text-slate-500">Нет данных.</p>
          </div>
          <div v-else-if="activeTab === 'prices'" class="prose prose-invert max-w-none">
            <pre v-if="pricesContent" class="whitespace-pre-wrap text-sm">{{ pricesContent }}</pre>
            <p v-else class="text-slate-500">Нет данных.</p>
          </div>
          <div v-else-if="activeTab === 'flats'">
            <RouterLink
              :to="`/object/${block.block_id}/checkerboard`"
              class="text-sky-400 hover:underline"
            >
              Планировки (шахматка) →
            </RouterLink>
          </div>
          <div v-else-if="activeTab === 'docs'">
            <p class="text-slate-500">Документы — в разработке.</p>
          </div>
        </div>
      </template>
    </div>
  </PageLayout>
</template>
