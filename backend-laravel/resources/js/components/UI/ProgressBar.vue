<script setup lang="ts">
import { computed } from 'vue';

defineOptions({ inheritAttrs: false });

const props = withDefaults(
  defineProps<{
    progress: number;
    showPercentage?: boolean;
    className?: string;
  }>(),
  {
    showPercentage: true,
    className: '',
  },
);

const clampedProgress = computed(() =>
  Math.max(0, Math.min(100, Number.isFinite(props.progress) ? props.progress : 0)),
);

const roundedProgress = computed(() => Math.round(clampedProgress.value));
</script>

<template>
  <div
    v-bind="$attrs"
    :class="['w-full', props.className]"
  >
    <div class="mb-1 flex items-center justify-between">
      <span class="text-sm font-medium text-gray-700">
        Subiendo archivo...
      </span>
      <span
        v-if="props.showPercentage"
        class="text-sm font-medium text-gray-700"
      >
        {{ roundedProgress }}%
      </span>
    </div>
    <div
      class="h-2 w-full rounded-full bg-gray-200"
      role="progressbar"
      aria-label="Progreso de subida"
      aria-valuemin="0"
      aria-valuemax="100"
      :aria-valuenow="roundedProgress"
    >
      <div
        class="h-2 rounded-full bg-blue-600 transition-all duration-300"
        :style="{ width: `${clampedProgress}%` }"
      />
    </div>
  </div>
</template>
