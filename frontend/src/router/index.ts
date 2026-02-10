import { createRouter, createWebHistory } from 'vue-router';
import HomeView from '../views/HomeView.vue';
import HealthCheckView from '../views/HealthCheckView.vue';

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    {
      path: '/',
      name: 'home',
      component: HomeView,
    },
    {
      path: '/health',
      name: 'health',
      component: HealthCheckView,
    },
  ],
});

export default router;

