<script setup lang="ts">
import { computed, unref, type Component } from 'vue';
import { RouterLink } from 'vue-router';
import {
  AlertCircle,
  Clock,
  FileText,
  RefreshCw,
  Scale,
  TrendingUp,
  Users,
} from '@lucide/vue';
import { useAuth } from '@/composables/useAuth';
import { useEstadisticas } from '@/composables/useEstadisticas';
import Button from '@/components/UI/Button.vue';

interface QuickAction {
  title: string;
  description: string;
  icon: Component;
  href: string;
  color: string;
}

const auth = useAuth();
const user = computed(() => unref(auth.user));
const isAdmin = computed(() => unref(auth.isAdmin));
const { dashboardStats, loading, refreshStats } = useEstadisticas();

const allQuickActions: Array<QuickAction & { adminOnly?: boolean }> = [
  {
    title: 'Ver Expedientes',
    description: 'Administrar y consultar todos los expedientes',
    icon: FileText,
    href: '/expedientes',
    color: 'bg-blue-500',
  },
  {
    title: 'Registrar Usuario',
    description: 'Crear nuevos usuarios del sistema',
    icon: Users,
    href: '/usuarios/registrar',
    color: 'bg-green-500',
    adminOnly: true,
  },
];

const quickActions = computed(() =>
  allQuickActions.filter((action) => !action.adminOnly || isAdmin.value),
);

const stats = computed(() => [
  {
    name: 'Expedientes Activos',
    value: loading.value ? '...' : String(dashboardStats.value.expedientesActivos),
    icon: Scale,
    color: 'text-blue-600',
    bgColor: 'bg-blue-100',
  },
  {
    name: 'En Progreso',
    value: loading.value ? '...' : String(dashboardStats.value.enProgreso),
    icon: Clock,
    color: 'text-yellow-600',
    bgColor: 'bg-yellow-100',
  },
  {
    name: 'Finalizados',
    value: loading.value ? '...' : String(dashboardStats.value.finalizados),
    icon: TrendingUp,
    color: 'text-green-600',
    bgColor: 'bg-green-100',
  },
  {
    name: 'Urgentes',
    value: loading.value ? '...' : String(dashboardStats.value.urgentes),
    icon: AlertCircle,
    color: 'text-red-600',
    bgColor: 'bg-red-100',
  },
]);

</script>

<template>
  <div class="space-y-8">
    <div class="flex items-start justify-between">
      <div>
        <h1 class="mb-2 text-3xl font-bold text-gray-900">
          Panel Principal
        </h1>
        <p class="text-gray-600">
          Bienvenido,
          <span class="font-semibold">{{ user?.username }}</span>.
          Administra expedientes y gestiona el sistema jurídico.
        </p>
      </div>
      <Button
        variant="outline"
        :disabled="loading"
        class="flex items-center space-x-2"
        @click="refreshStats"
      >
        <RefreshCw :class="['h-4 w-4', loading ? 'animate-spin' : '']" />
        <span>Actualizar</span>
      </Button>
    </div>

    <div class="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-4">
      <div
        v-for="stat in stats"
        :key="stat.name"
        class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm"
      >
        <div class="flex items-center">
          <div :class="['rounded-lg p-2', stat.bgColor]">
            <component
              :is="stat.icon"
              :class="['h-6 w-6', stat.color]"
            />
          </div>
          <div class="ml-4">
            <p class="text-sm font-medium text-gray-600">
              {{ stat.name }}
            </p>
            <p class="text-2xl font-semibold text-gray-900">
              {{ stat.value }}
            </p>
          </div>
        </div>
      </div>
    </div>

    <div>
      <h2 class="mb-4 text-xl font-semibold text-gray-900">
        Acciones Rápidas
      </h2>
      <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
        <RouterLink
          v-for="action in quickActions"
          :key="action.title"
          :to="action.href"
          class="group rounded-lg border border-gray-200 bg-white p-6 shadow-sm transition-shadow hover:shadow-md"
        >
          <div class="flex items-start space-x-4">
            <div
              :class="[
                'rounded-lg p-3 transition-transform group-hover:scale-110',
                action.color,
              ]"
            >
              <component
                :is="action.icon"
                class="h-6 w-6 text-white"
              />
            </div>
            <div class="flex-1">
              <h3 class="mb-2 text-lg font-semibold text-gray-900 transition-colors group-hover:text-blue-700">
                {{ action.title }}
              </h3>
              <p class="text-gray-600">
                {{ action.description }}
              </p>
            </div>
          </div>
        </RouterLink>
      </div>
    </div>
  </div>
</template>
