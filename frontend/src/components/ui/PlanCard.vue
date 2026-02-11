<script setup lang="ts">
defineProps<{
  id: string;
  title?: string;
  imageUrl?: string;
  area?: string;
  rooms?: string;
  price?: string;
}>();
</script>

<template>
  <div
    class="plancard-item rounded-xl border border-slate-700 bg-slate-800/50 overflow-hidden transition hover:border-slate-600"
  >
    <div
      v-if="imageUrl"
      class="aspect-[4/3] w-full overflow-hidden bg-slate-700"
    >
      <img
        :src="imageUrl"
        :alt="title ?? 'Планировка'"
        class="h-full w-full object-cover"
      />
    </div>
    <div class="p-3">
      <div v-if="title" class="font-medium text-slate-200">{{ title }}</div>
      <div class="mt-1 flex flex-wrap gap-2 text-sm text-slate-400">
        <span v-if="area">{{ area }} м²</span>
        <span v-if="rooms">{{ rooms }} комн.</span>
        <span v-if="price">{{ price }}</span>
      </div>
    </div>
  </div>
</template>
