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
        <header class="border-b border-slate-700 bg-slate-950 text-white shadow-sm">
            <div class="mx-auto flex h-16 max-w-7xl items-center justify-between px-4 sm:px-6 lg:px-8">
                <div class="flex min-w-0 items-center gap-3">
                    <div class="rounded-lg bg-blue-600 p-2">
                        <Shield class="h-5 w-5" />
                    </div>
                    <div class="min-w-0">
                        <p class="truncate font-semibold">Panel administrativo</p>
                        <p class="truncate text-xs text-slate-400">{{ user?.username }}</p>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <RouterLink
                        to="/main"
                        class="inline-flex items-center rounded-md px-3 py-2 text-sm text-slate-300 hover:bg-slate-800 hover:text-white"
                    >
                        <ArrowLeft class="mr-2 h-4 w-4" />
                        <span class="hidden sm:inline">Volver al sistema</span>
                    </RouterLink>
                    <button
                        type="button"
                        class="inline-flex items-center rounded-md bg-red-700 px-3 py-2 text-sm font-medium hover:bg-red-600"
                        @click="handleLogout"
                    >
                        <LogOut class="mr-2 h-4 w-4" />
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
