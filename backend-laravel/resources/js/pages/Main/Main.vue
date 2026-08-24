<script setup lang="ts">
import { computed, unref } from 'vue';
import { RouterLink, useRouter } from 'vue-router';
import { FileText, Plus, Search, List, LogOut } from '@lucide/vue';
import { useAuth } from '@/composables/useAuth';
import { authAPI } from '@/api';

const router = useRouter();
const auth = useAuth();
const user = computed(() => unref(auth.user));

const menuItems = [
  {
    title: 'Ver Expediente',
    description: 'Consulta un expediente existente',
    icon: Search,
    href: '/expedientes',
    color: 'bg-blue-600 hover:bg-blue-700',
  },
  {
    title: 'Crear Expediente',
    description: 'Crea un nuevo expediente',
    icon: Plus,
    href: '/expedientes?create=true',
    color: 'bg-green-600 hover:bg-green-700',
  },
  {
    title: 'Actualizar Expediente',
    description: 'Modifica un expediente existente',
    icon: FileText,
    href: '/expedientes?update=true',
    color: 'bg-yellow-600 hover:bg-yellow-700',
  },
  {
    title: 'Listar Expedientes',
    description: 'Ver todos los expedientes terminados',
    icon: List,
    href: '/expedientes?filter=finished',
    color: 'bg-purple-600 hover:bg-purple-700',
  },
];

const handleLogout = async () => {
  try {
    await authAPI.logout();
  } catch (error) {
    console.warn('No se pudo invalidar el token en el servidor.', error);
  } finally {
    auth.logout();
    await router.replace('/login');
  }
};
</script>

<template>
  <div class="min-h-screen bg-gradient-to-br from-blue-50 to-indigo-100 p-4 sm:p-8">
    <div class="mx-auto max-w-4xl">
      <!-- Header -->
      <div class="mb-8 text-center">
        <h1 class="mb-2 text-3xl font-bold text-gray-900 sm:text-4xl">
          Sistema de Gestión Jurídica
        </h1>
        <p class="text-lg text-gray-600">
          Bienvenido, <span class="font-semibold">{{ user?.username }}</span>
        </p>
      </div>

      <!-- Menu Buttons -->
      <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <RouterLink
          v-for="item in menuItems"
          :key="item.title"
          :to="item.href"
          class="group flex items-center rounded-xl border-2 border-gray-200 bg-white p-6 shadow-lg transition-all hover:border-blue-300 hover:shadow-xl sm:p-8"
        >
          <div
            :class="[
              'mr-4 flex h-16 w-16 items-center justify-center rounded-full text-white transition-transform group-hover:scale-110 sm:mr-6 sm:h-20 sm:w-20',
              item.color,
            ]"
          >
            <component :is="item.icon" class="h-8 w-8 sm:h-10 sm:w-10" />
          </div>
          <div>
            <h2 class="mb-1 text-xl font-bold text-gray-900 sm:text-2xl">
              {{ item.title }}
            </h2>
            <p class="text-gray-600">{{ item.description }}</p>
          </div>
        </RouterLink>
      </div>

      <!-- Logout Button -->
      <div class="mt-8 text-center">
        <button
          type="button"
          class="inline-flex items-center rounded-lg border-2 border-red-200 bg-white px-8 py-4 text-lg font-semibold text-red-600 shadow-md transition-all hover:border-red-300 hover:bg-red-50 hover:shadow-lg"
          @click="handleLogout"
        >
          <LogOut class="mr-3 h-6 w-6" />
          Cerrar Sesión
        </button>
      </div>

      <!-- Footer -->
      <div class="mt-8 text-center text-sm text-gray-500">
        Sistema de Gestión Jurídica • v2.0
      </div>
    </div>
  </div>
</template>
