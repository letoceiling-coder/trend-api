<script setup lang="ts">
defineOptions({ name: 'Pagination' });

defineProps<{
  page: number;
  totalPages: number;
  total?: number;
}>();

const emit = defineEmits<{ (e: 'update:page', value: number): void }>();
</script>

<template>
  <nav
    v-if="totalPages > 1"
    class="objects-pagination flex items-center justify-center gap-2 py-6"
    aria-label="Пагинация"
  >
    <button
      type="button"
      class="rounded border border-slate-600 px-3 py-1.5 text-sm disabled:opacity-50"
      :disabled="page <= 1"
      @click="emit('update:page', page - 1)"
    >
      Назад
    </button>
    <span class="text-sm text-slate-400">
      {{ page }} / {{ totalPages }}
      <span v-if="total != null"> (всего {{ total }})</span>
    </span>
    <button
      type="button"
      class="rounded border border-slate-600 px-3 py-1.5 text-sm disabled:opacity-50"
      :disabled="page >= totalPages"
      @click="emit('update:page', page + 1)"
    >
      Вперёд
    </button>
  </nav>
</template>
