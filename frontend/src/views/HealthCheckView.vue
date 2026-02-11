<script setup lang="ts">
import { ref } from 'vue';

type HealthResponse = {
  ok: boolean;
  time?: string;
  [key: string]: unknown;
};

const loading = ref(false);
const error = ref<string | null>(null);
const result = ref<HealthResponse | null>(null);

const apiBase =
  import.meta.env.VITE_API_BASE && import.meta.env.VITE_API_BASE.length > 0
    ? import.meta.env.VITE_API_BASE
    : 'http://localhost:8000';

async function checkHealth() {
  loading.value = true;
  error.value = null;
  result.value = null;

  try {
    const response = await fetch(`${apiBase}/api/health`, {
      headers: {
        Accept: 'application/json',
      },
    });

    const data: unknown = await response.json().catch(() => null);

    if (!response.ok) {
      throw new Error(
        typeof data === 'string'
          ? data
          : 'Request failed with status ' + response.status,
      );
    }

    result.value = (data || {}) as HealthResponse;
  } catch (e) {
    const message =
      e instanceof Error ? e.message : typeof e === 'string' ? e : 'Unknown error';
    error.value = message;
  } finally {
    loading.value = false;
  }
}
</script>

<template>
  <section class="space-y-6">
    <header class="space-y-2">
      <h1 class="text-2xl font-semibold tracking-tight text-slate-50">
        HealthCheck
      </h1>
      <p class="text-sm text-slate-300">
        Проверка доступности backend Laravel API по адресу
        <code class="rounded bg-slate-900 px-1.5 py-0.5 text-xs text-slate-100">
          {{ apiBase }}/api/health
        </code>
      </p>
    </header>

    <div class="flex items-center gap-3">
      <button
        type="button"
        class="inline-flex items-center rounded-md bg-emerald-500 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-emerald-600 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-400 focus-visible:ring-offset-2 focus-visible:ring-offset-slate-950 disabled:cursor-not-allowed disabled:opacity-60"
        :disabled="loading"
        @click="checkHealth"
      >
        <span v-if="!loading">Check API</span>
        <span v-else>Checking…</span>
      </button>

      <p v-if="loading" class="text-sm text-slate-400">
        Отправляем запрос к backend…
      </p>
    </div>

    <div class="space-y-3 rounded-lg border border-slate-800 bg-slate-950/60 p-4">
      <p class="text-xs font-mono text-slate-400">
        Result
      </p>

      <p v-if="error" class="rounded bg-red-900/40 px-3 py-2 text-sm text-red-100">
        {{ error }}
      </p>

      <p
        v-else-if="!result"
        class="text-sm text-slate-400"
      >
        Нажмите «Check API», чтобы выполнить запрос.
      </p>

      <pre
        v-else
        class="max-h-64 overflow-auto rounded bg-slate-900 px-3 py-2 text-xs text-slate-100"
      >{{ JSON.stringify(result, null, 2) }}</pre>
    </div>
  </section>
</template>

