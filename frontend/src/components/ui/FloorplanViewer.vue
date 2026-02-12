<script setup lang="ts">
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import { getImageCandidatesFromUnified } from '../../utils/unifiedPayloadImages';

const props = defineProps<{
  unifiedPayload?: Record<string, unknown> | null;
}>();

const mapContainer = ref<HTMLElement | null>(null);
let map: L.Map | null = null;
let overlay: L.ImageOverlay | null = null;

const imageResult = computed(() =>
  getImageCandidatesFromUnified(props.unifiedPayload ?? null)
);
const imageUrls = computed(() => imageResult.value.urls);
const hasImages = computed(() => imageUrls.value.length > 0);
const selectedIndex = ref(0);
const currentImageUrl = computed(
  () => imageUrls.value[selectedIndex.value] ?? null
);

function initMap() {
  if (!mapContainer.value || !currentImageUrl.value) return;
  map = L.map(mapContainer.value, {
    zoomControl: false,
    attributionControl: false,
  });
  L.control.zoom({ position: 'topright' }).addTo(map);
  const bounds: L.LatLngBoundsExpression = [
    [0, 0],
    [1, 1],
  ];
  overlay = L.imageOverlay(currentImageUrl.value, bounds).addTo(map);
  map.fitBounds(bounds);
}

function setOverlayUrl(url: string) {
  if (!map || !overlay) return;
  overlay.setUrl(url);
  const bounds: L.LatLngBoundsExpression = [
    [0, 0],
    [1, 1],
  ];
  map.fitBounds(bounds);
}

function destroyMap() {
  overlay?.remove();
  overlay = null;
  map?.remove();
  map = null;
}

onMounted(() => {
  if (currentImageUrl.value) setTimeout(initMap, 0);
});

onUnmounted(destroyMap);

watch(
  currentImageUrl,
  (url) => {
    if (url && overlay) setOverlayUrl(url);
    else if (!url && map) destroyMap();
    else if (url && !map && mapContainer.value) setTimeout(initMap, 0);
  },
  { immediate: false }
);

watch(
  hasImages,
  (has) => {
    if (has && !map && mapContainer.value && currentImageUrl.value)
      setTimeout(initMap, 0);
    if (!has) destroyMap();
  },
  { immediate: true }
);

function selectIndex(i: number) {
  selectedIndex.value = i;
}
</script>

<template>
  <div class="floorplan__container rounded-xl border border-slate-700 bg-slate-800/50 overflow-hidden">
    <div class="floorplan__map-wrapper relative min-h-[300px]">
      <div
        ref="mapContainer"
        class="floorplan__map leaflet-container h-full min-h-[300px] w-full"
      />
      <div
        v-if="!hasImages"
        class="absolute inset-0 flex items-center justify-center text-slate-500"
      >
        Нет изображений планировки
      </div>
    </div>
    <div
      v-if="import.meta.env.DEV && imageResult.candidatePaths.length"
      class="border-t border-slate-700 bg-slate-800/30 px-2 py-1 text-xs text-slate-500"
    >
      Отладка: пути с URL — {{ imageResult.candidatePaths.join(', ') }}
    </div>
    <div
      v-if="hasImages"
      class="gallery-nav__menu flex flex-wrap gap-2 border-t border-slate-700 bg-slate-900/50 p-2"
      role="menu"
      aria-label="Gallery navigation"
    >
      <button
        v-for="(url, i) in imageUrls"
        :key="i"
        type="button"
        role="menuitem"
        class="gallery-nav__item h-14 w-14 shrink-0 overflow-hidden rounded border-2 transition"
        :class="selectedIndex === i ? 'border-sky-500' : 'border-transparent hover:border-slate-500'"
        :data-index="i"
        @click="selectIndex(i)"
      >
        <img
          :src="url"
          :alt="`Планировка ${i + 1}`"
          class="h-full w-full object-cover"
        />
      </button>
    </div>
  </div>
</template>
