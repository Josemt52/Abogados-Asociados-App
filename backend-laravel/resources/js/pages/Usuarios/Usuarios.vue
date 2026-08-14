<script setup lang="ts">
import { computed, onMounted, reactive, ref, unref } from 'vue';
import { useRouter } from 'vue-router';
import {
  Edit,
  Search,
  Shield,
  Trash2,
  User as UserIcon,
  UserPlus,
} from '@lucide/vue';
import { usuariosAPI } from '@/api';
import { useAuth } from '@/composables/useAuth';
import { useToast } from '@/composables/useToast';
import Button from '@/components/UI/Button.vue';
import Modal from '@/components/UI/Modal.vue';
import Table from '@/components/UI/Table.vue';

interface User {
  id: number;
  nombre: string;
  username: string;
  rol: {
    id: number;
    nombre: string;
  };
}

interface EditUserData {
  nombre: string;
  username: string;
  rolId: number;
  password: string;
}

interface ApiErrorPayload {
  message?: string;
  error?: string;
  errors?: Record<string, string[]>;
}

const router = useRouter();
const auth = useAuth();
const toast = useToast();

const currentUser = computed(() => unref(auth.user));
const isAdmin = computed(
  () => currentUser.value?.rol?.nombre?.toLowerCase() === 'admin',
);

const usuarios = ref<User[]>([]);
const loading = ref(true);
const searchTerm = ref('');
const showEditModal = ref(false);
const showDeleteModal = ref(false);
const selectedUser = ref<User | null>(null);
const isSubmitting = ref(false);
const editFormData = reactive<EditUserData>({
  nombre: '',
  username: '',
  rolId: 2,
  password: '',
});

const columns = [
  { key: 'id', label: 'ID' },
  { key: 'nombre', label: 'Nombre' },
  { key: 'username', label: 'Usuario' },
  { key: 'rol', label: 'Rol' },
  { key: 'acciones', label: 'Acciones' },
];

const filteredUsuarios = computed(() => {
  const term = searchTerm.value.trim().toLowerCase();

  if (!term) {
    return usuarios.value;
  }

  return usuarios.value.filter((usuario) =>
    [usuario.nombre, usuario.username, usuario.rol?.nombre]
      .filter(Boolean)
      .some((value) => value.toLowerCase().includes(term)),
  );
});

const resultsMessage = computed(() => {
  const count = filteredUsuarios.value.length;
  const noun = count === 1 ? 'usuario' : 'usuarios';
  const status = searchTerm.value ? 'encontrado(s)' : 'registrado(s)';

  return `${count} ${noun} ${status}`;
});

const emptyMessage = computed(() =>
  searchTerm.value
    ? 'No se encontraron usuarios con ese criterio'
    : 'No hay usuarios registrados',
);

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

async function refetch() {
  loading.value = true;

  try {
    const response = await usuariosAPI.getAll();
    usuarios.value = Array.isArray(response) ? (response as User[]) : [];
  } catch (error) {
    usuarios.value = [];
    toast.error(apiErrorMessage(error, 'Error al cargar usuarios'));
  } finally {
    loading.value = false;
  }
}

function handleEditClick(usuario: User) {
  selectedUser.value = usuario;
  Object.assign(editFormData, {
    nombre: usuario.nombre,
    username: usuario.username,
    rolId: usuario.rol.id,
    password: '',
  });
  showEditModal.value = true;
}

function handleDeleteClick(usuario: User) {
  selectedUser.value = usuario;
  showDeleteModal.value = true;
}

function closeEditModal() {
  showEditModal.value = false;
  selectedUser.value = null;
}

function closeDeleteModal() {
  showDeleteModal.value = false;
  selectedUser.value = null;
}

async function handleEditSubmit() {
  if (!selectedUser.value) {
    return;
  }

  isSubmitting.value = true;

  try {
    const updateData: {
      nombre: string;
      username: string;
      rol_id: number;
      password?: string;
    } = {
      nombre: editFormData.nombre,
      username: editFormData.username,
      rol_id: editFormData.rolId,
    };

    if (editFormData.password.trim()) {
      updateData.password = editFormData.password;
    }

    await usuariosAPI.update(selectedUser.value.id, updateData);
    toast.success('Usuario actualizado correctamente');
    closeEditModal();
    await refetch();
  } catch (error) {
    toast.error(apiErrorMessage(error, 'Error al actualizar usuario'));
  } finally {
    isSubmitting.value = false;
  }
}

async function handleDeleteConfirm() {
  if (!selectedUser.value) {
    return;
  }

  isSubmitting.value = true;

  try {
    await usuariosAPI.delete(selectedUser.value.id);
    toast.success('Usuario eliminado correctamente');
    closeDeleteModal();
    await refetch();
  } catch (error) {
    toast.error(apiErrorMessage(error, 'Error al eliminar usuario'));
  } finally {
    isSubmitting.value = false;
  }
}

onMounted(refetch);
</script>

<template>
  <div
    v-if="!isAdmin"
    class="rounded-lg border border-gray-200 bg-white p-8 shadow-sm"
  >
    <div class="text-center">
      <Shield class="mx-auto mb-4 h-12 w-12 text-red-500" />
      <h2 class="mb-2 text-xl font-semibold text-gray-900">
        Acceso Restringido
      </h2>
      <p class="text-gray-600">
        No tienes permisos para acceder a esta sección.
        <br />
        Solo los administradores pueden gestionar usuarios.
      </p>
    </div>
  </div>

  <div
    v-else
    class="space-y-6"
  >
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-bold text-gray-900">
          Gestión de Usuarios
        </h1>
        <p class="text-gray-600">
          Administra los usuarios del sistema y sus roles
        </p>
      </div>
      <Button
        variant="primary"
        @click="router.push('/usuarios/registrar')"
      >
        <template #icon>
          <UserPlus class="h-4 w-4" />
        </template>
        Nuevo Usuario
      </Button>
    </div>

    <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
      <div class="relative">
        <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
          <Search class="h-5 w-5 text-gray-400" />
        </div>
        <input
          v-model="searchTerm"
          type="text"
          placeholder="Buscar por nombre, usuario o rol..."
          class="block w-full rounded-md border border-gray-300 bg-white py-2 pl-10 pr-3 leading-5 placeholder-gray-500 focus:border-blue-500 focus:placeholder-gray-400 focus:outline-none focus:ring-1 focus:ring-blue-500"
        />
      </div>
    </div>

    <div
      v-if="!loading"
      class="text-sm text-gray-600"
    >
      {{ resultsMessage }}
    </div>

    <Table
      :columns="columns"
      :rows="filteredUsuarios"
      :loading="loading"
      :empty-message="emptyMessage"
    >
      <template #cell-id="{ value }">
        <span class="font-mono text-xs text-gray-500">#{{ value }}</span>
      </template>

      <template #cell-nombre="{ value }">
        <div class="flex items-center space-x-2">
          <UserIcon class="h-4 w-4 text-gray-400" />
          <span class="font-medium text-gray-900">{{ value }}</span>
        </div>
      </template>

      <template #cell-username="{ value }">
        <span class="text-gray-700">{{ value }}</span>
      </template>

      <template #cell-rol="{ value }">
        <span
          :class="[
            'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium',
            value?.nombre?.toLowerCase() === 'admin'
              ? 'bg-purple-100 text-purple-800'
              : 'bg-blue-100 text-blue-800',
          ]"
        >
          <Shield class="mr-1 h-3 w-3" />
          {{ value?.nombre }}
        </span>
      </template>

      <template #cell-acciones="{ row }">
        <div class="flex items-center space-x-2">
          <button
            type="button"
            class="rounded p-1.5 text-blue-600 transition-colors hover:bg-blue-50"
            title="Editar usuario"
            @click.stop="handleEditClick(row)"
          >
            <Edit class="h-4 w-4" />
          </button>
          <button
            type="button"
            class="rounded p-1.5 text-red-600 transition-colors hover:bg-red-50 disabled:cursor-not-allowed disabled:opacity-40"
            title="Eliminar usuario"
            :disabled="row.id === Number(currentUser?.id)"
            @click.stop="handleDeleteClick(row)"
          >
            <Trash2 class="h-4 w-4" />
          </button>
        </div>
      </template>
    </Table>

    <Modal
      :open="showEditModal"
      title="Editar Usuario"
      size="md"
      @close="closeEditModal"
    >
      <form
        class="space-y-4"
        @submit.prevent="handleEditSubmit"
      >
        <div>
          <label class="mb-1 block text-sm font-medium text-gray-700">
            Nombre completo
          </label>
          <input
            v-model="editFormData.nombre"
            type="text"
            class="w-full rounded-md border border-gray-300 px-3 py-2 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
            required
          />
        </div>

        <div>
          <label class="mb-1 block text-sm font-medium text-gray-700">
            Nombre de usuario
          </label>
          <input
            v-model="editFormData.username"
            type="text"
            class="w-full rounded-md border border-gray-300 px-3 py-2 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
            required
          />
        </div>

        <div>
          <label class="mb-1 block text-sm font-medium text-gray-700">
            Rol
          </label>
          <select
            v-model.number="editFormData.rolId"
            class="w-full rounded-md border border-gray-300 px-3 py-2 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
          >
            <option :value="2">
              Usuario
            </option>
            <option :value="1">
              Admin
            </option>
          </select>
        </div>

        <div>
          <label class="mb-1 block text-sm font-medium text-gray-700">
            Nueva Contraseña (opcional)
          </label>
          <input
            v-model="editFormData.password"
            type="password"
            placeholder="Dejar vacío para mantener la actual"
            class="w-full rounded-md border border-gray-300 px-3 py-2 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
          />
          <p class="mt-1 text-xs text-gray-500">
            Solo ingresa una contraseña si deseas cambiarla
          </p>
        </div>

        <div class="flex justify-end space-x-3 border-t pt-4">
          <Button
            type="button"
            variant="outline"
            :disabled="isSubmitting"
            @click="closeEditModal"
          >
            Cancelar
          </Button>
          <Button
            type="submit"
            variant="primary"
            :loading="isSubmitting"
          >
            Guardar Cambios
          </Button>
        </div>
      </form>
    </Modal>

    <Modal
      :open="showDeleteModal"
      title="Confirmar Eliminación"
      size="sm"
      @close="closeDeleteModal"
    >
      <div class="space-y-4">
        <p class="text-gray-700">
          ¿Estás seguro de que deseas eliminar al usuario
          <span class="font-semibold">{{ selectedUser?.username }}</span>?
        </p>
        <p class="text-sm text-red-600">
          Esta acción no se puede deshacer.
        </p>

        <div class="flex justify-end space-x-3 border-t pt-4">
          <Button
            type="button"
            variant="outline"
            :disabled="isSubmitting"
            @click="closeDeleteModal"
          >
            Cancelar
          </Button>
          <Button
            type="button"
            variant="danger"
            :loading="isSubmitting"
            @click="handleDeleteConfirm"
          >
            Eliminar
          </Button>
        </div>
      </div>
    </Modal>
  </div>
</template>
