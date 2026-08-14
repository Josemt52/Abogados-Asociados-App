<script setup lang="ts">
import { computed } from 'vue';

type SpinnerSize = 'sm' | 'md' | 'lg';

const props = withDefaults(
  defineProps<{
    size?: SpinnerSize;
  }>(),
  {
    size: 'md',
  },
);

const sizeClasses: Record<SpinnerSize, string> = {
  sm: 'h-4 w-4',
  md: 'h-8 w-8',
  lg: 'h-12 w-12',
};

const spinnerClasses = computed(() => [
  'animate-spin rounded-full border-2 border-gray-300 border-t-blue-600',
  sizeClasses[props.size],
]);
</script>

<template>
  <div
    :class="spinnerClasses"
    role="status"
    aria-label="Cargando"
  />
</template>
