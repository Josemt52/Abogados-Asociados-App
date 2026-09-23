<script setup lang="ts">
import { computed, watch } from 'vue';
import { RouterView, useRoute, useRouter } from 'vue-router';
import {
  FolderOpen,
  Home,
  LogOut,
  PlusCircle,
  Scale,
  Shield,
  Upload,
  UserRound,
} from '@lucide/vue';
import { authAPI } from '@/api';
import { useAuth } from '@/composables/useAuth';

const route = useRoute();
const router = useRouter();
const { user, isAdmin, isAuthenticated, logout } = useAuth();
const appName = import.meta.env.VITE_APP_NAME || 'Abogados Asociados';

const primaryNavigation = [
  {
    key: 'home',
    label: 'Inicio',
    description: 'Tareas principales',
    icon: Home,
    to: '/main',
  },
  {
    key: 'expedientes',
    label: 'Expedientes',
    description: 'Buscar y consultar',
    icon: FolderOpen,
    to: '/expedientes',
  },
  {
    key: 'new-expediente',
    label: 'Nuevo expediente',
    description: 'Registrar uno nuevo',
    icon: PlusCircle,
    to: '/expedientes?create=true',
  },
  {
    key: 'bulk-upload',
    label: 'Carga masiva',
    description: 'Subir varios documentos',
    icon: Upload,
    to: '/carga-masiva',
  },
];

const navigation = computed(() => [
  ...primaryNavigation,
  ...(isAdmin.value
    ? [
        {
          key: 'admin',
          label: 'Panel de administración',
          description: 'Revisar cargas y ajustes',
          icon: Shield,
          to: '/paneladmin',
        },
      ]
    : []),
]);

const isNavigationActive = (key: string): boolean => {
  if (key === 'home') {
    return route.path === '/main';
  }

  if (key === 'expedientes') {
    return (
      (route.path === '/expedientes' && route.query.create !== 'true') ||
      route.path.startsWith('/expedientes/')
    );
  }

  if (key === 'new-expediente') {
    return route.path === '/expedientes' && route.query.create === 'true';
  }

  if (key === 'bulk-upload') {
    return route.path === '/carga-masiva';
  }

  return route.path === '/paneladmin';
};

const handleLogout = async (): Promise<void> => {
  try {
    await authAPI.logout();
  } catch (error) {
    console.warn('No se pudo invalidar el token en el servidor.', error);
  } finally {
    logout();
    await router.replace('/login');
  }
};

watch(isAuthenticated, (authenticated) => {
  if (!authenticated && route.name !== 'login') {
    void router.replace('/login');
  }
});
</script>

<template>
  <div class="min-h-screen bg-slate-100 text-slate-950">
    <aside
      class="fixed inset-y-0 left-0 z-50 hidden w-72 flex-col border-r border-slate-800 bg-slate-950 text-white shadow-2xl lg:flex"
      aria-label="Navegación principal"
    >
      <RouterLink
        to="/main"
        class="flex min-h-24 items-center gap-3 border-b border-slate-800 px-6 transition-colors hover:bg-slate-900 focus:outline-none focus-visible:ring-4 focus-visible:ring-inset focus-visible:ring-sky-300"
        :aria-label="`Ir al inicio de ${appName}`"
      >
        <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-sky-400 to-blue-600 shadow-lg shadow-blue-950/40">
          <Scale class="h-7 w-7" aria-hidden="true" />
        </span>
        <span class="min-w-0">
          <span class="block truncate text-base font-extrabold tracking-tight">{{ appName }}</span>
          <span class="mt-0.5 block text-xs font-medium text-sky-200">Gestión jurídica</span>
        </span>
      </RouterLink>

      <div class="px-4 pt-7">
        <p class="px-3 text-xs font-bold uppercase tracking-[0.16em] text-slate-400">Menú principal</p>
        <p class="mt-2 px-3 text-sm leading-5 text-slate-300">Elija la tarea que desea realizar.</p>
      </div>

      <nav class="mt-4 space-y-2 px-4" aria-label="Tareas del sistema">
        <RouterLink
          v-for="item in navigation"
          :key="item.key"
          :to="item.to"
          :aria-current="isNavigationActive(item.key) ? 'page' : undefined"
          :class="[
            'group flex min-h-16 items-center gap-3 rounded-2xl px-3 py-3 text-left transition-all focus:outline-none focus-visible:ring-4 focus-visible:ring-sky-300',
            isNavigationActive(item.key)
              ? 'bg-sky-500 text-slate-950 shadow-lg shadow-sky-950/30'
              : item.key === 'new-expediente'
                ? 'bg-amber-400 text-slate-950 shadow-lg shadow-amber-950/20 hover:bg-amber-300'
                : item.key === 'admin'
                  ? 'bg-violet-500/20 text-violet-100 hover:bg-violet-500/30'
                  : 'text-slate-100 hover:bg-slate-800',
          ]"
        >
          <span
            :class="[
              'flex h-10 w-10 shrink-0 items-center justify-center rounded-xl transition-transform group-hover:scale-105',
              isNavigationActive(item.key)
                ? 'bg-white/60'
                : item.key === 'new-expediente'
                  ? 'bg-amber-50/80'
                  : item.key === 'admin'
                    ? 'bg-violet-400/25'
                    : 'bg-slate-800',
            ]"
          >
            <component :is="item.icon" class="h-5 w-5" aria-hidden="true" />
          </span>
          <span class="min-w-0">
            <span class="block text-sm font-bold leading-5">{{ item.label }}</span>
            <span
              :class="[
                'block truncate text-xs leading-4',
                isNavigationActive(item.key) || item.key === 'new-expediente'
                  ? 'text-slate-800/75'
                  : 'text-slate-400',
              ]"
            >
              {{ item.description }}
            </span>
          </span>
        </RouterLink>
      </nav>

      <div class="mt-auto border-t border-slate-800 p-4">
        <div class="mb-3 flex items-center gap-3 rounded-2xl bg-slate-900 px-3 py-3">
          <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-slate-700 text-sky-200">
            <UserRound class="h-5 w-5" aria-hidden="true" />
          </span>
          <span class="min-w-0">
            <span class="block truncate text-sm font-bold">{{ user?.username }}</span>
            <span class="block truncate text-xs text-slate-400">Sesión activa</span>
          </span>
        </div>
        <button
          type="button"
          class="inline-flex min-h-12 w-full items-center justify-center rounded-xl border border-rose-400/40 bg-rose-500/10 px-4 py-3 text-sm font-bold text-rose-100 transition-colors hover:bg-rose-500 hover:text-white focus:outline-none focus-visible:ring-4 focus-visible:ring-rose-300"
          @click="handleLogout"
        >
          <LogOut class="mr-2 h-5 w-5" aria-hidden="true" />
          Cerrar sesión
        </button>
      </div>
    </aside>

    <div class="min-h-screen lg:pl-72">
      <header class="sticky top-0 z-40 border-b border-slate-200 bg-white/95 shadow-sm backdrop-blur">
        <div class="flex min-h-20 items-center justify-between gap-4 px-4 sm:px-6 lg:px-8">
          <RouterLink
            to="/main"
            class="flex min-w-0 items-center gap-3 rounded-xl"
            :aria-label="`Ir al inicio de ${appName}`"
          >
            <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-sky-500 to-blue-700 text-white shadow-md lg:hidden">
              <Scale class="h-6 w-6" aria-hidden="true" />
            </span>
            <span class="min-w-0">
              <span class="block truncate text-sm font-extrabold text-slate-950 sm:text-base">Sistema de gestión jurídica</span>
              <span class="block truncate text-xs font-medium text-slate-500">Accesos claros para el trabajo diario</span>
            </span>
          </RouterLink>

          <div class="flex shrink-0 items-center gap-3">
            <RouterLink
              to="/expedientes?create=true"
              class="hidden min-h-12 items-center rounded-xl bg-amber-400 px-4 py-3 text-sm font-extrabold text-slate-950 shadow-md shadow-amber-300/50 transition-colors hover:bg-amber-300 focus:outline-none focus-visible:ring-4 focus-visible:ring-amber-300 sm:inline-flex"
            >
              <PlusCircle class="mr-2 h-5 w-5" aria-hidden="true" />
              Nuevo expediente
            </RouterLink>
            <div class="hidden text-right md:block">
              <p class="text-xs font-medium text-slate-500">Sesión iniciada como</p>
              <p class="max-w-40 truncate text-sm font-bold text-slate-900">{{ user?.username }}</p>
            </div>
            <button
              type="button"
              class="inline-flex min-h-12 items-center rounded-xl border border-rose-200 bg-white px-3 py-3 text-sm font-bold text-rose-700 transition-colors hover:border-rose-300 hover:bg-rose-50 focus:outline-none focus-visible:ring-4 focus-visible:ring-rose-200 lg:hidden"
              @click="handleLogout"
            >
              <LogOut class="h-5 w-5 sm:mr-2" aria-hidden="true" />
              <span class="hidden sm:inline">Salir</span>
              <span class="sr-only sm:hidden">Cerrar sesión</span>
            </button>
          </div>
        </div>

        <nav class="border-t border-slate-100 bg-slate-50 px-3 py-2 lg:hidden" aria-label="Navegación principal">
          <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
            <RouterLink
              v-for="item in navigation"
              :key="item.key"
              :to="item.to"
              :aria-current="isNavigationActive(item.key) ? 'page' : undefined"
              :class="[
                'flex min-h-12 items-center justify-center gap-2 rounded-xl px-2 py-2 text-center text-sm font-bold transition-colors focus:outline-none focus-visible:ring-4 focus-visible:ring-sky-300',
                isNavigationActive(item.key)
                  ? 'bg-sky-600 text-white'
                  : item.key === 'new-expediente'
                    ? 'bg-amber-400 text-slate-950 hover:bg-amber-300'
                    : item.key === 'admin'
                      ? 'bg-violet-100 text-violet-900 hover:bg-violet-200'
                      : 'bg-white text-slate-700 hover:bg-slate-100',
              ]"
            >
              <component :is="item.icon" class="h-4 w-4 shrink-0" aria-hidden="true" />
              <span class="truncate">{{ item.label }}</span>
            </RouterLink>
          </div>
        </nav>
      </header>

      <main class="min-w-0">
        <RouterView />
      </main>
    </div>
  </div>
</template>
