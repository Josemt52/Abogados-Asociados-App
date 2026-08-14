<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue';
import { useRouter } from 'vue-router';
import { Lock, User, UserCheck } from '@lucide/vue';
import { usuariosAPI } from '@/api';
import { useToast } from '@/composables/useToast';
import Button from '@/components/UI/Button.vue';

interface Rol {
  id: number;
  nombre: string;
}

interface RegistrationForm {
  nombre: string;
  username: string;
  password: string;
  confirmarPassword: string;
  rol: string;
}

interface ApiErrorPayload {
  message?: string;
  error?: string;
  errors?: Record<string, string[]>;
}

const router = useRouter();
const toast = useToast();
const loading = ref(false);
const roles = ref<Rol[]>([]);
const loadingRoles = ref(true);
const errors = ref<Record<string, string>>({});
const formData = reactive<RegistrationForm>({
  nombre: '',
  username: '',
  password: '',
  confirmarPassword: '',
  rol: 'usuario',
});

function apiErrorMessage(error: unknown, fallback: string) {
  const payload = (
    error as { response?: { data?: ApiErrorPayload }; message?: string }
  ).response?.data;
  const validationMessage = payload?.errors
    ? Object.values(payload.errors).flat()[0]
    : undefined;

  return (
    payload?.message ||
    payload?.error ||
    validationMessage ||
    (error as { message?: string }).message ||
    fallback
  );
}

function loadRoles() {
  loadingRoles.value = true;

  try {
    // Son los roles definidos por los seeders y las validaciones de Laravel.
    roles.value = [
      { id: 1, nombre: 'ADMIN' },
      { id: 2, nombre: 'USUARIO' },
    ];
    formData.rol = 'USUARIO';
  } catch (error) {
    console.error('Error al cargar roles:', error);
    toast.error('No se pudieron cargar los roles disponibles');
  } finally {
    loadingRoles.value = false;
  }
}

function validateForm() {
  const nextErrors: Record<string, string> = {};

  if (!formData.nombre.trim()) {
    nextErrors.nombre = 'El nombre completo es obligatorio';
  }

  if (!formData.username.trim()) {
    nextErrors.username = 'El usuario es obligatorio';
  } else if (formData.username.length < 3) {
    nextErrors.username = 'El usuario debe tener al menos 3 caracteres';
  }

  if (!formData.password.trim()) {
    nextErrors.password = 'La contraseña es obligatoria';
  } else if (formData.password.length < 6) {
    nextErrors.password = 'La contraseña debe tener al menos 6 caracteres';
  }

  if (formData.password !== formData.confirmarPassword) {
    nextErrors.confirmarPassword = 'Las contraseñas no coinciden';
  }

  errors.value = nextErrors;
  return Object.keys(nextErrors).length === 0;
}

function clearError(field: keyof RegistrationForm) {
  if (errors.value[field]) {
    errors.value = { ...errors.value, [field]: '' };
  }
}

async function handleSubmit() {
  if (!validateForm()) {
    return;
  }

  loading.value = true;

  try {
    const selectedRole = roles.value.find((role) => role.nombre === formData.rol);

    await usuariosAPI.create({
      nombre: formData.nombre,
      username: formData.username,
      password: formData.password,
      rol_id: selectedRole?.id ?? 2,
    });

    toast.success('Usuario registrado correctamente');
    await router.push('/usuarios');
  } catch (error) {
    toast.error(apiErrorMessage(error, 'Error al registrar usuario'));
  } finally {
    loading.value = false;
  }
}

function displayRoleName(role: string) {
  return role.charAt(0).toUpperCase() + role.slice(1);
}

onMounted(loadRoles);
</script>

<template>
  <div class="mx-auto max-w-2xl">
    <div class="rounded-lg border border-gray-200 bg-white p-8 shadow-sm">
      <div class="mb-6">
        <h1 class="mb-2 text-2xl font-bold text-gray-900">
          Registrar Nuevo Usuario
        </h1>
        <p class="text-gray-600">
          Complete la información para crear un nuevo usuario del sistema
        </p>
      </div>

      <form
        class="space-y-6"
        @submit.prevent="handleSubmit"
      >
        <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
          <div>
            <label
              for="nombre"
              class="mb-1 block text-sm font-medium text-gray-700"
            >
              Nombre Completo *
            </label>
            <div class="relative">
              <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                <User class="h-5 w-5 text-gray-400" />
              </div>
              <input
                id="nombre"
                v-model="formData.nombre"
                type="text"
                name="nombre"
                :class="[
                  'block w-full rounded-md border py-2 pl-10 pr-3 shadow-sm placeholder-gray-400 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500',
                  errors.nombre ? 'border-red-500' : 'border-gray-300',
                ]"
                placeholder="Juan Pérez"
                @input="clearError('nombre')"
              />
            </div>
            <p
              v-if="errors.nombre"
              class="mt-1 text-sm text-red-600"
            >
              {{ errors.nombre }}
            </p>
          </div>

          <div>
            <label
              for="username"
              class="mb-1 block text-sm font-medium text-gray-700"
            >
              Usuario *
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
                :class="[
                  'block w-full rounded-md border py-2 pl-10 pr-3 shadow-sm placeholder-gray-400 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500',
                  errors.username ? 'border-red-500' : 'border-gray-300',
                ]"
                placeholder="Nombre de usuario"
                @input="clearError('username')"
              />
            </div>
            <p
              v-if="errors.username"
              class="mt-1 text-sm text-red-600"
            >
              {{ errors.username }}
            </p>
          </div>

          <div>
            <label
              for="password"
              class="mb-1 block text-sm font-medium text-gray-700"
            >
              Contraseña *
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
                :class="[
                  'block w-full rounded-md border py-2 pl-10 pr-3 shadow-sm placeholder-gray-400 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500',
                  errors.password ? 'border-red-500' : 'border-gray-300',
                ]"
                placeholder="Mínimo 6 caracteres"
                @input="clearError('password')"
              />
            </div>
            <p
              v-if="errors.password"
              class="mt-1 text-sm text-red-600"
            >
              {{ errors.password }}
            </p>
          </div>

          <div>
            <label
              for="confirmarPassword"
              class="mb-1 block text-sm font-medium text-gray-700"
            >
              Confirmar Contraseña *
            </label>
            <div class="relative">
              <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                <Lock class="h-5 w-5 text-gray-400" />
              </div>
              <input
                id="confirmarPassword"
                v-model="formData.confirmarPassword"
                type="password"
                name="confirmarPassword"
                :class="[
                  'block w-full rounded-md border py-2 pl-10 pr-3 shadow-sm placeholder-gray-400 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500',
                  errors.confirmarPassword ? 'border-red-500' : 'border-gray-300',
                ]"
                placeholder="Repita la contraseña"
                @input="clearError('confirmarPassword')"
              />
            </div>
            <p
              v-if="errors.confirmarPassword"
              class="mt-1 text-sm text-red-600"
            >
              {{ errors.confirmarPassword }}
            </p>
          </div>
        </div>

        <div>
          <label
            for="rol"
            class="mb-1 block text-sm font-medium text-gray-700"
          >
            Rol
          </label>
          <div class="relative">
            <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
              <UserCheck class="h-5 w-5 text-gray-400" />
            </div>
            <select
              id="rol"
              v-model="formData.rol"
              name="rol"
              :disabled="loadingRoles"
              class="block w-full rounded-md border border-gray-300 py-2 pl-10 pr-3 shadow-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 disabled:cursor-not-allowed disabled:bg-gray-100"
            >
              <option v-if="loadingRoles">
                Cargando roles...
              </option>
              <template v-else-if="roles.length > 0">
                <option
                  v-for="rol in roles"
                  :key="rol.id"
                  :value="rol.nombre"
                >
                  {{ displayRoleName(rol.nombre) }}
                </option>
              </template>
              <option
                v-else
                value="usuario"
              >
                Usuario
              </option>
            </select>
          </div>
        </div>

        <div class="flex justify-end space-x-4 border-t border-gray-200 pt-6">
          <Button
            type="button"
            variant="outline"
            @click="router.push('/main')"
          >
            Cancelar
          </Button>
          <Button
            type="submit"
            variant="primary"
            :loading="loading"
          >
            Registrar Usuario
          </Button>
        </div>
      </form>
    </div>
  </div>
</template>
