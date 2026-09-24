<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';

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
const panel = ref<HTMLElement | null>(null);
let previousFocus: HTMLElement | null = null;
const focusable = () => Array.from(panel.value?.querySelectorAll<HTMLElement>(
  'button:not(:disabled), a[href], input:not(:disabled), select:not(:disabled), textarea:not(:disabled), [tabindex="0"]',
) || []);
const handleKeydown = (event: KeyboardEvent) => {
  if (event.key === 'Escape') {
    event.preventDefault();
    event.stopPropagation();
    emit('close');
  }
  if (event.key !== 'Tab') return;
  const items = focusable();
  const first = items[0];
  const last = items[items.length - 1];
  if (!first) { event.preventDefault(); panel.value?.focus(); return; }
  if (event.shiftKey && (document.activeElement === first || document.activeElement === panel.value)) {
    event.preventDefault(); last.focus();
  } else if (!event.shiftKey && document.activeElement === last) {
    event.preventDefault(); first.focus();
  }
};
watch(isVisible, async (visible) => {
  if (visible) {
    previousFocus = document.activeElement as HTMLElement | null;
    await nextTick();
    panel.value?.focus();
  } else previousFocus?.focus();
}, { immediate: true });
onBeforeUnmount(() => previousFocus?.focus());
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
          tabindex="-1"
          @click="emit('close')"
        />

        <div
          ref="panel"
          tabindex="-1"
          @keydown="handleKeydown"
          :class="[
            'relative max-h-[calc(100vh-2rem)] w-full overflow-y-auto rounded border-2 border-gray-700 bg-white shadow-xl',
            sizeClasses[props.size],
          ]"
        >
          <div class="flex items-center justify-between gap-4 border-b-2 border-gray-300 bg-gray-100 px-6 py-4 text-gray-950">
            <h3 class="text-2xl font-bold">
              {{ props.title }}
            </h3>
            <button
              type="button"
              class="plain-button"
              aria-label="Cerrar"
              @click="emit('close')"
            >
              Cerrar
            </button>
          </div>

          <div class="bg-white p-6">
            <slot />
          </div>
        </div>
      </div>
    </div>
  </Teleport>
</template>
