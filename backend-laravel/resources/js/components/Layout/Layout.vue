<script setup lang="ts">
import { watch } from 'vue';
import { RouterView, useRoute, useRouter } from 'vue-router';
import { authAPI } from '@/api';
import { useAuth } from '@/composables/useAuth';

const route = useRoute();
const router = useRouter();
const { user, logout, isAuthenticated } = useAuth();
watch(isAuthenticated, (authenticated) => {
  if (!authenticated) void router.replace('/login');
});
const appName = import.meta.env.VITE_APP_NAME || 'Abogados Asociados';

const handleLogout = async () => {
  try { await authAPI.logout(); }
  catch { /* La sesión local también se cierra si el servidor no responde. */ }
  finally {
    logout();
    await router.replace('/login');
  }
};
</script>

<template>
  <div class="simple-app">
    <header class="simple-header">
      <span class="simple-brand">{{ appName }}</span>
      <div class="simple-header-actions">
        <span>{{ user?.username }}</span>
        <RouterLink v-if="route.name !== 'main'" class="plain-button" to="/main">Menú principal</RouterLink>
        <button type="button" class="plain-button" @click="handleLogout">Cerrar sesión</button>
      </div>
    </header>
    <main class="simple-content"><RouterView /></main>
  </div>
</template>
