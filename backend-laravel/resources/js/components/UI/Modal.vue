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
  full: 'max-w-[95vw]',
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
          class="fixed inset-0 cursor-default bg-black bg-opacity-50 transition-opacity"
          aria-label="Cerrar modal"
          @click="emit('close')"
        />

        <div
          :class="[
            'relative max-h-screen w-full overflow-y-auto rounded-lg bg-white shadow-xl',
            sizeClasses[props.size],
          ]"
        >
          <div class="flex items-center justify-between border-b border-gray-200 p-6">
            <h3 class="text-lg font-semibold text-gray-900">
              {{ props.title }}
            </h3>
            <button
              type="button"
              class="text-gray-400 transition-colors hover:text-gray-600"
              aria-label="Cerrar"
              @click="emit('close')"
            >
              <X class="h-6 w-6" />
            </button>
          </div>

          <div class="p-6">
            <slot />
          </div>
        </div>
      </div>
    </div>
  </Teleport>
</template>
