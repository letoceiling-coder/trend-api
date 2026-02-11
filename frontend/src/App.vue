<script setup lang="ts">
import { RouterLink, RouterView } from 'vue-router';
</script>

<template>
  <div class="min-h-screen bg-slate-950 text-slate-50">
    <header
      class="border-b border-slate-800 bg-slate-900/80 backdrop-blur"
    >
      <div
        class="mx-auto flex max-w-5xl items-center justify-between px-4 py-3"
      >
        <h1 class="text-lg font-semibold tracking-tight">
          TrendMirror UI
        </h1>
        <nav class="flex items-center gap-4 text-sm font-medium text-slate-300">
          <RouterLink
            to="/"
            class="hover:text-white"
            active-class="text-white"
          >
            Home
          </RouterLink>
          <RouterLink
            to="/objects/list"
            class="hover:text-white"
            active-class="text-white"
          >
            Объекты
          </RouterLink>
          <RouterLink
            to="/health"
            class="hover:text-white"
            active-class="text-white"
          >
            HealthCheck
          </RouterLink>
        </nav>
      </div>
    </header>

    <main class="mx-auto max-w-5xl px-4 py-8">
      <RouterView />
    </main>
  </div>
</template>

