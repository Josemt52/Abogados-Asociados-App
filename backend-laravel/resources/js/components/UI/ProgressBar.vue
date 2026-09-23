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
    <div class="mb-2 flex items-center justify-between">
      <span class="text-sm font-bold text-slate-800">
        Subiendo documento
      </span>
      <span
        v-if="props.showPercentage"
        class="text-sm font-bold text-blue-800"
      >
        {{ roundedProgress }}%
      </span>
    </div>
    <div
      class="h-3 w-full overflow-hidden rounded-full bg-slate-200"
      role="progressbar"
      aria-label="Progreso de subida"
      aria-valuemin="0"
      aria-valuemax="100"
      :aria-valuenow="roundedProgress"
    >
      <div
        class="h-3 rounded-full bg-gradient-to-r from-blue-600 to-cyan-500 transition-all duration-300"
        :style="{ width: `${clampedProgress}%` }"
      />
    </div>
  </div>
</template>
