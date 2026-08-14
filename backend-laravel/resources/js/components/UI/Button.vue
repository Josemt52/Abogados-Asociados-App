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
  'inline-flex items-center justify-center font-medium rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 transition-colors disabled:opacity-50 disabled:cursor-not-allowed';

const variantClasses: Record<ButtonVariant, string> = {
  primary: 'bg-blue-700 hover:bg-blue-800 text-white focus:ring-blue-500',
  secondary: 'bg-gray-200 hover:bg-gray-300 text-gray-900 focus:ring-gray-500',
  danger: 'bg-red-600 hover:bg-red-700 text-white focus:ring-red-500',
  outline:
    'border border-gray-300 bg-white hover:bg-gray-50 text-gray-700 focus:ring-blue-500',
};

const sizeClasses: Record<ButtonSize, string> = {
  sm: 'px-3 py-1.5 text-sm',
  md: 'px-4 py-2 text-sm',
  lg: 'px-6 py-3 text-base',
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
