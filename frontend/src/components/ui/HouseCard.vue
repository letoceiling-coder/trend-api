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
    class="house-card house-card_horizontal objects-list__item block rounded-xl border border-ta-border bg-white p-4 shadow-sm transition hover:shadow-md hover:border-ta-text-muted/30"
  >
    <figure class="house-card__figure flex gap-4">
      <div
        class="image-container image-container_horizontal image-container_mini h-28 w-40 shrink-0 overflow-hidden rounded-lg bg-ta-border"
      >
        <img
          v-if="imageUrl"
          :src="imageUrl"
          :alt="title"
          class="card-image card-image_horizontal h-full w-full object-cover"
          loading="lazy"
        />
        <div
          v-else
          class="flex h-full w-full items-center justify-center text-ta-text-muted text-xs"
        >
          Нет фото
        </div>
      </div>
      <figcaption class="apartment-horizontal-caption min-w-0 flex-1">
        <div class="apartment-horizontal-caption__header flex items-center gap-2">
          <h2 class="text-ta-h2 font-semibold text-ta-text" :title="title">
            {{ title }}
          </h2>
          <span
            v-if="deadline"
            class="text-block text-block_bold rounded bg-ta-border px-2 py-0.5 text-xs text-ta-text-muted"
          >
            {{ deadline }}
          </span>
        </div>
        <div
          v-if="address"
          class="address mt-1 flex items-center gap-1 text-sm text-ta-text-muted"
        >
          <span class="address__text truncate">{{ address }}</span>
        </div>
        <div
          v-if="price || rooms"
          class="mt-2 flex flex-wrap gap-4 text-sm text-ta-text-muted"
        >
          <span v-if="price">{{ price }}</span>
          <span v-if="rooms">Квартир {{ rooms }}</span>
        </div>
      </figcaption>
    </figure>
  </RouterLink>
</template>
