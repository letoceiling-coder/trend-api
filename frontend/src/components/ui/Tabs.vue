<script setup lang="ts">
defineOptions({ name: 'Tabs' });

const model = defineModel<string>({ required: true });
defineProps<{
  tabs: { id: string; label: string }[];
}>();
</script>

<template>
  <div class="tabs flex gap-1 border-b border-slate-700">
    <button
      v-for="tab in tabs"
      :key="tab.id"
      type="button"
      class="px-4 py-3 text-sm font-medium transition"
      :class="
        model === tab.id
          ? 'border-b-2 border-slate-400 text-white'
          : 'text-slate-400 hover:text-slate-200'
      "
      @click="model = tab.id"
    >
      {{ tab.label }}
    </button>
  </div>
</template>
