import { createRouter, createWebHistory, type RouteRecordRaw } from 'vue-router';
import { useAuth } from '@/composables/useAuth';

declare module 'vue-router' {
  interface RouteMeta {
    requiresAuth?: boolean;
    requiresAdmin?: boolean;
  }
}

const routes: RouteRecordRaw[] = [
  {
    path: '/login',
    name: 'login',
    component: () => import('@/pages/Login/Login.vue'),
  },
  {
    path: '/',
    component: () => import('@/components/Layout/Layout.vue'),
    meta: { requiresAuth: true },
    children: [
      {
        path: '',
        redirect: '/main',
      },
      {
        path: 'main',
        name: 'main',
        component: () => import('@/pages/Main/Main.vue'),
      },
      {
        path: 'expedientes',
        name: 'expedientes',
        component: () => import('@/pages/Expedientes/Expedientes.vue'),
      },
      {
        path: 'expedientes/:id',
        name: 'expediente-detail',
        component: () => import('@/pages/ExpedienteDetail/ExpedienteDetail.vue'),
        props: true,
      },
      {
        path: 'usuarios',
        name: 'usuarios',
        component: () => import('@/pages/Usuarios/Usuarios.vue'),
        meta: { requiresAdmin: true },
      },
      {
        path: 'usuarios/registrar',
        name: 'registrar-usuario',
        component: () => import('@/pages/RegistrarUsuario/RegistrarUsuario.vue'),
        meta: { requiresAdmin: true },
      },
    ],
  },
  {
    path: '/:pathMatch(.*)*',
    redirect: '/main',
  },
];

const router = createRouter({
  history: createWebHistory(),
  routes,
  scrollBehavior: () => ({ top: 0 }),
});

router.beforeEach((to) => {
  const { isAuthenticated, isAdmin } = useAuth();

  if (to.name === 'login' && isAuthenticated.value) {
    return { name: 'main', replace: true };
  }

  if (to.matched.some((record) => record.meta.requiresAuth) && !isAuthenticated.value) {
    return {
      name: 'login',
      query: { redirect: to.fullPath },
      replace: true,
    };
  }

  if (to.matched.some((record) => record.meta.requiresAdmin) && !isAdmin.value) {
    return { name: 'main', replace: true };
  }

  return true;
});

export default router;

