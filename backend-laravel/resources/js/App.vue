<script setup lang="ts">
import { RouterView } from 'vue-router';
import ToastContainer from '@/components/ToastContainer.vue';
import { useAuth } from '@/composables/useAuth';

const { isLoading } = useAuth();
</script>

<template>
  <div class="min-h-screen bg-gray-50">
    <div
      v-if="isLoading"
      class="flex min-h-screen items-center justify-center"
      role="status"
      aria-live="polite"
    >
      <span class="animate-pulse text-gray-500">Cargando aplicación...</span>
    </div>

    <RouterView v-else />
    <ToastContainer />
  </div>
</template>

