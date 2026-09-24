<script setup lang="ts">
import { computed } from 'vue';
import { ArrowLeft } from '@lucide/vue';
import { useRoute, useRouter } from 'vue-router';

const props = withDefaults(defineProps<{ fallback?: string }>(), { fallback: '/main' });
const route = useRoute();
const router = useRouter();
const previous = computed(() => {
  // Leer la ruta mantiene actualizado el estado tras cada navegación.
  void route.fullPath;
  const back = router.options.history.state.back;
  return typeof back === 'string' && back.startsWith('/') && !back.startsWith('//')
    && !back.startsWith('/login') && back !== route.fullPath ? back : null;
});
const canGoBack = computed(() => Boolean(previous.value) || (route.path !== props.fallback && route.path !== '/login'));
const goBack = () => {
  if (!canGoBack.value) return;
  if (previous.value) router.back();
  else void router.push(props.fallback);
};
</script>

<template>
  <button type="button" class="plain-button gap-2" :disabled="!canGoBack" @click="goBack">
    <ArrowLeft class="h-6 w-6" aria-hidden="true" />
    Volver atrás
  </button>
</template>
