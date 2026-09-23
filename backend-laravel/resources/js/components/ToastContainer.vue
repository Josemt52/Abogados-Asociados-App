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
    <div class="pointer-events-none fixed right-5 top-5 z-[100] flex w-[min(28rem,calc(100vw-2rem))] flex-col gap-3">
        <TransitionGroup name="toast">
            <div
                v-for="item in messages"
                :key="item.id"
                :class="[
                    'pointer-events-auto flex items-start gap-3 rounded-2xl border px-4 py-3 text-base shadow-xl',
                    item.type === 'success' && 'border-emerald-300 bg-emerald-950 text-white',
                    item.type === 'error' && 'border-red-300 bg-red-950 text-white',
                    item.type === 'info' && 'border-blue-300 bg-blue-950 text-white',
                ]"
                :role="item.type === 'error' ? 'alert' : 'status'"
            >
                <CheckCircle2 v-if="item.type === 'success'" class="mt-0.5 h-6 w-6 shrink-0 text-emerald-300" />
                <AlertCircle v-else-if="item.type === 'error'" class="mt-0.5 h-6 w-6 shrink-0 text-red-300" />
                <Info v-else class="mt-0.5 h-6 w-6 shrink-0 text-blue-300" />
                <span class="flex-1">{{ item.message }}</span>
                <button class="inline-flex min-h-9 min-w-9 items-center justify-center rounded-lg text-white/80 hover:bg-white/15 hover:text-white focus:outline-none focus:ring-2 focus:ring-white" type="button" aria-label="Cerrar" @click="remove(item.id)">
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
