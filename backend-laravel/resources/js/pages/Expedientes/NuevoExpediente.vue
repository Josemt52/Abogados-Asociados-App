<script setup lang="ts">
import { useRouter } from 'vue-router';
import type { Expediente } from '@/api';
import ExpedienteForm from '@/components/ExpedienteForm/ExpedienteForm.vue';

const router = useRouter();
const created = (expediente: Expediente) => router.replace({
  name: 'expediente-detail', params: { id: expediente.id },
});
</script>

<template>
  <section class="simple-page">
    <h1>Nuevo expediente</h1>
    <ExpedienteForm @success="created" @cancel="router.push('/main')" />
  </section>
</template>
