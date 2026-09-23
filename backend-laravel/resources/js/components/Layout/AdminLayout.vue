<script setup lang="ts">
import { RouterView, useRouter } from 'vue-router';
import { ArrowLeft, LogOut, Shield } from '@lucide/vue';
import { authAPI } from '@/api';
import { useAuth } from '@/composables/useAuth';

const router = useRouter();
const { user, logout } = useAuth();

const handleLogout = async (): Promise<void> => {
    try {
        await authAPI.logout();
    } catch {
        // La sesión local debe cerrarse incluso si el token ya expiró.
    } finally {
        logout();
        await router.replace('/login');
    }
};
</script>

<template>
    <div class="min-h-screen bg-slate-100">
        <header class="border-b border-blue-950 bg-gradient-to-r from-slate-950 via-slate-900 to-blue-950 text-white shadow-lg">
            <div class="mx-auto flex min-h-20 max-w-7xl items-center justify-between gap-4 px-4 py-3 sm:px-6 lg:px-8">
                <div class="flex min-w-0 items-center gap-3">
                    <div class="rounded-xl bg-violet-600 p-3 shadow-lg shadow-violet-950/50">
                        <Shield class="h-6 w-6" aria-hidden="true" />
                    </div>
                    <div class="min-w-0">
                        <p class="truncate text-lg font-bold">Panel de administración</p>
                        <p class="truncate text-sm text-slate-300">Usuario: {{ user?.username }}</p>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <RouterLink
                        to="/main"
                        class="inline-flex min-h-11 items-center rounded-xl border border-white/20 bg-white/10 px-4 text-sm font-bold text-white hover:bg-white/20 focus:outline-none focus:ring-4 focus:ring-blue-300"
                    >
                        <ArrowLeft class="mr-2 h-5 w-5" />
                        <span class="hidden sm:inline">Volver al sistema</span>
                    </RouterLink>
                    <button
                        type="button"
                        class="inline-flex min-h-11 items-center rounded-xl border border-red-400 bg-red-700 px-4 text-sm font-bold text-white hover:bg-red-600 focus:outline-none focus:ring-4 focus:ring-red-300"
                        @click="handleLogout"
                    >
                        <LogOut class="mr-2 h-5 w-5" />
                        Salir
                    </button>
                </div>
            </div>
        </header>

        <main>
            <RouterView />
        </main>
    </div>
</template>
