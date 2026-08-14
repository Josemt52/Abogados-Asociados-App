<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref } from 'vue';
import { AlertCircle, CheckCircle2, Info, X } from '@lucide/vue';
import type { ToastType } from '@/composables/useToast';

interface ToastMessage {
    id: number;
    type: ToastType;
    message: string;
}

const messages = ref<ToastMessage[]>([]);
let nextId = 1;

const remove = (id: number): void => {
    messages.value = messages.value.filter((item) => item.id !== id);
};

const handleToast = (event: Event): void => {
    const { type, message } = (event as CustomEvent<{ type: ToastType; message: string }>).detail;
    const id = nextId++;

    messages.value.push({ id, type, message });
    window.setTimeout(() => remove(id), 4000);
};

onMounted(() => window.addEventListener('app:toast', handleToast));
onBeforeUnmount(() => window.removeEventListener('app:toast', handleToast));
</script>

<template>
    <div class="pointer-events-none fixed right-4 top-4 z-[100] flex w-[min(24rem,calc(100vw-2rem))] flex-col gap-3">
        <TransitionGroup name="toast">
            <div
                v-for="item in messages"
                :key="item.id"
                class="pointer-events-auto flex items-start gap-3 rounded-lg bg-gray-800 px-4 py-3 text-sm text-white shadow-lg"
                role="status"
            >
                <CheckCircle2 v-if="item.type === 'success'" class="mt-0.5 h-5 w-5 shrink-0 text-green-400" />
                <AlertCircle v-else-if="item.type === 'error'" class="mt-0.5 h-5 w-5 shrink-0 text-red-400" />
                <Info v-else class="mt-0.5 h-5 w-5 shrink-0 text-blue-400" />
                <span class="flex-1">{{ item.message }}</span>
                <button class="rounded p-0.5 text-gray-300 hover:text-white" type="button" aria-label="Cerrar" @click="remove(item.id)">
                    <X class="h-4 w-4" />
                </button>
            </div>
        </TransitionGroup>
    </div>
</template>

<style scoped>
.toast-enter-active,
.toast-leave-active {
    transition: opacity 180ms ease, transform 180ms ease;
}

.toast-enter-from,
.toast-leave-to {
    opacity: 0;
    transform: translateX(1rem);
}
</style>
