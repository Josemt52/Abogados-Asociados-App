<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { RouterLink, RouterView, useRoute, useRouter } from 'vue-router';
import { FileText, Home, LogOut, Menu, Scale, Users, X } from '@lucide/vue';
import { authAPI } from '@/api';
import { useAuth } from '@/composables/useAuth';

const route = useRoute();
const router = useRouter();
const { user, isAuthenticated, isAdmin, logout } = useAuth();
const isMobileMenuOpen = ref(false);
const appName = import.meta.env.VITE_APP_NAME || 'Abogados Asociados';

const navigation = computed(() => [
  { name: 'Panel Principal', href: '/main', icon: Home, adminOnly: false },
  { name: 'Expedientes', href: '/expedientes', icon: FileText, adminOnly: false },
  { name: 'Usuarios', href: '/usuarios', icon: Users, adminOnly: true },
  { name: 'Registrar Usuario', href: '/usuarios/registrar', icon: Users, adminOnly: true },
].filter((item) => !item.adminOnly || isAdmin.value));

const isActiveRoute = (href: string) => (
  route.path === href || route.path.startsWith(`${href}/`)
);

const handleLogout = async () => {
  try {
    await authAPI.logout();
  } catch (error) {
    console.warn('No se pudo invalidar el token en el servidor.', error);
  } finally {
    logout();
    isMobileMenuOpen.value = false;
    await router.replace('/login');
  }
};

watch(
  () => route.path,
  () => {
    isMobileMenuOpen.value = false;
  },
);

watch(isAuthenticated, (authenticated) => {
  if (!authenticated && route.name !== 'login') {
    void router.replace('/login');
  }
});
</script>

<template>
  <div class="min-h-screen bg-gray-50">
    <header class="border-b border-gray-200 bg-white shadow-sm">
      <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="flex h-16 items-center justify-between">
          <div class="flex min-w-0 items-center space-x-2 sm:space-x-4">
            <Scale class="h-6 w-6 flex-shrink-0 text-blue-700 sm:h-8 sm:w-8" />
            <h1 class="truncate text-base font-semibold text-gray-900 sm:text-xl">
              {{ appName }}
            </h1>
          </div>

          <div v-if="user" class="flex items-center space-x-2 sm:space-x-4">
            <span class="hidden text-sm text-gray-700 md:block">
              Bienvenido, <span class="font-medium">{{ user.username }}</span>
            </span>

            <button
              type="button"
              class="inline-flex items-center rounded-md border border-transparent bg-blue-700 px-2 py-1.5 text-xs font-medium leading-4 text-white transition-colors hover:bg-blue-800 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 sm:px-3 sm:py-2 sm:text-sm"
              @click="handleLogout"
            >
              <LogOut class="h-3 w-3 sm:mr-2 sm:h-4 sm:w-4" />
              <span class="hidden sm:inline">Salir</span>
            </button>

            <button
              type="button"
              class="rounded-md p-2 text-gray-600 hover:bg-gray-100 md:hidden"
              :aria-expanded="isMobileMenuOpen"
              aria-controls="mobile-navigation"
              aria-label="Abrir menú de navegación"
              @click="isMobileMenuOpen = !isMobileMenuOpen"
            >
              <X v-if="isMobileMenuOpen" class="h-6 w-6" />
              <Menu v-else class="h-6 w-6" />
            </button>
          </div>
        </div>
      </div>

      <div
        v-if="user && isMobileMenuOpen"
        id="mobile-navigation"
        class="border-t border-gray-200 bg-white md:hidden"
      >
        <nav class="space-y-1 px-2 pb-3 pt-2" aria-label="Navegación móvil">
          <RouterLink
            v-for="item in navigation"
            :key="item.name"
            :to="item.href"
            class="flex w-full items-center rounded-md px-3 py-2 text-base font-medium transition-colors"
            :class="isActiveRoute(item.href)
              ? 'border-l-4 border-blue-700 bg-blue-100 text-blue-700'
              : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900'"
          >
            <component :is="item.icon" class="mr-3 h-5 w-5" />
            {{ item.name }}
          </RouterLink>
        </nav>
      </div>
    </header>

    <div class="flex">
      <nav
        v-if="user"
        class="hidden min-h-screen w-64 border-r border-gray-200 bg-white shadow-sm md:block"
        aria-label="Navegación principal"
      >
        <div class="space-y-2 p-4">
          <RouterLink
            v-for="item in navigation"
            :key="item.name"
            :to="item.href"
            class="flex w-full items-center rounded-md px-4 py-2 text-sm font-medium transition-colors"
            :class="isActiveRoute(item.href)
              ? 'border-r-2 border-blue-700 bg-blue-100 text-blue-700'
              : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900'"
          >
            <component :is="item.icon" class="mr-3 h-5 w-5" />
            {{ item.name }}
          </RouterLink>
        </div>
      </nav>

      <main class="min-w-0 flex-1">
        <div class="p-4 sm:p-6">
          <RouterView />
        </div>
      </main>
    </div>
  </div>
</template>
