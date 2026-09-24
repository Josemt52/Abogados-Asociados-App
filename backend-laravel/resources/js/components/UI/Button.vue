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
  'inline-flex items-center justify-center rounded border-2 font-semibold focus:outline-none focus:ring-2 focus:ring-gray-900 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50';

const variantClasses: Record<ButtonVariant, string> = {
  primary:
    'border-gray-800 bg-gray-100 text-gray-950 hover:bg-gray-200',
  secondary:
    'border-gray-600 bg-white text-gray-950 hover:bg-gray-100',
  danger:
    'border-gray-950 bg-white text-gray-950 hover:bg-gray-200',
  outline:
    'border-gray-500 bg-white text-gray-950 hover:bg-gray-100',
};

const sizeClasses: Record<ButtonSize, string> = {
  sm: 'min-h-12 px-4 py-2 text-base',
  md: 'min-h-14 px-5 py-3 text-lg',
  lg: 'min-h-16 px-6 py-3 text-xl',
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
