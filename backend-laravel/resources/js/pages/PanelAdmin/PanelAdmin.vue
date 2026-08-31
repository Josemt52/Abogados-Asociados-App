<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue';
import { AlertCircle, Check, Download, FileText, RefreshCw, Search, Settings } from '@lucide/vue';
import {
    adminCargasMasivasAPI,
    type AdminImportItem,
    type AdminImportStatus,
    type ImportConfiguration,
} from '@/api';
import Button from '@/components/UI/Button.vue';
import Modal from '@/components/UI/Modal.vue';
import { useToast } from '@/composables/useToast';
import { downloadBlob, formatFileSize } from '@/utils/fileDownload';

type FilterStatus = '' | AdminImportStatus;

const toast = useToast();
const loading = ref(true);
const savingConfiguration = ref(false);
const submitting = ref(false);
const items = ref<AdminImportItem[]>([]);
const currentPage = ref(1);
const lastPage = ref(1);
const totalItems = ref(0);
const filterStatus = ref<FilterStatus>('');
const searchTerm = ref('');
const selectedItem = ref<AdminImportItem | null>(null);
const showReview = ref(false);
const summary = reactive({ pendientes: 0, revision: 0, errores: 0 });
const configuration = reactive<ImportConfiguration>({
    registro_automatico: true,
    confianza_minima: 0.65,
});
const form = reactive({
    numero: '',
    materia: '',
    juzgado: '',
    especialista: '',
    tercero: '',
    demandado: '',
    demandante: '',
});

const actionableTotal = computed(() => summary.pendientes + summary.revision + summary.errores);

const statusLabel: Record<AdminImportStatus, string> = {
    pendiente: 'Pendiente',
    revision: 'En revisión',
    error: 'Error',
    registrado: 'Registrado',
};

const statusClass: Record<AdminImportStatus, string> = {
    pendiente: 'bg-amber-100 text-amber-900',
    revision: 'bg-violet-100 text-violet-900',
    error: 'bg-red-100 text-red-900',
    registrado: 'bg-emerald-100 text-emerald-900',
};

const reasonLabel = (reason: string | null): string => {
    const labels: Record<string, string> = {
        numero_no_detectado: 'Número no detectado',
        texto_no_detectado: 'Texto no detectado',
        confianza_baja: 'Confianza baja',
        numero_duplicado: 'Número duplicado',
        registro_manual_configurado: 'Revisión manual configurada',
        documento_ilegible: 'Documento ilegible',
        error_tecnico: 'Error técnico',
    };

    return reason ? labels[reason] ?? reason : '—';
};

const loadItems = async (page = 1): Promise<void> => {
    loading.value = true;
    try {
        const response = await adminCargasMasivasAPI.list({
            estado: filterStatus.value || undefined,
            buscar: searchTerm.value.trim() || undefined,
            page,
        });
        items.value = response.items.data;
        currentPage.value = response.items.current_page;
        lastPage.value = response.items.last_page;
        totalItems.value = response.items.total;
        Object.assign(summary, response.resumen);
    } catch {
        items.value = [];
    } finally {
        loading.value = false;
    }
};

const loadConfiguration = async (): Promise<void> => {
    try {
        Object.assign(configuration, await adminCargasMasivasAPI.getConfiguration());
    } catch {
        toast.error('No se pudo cargar la configuración de importación.');
    }
};

const saveConfiguration = async (): Promise<void> => {
    savingConfiguration.value = true;
    try {
        Object.assign(
            configuration,
            await adminCargasMasivasAPI.updateConfiguration({ ...configuration }),
        );
        toast.success('Configuración actualizada. Se aplicará a los lotes nuevos.');
    } catch {
        toast.error('No se pudo actualizar la configuración.');
    } finally {
        savingConfiguration.value = false;
    }
};

const splitLines = (value: string): string[] =>
    value
        .split(/\r?\n|;/)
        .map((part) => part.trim())
        .filter(Boolean);

const openReview = (item: AdminImportItem): void => {
    const data = item.datos;
    selectedItem.value = item;
    Object.assign(form, {
        numero: data?.numero ?? item.expediente?.numero ?? '',
        materia: data?.materia ?? '',
        juzgado: data?.juzgado ?? '',
        especialista: data?.especialista ?? '',
        tercero: (data?.tercero ?? []).join('\n'),
        demandado: (data?.demandado ?? []).join('\n'),
        demandante: (data?.demandante ?? []).join('\n'),
    });
    showReview.value = true;
};

const approve = async (): Promise<void> => {
    if (!selectedItem.value || !form.numero.trim()) {
        toast.error('Ingresa el número del expediente.');
        return;
    }

    submitting.value = true;
    try {
        const result = await adminCargasMasivasAPI.approve(selectedItem.value.id, {
            numero: form.numero.trim(),
            materia: form.materia.trim() || null,
            juzgado: form.juzgado.trim() || null,
            especialista: form.especialista.trim() || null,
            tercero: splitLines(form.tercero),
            demandado: splitLines(form.demandado),
            demandante: splitLines(form.demandante),
        });
        toast.success(result.message);
        showReview.value = false;
        selectedItem.value = null;
        await loadItems(currentPage.value);
    } catch {
        // El interceptor presenta la validación del servidor.
    } finally {
        submitting.value = false;
    }
};

const retry = async (item: AdminImportItem): Promise<void> => {
    try {
        await adminCargasMasivasAPI.retry(item.id);
        toast.success('Documento enviado nuevamente a procesamiento.');
        await loadItems(currentPage.value);
    } catch {
        // El interceptor presenta el error correspondiente.
    }
};

const download = async (item: AdminImportItem): Promise<void> => {
    try {
        downloadBlob(await adminCargasMasivasAPI.download(item.id), item.nombre_descarga);
    } catch {
        toast.error('No se pudo descargar el documento.');
    }
};

const confidenceText = (confidence: number | null): string =>
    confidence === null ? '—' : `${Math.round(confidence * 100)}%`;

onMounted(async () => {
    await Promise.all([loadItems(), loadConfiguration()]);
});
</script>

<template>
    <div class="px-4 py-8 sm:px-6 lg:px-8">
        <div class="mx-auto max-w-7xl space-y-6">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <p class="text-sm font-semibold uppercase tracking-wider text-blue-700">Control interno</p>
                    <h1 class="mt-1 text-3xl font-bold text-slate-950">Revisión de cargas masivas</h1>
                    <p class="mt-2 text-slate-600">
                        Corrige lecturas pendientes, resuelve duplicados y controla el registro automático.
                    </p>
                </div>
                <Button variant="outline" :disabled="loading" @click="loadItems(currentPage)">
                    <template #icon><RefreshCw class="h-4 w-4" /></template>
                    Actualizar
                </Button>
            </div>

            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                    <p class="text-sm font-medium text-slate-500">Por atender</p>
                    <p class="mt-2 text-3xl font-bold text-slate-950">{{ actionableTotal }}</p>
                </div>
                <div class="rounded-xl border border-amber-200 bg-amber-50 p-5">
                    <p class="text-sm font-medium text-amber-800">Pendientes</p>
                    <p class="mt-2 text-3xl font-bold text-amber-950">{{ summary.pendientes }}</p>
                </div>
                <div class="rounded-xl border border-violet-200 bg-violet-50 p-5">
                    <p class="text-sm font-medium text-violet-800">En revisión</p>
                    <p class="mt-2 text-3xl font-bold text-violet-950">{{ summary.revision }}</p>
                </div>
                <div class="rounded-xl border border-red-200 bg-red-50 p-5">
                    <p class="text-sm font-medium text-red-800">Errores técnicos</p>
                    <p class="mt-2 text-3xl font-bold text-red-950">{{ summary.errores }}</p>
                </div>
            </div>

            <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex items-start gap-3">
                    <Settings class="mt-0.5 h-5 w-5 text-blue-700" />
                    <div class="flex-1">
                        <h2 class="font-semibold text-slate-950">Modo de registro</h2>
                        <p class="mt-1 text-sm text-slate-600">
                            Esta preferencia se copia a cada lote nuevo; no altera documentos que ya están procesándose.
                        </p>
                        <div class="mt-4 flex flex-col gap-4 sm:flex-row sm:items-end">
                            <label class="flex flex-1 items-start gap-3 rounded-lg border border-slate-200 p-4">
                                <input
                                    v-model="configuration.registro_automatico"
                                    type="checkbox"
                                    class="mt-1 h-4 w-4 rounded border-slate-300 text-blue-700 focus:ring-blue-600"
                                />
                                <span>
                                    <span class="block text-sm font-semibold text-slate-900">Registro automático</span>
                                    <span class="mt-1 block text-sm text-slate-600">
                                        Los documentos confiables se registran sin intervención administrativa.
                                    </span>
                                </span>
                            </label>
                            <label class="block sm:w-52">
                                <span class="mb-1 block text-sm font-medium text-slate-700">Confianza mínima</span>
                                <input
                                    v-model.number="configuration.confianza_minima"
                                    type="number"
                                    min="0.5"
                                    max="0.99"
                                    step="0.01"
                                    class="w-full rounded-md border border-slate-300 px-3 py-2"
                                />
                            </label>
                            <Button :loading="savingConfiguration" @click="saveConfiguration">
                                Guardar configuración
                            </Button>
                        </div>
                    </div>
                </div>
            </section>

            <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                <div class="flex flex-col gap-3 border-b border-slate-200 p-5 md:flex-row">
                    <div class="relative flex-1">
                        <Search class="pointer-events-none absolute left-3 top-2.5 h-5 w-5 text-slate-400" />
                        <input
                            v-model="searchTerm"
                            type="search"
                            placeholder="Buscar por archivo o número..."
                            class="w-full rounded-md border border-slate-300 py-2 pl-10 pr-3"
                            @keyup.enter="loadItems(1)"
                        />
                    </div>
                    <select v-model="filterStatus" class="rounded-md border border-slate-300 px-3 py-2" @change="loadItems(1)">
                        <option value="">Todos los pendientes de atención</option>
                        <option value="pendiente">Pendientes</option>
                        <option value="revision">En revisión</option>
                        <option value="error">Errores</option>
                        <option value="registrado">Registrados</option>
                    </select>
                    <Button variant="outline" @click="loadItems(1)">Buscar</Button>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200">
                        <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                            <tr>
                                <th class="px-5 py-3">Documento</th>
                                <th class="px-5 py-3">Lectura</th>
                                <th class="px-5 py-3">Estado</th>
                                <th class="px-5 py-3">Confianza</th>
                                <th class="px-5 py-3">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white">
                            <tr v-if="loading">
                                <td colspan="5" class="px-5 py-12 text-center text-slate-500">Cargando revisiones...</td>
                            </tr>
                            <tr v-else-if="items.length === 0">
                                <td colspan="5" class="px-5 py-12 text-center">
                                    <Check class="mx-auto h-9 w-9 text-emerald-600" />
                                    <p class="mt-2 font-medium text-slate-900">No hay documentos en esta vista</p>
                                </td>
                            </tr>
                            <tr v-for="item in items" v-else :key="item.id" class="align-top hover:bg-slate-50">
                                <td class="px-5 py-4">
                                    <div class="flex max-w-xs items-start gap-3">
                                        <FileText class="mt-0.5 h-5 w-5 shrink-0 text-blue-700" />
                                        <div class="min-w-0">
                                            <p class="truncate text-sm font-medium text-slate-900">{{ item.nombre }}</p>
                                            <p class="mt-1 text-xs text-slate-500">
                                                {{ formatFileSize(item.tamano) }} · {{ item.lote?.usuario || 'Usuario eliminado' }}
                                            </p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-5 py-4 text-sm">
                                    <p class="font-mono font-medium text-slate-900">
                                        {{ item.datos?.numero || item.expediente?.numero || 'Sin número' }}
                                    </p>
                                    <p class="mt-1 max-w-xs text-xs text-slate-500">{{ reasonLabel(item.motivo) }}</p>
                                </td>
                                <td class="px-5 py-4">
                                    <span :class="['rounded-full px-2.5 py-1 text-xs font-semibold', statusClass[item.estado]]">
                                        {{ statusLabel[item.estado] }}
                                    </span>
                                </td>
                                <td class="px-5 py-4 text-sm text-slate-700">
                                    {{ confidenceText(item.confianza) }}
                                </td>
                                <td class="px-5 py-4">
                                    <div class="flex flex-wrap gap-2">
                                        <button
                                            type="button"
                                            class="rounded-md border border-slate-300 p-2 text-slate-600 hover:bg-slate-100"
                                            title="Descargar documento"
                                            @click="download(item)"
                                        >
                                            <Download class="h-4 w-4" />
                                        </button>
                                        <Button
                                            v-if="item.estado !== 'registrado'"
                                            size="sm"
                                            @click="openReview(item)"
                                        >
                                            Revisar
                                        </Button>
                                        <Button
                                            v-if="item.estado === 'pendiente' || item.estado === 'error'"
                                            variant="outline"
                                            size="sm"
                                            @click="retry(item)"
                                        >
                                            Reprocesar
                                        </Button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="flex items-center justify-between border-t border-slate-200 px-5 py-4 text-sm">
                    <span class="text-slate-500">{{ totalItems }} documentos</span>
                    <div class="flex items-center gap-3">
                        <Button variant="outline" size="sm" :disabled="currentPage <= 1" @click="loadItems(currentPage - 1)">
                            Anterior
                        </Button>
                        <span class="text-slate-600">{{ currentPage }} / {{ lastPage }}</span>
                        <Button variant="outline" size="sm" :disabled="currentPage >= lastPage" @click="loadItems(currentPage + 1)">
                            Siguiente
                        </Button>
                    </div>
                </div>
            </section>
        </div>

        <Modal :open="showReview" title="Revisar documento" size="xl" @close="showReview = false">
            <form class="space-y-5" @submit.prevent="approve">
                <div v-if="selectedItem?.es_duplicado" class="flex gap-3 rounded-lg border border-violet-200 bg-violet-50 p-4">
                    <AlertCircle class="h-5 w-5 shrink-0 text-violet-700" />
                    <p class="text-sm text-violet-900">
                        Este número ya existe. Al aprobar, el documento se conservará como archivo adicional del expediente
                        <strong>{{ selectedItem.expediente?.numero }}</strong>.
                    </p>
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <label class="block">
                        <span class="mb-1 block text-sm font-medium text-slate-700">Número de expediente *</span>
                        <input v-model="form.numero" required maxlength="100" class="w-full rounded-md border border-slate-300 px-3 py-2" />
                    </label>
                    <label class="block">
                        <span class="mb-1 block text-sm font-medium text-slate-700">Materia</span>
                        <input v-model="form.materia" maxlength="500" class="w-full rounded-md border border-slate-300 px-3 py-2" />
                    </label>
                    <label class="block">
                        <span class="mb-1 block text-sm font-medium text-slate-700">Juzgado</span>
                        <input v-model="form.juzgado" maxlength="255" class="w-full rounded-md border border-slate-300 px-3 py-2" />
                    </label>
                    <label class="block">
                        <span class="mb-1 block text-sm font-medium text-slate-700">Especialista</span>
                        <input v-model="form.especialista" maxlength="255" class="w-full rounded-md border border-slate-300 px-3 py-2" />
                    </label>
                </div>

                <div class="grid gap-4 md:grid-cols-3">
                    <label class="block">
                        <span class="mb-1 block text-sm font-medium text-slate-700">Terceros</span>
                        <textarea v-model="form.tercero" rows="4" class="w-full rounded-md border border-slate-300 px-3 py-2" placeholder="Una persona por línea" />
                    </label>
                    <label class="block">
                        <span class="mb-1 block text-sm font-medium text-slate-700">Demandados</span>
                        <textarea v-model="form.demandado" rows="4" class="w-full rounded-md border border-slate-300 px-3 py-2" placeholder="Una persona por línea" />
                    </label>
                    <label class="block">
                        <span class="mb-1 block text-sm font-medium text-slate-700">Demandantes</span>
                        <textarea v-model="form.demandante" rows="4" class="w-full rounded-md border border-slate-300 px-3 py-2" placeholder="Una persona por línea" />
                    </label>
                </div>

                <div v-if="selectedItem?.error" class="rounded-lg bg-red-50 p-4 text-sm text-red-900">
                    <strong>Detalle técnico:</strong> {{ selectedItem.error }}
                </div>

                <div class="flex justify-end gap-3 border-t border-slate-200 pt-5">
                    <Button type="button" variant="outline" :disabled="submitting" @click="showReview = false">
                        Cancelar
                    </Button>
                    <Button type="submit" :loading="submitting">
                        {{ selectedItem?.es_duplicado ? 'Asociar y resolver' : 'Registrar expediente' }}
                    </Button>
                </div>
            </form>
        </Modal>
    </div>
</template>
