<script setup lang="ts">
defineOptions({ name: 'ObjectGallery' });

defineProps<{
  images?: string[];
  alt?: string;
}>();
</script>

<template>
  <div class="object-gallery overflow-hidden rounded-xl border border-slate-700 bg-slate-800/50">
    <div
      v-if="images?.length"
      class="grid gap-2 p-2 sm:grid-cols-2 lg:grid-cols-3"
    >
      <img
        v-for="(src, i) in images"
        :key="i"
        :src="src"
        :alt="alt ? `${alt} ${i + 1}` : 'Фото'"
        class="h-40 w-full rounded-lg object-cover"
      />
    </div>
    <div
      v-else
      class="flex h-48 items-center justify-center text-slate-500"
    >
      Нет изображений
    </div>
  </div>
</template>
