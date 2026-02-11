<script setup lang="ts">
defineOptions({ name: 'DataTable' });

defineProps<{
  columns: { key: string; label: string }[];
  rows: Record<string, unknown>[];
}>();
</script>

<template>
  <div class="overflow-x-auto rounded-lg border border-slate-700">
    <table class="table w-full border-collapse text-left text-sm">
      <thead>
        <tr class="border-b border-slate-700 bg-slate-800/80">
          <th
            v-for="col in columns"
            :key="col.key"
            class="table-row px-4 py-3 font-medium text-slate-300"
          >
            {{ col.label }}
          </th>
        </tr>
      </thead>
      <tbody>
        <tr
          v-for="(row, i) in rows"
          :key="i"
          class="table-row border-b border-slate-700/80 hover:bg-slate-800/50"
        >
          <td
            v-for="col in columns"
            :key="col.key"
            class="px-4 py-3 text-slate-200"
          >
            {{ row[col.key] ?? '—' }}
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</template>
