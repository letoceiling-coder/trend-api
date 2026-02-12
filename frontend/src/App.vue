<script setup lang="ts">
import { computed } from 'vue';
import { RouterLink, RouterView } from 'vue-router';

const showAdminLink = computed(() => {
  return import.meta.env.DEV || !!import.meta.env.VITE_TA_ADMIN_KEY;
});
</script>

<template>
  <div class="min-h-screen bg-ta-surface text-ta-text">
    <header
      class="border-b border-ta-border bg-white shadow-sm"
    >
      <div
        class="mx-auto flex max-w-ta-container items-center justify-between px-4 py-3"
      >
        <h1 class="text-ta-h1 font-semibold tracking-tight text-ta-text">
          TrendAgent
        </h1>
        <nav class="flex items-center gap-6 text-sm font-medium text-ta-text-muted">
          <RouterLink
            to="/"
            class="hover:text-ta-text transition-colors"
            active-class="text-ta-text font-medium"
          >
            Главная
          </RouterLink>
          <RouterLink
            to="/objects/list"
            class="hover:text-ta-text transition-colors"
            active-class="text-ta-text font-medium"
          >
            Комплексы
          </RouterLink>
          <RouterLink
            to="/objects/table"
            class="hover:text-ta-text transition-colors"
            active-class="text-ta-text font-medium"
          >
            Квартиры
          </RouterLink>
          <RouterLink
            to="/objects/map"
            class="hover:text-ta-text transition-colors"
            active-class="text-ta-text font-medium"
          >
            Карта
          </RouterLink>
          <RouterLink
            to="/objects/plans"
            class="hover:text-ta-text transition-colors"
            active-class="text-ta-text font-medium"
          >
            Планировки
          </RouterLink>
          <RouterLink
            to="/health"
            class="hover:text-ta-text transition-colors"
            active-class="text-ta-text font-medium"
          >
            HealthCheck
          </RouterLink>
          <RouterLink
            v-if="showAdminLink"
            to="/admin/ta"
            class="hover:text-ta-text transition-colors"
            active-class="text-ta-text font-medium"
          >
            Админка TA
          </RouterLink>
        </nav>
      </div>
    </header>

    <main class="mx-auto max-w-ta-container px-4 py-8">
      <RouterView />
    </main>
  </div>
</template>

