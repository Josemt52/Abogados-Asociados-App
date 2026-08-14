<script setup lang="ts">
import { reactive, ref } from 'vue';
import { useRouter } from 'vue-router';
import { Lock, Scale, User } from '@lucide/vue';
import { isAxiosError } from 'axios';
import { authAPI } from '@/api';
import { useAuth } from '@/composables/useAuth';
import { useToast } from '@/composables/useToast';

const router = useRouter();
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
    await router.replace('/main');
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
  <div class="flex min-h-screen items-center justify-center bg-gradient-to-br from-blue-50 to-indigo-100 p-4">
    <div class="w-full max-w-md">
      <div class="rounded-lg bg-white p-8 shadow-xl">
        <div class="mb-8 text-center">
          <div class="mb-4 flex justify-center">
            <Scale class="h-12 w-12 text-blue-700" />
          </div>
          <h2 class="mb-2 text-2xl font-bold text-gray-900">
            {{ appName }}
          </h2>
          <p class="text-gray-600">Ingresa a tu cuenta</p>
        </div>

        <form class="space-y-6" @submit.prevent="handleSubmit">
          <div>
            <label for="username" class="mb-2 block text-sm font-medium text-gray-700">
              Usuario
            </label>
            <div class="relative">
              <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                <User class="h-5 w-5 text-gray-400" />
              </div>
              <input
                id="username"
                v-model="formData.username"
                type="text"
                name="username"
                autocomplete="username"
                class="block w-full rounded-md border border-gray-300 py-2 pl-10 pr-3 shadow-sm placeholder:text-gray-400 focus:border-blue-500 focus:outline-none focus:ring-blue-500"
                placeholder="Ingresa tu usuario"
                :disabled="loading"
                required
              >
            </div>
          </div>

          <div>
            <label for="password" class="mb-2 block text-sm font-medium text-gray-700">
              Contraseña
            </label>
            <div class="relative">
              <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                <Lock class="h-5 w-5 text-gray-400" />
              </div>
              <input
                id="password"
                v-model="formData.password"
                type="password"
                name="password"
                autocomplete="current-password"
                class="block w-full rounded-md border border-gray-300 py-2 pl-10 pr-3 shadow-sm placeholder:text-gray-400 focus:border-blue-500 focus:outline-none focus:ring-blue-500"
                placeholder="Ingresa tu contraseña"
                :disabled="loading"
                required
              >
            </div>
          </div>

          <button
            type="submit"
            class="inline-flex w-full items-center justify-center rounded-md border border-transparent bg-blue-700 px-4 py-3 text-base font-medium text-white transition-colors hover:bg-blue-800 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60"
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
            {{ loading ? 'Iniciando sesión...' : 'Iniciar Sesión' }}
          </button>
        </form>

        <div class="mt-6 text-center">
          <p class="text-sm text-gray-600">
            Sistema de Gestión Jurídica • v2.0
          </p>
        </div>
      </div>
    </div>
  </div>
</template>
