<script setup lang="ts">
import { RouterLink } from 'vue-router';

defineProps<{
  id: string;
  title: string;
  deadline?: string;
  address?: string;
  imageUrl?: string;
  price?: string;
  rooms?: string;
}>();
</script>

<template>
  <RouterLink
    :to="`/object/${id}`"
    class="house-card house-card_horizontal objects-list__item block rounded-xl border border-slate-700 bg-slate-800/50 p-4 transition hover:border-slate-600 hover:bg-slate-800"
  >
    <figure class="house-card__figure flex gap-4">
      <div
        v-if="imageUrl"
        class="image-container image-container_horizontal image-container_mini h-24 w-32 shrink-0 overflow-hidden rounded-lg bg-slate-700"
      >
        <img
          :src="imageUrl"
          :alt="title"
          class="card-image card-image_horizontal h-full w-full object-cover"
        />
      </div>
      <figcaption class="apartment-horizontal-caption min-w-0 flex-1">
        <div class="apartment-horizontal-caption__header flex items-center gap-2">
          <h2 class="text-lg font-semibold text-slate-100" :title="title">
            {{ title }}
          </h2>
          <span
            v-if="deadline"
            class="text-block text-block_bold rounded bg-slate-600 px-2 py-0.5 text-xs text-slate-200"
          >
            {{ deadline }}
          </span>
        </div>
        <div
          v-if="address"
          class="address mt-1 flex items-center gap-1 text-sm text-slate-400"
        >
          <span class="address__text truncate">{{ address }}</span>
        </div>
        <div
          v-if="price || rooms"
          class="mt-2 flex flex-wrap gap-4 text-sm text-slate-400"
        >
          <span v-if="price">{{ price }}</span>
          <span v-if="rooms">Квартир {{ rooms }}</span>
        </div>
      </figcaption>
    </figure>
  </RouterLink>
</template>
