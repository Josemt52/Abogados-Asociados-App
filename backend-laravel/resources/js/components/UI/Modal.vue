<script setup lang="ts">
import { computed } from 'vue';
import { X } from '@lucide/vue';

type ModalSize = 'sm' | 'md' | 'lg' | 'xl' | 'full';

const props = withDefaults(
  defineProps<{
    open?: boolean;
    isOpen?: boolean;
    title: string;
    size?: ModalSize;
  }>(),
  {
    open: false,
    isOpen: false,
    size: 'md',
  },
);

const emit = defineEmits<{
  close: [];
}>();

const sizeClasses: Record<ModalSize, string> = {
  sm: 'max-w-md',
  md: 'max-w-lg',
  lg: 'max-w-2xl',
  xl: 'max-w-4xl',
  full: 'max-w-[calc(100vw-2rem)]',
};

const isVisible = computed(() => props.open || props.isOpen);
</script>

<template>
  <Teleport to="body">
    <div
      v-if="isVisible"
      class="fixed inset-0 z-50 overflow-y-auto"
      role="dialog"
      aria-modal="true"
      :aria-label="props.title"
    >
      <div class="flex min-h-screen items-center justify-center p-4">
        <button
          type="button"
          class="fixed inset-0 cursor-default bg-slate-950/60 backdrop-blur-[1px] transition-opacity"
          aria-label="Cerrar modal"
          @click="emit('close')"
        />

        <div
          :class="[
            'relative max-h-[calc(100vh-2rem)] w-full overflow-y-auto rounded-2xl border border-slate-200 bg-white shadow-2xl',
            sizeClasses[props.size],
          ]"
        >
          <div class="flex items-center justify-between gap-4 border-b border-blue-900 bg-gradient-to-r from-slate-950 via-slate-900 to-blue-950 px-6 py-5 text-white">
            <h3 class="text-xl font-bold tracking-tight">
              {{ props.title }}
            </h3>
            <button
              type="button"
              class="inline-flex min-h-11 min-w-11 items-center justify-center rounded-xl border border-white/20 bg-white/10 text-white transition-colors hover:bg-white/20 focus:outline-none focus:ring-4 focus:ring-blue-300"
              aria-label="Cerrar"
              @click="emit('close')"
            >
              <X class="h-6 w-6" />
            </button>
          </div>

          <div class="bg-slate-50 p-6">
            <slot />
          </div>
        </div>
      </div>
    </div>
  </Teleport>
</template>
