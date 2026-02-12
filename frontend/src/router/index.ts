import { createRouter, createWebHistory } from 'vue-router';
import HomeView from '../views/HomeView.vue';
import HealthCheckView from '../views/HealthCheckView.vue';
import ObjectsList from '../pages/ObjectsList.vue';
import ObjectsTable from '../pages/ObjectsTable.vue';
import ObjectsMap from '../pages/ObjectsMap.vue';
import ObjectsPlans from '../pages/ObjectsPlans.vue';
import Checkerboard from '../pages/Checkerboard.vue';
import ObjectDetail from '../pages/ObjectDetail.vue';
import FlatDetail from '../pages/FlatDetail.vue';
import TaAdmin from '../pages/TaAdmin.vue';

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    { path: '/', name: 'home', component: HomeView },
    { path: '/health', name: 'health', component: HealthCheckView },
    { path: '/objects/list', name: 'objects-list', component: ObjectsList },
    { path: '/objects/table', name: 'objects-table', component: ObjectsTable },
    { path: '/objects/map', name: 'objects-map', component: ObjectsMap },
    { path: '/objects/plans', name: 'objects-plans', component: ObjectsPlans },
    {
      path: '/object/:blockId/checkerboard',
      name: 'checkerboard',
      component: Checkerboard,
    },
    { path: '/object/:blockId', name: 'object-detail', component: ObjectDetail },
    {
      path: '/flat/:apartmentId',
      name: 'flat-detail',
      component: FlatDetail,
    },
    { path: '/admin/ta', name: 'ta-admin', component: TaAdmin },
  ],
});

export default router;

