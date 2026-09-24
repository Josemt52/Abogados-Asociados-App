<script setup lang="ts">
import { reactive, ref } from 'vue';
import BackButton from '@/components/UI/BackButton.vue';
import { useRoute, useRouter } from 'vue-router';
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
  <main class="min-h-screen flex items-center justify-center p-8">
    <section class="w-full max-w-xl border-2 border-gray-500 bg-white p-8">
      <BackButton class="mb-6" />
      <h1 class="text-3xl font-bold">{{ appName }}</h1>
      <p class="mt-3 mb-8">Escriba su usuario y contraseña para entrar.</p>
      <form class="space-y-6" @submit.prevent="handleSubmit">
        <div>
          <label for="username" class="block mb-2 text-xl font-bold">Usuario</label>
          <input id="username" v-model="formData.username" type="text" name="username" autocomplete="username" class="plain-input w-full" :disabled="loading" required autofocus />
        </div>
        <div>
          <label for="password" class="block mb-2 text-xl font-bold">Contraseña</label>
          <input id="password" v-model="formData.password" type="password" name="password" autocomplete="current-password" class="plain-input w-full" :disabled="loading" required />
        </div>
        <button type="submit" class="task-button w-full" :disabled="loading">{{ loading ? 'Ingresando…' : 'Entrar al sistema' }}</button>
      </form>
    </section>
  </main>
</template>
