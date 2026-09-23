<script setup lang="ts">
import { computed } from 'vue';
import LoadingSpinner from './LoadingSpinner.vue';

defineOptions({ inheritAttrs: false });

type ButtonVariant = 'primary' | 'secondary' | 'danger' | 'outline';
type ButtonSize = 'sm' | 'md' | 'lg';
type ButtonType = 'button' | 'submit' | 'reset';

const props = withDefaults(
  defineProps<{
    variant?: ButtonVariant;
    size?: ButtonSize;
    loading?: boolean;
    disabled?: boolean;
    type?: ButtonType;
    as?: 'button' | 'span';
  }>(),
  {
    variant: 'primary',
    size: 'md',
    loading: false,
    disabled: false,
    type: 'button',
    as: 'button',
  },
);

const baseClasses =
  'inline-flex min-h-12 items-center justify-center rounded-xl border font-semibold shadow-sm transition-all duration-150 focus:outline-none focus:ring-4 focus:ring-offset-2 active:translate-y-px disabled:cursor-not-allowed disabled:opacity-50';

const variantClasses: Record<ButtonVariant, string> = {
  primary:
    'border-blue-800 bg-blue-700 text-white shadow-blue-200 hover:bg-blue-800 hover:shadow-md focus:ring-blue-400',
  secondary:
    'border-violet-700 bg-violet-600 text-white shadow-violet-200 hover:bg-violet-700 hover:shadow-md focus:ring-violet-300',
  danger:
    'border-red-700 bg-red-600 text-white shadow-red-200 hover:bg-red-700 hover:shadow-md focus:ring-red-300',
  outline:
    'border-slate-300 bg-white text-slate-800 hover:border-blue-400 hover:bg-blue-50 hover:text-blue-900 focus:ring-blue-300',
};

const sizeClasses: Record<ButtonSize, string> = {
  sm: 'min-h-10 px-3 py-2 text-sm',
  md: 'px-5 py-2.5 text-sm',
  lg: 'min-h-14 px-6 py-3 text-base',
};

const isDisabled = computed(() => props.disabled || props.loading);
const buttonClasses = computed(() => [
  baseClasses,
  variantClasses[props.variant],
  sizeClasses[props.size],
  props.as === 'span' && isDisabled.value
    ? 'pointer-events-none cursor-not-allowed opacity-50'
    : '',
]);
</script>

<template>
  <component
    :is="props.as"
    v-bind="$attrs"
    :class="buttonClasses"
    :type="props.as === 'button' ? props.type : undefined"
    :disabled="props.as === 'button' ? isDisabled : undefined"
    :aria-disabled="isDisabled || undefined"
  >
    <LoadingSpinner
      v-if="props.loading"
      size="sm"
      class="mr-2"
    />
    <span
      v-else-if="$slots.icon"
      class="mr-2"
    >
      <slot name="icon" />
    </span>
    <slot />
  </component>
</template>
