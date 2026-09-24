<script setup lang="ts">
import { RouterLink } from 'vue-router';
import { useAuth } from '@/composables/useAuth';

const { isAdmin } = useAuth();
const actions = [
  { label: 'Ver expedientes', action: 'ver' },
  { label: 'Editar expedientes', action: 'editar' },
  { label: 'Actualizar expedientes', action: 'actualizar' },
  { label: 'Eliminar expedientes', action: 'eliminar' },
];
</script>

<template>
  <section class="simple-page menu-page">
    <h1>Menú principal</h1>
    <p>Seleccione lo que desea hacer.</p>
    <nav class="main-menu" aria-label="Opciones principales">
      <RouterLink
        v-for="item in actions"
        :key="item.action"
        class="task-button"
        :to="{ name: 'expedientes', query: { accion: item.action } }"
      >{{ item.label }}</RouterLink>
      <RouterLink class="task-button" to="/carga-masiva">Carga masiva de expedientes</RouterLink>
    </nav>
    <RouterLink v-if="isAdmin" class="admin-link" to="/paneladmin">Panel de administración</RouterLink>
  </section>
</template>
