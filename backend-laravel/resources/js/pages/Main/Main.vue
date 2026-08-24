<script setup lang="ts">
import { computed, unref } from 'vue';
import { RouterLink } from 'vue-router';
import { Search } from '@lucide/vue';
import { useAuth } from '@/composables/useAuth';

const auth = useAuth();
const user = computed(() => unref(auth.user));

const menuItems = [
  {
    title: 'Ver expedientes',
    description: 'Busca, consulta y administra los expedientes registrados',
    icon: Search,
    href: '/expedientes',
  },
];
</script>

<template>
  <div class="min-h-screen bg-gray-50 p-4 sm:p-8">
    <div class="mx-auto max-w-3xl">
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
      <div class="grid grid-cols-1 gap-4">
        <RouterLink
          v-for="item in menuItems"
          :key="item.title"
          :to="item.href"
          class="group flex min-h-44 items-center rounded-2xl border-2 border-gray-300 bg-white p-6 shadow-lg transition-all hover:border-gray-500 hover:shadow-xl focus:outline-none focus:ring-4 focus:ring-gray-300 sm:p-10"
        >
          <div
            class="mr-5 flex h-16 w-16 shrink-0 items-center justify-center rounded-full bg-gray-800 text-white transition-transform group-hover:scale-105 sm:mr-8 sm:h-20 sm:w-20"
          >
            <component :is="item.icon" class="h-8 w-8 sm:h-10 sm:w-10" />
          </div>
          <div>
            <h2 class="mb-2 text-2xl font-bold text-gray-900 sm:text-3xl">
              {{ item.title }}
            </h2>
            <p class="text-base leading-relaxed text-gray-600 sm:text-lg">{{ item.description }}</p>
          </div>
        </RouterLink>
      </div>
    </div>
  </div>
</template>
