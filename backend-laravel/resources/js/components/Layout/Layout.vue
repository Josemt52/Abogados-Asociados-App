<script setup lang="ts">
import { watch } from 'vue';
import { RouterView, useRoute, useRouter } from 'vue-router';
import { LogOut, Scale } from '@lucide/vue';
import { authAPI } from '@/api';
import { useAuth } from '@/composables/useAuth';

const route = useRoute();
const router = useRouter();
const { user, isAuthenticated, logout } = useAuth();
const appName = import.meta.env.VITE_APP_NAME || 'Abogados Asociados';

const handleLogout = async () => {
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
  <div class="min-h-screen bg-gray-50">
    <header class="border-b border-gray-200 bg-white shadow-sm">
      <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="flex h-16 items-center justify-between">
          <RouterLink to="/main" class="flex min-w-0 items-center space-x-2 sm:space-x-4">
            <Scale class="h-6 w-6 flex-shrink-0 text-blue-700 sm:h-8 sm:w-8" />
            <h1 class="truncate text-base font-semibold text-gray-900 sm:text-xl">
              {{ appName }}
            </h1>
          </RouterLink>

          <div v-if="user" class="flex items-center space-x-2 sm:space-x-4">
            <span class="hidden text-sm text-gray-700 md:block">
              Bienvenido, <span class="font-medium">{{ user.username }}</span>
            </span>

            <button
              type="button"
              class="inline-flex items-center rounded-md border border-transparent bg-red-600 px-3 py-2 text-sm font-medium text-white transition-colors hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2"
              @click="handleLogout"
            >
              <LogOut class="mr-2 h-4 w-4" />
              Salir
            </button>
          </div>
        </div>
      </div>
    </header>

    <main>
      <RouterView />
    </main>
  </div>
</template>
