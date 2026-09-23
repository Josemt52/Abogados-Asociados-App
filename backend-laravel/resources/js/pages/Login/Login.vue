<script setup lang="ts">
import { reactive, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { Lock, Scale, User } from '@lucide/vue';
import { isAxiosError } from 'axios';
import { authAPI } from '@/api';
import { useAuth } from '@/composables/useAuth';
import { useToast } from '@/composables/useToast';

const router = useRouter();
const route = useRoute();
const { login } = useAuth();
const toast = useToast();
const appName = import.meta.env.VITE_APP_NAME || 'Abogados Asociados';

const formData = reactive({
  username: '',
  password: '',
});
const loading = ref(false);

const handleSubmit = async () => {
  if (!formData.username.trim() || !formData.password.trim()) {
    toast.error('Por favor, complete todos los campos');
    return;
  }

  loading.value = true;

  try {
    const { user, token } = await authAPI.login({
      username: formData.username.trim(),
      password: formData.password,
    });

    login(user, token);
    toast.success('Bienvenido al sistema');
    const requestedRedirect = typeof route.query.redirect === 'string' ? route.query.redirect : '/main';
    const safeRedirect = requestedRedirect.startsWith('/') && !requestedRedirect.startsWith('//')
      ? requestedRedirect
      : '/main';
    await router.replace(safeRedirect);
  } catch (error) {
    // El interceptor evita mostrar "sesión expirada" durante un login fallido.
    if (isAxiosError(error) && error.response?.status === 401) {
      const data = error.response.data as { message?: string } | undefined;
      toast.error(data?.message || 'Credenciales inválidas');
    } else if (!isAxiosError(error)) {
      toast.error('No fue posible iniciar sesión. Intenta nuevamente.');
    }
  } finally {
    loading.value = false;
  }
};
</script>

<template>
  <div class="relative flex min-h-screen items-center justify-center overflow-hidden bg-slate-950 p-5 sm:p-8">
    <div class="pointer-events-none absolute -left-32 top-0 h-96 w-96 rounded-full bg-blue-600/30 blur-3xl" />
    <div class="pointer-events-none absolute -bottom-36 right-0 h-96 w-96 rounded-full bg-violet-600/30 blur-3xl" />

    <div class="relative grid w-full max-w-5xl overflow-hidden rounded-3xl border border-white/15 bg-white shadow-2xl lg:grid-cols-[1fr_1.05fr]">
      <section class="hidden bg-gradient-to-br from-blue-700 via-blue-800 to-indigo-950 p-10 text-white lg:flex lg:flex-col lg:justify-between">
        <div>
          <div class="flex h-16 w-16 items-center justify-center rounded-2xl bg-white/15 ring-1 ring-white/30">
            <Scale class="h-9 w-9" aria-hidden="true" />
          </div>
          <p class="mt-8 text-sm font-bold uppercase tracking-[0.18em] text-cyan-200">Gestión jurídica clara</p>
          <h1 class="mt-3 text-4xl font-black leading-tight">Trabaje paso a paso, sin complicaciones.</h1>
          <p class="mt-5 max-w-md text-lg leading-7 text-blue-100">
            El sistema le mostrará las acciones importantes y le indicará qué sigue en cada expediente.
          </p>
        </div>

        <ol class="mt-12 space-y-4" aria-label="Cómo empezar">
          <li class="flex items-center gap-3 rounded-2xl bg-white/10 px-4 py-3">
            <span class="flex h-8 w-8 items-center justify-center rounded-full bg-cyan-300 font-black text-slate-950">1</span>
            <span class="font-bold">Ingrese con su usuario</span>
          </li>
          <li class="flex items-center gap-3 rounded-2xl bg-white/10 px-4 py-3">
            <span class="flex h-8 w-8 items-center justify-center rounded-full bg-cyan-300 font-black text-slate-950">2</span>
            <span class="font-bold">Elija una tarea del menú</span>
          </li>
          <li class="flex items-center gap-3 rounded-2xl bg-white/10 px-4 py-3">
            <span class="flex h-8 w-8 items-center justify-center rounded-full bg-cyan-300 font-black text-slate-950">3</span>
            <span class="font-bold">Siga las indicaciones de pantalla</span>
          </li>
        </ol>
      </section>

      <section class="p-7 sm:p-10">
        <div class="mb-8">
          <div class="mb-5 flex items-center gap-3 lg:hidden">
            <span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-blue-700 text-white shadow-md">
              <Scale class="h-7 w-7" aria-hidden="true" />
            </span>
            <span class="text-sm font-extrabold uppercase tracking-[0.14em] text-blue-800">Gestión jurídica</span>
          </div>
          <p class="text-sm font-bold uppercase tracking-[0.14em] text-blue-700">Acceso al sistema</p>
          <h2 class="mt-2 text-3xl font-black tracking-tight text-slate-950">{{ appName }}</h2>
          <p class="mt-2 text-base leading-6 text-slate-600">Escriba sus datos para comenzar a trabajar.</p>
        </div>

        <form class="space-y-6" @submit.prevent="handleSubmit">
          <div>
            <label for="username" class="mb-2 block text-base font-bold text-slate-800">
              1. Usuario
            </label>
            <p class="mb-2 text-sm text-slate-600">Escriba el nombre de usuario asignado.</p>
            <div class="relative">
              <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-5">
                <User class="h-6 w-6 text-blue-700" aria-hidden="true" />
              </div>
              <input
                id="username"
                v-model="formData.username"
                type="text"
                name="username"
                autocomplete="username"
                class="block min-h-14 w-full rounded-2xl border-2 border-slate-300 bg-white py-3 pl-14 pr-4 text-lg font-medium text-slate-950 shadow-sm placeholder:text-slate-400 focus:border-blue-600 focus:outline-none focus:ring-4 focus:ring-blue-100 disabled:bg-slate-100"
                placeholder="Ejemplo: mlopez"
                :disabled="loading"
                required
              >
            </div>
          </div>

          <div>
            <label for="password" class="mb-2 block text-base font-bold text-slate-800">
              2. Contraseña
            </label>
            <p class="mb-2 text-sm text-slate-600">Escriba la contraseña de su cuenta.</p>
            <div class="relative">
              <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-5">
                <Lock class="h-6 w-6 text-violet-700" aria-hidden="true" />
              </div>
              <input
                id="password"
                v-model="formData.password"
                type="password"
                name="password"
                autocomplete="current-password"
                class="block min-h-14 w-full rounded-2xl border-2 border-slate-300 bg-white py-3 pl-14 pr-4 text-lg font-medium text-slate-950 shadow-sm placeholder:text-slate-400 focus:border-blue-600 focus:outline-none focus:ring-4 focus:ring-blue-100 disabled:bg-slate-100"
                placeholder="Escriba su contraseña"
                :disabled="loading"
                required
              >
            </div>
          </div>

          <button
            type="submit"
            class="inline-flex min-h-14 w-full items-center justify-center rounded-2xl border-2 border-blue-800 bg-blue-700 px-5 py-3 text-lg font-extrabold text-white shadow-lg shadow-blue-200 transition-colors hover:bg-blue-800 focus:outline-none focus:ring-4 focus:ring-blue-200 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60"
            :disabled="loading"
          >
            <svg
              v-if="loading"
              class="mr-2 h-5 w-5 animate-spin"
              xmlns="http://www.w3.org/2000/svg"
              fill="none"
              viewBox="0 0 24 24"
              aria-hidden="true"
            >
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z" />
            </svg>
            {{ loading ? 'Ingresando al sistema...' : 'Entrar al sistema' }}
          </button>
        </form>

        <p class="mt-7 rounded-xl bg-slate-50 px-4 py-3 text-center text-sm text-slate-600">
          Ingrese ambos datos y pulse <strong class="text-slate-900">Entrar al sistema</strong>.
        </p>
      </section>
    </div>
  </div>
</template>
