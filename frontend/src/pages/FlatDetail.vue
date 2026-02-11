<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';
import { useRoute, RouterLink } from 'vue-router';
import PageLayout from '../components/ui/PageLayout.vue';
import ObjectGallery from '../components/ui/ObjectGallery.vue';
import {
  getApartmentDetail,
  refreshApartmentDetail,
  type ApartmentItemWithDetail,
  type ApartmentDetailPayload,
} from '../api/ta';

const route = useRoute();
const apartmentId = computed(() => String(route.params.apartmentId ?? ''));

const apartment = ref<ApartmentItemWithDetail | null>(null);
const loading = ref(true);
const error = ref<{ status?: number; message?: string } | null>(null);

const refreshState = ref('');
const POLL_INTERVAL_MS = 2000;
const POLL_TIMEOUT_MS = 30000;
let pollTimer: ReturnType<typeof setInterval> | null = null;
let pollDeadline = 0;

function stopPolling() {
  if (pollTimer) {
    clearInterval(pollTimer);
    pollTimer = null;
  }
}

async function refresh() {
  if (!apartmentId.value) return;
  error.value = null;
  refreshState.value = '';
  const previousFetchedAt = detail.value?.fetched_at ?? null;
  try {
    await refreshApartmentDetail(apartmentId.value);
    refreshState.value = 'queued';
    pollDeadline = Date.now() + POLL_TIMEOUT_MS;
    const check = async () => {
      if (Date.now() > pollDeadline) {
        stopPolling();
        refreshState.value = 'fail';
        return;
      }
      refreshState.value = 'loading';
      const res = await getApartmentDetail(apartmentId.value);
      const next = (res.data as ApartmentItemWithDetail) ?? null;
      apartment.value = next;
      const nextFetchedAt = next?.detail?.fetched_at ?? null;
      if (nextFetchedAt && nextFetchedAt !== previousFetchedAt) {
        stopPolling();
        refreshState.value = 'success';
        setTimeout(() => { refreshState.value = ''; }, 2000);
        return;
      }
    };
    await check();
    pollTimer = setInterval(check, POLL_INTERVAL_MS);
  } catch (e: unknown) {
    stopPolling();
    const ax = e as { response?: { status?: number }; message?: string };
    error.value = {
      status: ax.response?.status,
      message: ax.message ?? 'Ошибка загрузки',
    };
    refreshState.value = 'fail';
  }
}

async function load() {
  if (!apartmentId.value) return;
  loading.value = true;
  error.value = null;
  try {
    const res = await getApartmentDetail(apartmentId.value);
    apartment.value = (res.data as ApartmentItemWithDetail) ?? null;
  } catch (e: unknown) {
    const ax = e as { response?: { status?: number }; message?: string };
    error.value = {
      status: ax.response?.status,
      message: ax.message ?? 'Ошибка загрузки',
    };
    apartment.value = null;
  } finally {
    loading.value = false;
  }
}

onMounted(load);
onUnmounted(stopPolling);
watch(apartmentId, load);

const detail = computed(() => apartment.value?.detail ?? null);
const hasDetail = computed(() => !!detail.value);

const images = computed(() => {
  const a = apartment.value;
  if (!a) return [];
  const d = detail.value as ApartmentDetailPayload & { gallery?: string[] };
  const fromUnified = d?.unified_payload as { images?: string[] } | undefined;
  const list = fromUnified?.images ?? (a as Record<string, unknown>).gallery;
  if (Array.isArray(list)) return list.map(String);
  if (typeof list === 'string') return [list];
  return [];
});

/** prices_totals_payload — для блока цен и простого отображения */
const pricesTotals = computed(() => {
  const p = detail.value?.prices_totals_payload;
  if (p == null) return null;
  if (Array.isArray(p)) return p;
  if (typeof p === 'object') return p as Record<string, unknown>;
  return null;
});

const pricesTotalsText = computed(() => {
  const p = pricesTotals.value;
  if (!p) return null;
  if (Array.isArray(p)) return JSON.stringify(p, null, 2);
  return JSON.stringify(p, null, 2);
});

/** prices_graph_payload — для заглушки графика */
const pricesGraph = computed(() => detail.value?.prices_graph_payload);
</script>

<template>
  <PageLayout>
    <div id="flatpage" class="page-layout__content" data-root="flat-detail">
      <div class="flat-page__container">
        <div class="mb-4">
          <RouterLink
            to="/objects/table"
            class="text-sm text-slate-400 hover:text-white"
          >
            ← К списку квартир
          </RouterLink>
        </div>

        <div v-if="loading" class="py-12 text-center text-slate-400">
          Загрузка...
        </div>

        <div
          v-else-if="error && (error.status === 404 || !apartment)"
          class="py-12 text-center text-slate-400"
        >
          <p>Квартира не найдена.</p>
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

        <template v-else-if="apartment">
          <header class="apartment-header mb-6 leftColumn">
            <h1 class="text-2xl font-semibold text-slate-100">
              {{ apartment.title ?? 'Квартира' }}
            </h1>
          </header>

          <div v-if="!hasDetail || refreshState" class="mb-4 rounded-xl border border-amber-800 bg-amber-950/30 p-3 text-amber-200 text-sm">
            <span v-if="refreshState === 'queued'">В очереди…</span>
            <span v-else-if="refreshState === 'loading'">Обновление…</span>
            <span v-else-if="refreshState === 'success'">Готово.</span>
            <span v-else-if="refreshState === 'fail'">Обновление не пришло или ошибка.</span>
            <span v-else-if="!hasDetail">Детали пока не загружены.</span>
            <button
              type="button"
              class="ml-2 rounded border border-amber-600 bg-amber-900/50 px-2 py-1 hover:bg-amber-900/70 disabled:opacity-50"
              :disabled="refreshState === 'queued' || refreshState === 'loading'"
              @click="refresh"
            >
              {{ refreshState === 'queued' || refreshState === 'loading' ? 'Ждём…' : 'Обновить детали' }}
            </button>
          </div>

          <div class="grid gap-6 lg:grid-cols-2">
            <div class="apartment-row apartment-row--actions ctaBlock">
              <ObjectGallery
                class="object-gallery"
                :images="images"
                :alt="String(apartment.title ?? '')"
              />
              <div class="mt-4">
                <FloorplanViewer :unified-payload="detail?.unified_payload ?? undefined" />
              </div>
            </div>

            <div class="apartment-row rightColumn">
              <dl class="attributesTable space-y-2 text-sm">
                <template v-if="apartment.area_total != null">
                  <dt class="text-slate-500">S общая</dt>
                  <dd class="text-slate-200">{{ apartment.area_total }} м²</dd>
                </template>
                <template v-if="apartment.rooms != null">
                  <dt class="text-slate-500">Комнат</dt>
                  <dd class="text-slate-200">{{ apartment.rooms }}</dd>
                </template>
                <template v-if="apartment.price != null">
                  <dt class="text-slate-500">Цена</dt>
                  <dd class="text-slate-200">
                    {{ Number(apartment.price).toLocaleString('ru-RU') }} ₽
                  </dd>
                </template>
                <template v-if="apartment.block_id">
                  <dt class="text-slate-500">Комплекс</dt>
                  <dd class="text-slate-200">
                    <RouterLink
                      :to="`/object/${apartment.block_id}`"
                      class="text-sky-400 hover:underline"
                    >
                      {{ apartment.block_id }}
                    </RouterLink>
                  </dd>
                </template>
              </dl>

              <div
                v-if="hasDetail && (pricesTotals || pricesGraph)"
                class="mt-6 rounded-xl border border-slate-700 bg-slate-800/50 p-4 objectBlock"
              >
                <h3 class="mb-3 text-sm font-medium text-slate-300">
                  Блок цен
                </h3>
                <div
                  v-if="pricesTotalsText"
                  class="mb-3 whitespace-pre-wrap rounded bg-slate-900/50 p-2 text-xs text-slate-300"
                >
                  {{ pricesTotalsText }}
                </div>
                <div
                  v-if="pricesGraph"
                  class="rounded border border-slate-600 bg-slate-900/30 p-4 text-center text-sm text-slate-500"
                >
                  График цен — заглушка (данные есть, визуализация позже).
                </div>
              </div>
            </div>
          </div>
        </template>
      </div>
    </div>
  </PageLayout>
</template>
