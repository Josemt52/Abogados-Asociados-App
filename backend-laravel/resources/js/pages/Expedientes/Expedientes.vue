<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { RouterLink, useRoute, useRouter } from 'vue-router';
import { expedientesAPI, type Expediente } from '@/api';
import ExpedienteDocument from '@/components/ExpedienteDocument.vue';
import DeleteExpedienteModal from '@/components/DeleteExpedienteModal.vue';
import { getApiErrorMessage } from '@/utils/apiError';

const route = useRoute();
const router = useRouter();
const number = ref('');
const numberInput = ref<HTMLInputElement | null>(null);
const found = ref<Expediente | null>(null);
const searched = ref(false);
const searching = ref(false);
const error = ref('');
const notice = ref('');
const showDelete = ref(false);
const documentMode = ref<'view' | 'print' | null>(null);
const records = ref<Expediente[]>([]);
const listLoading = ref(false);
const listError = ref('');
const page = ref(1);
const pageSize = 15;
const totalPages = computed(() => Math.max(1, Math.ceil(records.value.length / pageSize)));
const visibleRecords = computed(() => records.value.slice((page.value - 1) * pageSize, page.value * pageSize));
let listRequestId = 0;
let requestId = 0;
const action = computed(() => String(route.query.accion || 'ver'));
const title = computed(() => ({
  ver: 'Ver expedientes', editar: 'Actualizar expedientes',
  actualizar: 'Actualizar expedientes', eliminar: 'Eliminar expedientes',
}[action.value] || 'Ver expedientes'));

const openEditor = (expediente: Expediente) => router.push({
  name: 'expediente-detail',
  params: { id: expediente.id },
  query: { editor: 'true' },
});

const loadList = async () => {
  const current = ++listRequestId;
  listLoading.value = true;
  listError.value = '';
  try {
    const rows = await expedientesAPI.getAll();
    if (current === listRequestId) records.value = rows;
  } catch (cause) {
    const message = await getApiErrorMessage(cause, 'No se pudo cargar la lista de expedientes.');
    if (current === listRequestId) listError.value = message;
  } finally {
    if (current === listRequestId) listLoading.value = false;
  }
};

const continueWithRecord = async (record: Expediente) => {
  if (['editar', 'actualizar'].includes(action.value)) await openEditor(record);
  else if (action.value === 'eliminar') showDelete.value = true;
};

const selectRecord = async (record: Expediente) => {
  resetSearch();
  number.value = record.numero;
  found.value = record;
  searched.value = true;
  await continueWithRecord(record);
  await nextTick();
  if (action.value === 'ver') document.getElementById('found-title')?.focus();
};

const resetSearch = () => {
  requestId += 1;
  found.value = null;
  searched.value = false;
  searching.value = false;
  error.value = '';
  notice.value = '';
  showDelete.value = false;
  documentMode.value = null;
};

watch(number, resetSearch, { flush: 'sync' });
watch(() => route.query.accion, async () => {
  number.value = '';
  resetSearch();
  await nextTick();
  numberInput.value?.focus();
});

const search = async () => {
  const term = number.value.trim();
  if (!term || searching.value) return;
  const current = ++requestId;
  found.value = null;
  searched.value = false;
  error.value = '';
  notice.value = '';
  searching.value = true;
  try {
    const rows = await expedientesAPI.getAll();
    if (current !== requestId) return;
    // Un número incompleto nunca debe seleccionar un expediente diferente.
    found.value = rows.find(row => row.numero.trim().toLocaleLowerCase('es') === term.toLocaleLowerCase('es')) || null;
    searched.value = true;
    if (found.value) await continueWithRecord(found.value);
  } catch (cause) {
    if (current !== requestId) return;
    const message = await getApiErrorMessage(cause, 'No se pudo buscar el expediente. Intente nuevamente.');
    if (current === requestId) error.value = message;
  } finally {
    if (current === requestId) searching.value = false;
  }
};

const deleted = () => {
  const deletedNumber = found.value?.numero;
  records.value = records.value.filter(record => record.id !== found.value?.id);
  page.value = Math.min(page.value, totalPages.value);
  // Evitar que una carga pendiente vuelva a mostrar el expediente eliminado.
  listRequestId += 1;
  listLoading.value = false;
  resetSearch();
  number.value = '';
  notice.value = `El expediente ${deletedNumber} fue eliminado.`;
};
onMounted(() => { void loadList(); numberInput.value?.focus(); });
onBeforeUnmount(() => { requestId += 1; listRequestId += 1; });
</script>

<template>
  <section class="simple-page">
    <h1>{{ title }}</h1>
    <form class="search-form" @submit.prevent="search">
      <label for="expediente-number">Número de expediente que desea buscar</label>
      <div class="search-controls">
        <input
          id="expediente-number" ref="numberInput" v-model="number" autofocus required
          type="text" autocomplete="off" placeholder="Escriba el número completo" class="plain-input"
        />
        <button type="submit" class="task-button" :disabled="searching || !number.trim()">
          {{ searching ? 'Buscando…' : 'Buscar expediente' }}
        </button>
      </div>
    </form>
    <p v-if="error" role="alert" class="plain-notice">{{ error }}</p>
    <p v-if="notice" role="status" class="plain-notice">{{ notice }}</p>
    <p v-if="searched && !found" role="status" class="plain-notice">
      No se encontró el expediente {{ number.trim() }}. Revise el número completo y vuelva a buscar.
    </p>

    <section v-if="found" class="search-result" aria-labelledby="found-title">
      <h2 id="found-title" tabindex="-1">Expediente N.º {{ found.numero }}</h2>
      <p v-if="found.materia">{{ found.materia }}</p>
      <p v-if="found.demandante || found.demandado">
        {{ found.demandante || 'Sin demandante registrado' }} — {{ found.demandado || 'Sin demandado registrado' }}
      </p>
      <div class="record-actions">
        <RouterLink class="task-button" :to="{ name: 'expediente-detail', params: { id: found.id } }">
          Ver expediente {{ found.numero }}
        </RouterLink>
        <button type="button" class="task-button" @click="openEditor(found)">Actualizar expediente</button>
        <button type="button" class="task-button" @click="showDelete = true">Eliminar expediente</button>
        <button type="button" class="task-button" @click="documentMode = 'print'">Imprimir expediente</button>
      </div>
    </section>
    <section class="plain-section" aria-labelledby="existing-records">
      <h2 id="existing-records">Expedientes existentes</h2>
      <p>Puede buscar por número arriba o elegir un expediente de esta lista.</p>
      <p v-if="listLoading" role="status">Cargando expedientes…</p>
      <div v-else-if="listError" class="plain-notice">
        <p role="alert">{{ listError }}</p>
        <button type="button" class="plain-button" @click="loadList">Reintentar lista</button>
      </div>
      <p v-else-if="!records.length">No hay expedientes registrados.</p>
      <template v-else>
        <div class="overflow-x-auto">
          <table class="records-table">
            <thead><tr><th scope="col">Número de expediente</th><th scope="col">Materia</th><th scope="col">Acción</th></tr></thead>
            <tbody>
              <tr v-for="record in visibleRecords" :key="record.id">
                <th scope="row">{{ record.numero }}</th>
                <td>{{ record.materia || 'Sin materia registrada' }}</td>
                <td>
                  <button type="button" class="plain-button" :aria-label="`Seleccionar expediente ${record.numero}`" @click="selectRecord(record)">
                    {{ ['editar', 'actualizar'].includes(action) ? 'Actualizar' : action === 'eliminar' ? 'Eliminar' : 'Ver opciones' }}
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        <nav v-if="totalPages > 1" class="mt-5 flex items-center gap-4" aria-label="Páginas de expedientes">
          <button type="button" class="plain-button" :disabled="page === 1" @click="page--">Anterior</button>
          <span>Página {{ page }} de {{ totalPages }}</span>
          <button type="button" class="plain-button" :disabled="page === totalPages" @click="page++">Siguiente</button>
        </nav>
      </template>
    </section>
    <DeleteExpedienteModal
      v-if="found && showDelete" :expediente="found"
      @close="showDelete = false" @deleted="deleted"
    />
    <ExpedienteDocument
      v-if="found && documentMode" :expediente="found" :mode="documentMode"
      @close="documentMode = null"
    />
  </section>
</template>
