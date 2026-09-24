<script setup lang="ts">
import { RouterLink } from 'vue-router';
const actions = [
  { label: 'Ver expedientes', to: '/expedientes?accion=ver' },
  { label: 'Nuevo expediente', to: '/expedientes/nuevo' },
  { label: 'Actualizar expedientes', to: '/expedientes?accion=actualizar' },
  { label: 'Eliminar expedientes', to: '/expedientes?accion=eliminar' },
];
</script>

<template>
  <section class="simple-page menu-page">
    <h1>Menú principal</h1>
    <p>Seleccione lo que desea hacer.</p>
    <nav class="main-menu" aria-label="Opciones principales">
      <RouterLink
        v-for="item in actions"
        :key="item.to"
        class="task-button"
        :to="item.to"
      >{{ item.label }}</RouterLink>
      <RouterLink class="task-button" to="/carga-masiva">Carga masiva de expedientes</RouterLink>
    </nav>
  </section>
</template>
